<?php
/**
 * Traducción de los códigos de error del servidor central a mensajes
 * pensados para el usuario final.
 *
 * Aquí no se decide nada: solo se elige qué se lee en pantalla. Si el
 * servidor añade un código nuevo, se añade una línea en messages().
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Errors {

  /** @return array<string,string> */
  private static function messages() {
    return array(
      'lm_otp_quota_exceeded' => __( 'El servicio de verificación por SMS no tiene saldo disponible. Contacta al administrador del sitio.', 'lmsms-2fa' ),
      'lm_otp_no_balance'     => __( 'El servicio de verificación por SMS no tiene saldo disponible. Contacta al administrador del sitio.', 'lmsms-2fa' ),
      'lm_otp_flood'          => __( 'Demasiadas solicitudes. Intenta más tarde.', 'lmsms-2fa' ),
      'lm_otp_invalid'        => __( 'El código no es correcto.', 'lmsms-2fa' ),
      'lm_otp_expired'        => __( 'El código expiró. Solicita uno nuevo.', 'lmsms-2fa' ),
      'lm_otp_invalidated'    => __( 'El código expiró. Solicita uno nuevo.', 'lmsms-2fa' ),
      'lm_otp_blocked'        => __( 'Se agotaron los intentos. Solicita un código nuevo.', 'lmsms-2fa' ),
      'lm_otp_used'           => __( 'Ese código ya fue utilizado.', 'lmsms-2fa' ),
      'lm_otp_not_found'      => __( 'La solicitud ya no existe. Pide un código nuevo.', 'lmsms-2fa' ),
      'lm_otp_bad_phone'      => __( 'El número registrado no es válido. Regístralo de nuevo.', 'lmsms-2fa' ),
      'lm_otp_send_failed'    => __( 'No pudimos entregar el SMS en este momento. Inténtalo de nuevo.', 'lmsms-2fa' ),
      'lm_otp_disabled'       => __( 'El servicio de verificación está desactivado.', 'lmsms-2fa' ),
      'lm_invalid_key'        => __( 'La conexión con el servicio de verificación no es válida. Avisa al administrador.', 'lmsms-2fa' ),
      'lm_missing_key'        => __( 'La conexión con el servicio de verificación no es válida. Avisa al administrador.', 'lmsms-2fa' ),
      'lm_rate_limited'       => __( 'El servicio está recibiendo demasiadas peticiones. Espera un momento.', 'lmsms-2fa' ),
    );
  }

  /** Mensaje listo para mostrar, con los matices que traiga el error. */
  public static function message( WP_Error $error ) {
    $code     = $error->get_error_code();
    $data     = (array) $error->get_error_data();
    $messages = self::messages();

    if ( 'lm_otp_cooldown' === $code ) {
      return isset( $data['retry_after'] )
        ? sprintf(
          /* translators: %d segundos que faltan para poder reenviar. */
          __( 'Espera %d segundos antes de pedir otro código.', 'lmsms-2fa' ),
          (int) $data['retry_after']
        )
        : __( 'Espera unos segundos antes de pedir otro código.', 'lmsms-2fa' );
    }

    $message = isset( $messages[ $code ] ) ? $messages[ $code ] : $error->get_error_message();

    if ( isset( $data['attempts_left'] ) && (int) $data['attempts_left'] > 0 ) {
      $left     = (int) $data['attempts_left'];
      $message .= ' ' . sprintf(
        /* translators: %d intentos restantes. */
        _n( 'Te queda %d intento.', 'Te quedan %d intentos.', $left, 'lmsms-2fa' ),
        $left
      );
    }

    return $message;
  }

  /** True cuando el problema es del servicio, no de lo que escribió el usuario. */
  public static function is_service_failure( WP_Error $error ) {
    return in_array(
      $error->get_error_code(),
      array( 'lm2fa_transport', 'lm2fa_bad_payload', 'lm2fa_not_configured', 'lm_otp_send_failed', 'lm_otp_no_balance', 'lm_otp_quota_exceeded', 'lm_otp_disabled' ),
      true
    );
  }
}
