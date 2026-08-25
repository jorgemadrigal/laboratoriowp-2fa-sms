<?php
/**
 * Pestaña "Seguridad" en Mi cuenta de WooCommerce.
 *
 * Solo aporta el endpoint, el menú y los estilos: la pantalla es la misma
 * que en el escritorio (LM2FA_Manager).
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Account {

  const ENDPOINT = 'seguridad';

  public static function init() {
    if ( ! self::is_available() ) {
      return;
    }

    add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
    add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
    add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_item' ) );
    add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
    add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
    add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
  }

  public static function is_available() {
    return class_exists( 'WooCommerce' ) && LM2FA_Settings::is_yes( 'lm2fa_account_tab' );
  }

  public static function add_endpoint() {
    add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
  }

  public static function query_vars( $vars ) {
    $vars[ self::ENDPOINT ] = self::ENDPOINT;
    return $vars;
  }

  /** Se coloca justo antes de "Cerrar sesión". */
  public static function menu_item( $items ) {
    $new = array();

    foreach ( $items as $key => $label ) {
      if ( 'customer-logout' === $key ) {
        $new[ self::ENDPOINT ] = __( 'Seguridad', 'lmsms-2fa' );
      }
      $new[ $key ] = $label;
    }

    if ( ! isset( $new[ self::ENDPOINT ] ) ) {
      $new[ self::ENDPOINT ] = __( 'Seguridad', 'lmsms-2fa' );
    }

    return $new;
  }

  public static function title() {
    return __( 'Seguridad de la cuenta', 'lmsms-2fa' );
  }

  private static function is_current_page() {
    global $wp;
    return isset( $wp->query_vars[ self::ENDPOINT ] );
  }

  public static function assets() {
    if ( ! self::is_current_page() ) {
      return;
    }

    wp_enqueue_style( 'lm2fa-account', LM2FA_URL . 'assets/css/account.css', array(), LM2FA_VERSION );
    wp_enqueue_script( 'lm2fa-manager', LM2FA_URL . 'assets/js/manager.js', array(), LM2FA_VERSION, true );
  }

  public static function render() {
    echo '<div class="lm2fa-wrap">';
    echo '<p class="lm2fa-hint">' . esc_html__( 'Añade una segunda comprobación al iniciar sesión: además de tu contraseña, pediremos un código enviado por SMS a tu celular.', 'lmsms-2fa' ) . '</p>';

    LM2FA_Manager::render( LM2FA_Manager::CONTEXT_ACCOUNT );

    echo '</div>';
  }
}
