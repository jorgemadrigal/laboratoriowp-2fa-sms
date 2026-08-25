<?php
/**
 * Borrado de datos al desinstalar.
 *
 * Solo se ejecuta si el administrador marcó la casilla: desinstalar por
 * error no debe costarle a nadie su segundo factor.
 *
 * Las listas de opciones y de meta keys se leen del propio plugin, así que
 * una opción nueva declarada en LM2FA_Settings::registry() se borra aquí
 * sin tocar este archivo.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  exit;
}

if ( 'yes' !== get_option( 'lm2fa_delete_on_uninstall' ) ) {
  return;
}

/*
 * Se cargan las clases sin arrancar el plugin: aquí solo se necesitan los
 * inventarios de opciones y de meta keys.
 */
define( 'LM2FA_DIR', plugin_dir_path( __FILE__ ) );
define( 'LM2FA_DEFAULT_SERVER', 'https://clientes.laboratoriowp.com' );

require_once LM2FA_DIR . 'includes/class-lm2fa-autoloader.php';
LM2FA_Autoloader::register();

global $wpdb;

/* ------------------------------- Opciones ------------------------------- */

$options = array_merge(
  LM2FA_Settings::option_names(),
  array(
    'lm2fa_log',
    'lm2fa_version',
    'lm2fa_needs_flush',
    'lm2fa_quota_time',
    'lm2fa_balance_state',
  )
);

foreach ( $options as $option ) {
  delete_option( $option );
}

/* ------------------------------ User meta ------------------------------- */

$meta_keys   = LM2FA_User::meta_keys();
$placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );

$wpdb->query(
  $wpdb->prepare(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ( {$placeholders} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- solo marcadores.
    $meta_keys
  )
);

/* ------------------------------ Transients ------------------------------ */

/*
 * Las claves llevan sufijos variables (id de usuario, hash del servidor),
 * así que se barren por patrón. En instalaciones con caché de objetos
 * persistente los transients no viven en la tabla, pero tampoco sobreviven
 * a la desinstalación de forma indefinida: caducan solos.
 */
foreach ( array( 'lm2fa_', '_transient_lm2fa_', '_transient_timeout_lm2fa_' ) as $prefix ) {
  $wpdb->query(
    $wpdb->prepare(
      "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
      $wpdb->esc_like( $prefix ) . '%'
    )
  );
}

wp_clear_scheduled_hook( 'lm2fa_daily_check' );
