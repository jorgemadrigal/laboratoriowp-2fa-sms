<?php
/**
 * Máquina de estados del desafío, sin pantalla.
 *
 * Aquí vive TODA la lógica de "qué pasa cuando el usuario envía el
 * formulario": reenviar, cambiar de canal, gastar un código de respaldo o
 * comprobar el código. No imprime nada, no redirige y no llama a exit.
 *
 * Devuelve un resultado y cada pantalla decide cómo pintarlo:
 *
 *   LM2FA_Login             -> wp-login.php
 *   LM2FA_Account_Challenge -> Mi cuenta de WooCommerce (plantilla del tema)
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Verifier {

  /** Hay que volver a pintar el formulario. */
  const PENDING = 'pending';

  /** Identidad confirmada: la pantalla debe completar el acceso. */
  const VERIFIED = 'verified';

  /** Sin salida por esta vía: solo queda empezar de nuevo. */
  const FATAL = 'fatal';

  /* ------------------------------ Primer envío --------------------------- */

  /**
   * Manda el primer código y deja un aviso preparado para la pantalla.
   *
   * Se llama justo después de validar la contraseña, cuando todavía no hay
   * nada que pintar: el mensaje viaja en el flash de la sesión pendiente.
   */
  public static function issue( WP_User $user, $token ) {
    $otp = LM2FA_Client::otp_request(
      LM2FA_User::phone( $user->ID ),
      LM2FA_Client::reference( $user->ID, 'login' )
    );

    if ( is_wp_error( $otp ) ) {
      self::issue_by_email( $user, $token, $otp );
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
  private static function issue_by_email( WP_User $user, $token, WP_Error $error ) {
    $message = LM2FA_Errors::message( $error );
    $session = LM2FA_Challenge::get( $user->ID, $token );

    if ( ! LM2FA_Errors::is_service_failure( $error )
      || ! LM2FA_Email_OTP::is_available_for( $user )
      || ! is_array( $session ) ) {
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

  /* -------------------------------- Proceso ------------------------------ */

  /**
   * Atiende el POST del formulario del desafío.
   *
   * @return array{outcome:string,error:string,notice:string,session:array,trust:bool}
   */
  public static function handle( WP_User $user, array $session ) {
    // Freno común a todo lo que llegue por POST desde una misma IP.
    if ( ! LM2FA_Util::rate_limit( 'challenge_ip_' . LM2FA_Util::ip(), 30, 15 * MINUTE_IN_SECONDS ) ) {
      return self::pending( $session, __( 'Demasiados intentos desde esta conexión. Espera unos minutos.', 'lmsms-2fa' ) );
    }

    if ( isset( $_POST['lm2fa_resend'] ) ) {
      return self::resend( $user, $session );
    }

    if ( isset( $_POST['lm2fa_email'] ) ) {
      return self::switch_to_email( $user, $session );
    }

    $recovery = isset( $_POST['lm2fa_recovery'] ) ? sanitize_text_field( wp_unslash( $_POST['lm2fa_recovery'] ) ) : '';

    if ( '' !== $recovery ) {
      return self::recovery( $user, $session, $recovery );
    }

    return self::code( $user, $session );
  }

  private static function resend( WP_User $user, array $session ) {
    if ( ! LM2FA_Challenge::can_resend( $session ) ) {
      return self::fatal( $session, __( 'Has alcanzado el límite de reenvíos. Vuelve a iniciar sesión.', 'lmsms-2fa' ) );
    }

    $otp = LM2FA_Client::otp_request(
      LM2FA_User::phone( $user->ID ),
      LM2FA_Client::reference( $user->ID, 'resend' )
    );

    if ( is_wp_error( $otp ) ) {
      return self::pending( $session, LM2FA_Errors::message( $otp ) );
    }

    $session['request_id'] = sanitize_text_field( $otp['request_id'] );
    $session['channel']    = LM2FA_Challenge::CHANNEL_SMS;
    $session['resends']    = (int) $session['resends'] + 1;

    // Pedir un SMS nuevo anula el código de correo: si no, se comprobaría
    // el del canal equivocado y el usuario no entendería nada.
    $session['email'] = array();

    LM2FA_Challenge::save( $user->ID, $session );
    LM2FA_Log::add( 'otp_sent', 'resend', $user->ID );

    return self::pending( $session, '', __( 'Enviamos un código nuevo.', 'lmsms-2fa' ) );
  }

  private static function switch_to_email( WP_User $user, array $session ) {
    $issued = LM2FA_Email_OTP::issue( $user, $session );

    if ( is_wp_error( $issued ) ) {
      return self::pending( $session, $issued->get_error_message() );
    }

    $issued['channel'] = LM2FA_Challenge::CHANNEL_EMAIL;
    LM2FA_Challenge::save( $user->ID, $issued );

    return self::pending(
      $issued,
      '',
      sprintf(
        /* translators: %s correo enmascarado. */
        __( 'Enviamos un código al correo %s.', 'lmsms-2fa' ),
        LM2FA_Email_OTP::masked_email( $user )
      )
    );
  }

  private static function recovery( WP_User $user, array $session, $code ) {
    if ( ! LM2FA_Util::rate_limit( 'recovery_' . $user->ID, 5, 15 * MINUTE_IN_SECONDS ) ) {
      return self::pending( $session, __( 'Demasiados intentos. Espera unos minutos.', 'lmsms-2fa' ) );
    }

    if ( ! LM2FA_Recovery::consume( $user->ID, $code ) ) {
      return self::pending( $session, __( 'Código de respaldo no válido.', 'lmsms-2fa' ) );
    }

    LM2FA_Log::add( 'recovery_used', 'quedan ' . LM2FA_Recovery::left( $user->ID ), $user->ID );

    return self::verified( $session );
  }

  private static function code( WP_User $user, array $session ) {
    $code = isset( $_POST['lm2fa_code'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['lm2fa_code'] ) ) : '';

    if ( '' === $code ) {
      return self::pending( $session, __( 'Escribe el código que recibiste.', 'lmsms-2fa' ) );
    }

    if ( ! LM2FA_Util::rate_limit( 'verify_' . $user->ID, 8, 15 * MINUTE_IN_SECONDS ) ) {
      return self::pending( $session, __( 'Demasiados intentos. Espera unos minutos.', 'lmsms-2fa' ) );
    }

    // Si hay un código de correo vigente se comprueba contra él.
    if ( LM2FA_Email_OTP::is_pending( $session ) ) {
      list( $valid, $session, $error ) = LM2FA_Email_OTP::verify( $session, $code );
      LM2FA_Challenge::save( $user->ID, $session );

      if ( $valid ) {
        return self::verified( $session );
      }

      LM2FA_Log::add( 'login_failed', 'email', $user->ID );

      return self::pending( $session, $error );
    }

    $result = LM2FA_Client::otp_verify( $session['request_id'], $code );

    if ( is_wp_error( $result ) ) {
      LM2FA_Log::add( 'login_failed', $result->get_error_code(), $user->ID );
      return self::pending( $session, LM2FA_Errors::message( $result ) );
    }

    return self::verified( $session );
  }

  /* -------------------------------- Cierre ------------------------------- */

  /**
   * Efectos del acceso verificado. No redirige: eso es cosa de la pantalla,
   * que sabe a qué filtro de destino tiene que obedecer.
   */
  public static function complete( WP_User $user, array $session, $trust_device ) {
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
  }

  /* ------------------------------- Presentación -------------------------- */

  /**
   * Datos que necesita cualquiera de las dos vistas del desafío.
   *
   * @return array
   */
  public static function view_vars( WP_User $user, array $session, $token, $error = '', $notice = '' ) {
    $by_email = LM2FA_Email_OTP::is_pending( $session );

    return array(
      'user_id'       => $user->ID,
      'token'         => $token,
      'error'         => $error,
      'notice'        => $notice,
      'by_email'      => $by_email,
      'destination'   => $by_email ? LM2FA_Email_OTP::masked_email( $user ) : LM2FA_User::masked_phone( $user->ID ),
      'codes_left'    => LM2FA_Recovery::left( $user->ID ),
      'trust_enabled' => LM2FA_Devices::is_enabled(),
      'trust_days'    => LM2FA_Settings::int( 'lm2fa_trust_days' ),
      'can_resend'    => LM2FA_Challenge::can_resend( $session ),
      'email_offer'   => ! $by_email && LM2FA_Email_OTP::is_available_for( $user ),
    );
  }

  /* -------------------------- Constructores del resultado ---------------- */

  private static function pending( array $session, $error = '', $notice = '' ) {
    return array(
      'outcome' => self::PENDING,
      'error'   => $error,
      'notice'  => $notice,
      'session' => $session,
      'trust'   => false,
    );
  }

  private static function fatal( array $session, $error ) {
    return array(
      'outcome' => self::FATAL,
      'error'   => $error,
      'notice'  => '',
      'session' => $session,
      'trust'   => false,
    );
  }

  private static function verified( array $session ) {
    return array(
      'outcome' => self::VERIFIED,
      'error'   => '',
      'notice'  => '',
      'session' => $session,
      'trust'   => ! empty( $_POST['lm2fa_trust'] ),
    );
  }
}
