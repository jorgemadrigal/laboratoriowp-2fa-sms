<?php
/**
 * Avisos de un solo uso entre peticiones.
 *
 * Los formularios del plugin usan Post/Redirect/Get, así que el resultado
 * de una acción tiene que sobrevivir a una redirección. Se guarda por
 * usuario y se consume al leerlo: quien lo pinta primero se lo queda, y por
 * eso la pantalla que muestra el gestor silencia el aviso global.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Notices {

  const TTL = 60;

  const SUCCESS = 'success';
  const ERROR   = 'error';

  private static function key( $bucket, $user_id ) {
    return 'lm2fa_' . $bucket . '_' . (int) $user_id;
  }

  public static function add( $user_id, $status, $message ) {
    set_transient( self::key( 'notice', $user_id ), array( $status, $message ), self::TTL );
  }

  /**
   * Lee y borra el aviso pendiente.
   *
   * @return array{0:string,1:string}|null
   */
  public static function take( $user_id ) {
    $key    = self::key( 'notice', $user_id );
    $notice = get_transient( $key );

    if ( ! $notice ) {
      return null;
    }

    delete_transient( $key );

    return is_array( $notice ) ? $notice : null;
  }

  /* ------------------- Códigos de respaldo recién creados ------------------ */

  public static function store_codes( $user_id, array $codes ) {
    set_transient( self::key( 'codes', $user_id ), $codes, 10 * MINUTE_IN_SECONDS );
  }

  /** @return string[] Se muestran una sola vez. */
  public static function take_codes( $user_id ) {
    $key   = self::key( 'codes', $user_id );
    $codes = get_transient( $key );

    if ( $codes ) {
      delete_transient( $key );
    }

    return (array) $codes;
  }

  /* --------------------------- Alta en curso ----------------------------- */

  /**
   * Guarda la solicitud OTP abierta mientras el usuario confirma su número.
   *
   * Vive lo que dure el código en el servidor (expires_in) más un margen
   * para teclearlo, nunca menos del cuarto de hora de siempre: el alta no
   * puede caducar antes que el código que se acaba de mandar.
   *
   * @param int $expires_in Vigencia declarada por el servidor, en segundos.
   */
  public static function store_enrollment( $user_id, $request_id, $phone, $expires_in = 0 ) {
    $ttl = max( 15 * MINUTE_IN_SECONDS, (int) $expires_in + LM2FA_Challenge::GRACE );

    set_transient(
      self::key( 'enroll', $user_id ),
      array(
        'request_id' => $request_id,
        'phone'      => $phone,
      ),
      min( LM2FA_Challenge::MAX_LIFETIME, $ttl )
    );
  }

  /** @return array{request_id:string,phone:string}|null */
  public static function get_enrollment( $user_id ) {
    $data = get_transient( self::key( 'enroll', $user_id ) );
    return ( is_array( $data ) && ! empty( $data['request_id'] ) ) ? $data : null;
  }

  public static function clear_enrollment( $user_id ) {
    delete_transient( self::key( 'enroll', $user_id ) );
  }
}
