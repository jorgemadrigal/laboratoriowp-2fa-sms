<?php
/**
 * Canal alternativo: código de verificación por correo.
 *
 * Estos códigos se generan y se comprueban AQUÍ. El servidor central no
 * participa, no se le pide nada y no consume saldo. Es la salida prevista
 * para cuando la pasarela SMS falla o el usuario no recibe el mensaje,
 * y sustituye a la única alternativa que había antes: quedarse fuera.
 *
 * Solo interviene en el desafío de acceso. El alta sigue siendo por SMS:
 * el objetivo de esa pantalla es demostrar que el teléfono es suyo.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Email_OTP {

  const TTL          = 10 * MINUTE_IN_SECONDS;
  const MAX_ATTEMPTS = 5;
  const LENGTH       = 6;

  public static function is_enabled() {
    return LM2FA_Settings::is_yes( 'lm2fa_email_fallback' );
  }

  public static function is_available_for( WP_User $user ) {
    return self::is_enabled() && is_email( $user->user_email );
  }

  /** Correo enmascarado para poder nombrarlo sin exponerlo. */
  public static function masked_email( WP_User $user ) {
    $parts = explode( '@', (string) $user->user_email );

    if ( 2 !== count( $parts ) || '' === $parts[0] ) {
      return '';
    }

    $name = ( strlen( $parts[0] ) <= 2 )
      ? substr( $parts[0], 0, 1 ) . '*'
      : substr( $parts[0], 0, 2 ) . str_repeat( '*', min( 6, strlen( $parts[0] ) - 2 ) );

    return $name . '@' . $parts[1];
  }

  private static function hash( $code, $salt ) {
    return hash_hmac( 'sha256', $salt . '|' . $code, wp_salt( 'lm2fa_email_otp' ) );
  }

  /**
   * Genera un código, lo envía y lo guarda (hasheado) dentro de la sesión.
   *
   * @param array $session Sesión pendiente; se devuelve modificada.
   * @return array|WP_Error
   */
  public static function issue( WP_User $user, array $session ) {
    if ( ! self::is_available_for( $user ) ) {
      return new WP_Error( 'lm2fa_email_unavailable', __( 'El envío por correo no está disponible.', 'lmsms-2fa' ) );
    }

    if ( ! LM2FA_Util::rate_limit( 'email_otp_' . $user->ID, 3, 15 * MINUTE_IN_SECONDS ) ) {
      return new WP_Error( 'lm2fa_email_flood', __( 'Ya enviamos varios códigos por correo. Espera unos minutos.', 'lmsms-2fa' ) );
    }

    $code = '';
    for ( $i = 0; $i < self::LENGTH; $i++ ) {
      $code .= (string) wp_rand( 0, 9 );
    }

    $salt = wp_generate_password( 16, false, false );
    $sent = LM2FA_Mailer::login_code( $user, $code, (int) round( self::TTL / MINUTE_IN_SECONDS ) );

    if ( ! $sent ) {
      return new WP_Error( 'lm2fa_email_failed', __( 'No pudimos enviar el correo. Avisa al administrador del sitio.', 'lmsms-2fa' ) );
    }

    $session['email'] = array(
      'salt'     => $salt,
      'hash'     => self::hash( $code, $salt ),
      'expires'  => time() + self::TTL,
      'attempts' => 0,
    );

    LM2FA_Log::add( 'email_sent', 'user:' . $user->ID, $user->ID );

    return $session;
  }

  public static function is_pending( array $session ) {
    return ! empty( $session['email']['hash'] ) && (int) $session['email']['expires'] > time();
  }

  /**
   * Comprueba el código. Devuelve la sesión actualizada (el contador de
   * intentos sube aunque falle) junto al veredicto.
   *
   * @return array{0:bool,1:array,2:string} [válido, sesión, mensaje de error]
   */
  public static function verify( array $session, $code ) {
    $code = preg_replace( '/\D/', '', (string) $code );

    if ( ! self::is_pending( $session ) ) {
      return array( false, $session, __( 'El código por correo expiró. Solicita uno nuevo.', 'lmsms-2fa' ) );
    }

    if ( (int) $session['email']['attempts'] >= self::MAX_ATTEMPTS ) {
      return array( false, $session, __( 'Se agotaron los intentos. Solicita un código nuevo.', 'lmsms-2fa' ) );
    }

    $session['email']['attempts'] = (int) $session['email']['attempts'] + 1;

    $expected = self::hash( $code, $session['email']['salt'] );

    if ( ! hash_equals( (string) $session['email']['hash'], $expected ) ) {
      $left = max( 0, self::MAX_ATTEMPTS - (int) $session['email']['attempts'] );

      $message = __( 'El código no es correcto.', 'lmsms-2fa' );
      if ( $left > 0 ) {
        $message .= ' ' . sprintf(
          /* translators: %d intentos restantes. */
          _n( 'Te queda %d intento.', 'Te quedan %d intentos.', $left, 'lmsms-2fa' ),
          $left
        );
      }

      return array( false, $session, $message );
    }

    // Un código, un uso.
    $session['email'] = array();

    return array( true, $session, '' );
  }
}
