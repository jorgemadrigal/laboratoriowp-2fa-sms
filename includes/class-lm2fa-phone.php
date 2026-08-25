<?php
/**
 * Normalización y enmascarado de teléfonos.
 *
 * El servidor central (LM_Phone del plugin SaaS) valida móviles de México a
 * 10 dígitos. Aquí se hace la misma comprobación antes de gastar una llamada
 * a la API, y se deja un filtro para quien opere en otro país.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Phone {

  /**
   * Devuelve 10 dígitos nacionales o false.
   *
   * Acepta lo que la gente escribe de verdad: +52, 0052, 52 1, espacios,
   * guiones y paréntesis.
   *
   * @return string|false
   */
  public static function normalize( $raw ) {
    $digits = preg_replace( '/\D+/', '', (string) $raw );
    $digits = preg_replace( '/^(00)?52(1)?/', '', $digits );

    $valid = ( 10 === strlen( $digits ) && '0' !== $digits[0] && '1' !== $digits[0] );

    /**
     * Permite validar numeraciones de otros países.
     *
     * @param string|false $result Número normalizado o false.
     * @param string       $raw    Valor tal como lo escribió el usuario.
     */
    return apply_filters( 'lm2fa_normalize_phone', $valid ? $digits : false, $raw );
  }

  /** 55******89 — lo suficiente para reconocerlo sin exponerlo. */
  public static function mask( $phone ) {
    $phone = (string) $phone;

    if ( 10 !== strlen( $phone ) ) {
      return $phone;
    }
    return substr( $phone, 0, 2 ) . '******' . substr( $phone, -2 );
  }
}
