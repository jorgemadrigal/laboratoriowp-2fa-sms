<?php
/**
 * Helpers transversales: IP, fechas, rate limiting, cookies y vistas.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Util {

  public static function ip() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
    return rest_is_ip_address( $ip ) ? substr( $ip, 0, 45 ) : '';
  }

  public static function user_agent() {
    $agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
    return substr( $agent, 0, 160 );
  }

  public static function now_gmt() {
    return current_time( 'mysql', true );
  }

  public static function local_date( $gmt_datetime ) {
    if ( empty( $gmt_datetime ) ) {
      return '—';
    }
    return wp_date( 'd/m/Y H:i', strtotime( $gmt_datetime . ' UTC' ) );
  }

  public static function time_ago( $gmt_datetime ) {
    if ( empty( $gmt_datetime ) ) {
      return '';
    }
    return human_time_diff( strtotime( $gmt_datetime . ' UTC' ), time() );
  }

  public static function is_post() {
    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
    return 'POST' === $method;
  }

  /** Límite simple por ventana con transients. */
  public static function rate_limit( $bucket, $limit, $window ) {
    $key   = 'lm2fa_rl_' . md5( (string) $bucket );
    $count = (int) get_transient( $key );

    if ( $count >= $limit ) {
      return false;
    }

    set_transient( $key, $count + 1, $window );
    return true;
  }

  public static function clear_rate_limit( $bucket ) {
    delete_transient( 'lm2fa_rl_' . md5( (string) $bucket ) );
  }

  /* ------------------------------- Cookies ------------------------------ */

  /**
   * Cookie propia del plugin: siempre HttpOnly y SameSite=Lax.
   *
   * Lax es lo correcto aquí: el desafío se completa con un POST del propio
   * sitio, y así la cookie no viaja en peticiones de terceros.
   */
  public static function set_cookie( $name, $value, $expires ) {
    $options = array(
      'expires'  => $expires,
      'path'     => COOKIEPATH ? COOKIEPATH : '/',
      'domain'   => COOKIE_DOMAIN,
      'secure'   => is_ssl(),
      'httponly' => true,
      'samesite' => 'Lax',
    );

    if ( ! headers_sent() ) {
      setcookie( $name, $value, $options );
    }

    $_COOKIE[ $name ] = $value;
  }

  public static function clear_cookie( $name ) {
    self::set_cookie( $name, ' ', time() - YEAR_IN_SECONDS );
    unset( $_COOKIE[ $name ] );
  }

  public static function read_cookie( $name ) {
    return isset( $_COOKIE[ $name ] ) ? (string) wp_unslash( $_COOKIE[ $name ] ) : '';
  }

  /* -------------------------------- Vistas ------------------------------- */

  /**
   * Carga una vista PHP pasándole variables.
   *
   * Las vistas viven en admin/views/ y public/views/ y solo pintan HTML:
   * nada de consultas ni de lógica de negocio.
   *
   * @param string $relative Ruta relativa a la raíz del plugin, sin .php.
   * @param array  $vars     Variables disponibles dentro de la vista.
   */
  public static function view( $relative, array $vars = array() ) {
    $file = LM2FA_DIR . ltrim( $relative, '/' ) . '.php';

    if ( ! is_readable( $file ) ) {
      return;
    }

    // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- idioma habitual de plantillas en WP.
    extract( $vars, EXTR_SKIP );
    include $file;
  }

  /** Igual que view() pero devuelve el HTML en lugar de imprimirlo. */
  public static function view_to_string( $relative, array $vars = array() ) {
    ob_start();
    self::view( $relative, $vars );
    return (string) ob_get_clean();
  }

  public static function site_name() {
    return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
  }
}
