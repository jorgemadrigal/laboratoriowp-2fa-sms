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

  /**
   * Versión del servidor a partir de la cual el contrato es el que espera
   * este plugin: /otp/request devuelve expires_in y quota, y los errores
   * traen retry_after y attempts_left. Con menos, se avisa al administrador.
   */
  const MIN_SERVER = '15.0.0';

  const QUOTA_TTL     = 5 * MINUTE_IN_SECONDS;
  const OPTION_TIME   = 'lm2fa_quota_time';
  const OPTION_SERVER_VERSION = 'lm2fa_server_version';
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

  /**
   * Enlace directo a una pestaña del panel de cliente.
   *
   * El panel es un endpoint de "Mi cuenta" de WooCommerce en el servidor
   * (LMSAAS_ENDPOINT = 'sms-panel'), colgado del slug que allí tenga esa
   * página. 'mi-cuenta' es el valor habitual, pero no es una constante del
   * contrato: quien lo tenga distinto lo ajusta con el filtro sin tocar
   * código.
   *
   * @param string $tab Pestaña del panel: otp, api, enviar, historial...
   */
  public static function panel_url( $tab = 'otp' ) {
    /**
     * Ruta del panel dentro del servidor, sin barras al principio ni al final.
     *
     * @param string $path Por defecto 'mi-cuenta/sms-panel'.
     */
    $path = trim( (string) apply_filters( 'lm2fa_panel_path', 'mi-cuenta/sms-panel' ), '/' );
    $url  = self::server_url() . '/' . $path . '/?tab=' . rawurlencode( $tab );

    /**
     * URL final, por si el panel vive en otro dominio.
     *
     * @param string $url
     * @param string $tab
     */
    return apply_filters( 'lm2fa_panel_url', $url, $tab );
  }

  /* ------------------------------ Versión -------------------------------- */

  /** Última versión que declaró el servidor en /account. '' si nunca se pidió. */
  public static function server_version() {
    return (string) get_option( self::OPTION_SERVER_VERSION, '' );
  }

  /**
   * ¿El servidor cumple el contrato mínimo?
   *
   * Mientras no se haya hablado con él no hay motivo para alarmar: se da
   * por bueno y ya lo dirá la primera llamada a /account.
   */
  public static function is_supported_server() {
    $version = self::server_version();

    return ( '' === $version ) || version_compare( $version, self::MIN_SERVER, '>=' );
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

  /**
   * Guarda un estado de cuota recién llegado y avisa a quien vigile el saldo.
   *
   * El servidor manda quota_status en más sitios que /otp/quota: también en
   * la respuesta de /otp/request y dentro del error 402 de "sin saldo". Todos
   * pasan por aquí, así que el aviso de saldo bajo salta en el momento en que
   * el servidor lo dice y no hasta la siguiente tarea diaria.
   *
   * @param array $quota Estructura quota_status del servidor.
   */
  private static function store_quota( $quota ) {
    if ( ! is_array( $quota ) || ! isset( $quota['total_capacity'] ) ) {
      return;
    }

    set_transient( self::cache_key(), $quota, self::QUOTA_TTL );
    update_option( self::OPTION_TIME, LM2FA_Util::now_gmt(), false );

    /**
     * Hay una lectura fresca del saldo.
     *
     * @param array $quota Estructura quota_status del servidor.
     */
    do_action( 'lm2fa_quota_updated', $quota );
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

    // Cualquier solicitud altera el saldo: se tira la lectura anterior.
    self::flush_cache();

    // Pero el servidor acaba de decir cómo queda la cuenta, tanto si el
    // envío salió como si falló por falta de saldo (402). Se aprovecha en
    // vez de volver a preguntar.
    if ( is_wp_error( $result ) ) {
      $data = (array) $result->get_error_data();
      self::store_quota( isset( $data['quota'] ) ? $data['quota'] : null );
    } elseif ( isset( $result['quota'] ) ) {
      self::store_quota( $result['quota'] );
    }

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
      self::store_quota( $result );
    }

    return $result;
  }

  /** @return array|WP_Error { user_id, credits, otp, version } */
  public static function account() {
    $result = self::request( '/account', null, 'GET' );

    if ( is_wp_error( $result ) ) {
      return $result;
    }

    // /account es la única ruta que declara la versión del servidor: se
    // anota para poder avisar si se queda por debajo del contrato.
    if ( isset( $result['version'] ) ) {
      update_option( self::OPTION_SERVER_VERSION, sanitize_text_field( (string) $result['version'] ), false );
    }

    if ( isset( $result['otp'] ) ) {
      self::store_quota( $result['otp'] );
    }

    return $result;
  }

  /** Identificador de origen que verá el administrador del servidor central. */
  public static function reference( $user_id, $context = 'login' ) {
    $host = wp_parse_url( home_url(), PHP_URL_HOST );
    return substr( $context . ':' . $host . ':' . (int) $user_id, 0, 64 );
  }
}
