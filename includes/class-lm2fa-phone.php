<?php
/**
 * Normalización y enmascarado de teléfonos.
 *
 * El servidor central (LM_Phone del plugin SaaS) valida móviles de México a
 * 10 dígitos. Aquí se hace la MISMA comprobación antes de gastar una llamada
 * a la API, y se deja un filtro para quien opere en otro país.
 *
 * La paridad importa en las dos direcciones: si aquí se rechaza un número que
 * el servidor habría aceptado, el usuario no puede darse de alta sin motivo;
 * si se acepta uno que el servidor rechaza, se gasta una llamada para nada.
 * Por eso normalize() replica la cadena de casos de LM_Phone::normalize().
 *
 * Diferencia deliberada: allí se guarda en internacional (52XXXXXXXXXX) y
 * aquí en nacional (10 dígitos), que es lo que teclea y lee el usuario. La
 * conversión entre ambos formatos vive en national().
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
   * El prefijo internacional se quita SOLO cuando la longitud dice que
   * sobra. Quitarlo a ciegas rompía los números nacionales cuya lada
   * empieza por 52, que el servidor sí acepta.
   *
   * @return string|false
   */
  public static function normalize( $raw ) {
    $digits = preg_replace( '/\D+/', '', (string) $raw );
    $digits = preg_replace( '/^00/', '', $digits );

    $national = false;

    if ( 10 === strlen( $digits ) && '0' !== $digits[0] && '1' !== $digits[0] ) {
      $national = $digits;
    } elseif ( 12 === strlen( $digits ) && 0 === strpos( $digits, '52' ) ) {
      $national = substr( $digits, 2 );
    } elseif ( 13 === strlen( $digits ) && 0 === strpos( $digits, '521' ) ) {
      $national = substr( $digits, 3 );
    }

    /**
     * Permite validar numeraciones de otros países.
     *
     * @param string|false $result Número normalizado o false.
     * @param string       $raw    Valor tal como lo escribió el usuario.
     */
    return apply_filters( 'lm2fa_normalize_phone', $national, $raw );
  }

  /**
   * Pasa a nacional lo que venga del servidor, que trabaja en internacional.
   *
   * Vale tanto para un MSISDN completo (525512345678) como para uno ya
   * enmascarado (5255****78): en ambos casos sobran los dos primeros dígitos.
   *
   * @return string
   */
  public static function national( $msisdn ) {
    $msisdn = trim( (string) $msisdn );

    if ( 12 === strlen( $msisdn ) && 0 === strpos( $msisdn, '52' ) ) {
      return substr( $msisdn, 2 );
    }
    return $msisdn;
  }

  /** 55******89 — lo suficiente para reconocerlo sin exponerlo. */
  public static function mask( $phone ) {
    $phone = (string) $phone;

    if ( 10 !== strlen( $phone ) ) {
      return $phone;
    }
    return substr( $phone, 0, 2 ) . '******' . substr( $phone, -2 );
  }

  /**
   * Enmascarado de un teléfono que llega del servidor.
   *
   * LM_Phone::mask() enmascara sobre el MSISDN: deja los cuatro primeros
   * caracteres (52 + las dos primeras cifras del número) y los dos últimos,
   * con tantos asteriscos como haga falta —5255******78—. En pantalla eso
   * convive con el 55******89 del resto del plugin y parecen dos teléfonos
   * distintos, así que se reescribe con la forma de casa.
   *
   * No se cuentan los asteriscos: solo se leen las posiciones que el
   * servidor deja a la vista, que son las mismas sea cual sea la longitud.
   *
   * @return string
   */
  public static function mask_remote( $masked ) {
    $masked = trim( (string) $masked );

    // Llegó sin enmascarar: se enmascara aquí.
    if ( false === strpos( $masked, '*' ) ) {
      return self::mask( self::national( $masked ) );
    }

    if ( 0 === strpos( $masked, '52' ) && strlen( $masked ) >= 6 ) {
      return substr( $masked, 2, 2 ) . '******' . substr( $masked, -2 );
    }

    // Numeración de otro país o formato que no reconocemos: tal cual.
    return $masked;
  }
}
