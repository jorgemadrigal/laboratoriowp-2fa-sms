<?php
/**
 * Estado del segundo factor de un usuario.
 *
 * Todo lo que se guarda en user_meta pasa por aquí; ni las vistas ni los
 * controladores llaman a get_user_meta() directamente.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_User {

  const META_PHONE     = 'lm2fa_phone';
  const META_VERIFIED  = 'lm2fa_phone_verified';
  const META_ENABLED   = 'lm2fa_enabled';
  const META_LAST_AUTH = 'lm2fa_last_auth';

  /* ------------------------------ Lecturas ------------------------------ */

  public static function phone( $user_id ) {
    return (string) get_user_meta( $user_id, self::META_PHONE, true );
  }

  public static function masked_phone( $user_id ) {
    return LM2FA_Phone::mask( self::phone( $user_id ) );
  }

  public static function phone_verified( $user_id ) {
    return 'yes' === get_user_meta( $user_id, self::META_VERIFIED, true );
  }

  public static function is_active( $user_id ) {
    return self::phone_verified( $user_id ) && 'yes' === get_user_meta( $user_id, self::META_ENABLED, true );
  }

  public static function last_auth( $user_id ) {
    return (string) get_user_meta( $user_id, self::META_LAST_AUTH, true );
  }

  public static function touch_last_auth( $user_id ) {
    update_user_meta( $user_id, self::META_LAST_AUTH, LM2FA_Util::now_gmt() );
  }

  /* ------------------------------ Obligación ----------------------------- */

  /** ¿Su rol está en la lista de roles obligados? */
  public static function is_enforced( $user ) {
    $roles = LM2FA_Settings::enforced_roles();

    if ( empty( $roles ) || ! $user instanceof WP_User ) {
      return false;
    }
    return (bool) array_intersect( $roles, (array) $user->roles );
  }

  /** Obligado por rol pero todavía sin teléfono confirmado. */
  public static function is_pending_enrollment( $user ) {
    return self::is_enforced( $user ) && ! self::is_active( $user->ID );
  }

  /**
   * ¿Hay que pedirle el código al iniciar sesión?
   *
   * Un usuario obligado por rol que aún no ha confirmado su teléfono no
   * puede recibir SMS: se le deja entrar y se le empuja al alta (ver
   * LM2FA_Enroll::guard_enforced()).
   */
  public static function requires_challenge( $user ) {
    if ( ! $user instanceof WP_User || ! LM2FA_Client::is_configured() ) {
      return false;
    }

    $required = self::is_active( $user->ID )
      || ( self::is_enforced( $user ) && self::phone_verified( $user->ID ) );

    /**
     * Última palabra sobre si este acceso pide segundo factor.
     *
     * @param bool    $required
     * @param WP_User $user
     */
    return (bool) apply_filters( 'lm2fa_requires_challenge', $required, $user );
  }

  /* ------------------------------ Escrituras ----------------------------- */

  public static function set_phone( $user_id, $phone ) {
    update_user_meta( $user_id, self::META_PHONE, $phone );
    update_user_meta( $user_id, self::META_VERIFIED, 'no' );
  }

  public static function confirm_phone( $user_id ) {
    update_user_meta( $user_id, self::META_VERIFIED, 'yes' );
    update_user_meta( $user_id, self::META_ENABLED, 'yes' );

    do_action( 'lm2fa_enrolled', $user_id );
  }

  /** Apaga el segundo factor conservando el teléfono confirmado. */
  public static function disable( $user_id ) {
    update_user_meta( $user_id, self::META_ENABLED, 'no' );

    LM2FA_Recovery::clear( $user_id );
    LM2FA_Devices::forget_all( $user_id );

    do_action( 'lm2fa_disabled', $user_id );
  }

  /** Borra todo: el usuario tendrá que registrar un número desde cero. */
  public static function reset( $user_id ) {
    delete_user_meta( $user_id, self::META_PHONE );
    delete_user_meta( $user_id, self::META_VERIFIED );
    delete_user_meta( $user_id, self::META_ENABLED );

    LM2FA_Recovery::clear( $user_id );
    LM2FA_Devices::forget_all( $user_id );
    LM2FA_Challenge::close( $user_id );

    delete_user_meta( $user_id, LM2FA_Devices::META_SEEN );

    do_action( 'lm2fa_reset', $user_id );
  }

  /** @return string[] Todas las meta keys del plugin, para la desinstalación. */
  public static function meta_keys() {
    return array(
      self::META_PHONE,
      self::META_VERIFIED,
      self::META_ENABLED,
      self::META_LAST_AUTH,
      LM2FA_Recovery::META,
      LM2FA_Devices::META,
      LM2FA_Devices::META_SEEN,
      LM2FA_Challenge::META,
    );
  }
}
