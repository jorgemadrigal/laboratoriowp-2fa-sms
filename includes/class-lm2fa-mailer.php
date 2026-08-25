<?php
/**
 * Correos que salen de ESTE sitio.
 *
 * El servidor central no manda correo a los usuarios finales a propósito:
 * saldría de un dominio ajeno al comercio y parecería phishing. Aquí sí
 * tiene sentido, porque el correo sale del dominio de este mismo sitio, con
 * su SMTP y su marca.
 *
 * Se usa wp_mail(), así que respeta cualquier plugin de correo instalado.
 * El Content-Type va como cabecera del mensaje concreto para no alterar los
 * correos de otros plugins.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Mailer {

  /**
   * @param string $view Vista dentro de public/views/, sin .php.
   */
  public static function send( $to, $subject, $view, array $vars = array() ) {
    if ( ! is_email( $to ) ) {
      return false;
    }

    $body = LM2FA_Util::view_to_string(
      'public/views/email-wrapper',
      array(
        'title'   => $subject,
        'content' => LM2FA_Util::view_to_string( 'public/views/' . $view, $vars ),
      )
    );

    return wp_mail(
      $to,
      $subject,
      $body,
      array( 'Content-Type: text/html; charset=UTF-8' )
    );
  }

  /** Código de verificación por correo (canal alternativo al SMS). */
  public static function login_code( WP_User $user, $code, $minutes ) {
    return self::send(
      $user->user_email,
      sprintf(
        /* translators: %s nombre del sitio. */
        __( 'Tu código de acceso a %s', 'lmsms-2fa' ),
        LM2FA_Util::site_name()
      ),
      'email-login-code',
      array(
        'user'    => $user,
        'code'    => $code,
        'minutes' => (int) $minutes,
      )
    );
  }

  /** Aviso al usuario cuando su cuenta se verifica desde un equipo nuevo. */
  public static function new_device( WP_User $user ) {
    return self::send(
      $user->user_email,
      sprintf(
        /* translators: %s nombre del sitio. */
        __( 'Nuevo acceso verificado en %s', 'lmsms-2fa' ),
        LM2FA_Util::site_name()
      ),
      'email-new-device',
      array(
        'user'  => $user,
        'ip'    => LM2FA_Util::ip(),
        'agent' => LM2FA_Util::user_agent(),
        'when'  => LM2FA_Util::local_date( LM2FA_Util::now_gmt() ),
      )
    );
  }

  /** Aviso al administrador cuando el saldo del servicio se está agotando. */
  public static function low_balance( $to, array $quota ) {
    return self::send(
      $to,
      sprintf(
        /* translators: %s nombre del sitio. */
        __( '[%s] Queda poco saldo para la verificación en dos pasos', 'lmsms-2fa' ),
        LM2FA_Util::site_name()
      ),
      'email-low-balance',
      array(
        'quota'     => $quota,
        'panel_url' => LM2FA_Client::panel_url( 'otp' ),
      )
    );
  }
}
