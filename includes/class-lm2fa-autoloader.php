<?php
/**
 * Autoloader con la convención de nombres de WordPress.
 *
 * LM2FA_Client     -> includes/class-lm2fa-client.php
 * LM2FA_Email_OTP  -> includes/class-lm2fa-email-otp.php
 * LM2FA_Admin      -> admin/class-lm2fa-admin.php
 * LM2FA_Account    -> public/class-lm2fa-account.php
 *
 * Para añadir una clase basta con crear el archivo en una de las carpetas
 * registradas; no hay que tocar ningún índice.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Autoloader {

  /** Carpetas donde se buscan las clases, en orden. */
  private static $paths = array( 'includes/', 'admin/', 'public/' );

  public static function register() {
    spl_autoload_register( array( __CLASS__, 'load' ) );
  }

  public static function load( $class ) {
    if ( 0 !== strpos( $class, 'LM2FA_' ) ) {
      return;
    }

    $file = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';

    foreach ( self::$paths as $path ) {
      $candidate = LM2FA_DIR . $path . $file;
      if ( is_readable( $candidate ) ) {
        require_once $candidate;
        return;
      }
    }
  }
}
