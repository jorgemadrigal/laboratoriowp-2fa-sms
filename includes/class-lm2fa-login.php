<?php
/**
 * Interceptación del acceso y desafío en wp-login.php.
 *
 * Flujo completo:
 *
 *   wp_login          la contraseña ya era correcta -> se anula la sesión
 *                     recién creada y se abre una sesión pendiente
 *   redirect          a la pantalla que corresponda: wp-login.php si entró
 *                     por ahí, Mi cuenta si entró por el formulario de
 *                     WooCommerce (LM2FA_Account_Challenge)
 *   login_form_...    pinta el formulario y procesa el POST
 *   complete()        wp_set_auth_cookie() y a donde iba
 *
 * La lógica de verificación no está aquí: vive en LM2FA_Verifier, para que
 * las dos pantallas se comporten exactamente igual.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Login {

  const ACTION = 'lm2fa_challenge';

  public static function init() {
    add_action( 'wp_login', array( __CLASS__, 'maybe_intercept' ), 10, 2 );
    add_action( 'login_form_' . self::ACTION, array( __CLASS__, 'route' ) );
    add_action( 'login_enqueue_scripts', array( __CLASS__, 'assets' ) );

    // Vías que no pasan por un formulario y se saltarían el segundo factor.
    add_filter( 'authenticate', array( __CLASS__, 'block_legacy_auth' ), 50 );
    add_filter( 'wp_is_application_passwords_available_for_user', array( __CLASS__, 'block_app_passwords' ), 10, 2 );
  }

  /* --------------------------- Interceptación --------------------------- */

  /**
   * Se ejecuta cuando las credenciales ya son correctas.
   *
   * No imprime nada: deja la sesión pendiente lista y redirige.
   */
  public static function maybe_intercept( $user_login, $user = null ) {
    if ( ! $user instanceof WP_User ) {
      $user = get_user_by( 'login', $user_login );
    }

    if ( ! LM2FA_User::requires_challenge( $user ) ) {
      return;
    }

    if ( LM2FA_Devices::is_trusted( $user->ID ) ) {
      return;
    }

    /*
     * La contraseña era correcta, pero aún no hay acceso. wp_login() ya
     * había creado un token de sesión: hay que destruirlo, no basta con
     * borrar la cookie del navegador.
     */
    wp_destroy_current_session();
    wp_clear_auth_cookie();
    wp_set_current_user( 0 );

    // Quien haya iniciado sesión desde el front se queda en el front.
    $on_account = LM2FA_Account_Challenge::claims_login();

    $token = LM2FA_Challenge::open(
      $user->ID,
      $on_account ? LM2FA_Account_Challenge::intended_redirect() : self::intended_redirect(),
      ! empty( $_REQUEST['rememberme'] ),
      $on_account ? LM2FA_Challenge::SCREEN_ACCOUNT : LM2FA_Challenge::SCREEN_LOGIN
    );

    LM2FA_Verifier::issue( $user, $token );
    LM2FA_Challenge::set_cookie( $user->ID, $token );

    wp_safe_redirect( $on_account ? LM2FA_Account_Challenge::url() : self::url() );
    exit;
  }

  /** URL de esta pantalla. */
  public static function url() {
    return site_url( 'wp-login.php?action=' . self::ACTION, 'login' );
  }

  private static function intended_redirect() {
    return isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : admin_url();
  }

  /* ------------------ Vías alternativas de autenticación ----------------- */

  /**
   * XML-RPC y las peticiones autenticadas por contraseña fuera de un
   * formulario no pueden mostrar un desafío, así que la única postura
   * segura es rechazarlas para quien tiene el segundo factor activo.
   */
  public static function block_legacy_auth( $user ) {
    if ( ! $user instanceof WP_User || ! LM2FA_Settings::is_yes( 'lm2fa_block_legacy_auth' ) ) {
      return $user;
    }

    $is_xmlrpc = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;

    if ( ! $is_xmlrpc || ! LM2FA_User::requires_challenge( $user ) ) {
      return $user;
    }

    LM2FA_Log::add( 'legacy_blocked', 'xmlrpc', $user->ID );

    return new WP_Error(
      'lm2fa_xmlrpc_blocked',
      __( 'Esta cuenta usa verificación en dos pasos y no puede autenticarse por XML-RPC.', 'lmsms-2fa' )
    );
  }

  /**
   * Las contraseñas de aplicación saltan el segundo factor por diseño.
   * Se desactivan para las cuentas protegidas.
   */
  public static function block_app_passwords( $available, $user ) {
    if ( ! LM2FA_Settings::is_yes( 'lm2fa_block_legacy_auth' ) ) {
      return $available;
    }

    if ( $user instanceof WP_User && LM2FA_User::is_active( $user->ID ) ) {
      return false;
    }

    return $available;
  }

  /* ------------------------------- Enrutado ------------------------------ */

  public static function route() {
    list( $user_id, $token ) = LM2FA_Challenge::read_credentials();

    $session = $user_id ? LM2FA_Challenge::get( $user_id, $token ) : false;
    $user    = $user_id ? get_userdata( $user_id ) : false;

    if ( ! $session || ! $user ) {
      LM2FA_Challenge::close( (int) $user_id );
      wp_safe_redirect( wp_login_url() );
      exit;
    }

    if ( ! LM2FA_Util::is_post() ) {
      list( $type, $message ) = LM2FA_Challenge::read_flash( $token );

      self::render(
        $user,
        $session,
        $token,
        'error' === $type ? $message : '',
        'notice' === $type ? $message : ''
      );
      exit;
    }

    $result = LM2FA_Verifier::handle( $user, $session );

    if ( LM2FA_Verifier::VERIFIED === $result['outcome'] ) {
      self::complete( $user, $result['session'], $result['trust'] );
    }

    self::render(
      $user,
      $result['session'],
      $token,
      $result['error'],
      $result['notice'],
      LM2FA_Verifier::FATAL === $result['outcome']
    );
    exit;
  }

  private static function complete( WP_User $user, array $session, $trust_device ) {
    LM2FA_Verifier::complete( $user, $session, $trust_device );

    $redirect = ! empty( $session['redirect'] ) ? $session['redirect'] : admin_url();

    wp_safe_redirect( apply_filters( 'login_redirect', $redirect, $redirect, $user ) );
    exit;
  }

  /* ------------------------------ Presentación --------------------------- */

  public static function assets() {
    $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

    if ( self::ACTION !== $action ) {
      return;
    }

    wp_enqueue_style( 'lm2fa-login', LM2FA_URL . 'assets/css/login.css', array( 'login' ), LM2FA_VERSION );
    wp_enqueue_script( 'lm2fa-login', LM2FA_URL . 'assets/js/login.js', array(), LM2FA_VERSION, true );
  }

  /**
   * @param bool $fatal Sin salida posible: solo se ofrece volver a empezar.
   */
  private static function render( WP_User $user, array $session, $token, $error = '', $notice = '', $fatal = false ) {
    $vars = LM2FA_Verifier::view_vars( $user, $session, $token, $error, $notice );

    $vars['fatal']       = $fatal;
    $vars['form_action'] = site_url( 'wp-login.php?action=' . self::ACTION, 'login_post' );
    $vars['cancel_url']  = wp_login_url();

    login_header( __( 'Verificación en dos pasos', 'lmsms-2fa' ), '', new WP_Error() );
    LM2FA_Util::view( 'public/views/login-challenge', $vars );
    login_footer( 'lm2fa_code' );
  }
}
