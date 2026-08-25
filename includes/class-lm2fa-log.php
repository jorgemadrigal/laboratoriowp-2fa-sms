<?php
/**
 * Registro de eventos.
 *
 * Es un diario corto de diagnóstico, no una auditoría: vive en una opción
 * (sin autoload) y se queda con los últimos MAX eventos. Si algún día hace
 * falta histórico largo, este es el único archivo que hay que cambiar.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Log {

  const OPTION = 'lm2fa_log';
  const MAX    = 60;

  /**
   * @param string $type    Clave corta del evento: otp_sent, login_ok...
   * @param string $detail  Texto libre para el administrador.
   * @param int    $user_id Usuario implicado, si aplica.
   */
  public static function add( $type, $detail = '', $user_id = 0 ) {
    $log = (array) get_option( self::OPTION, array() );

    $log[] = array(
      'time'    => LM2FA_Util::now_gmt(),
      'type'    => sanitize_key( $type ),
      'detail'  => substr( sanitize_text_field( $detail ), 0, 200 ),
      'user_id' => (int) $user_id,
      'ip'      => LM2FA_Util::ip(),
    );

    if ( count( $log ) > self::MAX ) {
      $log = array_slice( $log, -self::MAX );
    }

    update_option( self::OPTION, $log, false );

    /**
     * Para reenviar los eventos a un SIEM o a un log externo.
     *
     * @param string $type
     * @param string $detail
     * @param int    $user_id
     */
    do_action( 'lm2fa_event', $type, $detail, $user_id );
  }

  /** @return array[] Del más reciente al más antiguo. */
  public static function all( $limit = 0 ) {
    $log = array_reverse( (array) get_option( self::OPTION, array() ) );
    return $limit > 0 ? array_slice( $log, 0, $limit ) : $log;
  }

  public static function clear() {
    delete_option( self::OPTION );
  }

  /** Etiquetas legibles para la tabla del administrador. */
  public static function labels() {
    return array(
      'otp_sent'        => __( 'Código enviado', 'lmsms-2fa' ),
      'login_ok'        => __( 'Acceso verificado', 'lmsms-2fa' ),
      'login_failed'    => __( 'Código incorrecto', 'lmsms-2fa' ),
      'enrolled'        => __( 'Alta completada', 'lmsms-2fa' ),
      'disabled'        => __( 'Desactivado', 'lmsms-2fa' ),
      'recovery_used'   => __( 'Código de respaldo', 'lmsms-2fa' ),
      'email_sent'      => __( 'Código por email', 'lmsms-2fa' ),
      'admin_reset'     => __( 'Restablecido por admin', 'lmsms-2fa' ),
      'legacy_blocked'  => __( 'Acceso heredado bloqueado', 'lmsms-2fa' ),
      'low_balance'     => __( 'Saldo bajo', 'lmsms-2fa' ),
      'api_error'       => __( 'Error del servicio', 'lmsms-2fa' ),
      'transport_error' => __( 'Sin conexión', 'lmsms-2fa' ),
      'bad_payload'     => __( 'Respuesta inesperada', 'lmsms-2fa' ),
    );
  }

  public static function label( $type ) {
    $labels = self::labels();
    return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
  }
}
