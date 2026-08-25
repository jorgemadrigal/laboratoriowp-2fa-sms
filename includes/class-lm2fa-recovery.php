<?php
/**
 * Códigos de respaldo.
 *
 * Se guardan siempre como HMAC: ni el administrador de la base de datos
 * puede leerlos. El texto en claro se muestra una única vez, justo después
 * de generarlos.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Recovery {

  const META      = 'lm2fa_recovery_codes';
  const COUNT     = 8;
  const LOW_WATER = 2;

  private static function hash( $code ) {
    return hash_hmac( 'sha256', strtoupper( $code ), wp_salt( 'lm2fa_recovery' ) );
  }

  /**
   * Genera un juego nuevo e invalida el anterior.
   *
   * @return string[] Códigos en claro, se muestran una sola vez.
   */
  public static function generate( $user_id, $count = self::COUNT ) {
    $plain  = array();
    $hashed = array();

    for ( $i = 0; $i < $count; $i++ ) {
      $code     = strtoupper( wp_generate_password( 4, false, false ) . '-' . wp_generate_password( 4, false, false ) );
      $plain[]  = $code;
      $hashed[] = self::hash( $code );
    }

    update_user_meta( $user_id, self::META, $hashed );

    return $plain;
  }

  public static function left( $user_id ) {
    return count( (array) get_user_meta( $user_id, self::META, true ) );
  }

  public static function is_running_low( $user_id ) {
    $left = self::left( $user_id );
    return $left > 0 && $left <= self::LOW_WATER;
  }

  /** Comprueba y gasta un código. Cada uno sirve una sola vez. */
  public static function consume( $user_id, $code ) {
    $code   = strtoupper( preg_replace( '/\s+/', '', (string) $code ) );
    $stored = (array) get_user_meta( $user_id, self::META, true );

    if ( empty( $stored ) || '' === $code ) {
      return false;
    }

    $target = self::hash( $code );

    foreach ( $stored as $index => $hash ) {
      if ( hash_equals( (string) $hash, $target ) ) {
        unset( $stored[ $index ] );
        update_user_meta( $user_id, self::META, array_values( $stored ) );
        return true;
      }
    }

    return false;
  }

  public static function clear( $user_id ) {
    delete_user_meta( $user_id, self::META );
  }
}
