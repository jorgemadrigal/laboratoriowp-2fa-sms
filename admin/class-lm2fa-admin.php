<?php
/**
 * Pantallas de administración: menús, assets y carga de vistas.
 *
 * Aquí no se procesan formularios (eso es LM2FA_Admin_Actions) ni se pinta
 * HTML (eso es admin/views/). Este archivo solo reúne los datos y elige la
 * vista.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Admin {

  const PAGE_MAIN  = 'lm2fa';
  const PAGE_USERS = 'lm2fa-users';
  const PAGE_ME    = 'lm2fa-me';

  const PER_PAGE = 25;

  /** Hook suffixes reales devueltos por las funciones de menú. */
  private static $hooks = array();

  public static function init() {
    add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
    add_action( 'admin_init', array( 'LM2FA_Settings', 'register' ) );
    add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

    add_action( 'show_user_profile', array( __CLASS__, 'profile_section' ) );
    add_action( 'edit_user_profile', array( __CLASS__, 'profile_section' ) );

    add_filter( 'plugin_action_links_' . plugin_basename( LM2FA_FILE ), array( __CLASS__, 'action_links' ) );

    LM2FA_Admin_Actions::init();
  }

  /* -------------------------------- Rutas -------------------------------- */

  public static function settings_url( $tab = '' ) {
    $url = admin_url( 'admin.php?page=' . self::PAGE_MAIN );
    return $tab ? add_query_arg( 'tab', $tab, $url ) : $url;
  }

  public static function users_url() {
    return admin_url( 'admin.php?page=' . self::PAGE_USERS );
  }

  public static function me_url() {
    return admin_url( 'options-general.php?page=' . self::PAGE_ME );
  }

  public static function action_links( $links ) {
    array_unshift(
      $links,
      '<a href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Ajustes', 'lmsms-2fa' ) . '</a>'
    );

    return $links;
  }

  /* -------------------------------- Menús -------------------------------- */

  public static function menu() {
    self::$hooks['settings'] = add_menu_page(
      __( 'LabWP 2FA', 'lmsms-2fa' ),
      __( 'LabWP 2FA', 'lmsms-2fa' ),
      'manage_options',
      self::PAGE_MAIN,
      array( __CLASS__, 'page_settings' ),
      'dashicons-shield-alt'
    );

    add_submenu_page(
      self::PAGE_MAIN,
      __( 'Ajustes', 'lmsms-2fa' ),
      __( 'Ajustes', 'lmsms-2fa' ),
      'manage_options',
      self::PAGE_MAIN,
      array( __CLASS__, 'page_settings' )
    );

    self::$hooks['users'] = add_submenu_page(
      self::PAGE_MAIN,
      __( 'Usuarios', 'lmsms-2fa' ),
      __( 'Usuarios', 'lmsms-2fa' ),
      'list_users',
      self::PAGE_USERS,
      array( __CLASS__, 'page_users' )
    );

    self::$hooks['me'] = add_options_page(
      __( 'Mi verificación en dos pasos', 'lmsms-2fa' ),
      __( 'Mi verificación 2FA', 'lmsms-2fa' ),
      'read',
      self::PAGE_ME,
      array( __CLASS__, 'page_me' )
    );
  }

  public static function assets( $hook ) {
    if ( ! in_array( $hook, self::$hooks, true ) ) {
      return;
    }

    wp_enqueue_style( 'lm2fa-admin', LM2FA_URL . 'assets/css/admin.css', array(), LM2FA_VERSION );
    wp_enqueue_script( 'lm2fa-manager', LM2FA_URL . 'assets/js/manager.js', array(), LM2FA_VERSION, true );

    // La biblioteca de medios y el selector de color solo hacen falta en Ajustes.
    if ( ! isset( self::$hooks['settings'] ) || $hook !== self::$hooks['settings'] ) {
      return;
    }

    wp_enqueue_media();
    wp_enqueue_style( 'wp-color-picker' );

    wp_enqueue_script(
      'lm2fa-admin',
      LM2FA_URL . 'assets/js/admin.js',
      array( 'jquery', 'wp-color-picker' ),
      LM2FA_VERSION,
      true
    );

    wp_localize_script(
      'lm2fa-admin',
      'lm2faL10n',
      array(
        'frameTitle'  => __( 'Logo para la pantalla de inicio de sesión', 'lmsms-2fa' ),
        'frameButton' => __( 'Usar esta imagen', 'lmsms-2fa' ),
        'emptyLabel'  => __( 'Sin imagen seleccionada.', 'lmsms-2fa' ),
      )
    );
  }

  /* ------------------------------- Pantallas ------------------------------ */

  /** @return array<string,string> slug => etiqueta. */
  public static function tabs() {
    return array(
      'connection'  => __( 'Conexión', 'lmsms-2fa' ),
      'behavior'    => __( 'Comportamiento', 'lmsms-2fa' ),
      'branding'    => __( 'Apariencia', 'lmsms-2fa' ),
      'maintenance' => __( 'Registro y mantenimiento', 'lmsms-2fa' ),
    );
  }

  private static function current_tab() {
    $tabs = self::tabs();
    $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

    return isset( $tabs[ $tab ] ) ? $tab : 'connection';
  }

  public static function page_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    $tab  = self::current_tab();
    $vars = array(
      'tab'    => $tab,
      'tabs'   => self::tabs(),
      'result' => LM2FA_Admin_Actions::take_result(),
    );

    if ( 'connection' === $tab ) {
      $vars['configured'] = LM2FA_Client::is_configured();
      $vars['quota']      = $vars['configured'] ? LM2FA_Client::quota() : null;
      $vars['updated']    = LM2FA_Client::quota_updated_at();
      $vars['server']     = LM2FA_Client::server_url();
      $vars['host']       = wp_parse_url( LM2FA_Client::server_url(), PHP_URL_HOST );
      $vars['has_key']    = '' !== LM2FA_Client::api_key();
      $vars['panel_api']  = LM2FA_Client::panel_url( 'api' );
      $vars['panel_otp']  = LM2FA_Client::panel_url( 'otp' );
    }

    if ( 'behavior' === $tab ) {
      $vars['roles']    = wp_roles()->get_names();
      $vars['enforced'] = LM2FA_Settings::enforced_roles();
    }

    if ( 'branding' === $tab ) {
      $vars['logo'] = LM2FA_Branding::logo_preview();
    }

    if ( 'maintenance' === $tab ) {
      $vars['log']         = self::describe_log( LM2FA_Log::all( 30 ) );
      $vars['state']       = LM2FA_Monitor::state();
      $vars['admin_email'] = get_option( 'admin_email' );
    }

    LM2FA_Util::view( 'admin/views/page-settings', $vars );
  }

  public static function page_me() {
    LM2FA_Util::view( 'admin/views/page-me' );
  }

  public static function page_users() {
    if ( ! current_user_can( 'list_users' ) ) {
      return;
    }

    $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $filter = isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : '';
    $paged  = max( 1, isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1 );

    $query = self::query_users( $search, $filter, $paged );

    LM2FA_Util::view(
      'admin/views/page-users',
      array(
        'rows'    => self::describe( $query->get_results() ),
        'total'   => (int) $query->get_total(),
        'paged'   => $paged,
        'pages'   => (int) ceil( $query->get_total() / self::PER_PAGE ),
        'search'  => $search,
        'filter'  => $filter,
        'can_edit' => current_user_can( 'edit_users' ),
        'reset'   => isset( $_GET['reset'] ),
      )
    );
  }

  /**
   * El filtro por estado se resuelve con meta_query para no traerse a todos
   * los usuarios del sitio y descartarlos en PHP.
   */
  private static function query_users( $search, $filter, $paged ) {
    $args = array(
      'number' => self::PER_PAGE,
      'paged'  => $paged,
      'offset' => ( $paged - 1 ) * self::PER_PAGE,
      'fields' => 'all',
      'orderby' => 'user_login',
      'order'   => 'ASC',
    );

    if ( '' !== $search ) {
      $args['search']         = '*' . $search . '*';
      $args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
    }

    if ( 'activa' === $filter ) {
      $args['meta_query'] = array(
        array(
          'key'   => LM2FA_User::META_ENABLED,
          'value' => 'yes',
        ),
      );
    } elseif ( 'inactiva' === $filter ) {
      $args['meta_query'] = array(
        'relation' => 'OR',
        array(
          'key'     => LM2FA_User::META_ENABLED,
          'compare' => 'NOT EXISTS',
        ),
        array(
          'key'     => LM2FA_User::META_ENABLED,
          'value'   => 'yes',
          'compare' => '!=',
        ),
      );
    }

    return new WP_User_Query( $args );
  }

  /**
   * Convierte usuarios en filas listas para pintar: la vista no consulta.
   *
   * @param WP_User[] $users
   * @return array[]
   */
  private static function describe( array $users ) {
    $rows = array();

    foreach ( $users as $user ) {
      $phone = LM2FA_User::phone( $user->ID );

      $rows[] = array(
        'id'         => $user->ID,
        'login'      => $user->user_login,
        'email'      => $user->user_email,
        'roles'      => implode( ', ', array_map( 'translate_user_role', array_map( 'ucfirst', (array) $user->roles ) ) ),
        'active'     => LM2FA_User::is_active( $user->ID ),
        'enforced'   => LM2FA_User::is_enforced( $user ),
        'phone'      => $phone ? LM2FA_Phone::mask( $phone ) : '—',
        'has_phone'  => '' !== $phone,
        'codes_left' => LM2FA_Recovery::left( $user->ID ),
        'devices'    => LM2FA_Devices::count( $user->ID ),
        'last_auth'  => LM2FA_Util::local_date( LM2FA_User::last_auth( $user->ID ) ),
      );
    }

    return $rows;
  }

  /**
   * Resuelve etiquetas y nombres de usuario antes de pintar el registro.
   *
   * @param array[] $entries
   * @return array[]
   */
  private static function describe_log( array $entries ) {
    $labels = LM2FA_Log::labels();
    $rows   = array();

    foreach ( $entries as $entry ) {
      $user = empty( $entry['user_id'] ) ? null : get_userdata( $entry['user_id'] );

      $rows[] = array(
        'time'   => LM2FA_Util::local_date( $entry['time'] ),
        'label'  => isset( $labels[ $entry['type'] ] ) ? $labels[ $entry['type'] ] : $entry['type'],
        'user'   => $user ? $user->user_login : '—',
        'detail' => $entry['detail'],
        'ip'     => empty( $entry['ip'] ) ? '—' : $entry['ip'],
      );
    }

    return $rows;
  }

  /** Bloque en el perfil del usuario, propio y ajeno. */
  public static function profile_section( $user ) {
    LM2FA_Util::view(
      'admin/views/profile-section',
      array(
        'is_own'     => ( get_current_user_id() === (int) $user->ID ),
        'active'     => LM2FA_User::is_active( $user->ID ),
        'verified'   => LM2FA_User::phone_verified( $user->ID ),
        'phone'      => LM2FA_User::masked_phone( $user->ID ),
        'codes_left' => LM2FA_Recovery::left( $user->ID ),
        'last_auth'  => LM2FA_Util::local_date( LM2FA_User::last_auth( $user->ID ) ),
        'can_edit'   => current_user_can( 'edit_users' ),
      )
    );
  }
}
