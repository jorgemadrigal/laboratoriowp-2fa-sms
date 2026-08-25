<?php
/**
 * Sesión pendiente de verificación.
 *
 * Entre "la contraseña era correcta" y "el usuario ha entrado" existe un
 * estado intermedio que hay que guardar en algún sitio. Vive en user_meta
 * con caducidad corta, y el navegador solo conserva un token aleatorio:
 *
 *   contraseña OK -> open()  -> cookie con el token -> formulario del código
 *   código OK     -> close() -> wp_set_auth_cookie()
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Challenge {

  const META     = 'lm2fa_pending_session';
  const COOKIE   = 'lm2fa_pending';
  const LIFETIME = 10 * MINUTE_IN_SECONDS;
  const MAX_RESENDS = 2;

  const CHANNEL_SMS   = 'sms';
  const CHANNEL_EMAIL = 'email';

  /** Dónde se pinta el desafío, según dónde escribiera la contraseña. */
  const SCREEN_LOGIN   = 'login';
  const SCREEN_ACCOUNT = 'account';

  /* ------------------------------- Sesión ------------------------------- */

  /**
   * Abre la sesión pendiente y devuelve el token en claro.
   *
   * @param string $screen self::SCREEN_LOGIN o self::SCREEN_ACCOUNT.
   * @return string
   */
  public static function open( $user_id, $redirect, $remember, $screen = self::SCREEN_LOGIN ) {
    $token = wp_generate_password( 32, false, false );

    update_user_meta(
      $user_id,
      self::META,
      array(
        'token'      => wp_hash( $token ),
        'expires'    => time() + self::LIFETIME,
        'redirect'   => $redirect,
        'remember'   => (bool) $remember,
        'screen'     => $screen,
        'channel'    => self::CHANNEL_SMS,
        'request_id' => '',
        'resends'    => 0,
        'email'      => array(),
        'ip'         => LM2FA_Util::ip(),
      )
    );

    return $token;
  }

  public static function screen( array $session ) {
    return isset( $session['screen'] ) ? $session['screen'] : self::SCREEN_LOGIN;
  }

  /** @return array|false */
  public static function get( $user_id, $token ) {
    $session = get_user_meta( $user_id, self::META, true );

    if ( ! is_array( $session ) || empty( $session['token'] ) ) {
      return false;
    }

    if ( empty( $session['expires'] ) || $session['expires'] < time() ) {
      self::close( $user_id );
      return false;
    }

    if ( ! hash_equals( (string) $session['token'], wp_hash( (string) $token ) ) ) {
      return false;
    }

    return $session;
  }

  public static function save( $user_id, array $session ) {
    update_user_meta( $user_id, self::META, $session );
  }

  public static function close( $user_id ) {
    delete_user_meta( $user_id, self::META );
    LM2FA_Util::clear_cookie( self::cookie_name() );
  }

  public static function can_resend( array $session ) {
    return (int) $session['resends'] < self::MAX_RESENDS;
  }

  /* ------------------------------- Cookie ------------------------------- */

  private static function cookie_name() {
    return self::COOKIE . '_' . COOKIEHASH;
  }

  public static function set_cookie( $user_id, $token ) {
    LM2FA_Util::set_cookie( self::cookie_name(), $user_id . '|' . $token, time() + self::LIFETIME );
  }

  /**
   * Quién dice ser y con qué token. El POST manda porque puede haber
   * navegadores que bloqueen la cookie; el token se valida igual en get().
   *
   * @return array{0:int,1:string}
   */
  public static function read_credentials() {
    if ( isset( $_POST['lm2fa_user'], $_POST['lm2fa_token'] ) ) {
      return array(
        absint( wp_unslash( $_POST['lm2fa_user'] ) ),
        sanitize_text_field( wp_unslash( $_POST['lm2fa_token'] ) ),
      );
    }

    $raw = LM2FA_Util::read_cookie( self::cookie_name() );
    if ( '' !== $raw ) {
      $parts = explode( '|', $raw );
      if ( 2 === count( $parts ) ) {
        return array( absint( $parts[0] ), sanitize_text_field( $parts[1] ) );
      }
    }

    return array( 0, '' );
  }

  /* -------------------------- Avisos entre pasos ------------------------- */

  /**
   * El desafío usa Post/Redirect/Get, así que el mensaje de un paso tiene
   * que sobrevivir a una redirección sin poder usar user_meta (aún no hay
   * sesión de WordPress).
   */
  public static function flash( $token, $type, $message ) {
    set_transient( self::flash_key( $token ), array( $type, $message ), self::LIFETIME );
  }

  /** @return array{0:string,1:string} */
  public static function read_flash( $token ) {
    $key   = self::flash_key( $token );
    $flash = get_transient( $key );

    if ( $flash ) {
      delete_transient( $key );
    }

    return is_array( $flash ) ? $flash : array( '', '' );
  }

  private static function flash_key( $token ) {
    return 'lm2fa_flash_' . md5( (string) $token );
  }
}
