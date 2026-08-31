<?php
/**
 * Alta y gestión del segundo factor por parte del propio usuario.
 *
 * Es un controlador puro: recibe el POST, decide y redirige. El HTML vive
 * en LM2FA_Manager y sus vistas.
 *
 * Para añadir una acción nueva: una entrada en handlers() y un método con
 * ese nombre que devuelva array( estado, mensaje ).
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Enroll {

  const ACTION = 'lm2fa_enroll';
  const NONCE  = 'lm2fa_enroll';

  /** @return array<string,string> paso => método que lo atiende. */
  private static function handlers() {
    return array(
      'send'           => 'step_send',
      'resend'         => 'step_send',
      'confirm'        => 'step_confirm',
      'disable'        => 'step_disable',
      'regenerate'     => 'step_regenerate',
      'change_phone'   => 'step_change_phone',
      'forget_devices' => 'step_forget_devices',
      'forget_device'  => 'step_forget_device',
    );
  }

  public static function init() {
    add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
    add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
    add_action( 'admin_init', array( __CLASS__, 'guard_enforced' ) );
  }

  /* ------------------------------ Despacho ------------------------------- */

  public static function handle() {
    if ( ! is_user_logged_in() ) {
      wp_die( esc_html__( 'Necesitas iniciar sesión.', 'lmsms-2fa' ) );
    }

    check_admin_referer( self::NONCE );

    $user_id  = get_current_user_id();
    $step     = isset( $_POST['lm2fa_step'] ) ? sanitize_key( wp_unslash( $_POST['lm2fa_step'] ) ) : '';
    $handlers = self::handlers();

    if ( isset( $handlers[ $step ] ) ) {
      list( $status, $message ) = call_user_func( array( __CLASS__, $handlers[ $step ] ), $user_id );
    } else {
      $status  = LM2FA_Notices::ERROR;
      $message = __( 'Acción no reconocida.', 'lmsms-2fa' );
    }

    LM2FA_Notices::add( $user_id, $status, $message );
    wp_safe_redirect( self::redirect_target() );
    exit;
  }

  /** Solo se vuelve a una página del propio sitio. */
  private static function redirect_target() {
    $fallback = admin_url( 'options-general.php?page=' . LM2FA_Admin::PAGE_ME );
    $target   = isset( $_POST['lm2fa_redirect'] ) ? wp_unslash( $_POST['lm2fa_redirect'] ) : '';

    return $target ? wp_validate_redirect( $target, $fallback ) : $fallback;
  }

  /* -------------------------------- Pasos -------------------------------- */

  /** @return array{0:string,1:string} */
  private static function step_send( $user_id ) {
    $pending = LM2FA_Notices::get_enrollment( $user_id );

    // En un reenvío el número ya está registrado; en un alta llega por POST.
    $phone = $pending
      ? $pending['phone']
      : LM2FA_Phone::normalize( isset( $_POST['lm2fa_phone'] ) ? wp_unslash( $_POST['lm2fa_phone'] ) : '' );

    if ( ! $phone ) {
      return array( LM2FA_Notices::ERROR, __( 'Escribe un número de celular de 10 dígitos.', 'lmsms-2fa' ) );
    }

    if ( ! LM2FA_Util::rate_limit( 'enroll_' . $user_id, 5, HOUR_IN_SECONDS ) ) {
      return array( LM2FA_Notices::ERROR, __( 'Demasiados intentos de alta. Intenta más tarde.', 'lmsms-2fa' ) );
    }

    LM2FA_User::set_phone( $user_id, $phone );

    $otp = LM2FA_Client::otp_request( $phone, LM2FA_Client::reference( $user_id, 'enroll' ) );

    if ( is_wp_error( $otp ) ) {
      return array( LM2FA_Notices::ERROR, LM2FA_Errors::message( $otp ) );
    }

    LM2FA_Notices::store_enrollment(
      $user_id,
      sanitize_text_field( $otp['request_id'] ),
      $phone,
      isset( $otp['expires_in'] ) ? $otp['expires_in'] : 0
    );

    LM2FA_Log::add( 'otp_sent', 'enroll · ' . ( isset( $otp['billed'] ) ? $otp['billed'] : '?' ), $user_id );

    return array(
      LM2FA_Notices::SUCCESS,
      sprintf(
        /* translators: %s teléfono enmascarado. */
        __( 'Enviamos un código a %s. Escríbelo para confirmar tu teléfono.', 'lmsms-2fa' ),
        LM2FA_Phone::mask( $phone )
      ),
    );
  }

  private static function step_confirm( $user_id ) {
    $pending = LM2FA_Notices::get_enrollment( $user_id );

    if ( ! $pending ) {
      return array( LM2FA_Notices::ERROR, __( 'La solicitud expiró. Envía un código nuevo.', 'lmsms-2fa' ) );
    }

    if ( ! LM2FA_Util::rate_limit( 'enroll_verify_' . $user_id, 8, 15 * MINUTE_IN_SECONDS ) ) {
      return array( LM2FA_Notices::ERROR, __( 'Demasiados intentos. Espera unos minutos.', 'lmsms-2fa' ) );
    }

    $code   = isset( $_POST['lm2fa_code'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['lm2fa_code'] ) ) : '';
    $result = LM2FA_Client::otp_verify( $pending['request_id'], $code );

    if ( is_wp_error( $result ) ) {
      return array( LM2FA_Notices::ERROR, LM2FA_Errors::message( $result ) );
    }

    LM2FA_Notices::clear_enrollment( $user_id );
    LM2FA_User::confirm_phone( $user_id );
    LM2FA_Notices::store_codes( $user_id, LM2FA_Recovery::generate( $user_id ) );

    LM2FA_Log::add( 'enrolled', LM2FA_Phone::mask( $pending['phone'] ), $user_id );

    return array(
      LM2FA_Notices::SUCCESS,
      __( 'Verificación en dos pasos activada. Guarda tus códigos de respaldo.', 'lmsms-2fa' ),
    );
  }

  private static function step_disable( $user_id ) {
    if ( LM2FA_User::is_enforced( wp_get_current_user() ) ) {
      return array( LM2FA_Notices::ERROR, __( 'El administrador exige la verificación en dos pasos para tu rol.', 'lmsms-2fa' ) );
    }

    LM2FA_User::disable( $user_id );
    LM2FA_Log::add( 'disabled', '', $user_id );

    return array( LM2FA_Notices::SUCCESS, __( 'Verificación en dos pasos desactivada.', 'lmsms-2fa' ) );
  }

  private static function step_regenerate( $user_id ) {
    if ( ! LM2FA_User::is_active( $user_id ) ) {
      return array( LM2FA_Notices::ERROR, __( 'Primero activa la verificación en dos pasos.', 'lmsms-2fa' ) );
    }

    LM2FA_Notices::store_codes( $user_id, LM2FA_Recovery::generate( $user_id ) );

    return array(
      LM2FA_Notices::SUCCESS,
      __( 'Generamos códigos de respaldo nuevos. Los anteriores dejaron de servir.', 'lmsms-2fa' ),
    );
  }

  private static function step_change_phone( $user_id ) {
    LM2FA_User::disable( $user_id );

    delete_user_meta( $user_id, LM2FA_User::META_VERIFIED );
    LM2FA_Notices::clear_enrollment( $user_id );

    return array( LM2FA_Notices::SUCCESS, __( 'Registra tu nuevo número para volver a activar el servicio.', 'lmsms-2fa' ) );
  }

  private static function step_forget_devices( $user_id ) {
    LM2FA_Devices::forget_all( $user_id );

    return array( LM2FA_Notices::SUCCESS, __( 'Se pedirá el código en todos tus dispositivos.', 'lmsms-2fa' ) );
  }

  private static function step_forget_device( $user_id ) {
    $id = isset( $_POST['lm2fa_device'] ) ? sanitize_text_field( wp_unslash( $_POST['lm2fa_device'] ) ) : '';

    if ( ! LM2FA_Devices::forget_one( $user_id, $id ) ) {
      return array( LM2FA_Notices::ERROR, __( 'Ese equipo ya no estaba en la lista.', 'lmsms-2fa' ) );
    }

    return array( LM2FA_Notices::SUCCESS, __( 'Se pedirá el código la próxima vez que se entre desde ese equipo.', 'lmsms-2fa' ) );
  }

  /* ------------------------------- Avisos -------------------------------- */

  public static function admin_notices() {
    $user = wp_get_current_user();

    if ( ! $user->ID ) {
      return;
    }

    // En la pantalla del gestor el aviso lo pinta el propio gestor.
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    $is_own = $screen && false !== strpos( (string) $screen->id, LM2FA_Admin::PAGE_ME );

    if ( ! $is_own ) {
      $notice = LM2FA_Notices::take( $user->ID );

      if ( $notice ) {
        printf(
          '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
          esc_attr( LM2FA_Notices::SUCCESS === $notice[0] ? 'success' : 'error' ),
          esc_html( $notice[1] )
        );
      }
    }

    self::pending_enrollment_notice( $user );
    self::low_recovery_notice( $user );
  }

  /** Recordatorio para quien tiene el 2FA obligatorio y aún no lo activó. */
  private static function pending_enrollment_notice( WP_User $user ) {
    if ( ! LM2FA_Client::is_configured() || ! LM2FA_User::is_pending_enrollment( $user ) ) {
      return;
    }

    printf(
      '<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
      esc_html__( 'Seguridad:', 'lmsms-2fa' ),
      esc_html__( 'tu rol requiere verificación en dos pasos por SMS.', 'lmsms-2fa' ),
      esc_url( LM2FA_Admin::me_url() ),
      esc_html__( 'Activarla ahora', 'lmsms-2fa' )
    );
  }

  /** Quedarse sin códigos de respaldo es la causa más común de bloqueo. */
  private static function low_recovery_notice( WP_User $user ) {
    if ( ! LM2FA_User::is_active( $user->ID ) || ! LM2FA_Recovery::is_running_low( $user->ID ) ) {
      return;
    }

    printf(
      '<div class="notice notice-warning is-dismissible"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
      esc_html(
        sprintf(
          /* translators: %d códigos de respaldo restantes. */
          _n(
            'Te queda %d código de respaldo para entrar si pierdes el celular.',
            'Te quedan %d códigos de respaldo para entrar si pierdes el celular.',
            LM2FA_Recovery::left( $user->ID ),
            'lmsms-2fa'
          ),
          LM2FA_Recovery::left( $user->ID )
        )
      ),
      esc_url( LM2FA_Admin::me_url() ),
      esc_html__( 'Generar códigos nuevos', 'lmsms-2fa' )
    );
  }

  /* ------------------------------ Obligación ----------------------------- */

  /**
   * Con "bloquear el escritorio" activo, un usuario obligado por rol no
   * puede usar el administrador hasta registrar su teléfono. El aviso solo
   * se puede ignorar; esto no.
   */
  public static function guard_enforced() {
    if ( ! LM2FA_Settings::is_yes( 'lm2fa_enforce_lock' ) || wp_doing_ajax() ) {
      return;
    }

    $user = wp_get_current_user();

    if ( ! LM2FA_Client::is_configured() || ! LM2FA_User::is_pending_enrollment( $user ) ) {
      return;
    }

    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

    $allowed = array( 'profile.php', 'admin-post.php', 'options-general.php' );
    $script  = isset( $_SERVER['PHP_SELF'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) ) : '';

    if ( in_array( $script, $allowed, true ) && ( 'options-general.php' !== $script || LM2FA_Admin::PAGE_ME === $page ) ) {
      return;
    }

    wp_safe_redirect( LM2FA_Admin::me_url() );
    exit;
  }
}
