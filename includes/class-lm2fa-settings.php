<?php
/**
 * Registro central de opciones.
 *
 * Toda opción del plugin se declara UNA sola vez en registry(). De ahí salen:
 *  - los valores por defecto que se siembran al activar,
 *  - el sanitize_callback de register_setting(),
 *  - la lista de opciones a borrar en uninstall.php.
 *
 * Añadir una opción nueva = añadir una línea en registry() y pintar el campo
 * en la vista de la pestaña que le corresponda.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Settings {

  /** Grupos de settings_fields(), uno por pestaña del panel de ajustes. */
  const GROUP_CONNECTION = 'lm2fa_connection';
  const GROUP_BEHAVIOR   = 'lm2fa_behavior';
  const GROUP_BRANDING   = 'lm2fa_branding_group';
  const GROUP_MAINTENANCE = 'lm2fa_maintenance';

  /**
   * key => array( grupo, valor por defecto, sanitize_callback )
   *
   * Un default `null` significa "no sembrar al activar" (secretos).
   *
   * @return array<string,array{0:string,1:mixed,2:callable|string}>
   */
  public static function registry() {
    return array(
      /* ---------------------------- Conexión ---------------------------- */
      'lm2fa_server_url' => array( self::GROUP_CONNECTION, LM2FA_DEFAULT_SERVER, array( __CLASS__, 'sanitize_server_url' ) ),
      'lm2fa_api_key'    => array( self::GROUP_CONNECTION, null, array( __CLASS__, 'sanitize_api_key' ) ),

      /* -------------------------- Comportamiento ------------------------- */
      'lm2fa_enforced_roles'    => array( self::GROUP_BEHAVIOR, array(), array( __CLASS__, 'sanitize_roles' ) ),
      'lm2fa_enforce_lock'      => array( self::GROUP_BEHAVIOR, 'no', array( __CLASS__, 'sanitize_yesno' ) ),
      'lm2fa_trust_days'        => array( self::GROUP_BEHAVIOR, 0, array( __CLASS__, 'sanitize_trust_days' ) ),
      'lm2fa_account_tab'       => array( self::GROUP_BEHAVIOR, 'yes', array( __CLASS__, 'sanitize_yesno' ) ),
      'lm2fa_email_fallback'    => array( self::GROUP_BEHAVIOR, 'no', array( __CLASS__, 'sanitize_yesno' ) ),
      'lm2fa_block_legacy_auth' => array( self::GROUP_BEHAVIOR, 'yes', array( __CLASS__, 'sanitize_yesno' ) ),
      'lm2fa_new_device_alert'  => array( self::GROUP_BEHAVIOR, 'yes', array( __CLASS__, 'sanitize_yesno' ) ),

      /* --------------------------- Apariencia ---------------------------- */
      'lm2fa_branding'    => array( self::GROUP_BRANDING, 'no', array( __CLASS__, 'sanitize_yesno' ) ),
      'lm2fa_logo_id'     => array( self::GROUP_BRANDING, 0, 'absint' ),
      'lm2fa_logo_width'  => array( self::GROUP_BRANDING, 240, 'absint' ),
      'lm2fa_logo_height' => array( self::GROUP_BRANDING, 90, 'absint' ),
      'lm2fa_login_bg'    => array( self::GROUP_BRANDING, '', 'sanitize_hex_color' ),

      /* -------------------------- Mantenimiento -------------------------- */
      'lm2fa_low_balance'         => array( self::GROUP_MAINTENANCE, 10, 'absint' ),
      'lm2fa_low_balance_email'   => array( self::GROUP_MAINTENANCE, 'yes', array( __CLASS__, 'sanitize_yesno' ) ),
      'lm2fa_delete_on_uninstall' => array( self::GROUP_MAINTENANCE, 'no', array( __CLASS__, 'sanitize_yesno' ) ),
    );
  }

  /** Lee una opción aplicando el default declarado en el registro. */
  public static function get( $key, $fallback = null ) {
    $registry = self::registry();
    $default  = isset( $registry[ $key ] ) ? $registry[ $key ][1] : $fallback;

    if ( null === $default ) {
      $default = ( null === $fallback ) ? '' : $fallback;
    }
    return get_option( $key, $default );
  }

  public static function is_yes( $key ) {
    return 'yes' === self::get( $key );
  }

  public static function int( $key, $min = null, $max = null ) {
    $value = (int) self::get( $key );
    if ( null !== $min ) {
      $value = max( $min, $value );
    }
    if ( null !== $max ) {
      $value = min( $max, $value );
    }
    return $value;
  }

  /** @return string[] Roles a los que se exige el segundo factor. */
  public static function enforced_roles() {
    return array_filter( (array) self::get( 'lm2fa_enforced_roles', array() ) );
  }

  /** Siembra los valores por defecto sin pisar los ya configurados. */
  public static function seed_defaults() {
    foreach ( self::registry() as $key => $spec ) {
      list( , $default ) = $spec;
      if ( null === $default ) {
        continue;
      }
      if ( false === get_option( $key, false ) ) {
        add_option( $key, $default, '', false );
      }
    }
  }

  /** Registra todas las opciones en la Settings API. */
  public static function register() {
    foreach ( self::registry() as $key => $spec ) {
      list( $group, , $sanitize ) = $spec;
      register_setting( $group, $key, array( 'sanitize_callback' => $sanitize ) );
    }
  }

  /** @return string[] Nombres de todas las opciones, para la desinstalación. */
  public static function option_names() {
    return array_keys( self::registry() );
  }

  /* --------------------------- Sanitizadores --------------------------- */

  public static function sanitize_server_url( $value ) {
    $value = esc_url_raw( trim( (string) $value ) );
    $value = $value ? untrailingslashit( $value ) : LM2FA_DEFAULT_SERVER;

    LM2FA_Client::flush_cache();
    return $value;
  }

  /** Si el campo llega vacío conserva la clave guardada: nunca se muestra. */
  public static function sanitize_api_key( $value ) {
    $value = trim( (string) $value );
    if ( '' === $value ) {
      return (string) get_option( 'lm2fa_api_key', '' );
    }

    LM2FA_Client::flush_cache();
    return sanitize_text_field( $value );
  }

  public static function sanitize_roles( $value ) {
    $roles = array_keys( wp_roles()->get_names() );
    return array_values( array_intersect( (array) $value, $roles ) );
  }

  public static function sanitize_trust_days( $value ) {
    return min( 365, absint( $value ) );
  }

  public static function sanitize_yesno( $value ) {
    return 'yes' === $value ? 'yes' : 'no';
  }
}
