<?php
/**
 * Desafío dentro de Mi cuenta de WooCommerce.
 *
 * Quien escribe su contraseña en el formulario de Mi cuenta espera seguir en
 * la tienda: mandarlo a wp-login.php rompe la marca, pierde el carrito de
 * vista y parece otro sitio. Esta pantalla pinta el mismo desafío donde
 * WooCommerce pintaría el formulario de acceso, así que hereda la plantilla
 * del tema, su cabecera, su pie y sus estilos.
 *
 * Cómo se sustituye el formulario: WooCommerce carga
 * `myaccount/form-login.php` para el visitante sin sesión, y todas sus
 * plantillas pasan por el filtro `wc_get_template`. Mientras haya un desafío
 * pendiente se devuelve la plantilla de este plugin en su lugar; el resto de
 * la página lo sigue montando el tema.
 *
 * La lógica de verificación es la misma que en wp-login.php: LM2FA_Verifier.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Account_Challenge {

  /** Plantilla de WooCommerce que se sustituye. */
  const TEMPLATE = 'myaccount/form-login.php';

  /** Desafío pendiente de este visitante: array, false o null (sin resolver). */
  private static $pending = null;

  /** Resultado del POST ya procesado, para pintarlo más abajo en la página. */
  private static $result = array(
    'error'  => '',
    'notice' => '',
    'fatal'  => false,
  );

  /**
   * Aquí solo se registran hooks.
   *
   * Ojo con preguntar nada sobre URLs en este punto: init() corre en
   * 'plugins_loaded' y WordPress todavía no ha creado $wp_rewrite, así que
   * cualquier permalink revienta. Todo lo demás se comprueba más tarde,
   * cuando ya hay entorno.
   */
  public static function init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
      return;
    }

    add_action( 'template_redirect', array( __CLASS__, 'maybe_process' ), 5 );
    add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
    add_filter( 'wc_get_template', array( __CLASS__, 'swap_login_template' ), 10, 2 );
  }

  /**
   * WooCommerce activo y con página de Mi cuenta publicada.
   *
   * Consulta el permalink, así que no puede llamarse antes de 'init'.
   */
  public static function is_available() {
    return function_exists( 'wc_get_page_permalink' ) && '' !== (string) wc_get_page_permalink( 'myaccount' );
  }

  /**
   * ¿Se puede desviar aquí el desafío?
   *
   * Se comprueba antes de mandar a nadie: si la página de Mi cuenta se montó
   * con otra cosa (un constructor visual, bloques propios del tema), el
   * desafío no llegaría a pintarse y el usuario se quedaría dando vueltas
   * entre el formulario y el redirect. En ese caso se usa wp-login.php, que
   * es menos bonito pero funciona siempre.
   */
  private static function can_render() {
    static $can = null;

    if ( null !== $can ) {
      return $can;
    }

    $page    = self::is_available() ? get_post( wc_get_page_id( 'myaccount' ) ) : null;
    $content = $page ? $page->post_content : '';

    // El shortcode clásico y su equivalente en bloques acaban los dos en
    // WC_Shortcode_My_Account::output(), que es quien carga la plantilla.
    $renders = ( false !== strpos( $content, 'woocommerce_my_account' ) )
      || ( false !== strpos( $content, 'woocommerce/classic-shortcode' ) );

    /**
     * Por si un tema pinta el formulario de acceso de otra forma que
     * también pase por wc_get_template().
     *
     * @param bool $renders
     */
    $can = (bool) apply_filters( 'lm2fa_account_renders_login_form', $renders );

    return $can;
  }

  public static function url() {
    return wc_get_page_permalink( 'myaccount' );
  }

  /* ---------------------------- Reconocimiento --------------------------- */

  /**
   * ¿El acceso que se acaba de validar venía del formulario de WooCommerce?
   *
   * Se comprueba igual que WC_Form_Handler::process_login(), que es quien lo
   * ha lanzado: mismos campos y mismo nonce.
   */
  public static function claims_login() {
    $claims = false;

    if ( isset( $_POST['login'], $_POST['username'], $_POST['password'] ) && self::can_render() ) {
      $nonce = '';

      if ( isset( $_POST['woocommerce-login-nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_POST['woocommerce-login-nonce'] ) );
      } elseif ( isset( $_POST['_wpnonce'] ) ) {
        // Las plantillas anteriores a WooCommerce 3.3 usaban _wpnonce.
        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
      }

      $claims = (bool) wp_verify_nonce( $nonce, 'woocommerce-login' );
    }

    /**
     * Permite forzar (o descartar) la pantalla del front para otros
     * formularios de acceso que no sean los de WooCommerce.
     *
     * @param bool $claims
     */
    return (bool) apply_filters( 'lm2fa_use_account_screen', $claims );
  }

  /** A dónde iba el usuario, con el mismo criterio que WooCommerce. */
  public static function intended_redirect() {
    if ( ! empty( $_POST['redirect'] ) ) {
      $redirect = wp_unslash( $_POST['redirect'] );
    } elseif ( function_exists( 'wc_get_raw_referer' ) && wc_get_raw_referer() ) {
      $redirect = wc_get_raw_referer();
    } else {
      $redirect = self::url();
    }

    return remove_query_arg( array( 'wc_error', 'password-reset' ), $redirect );
  }

  /* ------------------------------- Estado -------------------------------- */

  /**
   * Desafío pendiente para este visitante en esta pantalla.
   *
   * @return array{user:WP_User,session:array,token:string}|false
   */
  private static function pending() {
    if ( null !== self::$pending ) {
      return self::$pending;
    }

    self::$pending = false;

    if ( is_user_logged_in() ) {
      return false;
    }

    list( $user_id, $token ) = LM2FA_Challenge::read_credentials();

    if ( ! $user_id ) {
      return false;
    }

    $session = LM2FA_Challenge::get( $user_id, $token );
    $user    = get_userdata( $user_id );

    if ( ! $session || ! $user || LM2FA_Challenge::SCREEN_ACCOUNT !== LM2FA_Challenge::screen( $session ) ) {
      return false;
    }

    self::$pending = array(
      'user'    => $user,
      'session' => $session,
      'token'   => $token,
    );

    return self::$pending;
  }

  /* ------------------------------- Proceso ------------------------------- */

  /**
   * Atiende el POST del desafío antes de que se imprima nada, que es la
   * única forma de poder redirigir cuando el código es correcto.
   */
  public static function maybe_process() {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
      return;
    }

    $pending = self::pending();

    if ( ! $pending ) {
      return;
    }

    /*
     * "Cancelar" cierra el desafío para que vuelva a verse el formulario de
     * acceso. No lleva nonce porque solo puede abandonar la sesión pendiente
     * del propio visitante, identificada por su cookie.
     */
    if ( isset( $_GET['lm2fa_cancel'] ) ) {
      LM2FA_Challenge::close( $pending['user']->ID );
      wp_safe_redirect( self::url() );
      exit;
    }

    // El primer aviso viaja en el flash: se recoge aunque no haya POST.
    list( $type, $message ) = LM2FA_Challenge::read_flash( $pending['token'] );

    if ( $message ) {
      self::$result['error']  = ( 'error' === $type ) ? $message : '';
      self::$result['notice'] = ( 'notice' === $type ) ? $message : '';
    }

    if ( ! LM2FA_Util::is_post() || ! isset( $_POST['lm2fa_token'] ) ) {
      return;
    }

    $result = LM2FA_Verifier::handle( $pending['user'], $pending['session'] );

    if ( LM2FA_Verifier::VERIFIED === $result['outcome'] ) {
      self::complete( $pending['user'], $result['session'], $result['trust'] );
    }

    self::$pending['session'] = $result['session'];

    self::$result = array(
      'error'  => $result['error'],
      'notice' => $result['notice'],
      'fatal'  => ( LM2FA_Verifier::FATAL === $result['outcome'] ),
    );
  }

  private static function complete( WP_User $user, array $session, $trust_device ) {
    LM2FA_Verifier::complete( $user, $session, $trust_device );

    $redirect = ! empty( $session['redirect'] ) ? $session['redirect'] : self::url();

    wp_safe_redirect(
      wp_validate_redirect(
        apply_filters( 'woocommerce_login_redirect', $redirect, $user ),
        self::url()
      )
    );
    exit;
  }

  /* ----------------------------- Presentación ---------------------------- */

  public static function assets() {
    if ( ! self::pending() ) {
      return;
    }

    wp_enqueue_style( 'lm2fa-account', LM2FA_URL . 'assets/css/account.css', array(), LM2FA_VERSION );
    wp_enqueue_script( 'lm2fa-login', LM2FA_URL . 'assets/js/login.js', array(), LM2FA_VERSION, true );
  }

  /**
   * Sustituye el formulario de acceso por el del segundo factor.
   *
   * @param string $template      Ruta que iba a cargarse.
   * @param string $template_name Nombre lógico de la plantilla.
   * @return string
   */
  public static function swap_login_template( $template, $template_name ) {
    if ( self::TEMPLATE !== $template_name || ! self::pending() ) {
      return $template;
    }

    return LM2FA_DIR . 'public/views/account-challenge-loader.php';
  }

  /** Llamado por el cargador de plantilla. Prepara los datos y pinta. */
  public static function render() {
    $pending = self::pending();

    if ( ! $pending ) {
      return;
    }

    $vars = LM2FA_Verifier::view_vars(
      $pending['user'],
      $pending['session'],
      $pending['token'],
      self::$result['error'],
      self::$result['notice']
    );

    $vars['fatal']       = self::$result['fatal'];
    $vars['form_action'] = self::url();
    $vars['cancel_url']  = add_query_arg( 'lm2fa_cancel', '1', self::url() );

    LM2FA_Util::view( 'public/views/account-challenge', $vars );
  }
}
