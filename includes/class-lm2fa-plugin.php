<?php
/**
 * Punto de entrada único: aquí se ve de un vistazo qué módulos existen
 * y en qué hook arranca cada uno.
 *
 * Para añadir un módulo nuevo: créalo con un método estático init()
 * y añádelo al array de self::modules().
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Plugin {

  /** Evento de cron diario que vigila el saldo del servicio. */
  const CRON_DAILY = 'lm2fa_daily_check';

  /**
   * Módulos que se inicializan en 'plugins_loaded'.
   * Cada uno expone un init() estático que registra sus propios hooks.
   *
   * @return string[]
   */
  private static function modules() {
    return array(
      'LM2FA_Login',             // Intercepta el acceso y pinta el desafío en wp-login.php.
      'LM2FA_Account_Challenge', // El mismo desafío dentro de Mi cuenta.
      'LM2FA_Enroll',            // Alta y gestión del segundo factor.
      'LM2FA_Account',           // Pestaña "Seguridad" en Mi cuenta de WooCommerce.
      'LM2FA_Branding',          // Apariencia de la pantalla de acceso.
      'LM2FA_Monitor',           // Vigilancia del saldo del servicio.
      'LM2FA_Admin',             // Pantallas de administración.
    );
  }

  public static function boot() {
    add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
    add_action( 'plugins_loaded', array( 'LM2FA_Install', 'maybe_upgrade' ) );
    add_action( 'plugins_loaded', array( __CLASS__, 'init_modules' ) );

    add_action( 'init', array( 'LM2FA_Install', 'maybe_flush_rules' ), 999 );
    add_action( self::CRON_DAILY, array( 'LM2FA_Monitor', 'run' ) );

    if ( defined( 'WP_CLI' ) && WP_CLI ) {
      add_action( 'plugins_loaded', array( 'LM2FA_CLI', 'init' ) );
    }
  }

  public static function load_textdomain() {
    load_plugin_textdomain( 'lmsms-2fa', false, dirname( plugin_basename( LM2FA_FILE ) ) . '/languages' );
  }

  public static function init_modules() {
    foreach ( self::modules() as $module ) {
      if ( is_callable( array( $module, 'init' ) ) ) {
        call_user_func( array( $module, 'init' ) );
      }
    }

    /**
     * Permite a integraciones externas engancharse una vez que todas las
     * clases del plugin están disponibles.
     */
    do_action( 'lm2fa_loaded' );
  }
}
