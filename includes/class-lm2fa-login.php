<?php
/**
 * Desafío de inicio de sesión.
 *
 * Flujo completo:
 *
 *   wp_login          la contraseña ya era correcta -> se anula la sesión
 *                     recién creada y se abre una sesión pendiente
 *   redirect          wp-login.php?action=lm2fa_challenge
 *   login_form_...    pinta el formulario y procesa el POST
 *   complete()        wp_set_auth_cookie() y a donde iba
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

    // Vías que no pasan por wp-login.php y se saltarían el segundo factor.
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

    $redirect = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : admin_url();
    $remember = ! empty( $_REQUEST['rememberme'] );
    $token    = LM2FA_Challenge::open( $user->ID, $redirect, $remember );

    self::send_code( $user, $token );
    LM2FA_Challenge::set_cookie( $user->ID, $token );

    wp_safe_redirect( site_url( 'wp-login.php?action=' . self::ACTION, 'login' ) );
    exit;
  }

  /** Primer envío del código, con el resultado guardado como aviso. */
  private static function send_code( WP_User $user, $token ) {
    $otp = LM2FA_Client::otp_request(
      LM2FA_User::phone( $user->ID ),
      LM2FA_Client::reference( $user->ID, 'login' )
    );

    if ( is_wp_error( $otp ) ) {
      self::fall_back_to_email( $user, $token, $otp );
      return;
    }

    $session = LM2FA_Challenge::get( $user->ID, $token );
    if ( is_array( $session ) ) {
      $session['request_id'] = sanitize_text_field( $otp['request_id'] );
      LM2FA_Challenge::save( $user->ID, $session );
    }

    LM2FA_Log::add(
      'otp_sent',
      'login · ' . ( isset( $otp['billed'] ) ? $otp['billed'] : '?' ),
      $user->ID
    );

    LM2FA_Challenge::flash(
      $token,
      'notice',
      sprintf(
        /* translators: %s teléfono enmascarado. */
        __( 'Enviamos un código al teléfono %s.', 'lmsms-2fa' ),
        isset( $otp['phone'] ) ? $otp['phone'] : LM2FA_User::masked_phone( $user->ID )
      )
    );
  }

  /**
   * El SMS no salió. Si la culpa es del servicio —pasarela caída, sin saldo,
   * sin conexión— y el correo está habilitado, se entrega por ahí en lugar de
   * dejar al usuario delante de un formulario que no puede completar.
   *
   * Un error atribuible al usuario (teléfono mal registrado) no activa el
   * respaldo: ahí lo correcto es decírselo.
   */
  private static function fall_back_to_email( WP_User $user, $token, WP_Error $error ) {
    $message = LM2FA_Errors::message( $error );

    if ( ! LM2FA_Errors::is_service_failure( $error ) || ! LM2FA_Email_OTP::is_available_for( $user ) ) {
      LM2FA_Challenge::flash( $token, 'error', $message );
      return;
    }

    $session = LM2FA_Challenge::get( $user->ID, $token );

    if ( ! is_array( $session ) ) {
      LM2FA_Challenge::flash( $token, 'error', $message );
      return;
    }

    $issued = LM2FA_Email_OTP::issue( $user, $session );

    if ( is_wp_error( $issued ) ) {
      LM2FA_Challenge::flash( $token, 'error', $message );
      return;
    }

    $issued['channel'] = LM2FA_Challenge::CHANNEL_EMAIL;
    LM2FA_Challenge::save( $user->ID, $issued );

    LM2FA_Log::add( 'email_sent', 'respaldo · ' . $error->get_error_code(), $user->ID );

    LM2FA_Challenge::flash(
      $token,
      'notice',
      sprintf(
        /* translators: %s correo enmascarado. */
        __( 'No pudimos enviar el SMS, así que te mandamos el código al correo %s.', 'lmsms-2fa' ),
        LM2FA_Email_OTP::masked_email( $user )
      )
    );
  }

  /* ------------------ Vías alternativas de autenticación ----------------- */

  /**
   * XML-RPC y las peticiones autenticadas por contraseña fuera de
   * wp-login.php no pueden mostrar un formulario, así que la única postura
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
        $token,
        $session,
        'error' === $type ? $message : '',
        'notice' === $type ? $message : ''
      );
      exit;
    }

    self::process( $user, $session, $token );
  }

  private static function process( WP_User $user, array $session, $token ) {
    // Freno común a todo lo que llegue por POST desde una misma IP.
    if ( ! LM2FA_Util::rate_limit( 'challenge_ip_' . LM2FA_Util::ip(), 30, 15 * MINUTE_IN_SECONDS ) ) {
      self::render( $user, $token, $session, __( 'Demasiados intentos desde esta conexión. Espera unos minutos.', 'lmsms-2fa' ) );
      exit;
    }

    if ( isset( $_POST['lm2fa_resend'] ) ) {
      self::handle_resend( $user, $session, $token );
    }

    if ( isset( $_POST['lm2fa_email'] ) ) {
      self::handle_email( $user, $session, $token );
    }

    $recovery = isset( $_POST['lm2fa_recovery'] ) ? sanitize_text_field( wp_unslash( $_POST['lm2fa_recovery'] ) ) : '';
    if ( '' !== $recovery ) {
      self::handle_recovery( $user, $session, $token, $recovery );
    }

    self::handle_code( $user, $session, $token );
  }

  /* ------------------------------ Acciones ------------------------------- */

  private static function handle_resend( WP_User $user, array $session, $token ) {
    if ( ! LM2FA_Challenge::can_resend( $session ) ) {
      self::render( $user, $token, $session, __( 'Has alcanzado el límite de reenvíos. Vuelve a iniciar sesión.', 'lmsms-2fa' ), '', true );
      exit;
    }

    $otp = LM2FA_Client::otp_request(
      LM2FA_User::phone( $user->ID ),
      LM2FA_Client::reference( $user->ID, 'resend' )
    );

    if ( is_wp_error( $otp ) ) {
      self::render( $user, $token, $session, LM2FA_Errors::message( $otp ) );
      exit;
    }

    $session['request_id'] = sanitize_text_field( $otp['request_id'] );
    $session['channel']    = LM2FA_Challenge::CHANNEL_SMS;
    $session['resends']    = (int) $session['resends'] + 1;

    // Pedir un SMS nuevo anula el código de correo: si no, se comprobaría
    // el del canal equivocado y el usuario no entendería nada.
    $session['email'] = array();

    LM2FA_Challenge::save( $user->ID, $session );

    LM2FA_Log::add( 'otp_sent', 'resend', $user->ID );

    self::render( $user, $token, $session, '', __( 'Enviamos un código nuevo.', 'lmsms-2fa' ) );
    exit;
  }

  private static function handle_email( WP_User $user, array $session, $token ) {
    $issued = LM2FA_Email_OTP::issue( $user, $session );

    if ( is_wp_error( $issued ) ) {
      self::render( $user, $token, $session, $issued->get_error_message() );
      exit;
    }

    $issued['channel'] = LM2FA_Challenge::CHANNEL_EMAIL;
    LM2FA_Challenge::save( $user->ID, $issued );

    self::render(
      $user,
      $token,
      $issued,
      '',
      sprintf(
        /* translators: %s correo enmascarado. */
        __( 'Enviamos un código al correo %s.', 'lmsms-2fa' ),
        LM2FA_Email_OTP::masked_email( $user )
      )
    );
    exit;
  }

  private static function handle_recovery( WP_User $user, array $session, $token, $recovery ) {
    if ( ! LM2FA_Util::rate_limit( 'recovery_' . $user->ID, 5, 15 * MINUTE_IN_SECONDS ) ) {
      self::render( $user, $token, $session, __( 'Demasiados intentos. Espera unos minutos.', 'lmsms-2fa' ) );
      exit;
    }

    if ( LM2FA_Recovery::consume( $user->ID, $recovery ) ) {
      LM2FA_Log::add( 'recovery_used', 'quedan ' . LM2FA_Recovery::left( $user->ID ), $user->ID );
      self::complete( $user, $session, false );
    }

    self::render( $user, $token, $session, __( 'Código de respaldo no válido.', 'lmsms-2fa' ) );
    exit;
  }

  private static function handle_code( WP_User $user, array $session, $token ) {
    $code = isset( $_POST['lm2fa_code'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['lm2fa_code'] ) ) : '';

    if ( '' === $code ) {
      self::render( $user, $token, $session, __( 'Escribe el código que recibiste.', 'lmsms-2fa' ) );
      exit;
    }

    if ( ! LM2FA_Util::rate_limit( 'verify_' . $user->ID, 8, 15 * MINUTE_IN_SECONDS ) ) {
      self::render( $user, $token, $session, __( 'Demasiados intentos. Espera unos minutos.', 'lmsms-2fa' ) );
      exit;
    }

    // Si hay un código de correo vigente se comprueba contra él.
    if ( LM2FA_Email_OTP::is_pending( $session ) ) {
      list( $valid, $session, $error ) = LM2FA_Email_OTP::verify( $session, $code );
      LM2FA_Challenge::save( $user->ID, $session );

      if ( $valid ) {
        self::complete( $user, $session, ! empty( $_POST['lm2fa_trust'] ) );
      }

      LM2FA_Log::add( 'login_failed', 'email', $user->ID );
      self::render( $user, $token, $session, $error );
      exit;
    }

    $result = LM2FA_Client::otp_verify( $session['request_id'], $code );

    if ( is_wp_error( $result ) ) {
      LM2FA_Log::add( 'login_failed', $result->get_error_code(), $user->ID );
      self::render( $user, $token, $session, LM2FA_Errors::message( $result ) );
      exit;
    }

    self::complete( $user, $session, ! empty( $_POST['lm2fa_trust'] ) );
  }

  /* ------------------------------ Cierre --------------------------------- */

  private static function complete( WP_User $user, array $session, $trust_device ) {
    LM2FA_Challenge::close( $user->ID );

    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, ! empty( $session['remember'] ) );

    if ( $trust_device ) {
      LM2FA_Devices::remember( $user->ID );
    }

    LM2FA_User::touch_last_auth( $user->ID );
    LM2FA_Log::add( 'login_ok', isset( $session['channel'] ) ? $session['channel'] : 'sms', $user->ID );

    if ( LM2FA_Devices::note_fingerprint( $user->ID ) && LM2FA_Settings::is_yes( 'lm2fa_new_device_alert' ) ) {
      LM2FA_Mailer::new_device( $user );
    }

    /**
     * El acceso se ha verificado por completo.
     *
     * @param WP_User $user
     */
    do_action( 'lm2fa_login_verified', $user );

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
  private static function render( WP_User $user, $token, array $session, $error = '', $notice = '', $fatal = false ) {
    $by_email = LM2FA_Email_OTP::is_pending( $session );

    login_header( __( 'Verificación en dos pasos', 'lmsms-2fa' ), '', new WP_Error() );

    LM2FA_Util::view(
      'public/views/login-challenge',
      array(
        'user_id'        => $user->ID,
        'token'          => $token,
        'error'          => $error,
        'notice'         => $notice,
        'fatal'          => $fatal,
        'by_email'       => $by_email,
        'destination'    => $by_email ? LM2FA_Email_OTP::masked_email( $user ) : LM2FA_User::masked_phone( $user->ID ),
        'codes_left'     => LM2FA_Recovery::left( $user->ID ),
        'trust_enabled'  => LM2FA_Devices::is_enabled(),
        'trust_days'     => LM2FA_Settings::int( 'lm2fa_trust_days' ),
        'can_resend'     => LM2FA_Challenge::can_resend( $session ),
        'email_offer'    => ! $by_email && LM2FA_Email_OTP::is_available_for( $user ),
        'form_action'    => site_url( 'wp-login.php?action=' . self::ACTION, 'login_post' ),
      )
    );

    login_footer( 'lm2fa_code' );
  }
}
