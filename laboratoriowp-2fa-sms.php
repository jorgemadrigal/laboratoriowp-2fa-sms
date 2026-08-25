<?php
/**
 * Plugin Name: LaboratorioWP 2FA por SMS
 * Plugin URI:  https://laboratoriowp.com/sms-marketing-y-otp-por-sms/
 * Description: Verificación en dos pasos por SMS para WordPress y WooCommerce, conectada a la plataforma LaboratorioWP. Requiere una clave API de tu panel de cliente.
 * Version:     2.0.0
 * Author:      LaboratorioWP.com
 * Author URI:  https://laboratoriowp.com
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * Text Domain: lmsms-2fa
 * Domain Path: /languages
 *
 * @package LaboratorioWP_2FA
 *
 * -------------------------------------------------------------------------
 * Este archivo es solo el arranque. No contiene lógica de negocio:
 * define constantes, registra el autoloader y delega en LM2FA_Plugin.
 * Para entender el plugin lee README.md y empieza por
 * includes/class-lm2fa-plugin.php.
 * -------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

define( 'LM2FA_VERSION', '2.0.0' );
define( 'LM2FA_FILE', __FILE__ );
define( 'LM2FA_DIR', plugin_dir_path( __FILE__ ) );
define( 'LM2FA_URL', plugin_dir_url( __FILE__ ) );

/** Servidor central por defecto: el panel de cliente de LaboratorioWP. */
define( 'LM2FA_DEFAULT_SERVER', 'https://clientes.laboratoriowp.com' );

require_once LM2FA_DIR . 'includes/class-lm2fa-autoloader.php';
LM2FA_Autoloader::register();

register_activation_hook( LM2FA_FILE, array( 'LM2FA_Install', 'activate' ) );
register_deactivation_hook( LM2FA_FILE, array( 'LM2FA_Install', 'deactivate' ) );

LM2FA_Plugin::boot();
