<?php
/**
 * Cliente HTTP del servidor central.
 *
 * El servicio y sus condiciones están en
 * https://laboratoriowp.com/sms-marketing-y-otp-por-sms/
 *
 * Es el ÚNICO archivo que sabe que existe una API remota. Habla el namespace
 * lm-saas/v1 y traduce cualquier respuesta a array o WP_Error:
 *
 *   GET  /account      Datos de la cuenta y cuota.
 *   POST /otp/request  Envía un código por SMS. Devuelve request_id.
 *   POST /otp/verify   Comprueba un código contra su request_id.
 *   GET  /otp/quota    Saldo y capacidad restante.
 *
 * El código en claro NUNCA sale del servidor: aquí solo se manejan
 * identificadores de solicitud. Ese reparto es deliberado (ver README).
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Client {

  const NAMESPACE_PATH = '/wp-json/lm-saas/v1';
  const REST_ROUTE     = '/lm-saas/v1';

  const QUOTA_TTL     = 5 * MINUTE_IN_SECONDS;
  const OPTION_TIME   = 'lm2fa_quota_time';
  const LEGACY_QUOTA  = 'lm2fa_quota';

  public static function server_url() {
    $url = (string) LM2FA_Settings::get( 'lm2fa_server_url' );
    return untrailingslashit( $url ? $url : LM2FA_DEFAULT_SERVER );
  }

  public static function api_key() {
    return trim( (string) LM2FA_Settings::get( 'lm2fa_api_key' ) );
  }

  public static function is_configured() {
    return '' !== self::api_key() && '' !== self::server_url();
  }

  /** Enlace directo a la pestaña de claves API del panel de cliente. */
  public static function panel_url( $tab = 'otp' ) {
    return self::server_url() . '/mi-cuenta/sms-panel/?tab=' . rawurlencode( $tab );
  }

  /* ------------------------------- Caché -------------------------------- */

  /** La caché depende del servidor y de la clave: si cambia alguno, es otro contexto. */
  private static function cache_key() {
    return 'lm2fa_quota_' . substr( md5( self::server_url() . '|' . self::api_key() ), 0, 16 );
  }

  public static function flush_cache() {
    delete_transient( self::cache_key() );
    delete_transient( self::LEGACY_QUOTA );
    delete_option( self::OPTION_TIME );
  }

  public static function quota_updated_at() {
    return (string) get_option( self::OPTION_TIME, '' );
  }

  /* ------------------------------ Transporte ----------------------------- */

  /**
   * Petición autenticada al servidor central.
   *
   * @param string     $endpoint Ruta dentro del namespace, con barra inicial.
   * @param array|null $body     Cuerpo JSON, o null para GET.
   * @param string     $method   Verbo HTTP.
   * @return array|WP_Error
   */
  private static function request( $endpoint, $body = null, $method = 'POST' ) {
    if ( ! self::is_configured() ) {
      return new WP_Error( 'lm2fa_not_configured', __( 'El servicio de verificación no está configurado.', 'lmsms-2fa' ) );
    }

    $response = self::dispatch( self::url( $endpoint, $method ), $body, $method );

    // Permalinks planos en el servidor: se reintenta por ?rest_route=.
    if ( self::is_missing_route( $response ) ) {
      $response = self::dispatch( self::url( $endpoint, $method, true ), $body, $method );
    }

    return self::parse( $response, $endpoint );
  }

  private static function url( $endpoint, $method, $plain_permalinks = false ) {
    $url = $plain_permalinks
      ? add_query_arg( 'rest_route', self::REST_ROUTE . $endpoint, self::server_url() . '/' )
      : self::server_url() . self::NAMESPACE_PATH . $endpoint;

    // Rompe cualquier caché intermedia en las lecturas.
    if ( 'GET' === strtoupper( $method ) ) {
      $url = add_query_arg(
        array(
          '_nocache' => time(),
          '_r'       => wp_generate_password( 6, false, false ),
        ),
        $url
      );
    }

    return $url;
  }

  /** @return array|WP_Error Respuesta cruda de wp_remote_request(). */
  private static function dispatch( $url, $body, $method ) {
    $key = self::api_key();

    $args = array(
      'method'      => $method,
      'timeout'     => 20,
      'redirection' => 2,
      'headers'     => array(
        'X-API-KEY'        => $key,
        'Authorization'    => 'Bearer ' . $key,
        'Content-Type'     => 'application/json',
        'Accept'           => 'application/json',
        'Cache-Control'    => 'no-cache, no-store, max-age=0',
        'Pragma'           => 'no-cache',
        'X-Requested-With' => 'LM2FA/' . LM2FA_VERSION,
      ),
    );

    if ( null !== $body ) {
      $args['body'] = wp_json_encode( $body );
    }

    return wp_remote_request( $url, $args );
  }

  /** El 404 de "no existe la ruta" merece un segundo intento; el resto no. */
  private static function is_missing_route( $response ) {
    if ( is_wp_error( $response ) ) {
      return false;
    }
    if ( 404 !== (int) wp_remote_retrieve_response_code( $response ) ) {
      return false;
    }

    $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
    return ! is_array( $decoded ) || ( isset( $decoded['code'] ) && 'rest_no_route' === $decoded['code'] );
  }

  /**
   * Convierte la respuesta HTTP en array de datos o en WP_Error con el
   * código que devolvió el servidor (lm_otp_expired, lm_otp_no_balance...).
   *
   * @return array|WP_Error
   */
  private static function parse( $response, $endpoint ) {
    if ( is_wp_error( $response ) ) {
      LM2FA_Log::add(
        'transport_error',
        $response->get_error_code() . ': ' . $response->get_error_message() . ' → ' . $endpoint
      );

      return new WP_Error(
        'lm2fa_transport',
        sprintf(
          /* translators: %s detalle técnico devuelto por WordPress. */
          __( 'No fue posible contactar al servicio de verificación (%s).', 'lmsms-2fa' ),
          $response->get_error_message()
        )
      );
    }

    $code    = (int) wp_remote_retrieve_response_code( $response );
    $raw     = wp_remote_retrieve_body( $response );
    $decoded = json_decode( $raw, true );

    if ( $code >= 200 && $code < 300 ) {
      if ( ! is_array( $decoded ) ) {
        // HTTP 200 sin JSON: suele ser una página de mantenimiento o un WAF.
        LM2FA_Log::add( 'bad_payload', 'HTTP 200 sin JSON: ' . substr( wp_strip_all_tags( $raw ), 0, 120 ) );
        return new WP_Error( 'lm2fa_bad_payload', __( 'El servidor respondió con un contenido inesperado.', 'lmsms-2fa' ) );
      }
      return $decoded;
    }

    $error_code = isset( $decoded['code'] ) ? (string) $decoded['code'] : 'lm2fa_http_' . $code;
    $message    = isset( $decoded['message'] ) ? (string) $decoded['message'] : __( 'Error del servicio de verificación.', 'lmsms-2fa' );
    $data       = ( isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) ? $decoded['data'] : array();

    $data['status'] = $code;

    LM2FA_Log::add( 'api_error', $error_code . ' — ' . $message );

    return new WP_Error( $error_code, $message, $data );
  }

  /* ------------------------------ Endpoints ------------------------------ */

  /**
   * Pide un código por SMS.
   *
   * @return array|WP_Error { request_id, phone (enmascarado), expires_in, billed, quota }
   */
  public static function otp_request( $phone, $reference = '' ) {
    $result = self::request(
      '/otp/request',
      array(
        'phone'     => $phone,
        'reference' => $reference,
      )
    );

    // Cualquier solicitud altera el saldo: se refresca en el próximo acceso.
    self::flush_cache();

    return $result;
  }

  /**
   * Verifica un código contra su solicitud.
   *
   * @return array|WP_Error { verified, request_id, phone, reference }
   */
  public static function otp_verify( $request_id, $code ) {
    return self::request(
      '/otp/verify',
      array(
        'request_id' => $request_id,
        'code'       => $code,
      )
    );
  }

  /**
   * Saldo y cuota. Cacheado QUOTA_TTL salvo que se fuerce.
   *
   * @return array|WP_Error
   */
  public static function quota( $force = false ) {
    if ( ! $force ) {
      $cached = get_transient( self::cache_key() );
      if ( is_array( $cached ) ) {
        return $cached;
      }
    }

    $result = self::request( '/otp/quota', null, 'GET' );

    if ( ! is_wp_error( $result ) ) {
      set_transient( self::cache_key(), $result, self::QUOTA_TTL );
      update_option( self::OPTION_TIME, LM2FA_Util::now_gmt(), false );
    }

    return $result;
  }

  /** @return array|WP_Error { user_id, credits, otp, version } */
  public static function account() {
    return self::request( '/account', null, 'GET' );
  }

  /** Identificador de origen que verá el administrador del servidor central. */
  public static function reference( $user_id, $context = 'login' ) {
    $host = wp_parse_url( home_url(), PHP_URL_HOST );
    return substr( $context . ':' . $host . ':' . (int) $user_id, 0, 64 );
  }
}
