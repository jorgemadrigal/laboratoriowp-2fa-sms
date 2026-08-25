<?php
/**
 * Gestor del segundo factor del usuario.
 *
 * Una sola pantalla reutilizada en dos sitios: Ajustes → Mi verificación 2FA
 * y la pestaña "Seguridad" de Mi cuenta de WooCommerce. Decide qué fragmento
 * toca pintar; el HTML está en public/views/manager-*.php.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Manager {

  const CONTEXT_ADMIN   = 'admin';
  const CONTEXT_ACCOUNT = 'account';

  /**
   * @param string $context self::CONTEXT_ADMIN o self::CONTEXT_ACCOUNT.
   */
  public static function render( $context = self::CONTEXT_ADMIN ) {
    if ( ! is_user_logged_in() ) {
      return;
    }

    if ( ! LM2FA_Client::is_configured() ) {
      LM2FA_Util::view( 'public/views/manager-unconfigured' );
      return;
    }

    $user     = wp_get_current_user();
    $user_id  = $user->ID;
    $redirect = self::redirect_for( $context );

    $notice  = LM2FA_Notices::take( $user_id );
    $codes   = LM2FA_Notices::take_codes( $user_id );
    $pending = LM2FA_Notices::get_enrollment( $user_id );

    if ( $notice ) {
      LM2FA_Util::view(
        'public/views/manager-notice',
        array(
          'status'  => $notice[0],
          'message' => $notice[1],
        )
      );
    }

    if ( ! empty( $codes ) ) {
      LM2FA_Util::view( 'public/views/manager-codes', array( 'codes' => $codes ) );
    }

    $active = LM2FA_User::is_active( $user_id );

    LM2FA_Util::view(
      'public/views/manager-status',
      array(
        'active'     => $active,
        'enforced'   => LM2FA_User::is_enforced( $user ),
        'phone'      => LM2FA_User::masked_phone( $user_id ),
        'codes_left' => LM2FA_Recovery::left( $user_id ),
        'low_codes'  => LM2FA_Recovery::is_running_low( $user_id ),
        'last_auth'  => LM2FA_Util::local_date( LM2FA_User::last_auth( $user_id ) ),
      )
    );

    if ( ! $active && ! $pending ) {
      LM2FA_Util::view(
        'public/views/manager-enroll',
        array(
          'redirect' => $redirect,
          'phone'    => LM2FA_User::phone( $user_id ),
        )
      );
      return;
    }

    if ( $pending ) {
      LM2FA_Util::view(
        'public/views/manager-confirm',
        array(
          'redirect' => $redirect,
          'phone'    => LM2FA_Phone::mask( $pending['phone'] ),
        )
      );
      return;
    }

    LM2FA_Util::view(
      'public/views/manager-options',
      array(
        'redirect' => $redirect,
        'enforced' => LM2FA_User::is_enforced( $user ),
        'trust'    => LM2FA_Devices::is_enabled(),
      )
    );

    if ( LM2FA_Devices::is_enabled() ) {
      LM2FA_Util::view(
        'public/views/manager-devices',
        array(
          'redirect' => $redirect,
          'devices'  => LM2FA_Devices::all( $user_id ),
        )
      );
    }
  }

  /** A dónde vuelve el formulario según dónde se esté pintando. */
  private static function redirect_for( $context ) {
    if ( self::CONTEXT_ACCOUNT === $context && function_exists( 'wc_get_account_endpoint_url' ) ) {
      return wc_get_account_endpoint_url( LM2FA_Account::ENDPOINT );
    }
    return LM2FA_Admin::me_url();
  }

  /* --------------------------- Ayudas de vista --------------------------- */

  public static function form_action() {
    return esc_url( admin_url( 'admin-post.php' ) );
  }

  /** Campos ocultos comunes a todos los formularios del gestor. */
  public static function fields( $step, $redirect ) {
    wp_nonce_field( LM2FA_Enroll::NONCE );

    printf( '<input type="hidden" name="action" value="%s">', esc_attr( LM2FA_Enroll::ACTION ) );
    printf( '<input type="hidden" name="lm2fa_step" value="%s">', esc_attr( $step ) );
    printf( '<input type="hidden" name="lm2fa_redirect" value="%s">', esc_attr( $redirect ) );
  }
}
