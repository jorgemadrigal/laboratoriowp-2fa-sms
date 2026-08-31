<?php
/**
 * Activación, desactivación y migraciones entre versiones.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Install {

  const OPTION_VERSION = 'lm2fa_version';
  const OPTION_FLUSH   = 'lm2fa_needs_flush';

  public static function activate() {
    LM2FA_Settings::seed_defaults();
    self::schedule_cron();

    update_option( self::OPTION_VERSION, LM2FA_VERSION, false );
    update_option( self::OPTION_FLUSH, 'yes', false );
  }

  public static function deactivate() {
    wp_clear_scheduled_hook( LM2FA_Plugin::CRON_DAILY );
    flush_rewrite_rules();
  }

  /**
   * Se ejecuta en cada carga: detecta que el código es más nuevo que lo
   * último instalado y pone al día opciones y tareas programadas.
   */
  public static function maybe_upgrade() {
    $installed = (string) get_option( self::OPTION_VERSION, '' );

    if ( LM2FA_VERSION === $installed ) {
      return;
    }

    LM2FA_Settings::seed_defaults();
    self::schedule_cron();

    // 1.x guardaba el saldo en una clave fija; ahora depende de servidor+clave.
    if ( '' !== $installed && version_compare( $installed, '2.0.0', '<' ) ) {
      delete_transient( 'lm2fa_quota' );
      delete_option( 'lm2fa_quota_time' );
    }

    // 2.1.0 lee campos del saldo que antes se ignoraban (enabled) y anota la
    // versión del servidor: se descarta lo cacheado para que la próxima
    // consulta lo traiga completo.
    if ( '' !== $installed && version_compare( $installed, '2.1.0', '<' ) ) {
      LM2FA_Client::flush_cache();
      delete_option( 'lm2fa_balance_state' );
    }

    update_option( self::OPTION_VERSION, LM2FA_VERSION, false );
    update_option( self::OPTION_FLUSH, 'yes', false );
  }

  /** El endpoint de Mi cuenta necesita reescrituras frescas tras activar. */
  public static function maybe_flush_rules() {
    if ( 'yes' !== get_option( self::OPTION_FLUSH ) ) {
      return;
    }
    if ( ! isset( $GLOBALS['wp_rewrite'] ) ) {
      return;
    }

    flush_rewrite_rules( false );
    delete_option( self::OPTION_FLUSH );
  }

  private static function schedule_cron() {
    if ( ! wp_next_scheduled( LM2FA_Plugin::CRON_DAILY ) ) {
      wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', LM2FA_Plugin::CRON_DAILY );
    }
  }
}
