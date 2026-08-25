<?php
/**
 * Equipos de confianza.
 *
 * Con "días de confianza" a 0 (valor por defecto) esta clase no hace nada:
 * siempre se pide el código. Cuando se activa, cada equipo recordado guarda
 * un token aleatorio cuyo hash vive en user_meta; la cookie solo lleva el
 * token, así que robar la base de datos no basta para saltarse el segundo
 * factor.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Devices {

  const META   = 'lm2fa_trusted_devices';
  const META_SEEN = 'lm2fa_known_agents';
  const COOKIE = 'lm2fa_device';

  /** Cuántas huellas de navegador se recuerdan por usuario. */
  const MAX_SEEN = 10;

  private static function days() {
    return LM2FA_Settings::int( 'lm2fa_trust_days', 0, 365 );
  }

  public static function is_enabled() {
    return self::days() > 0;
  }

  private static function cookie_name() {
    return self::COOKIE . '_' . COOKIEHASH;
  }

  /** Identificador corto y estable para poder revocar un equipo concreto. */
  private static function id( array $device ) {
    return substr( md5( (string) $device['hash'] ), 0, 12 );
  }

  /** @return array[] Equipos vigentes, del más reciente al más antiguo. */
  public static function all( $user_id ) {
    $devices = (array) get_user_meta( $user_id, self::META, true );
    $current = self::current_token_hash( $user_id );
    $list    = array();

    foreach ( $devices as $device ) {
      if ( empty( $device['hash'] ) || empty( $device['expires'] ) || $device['expires'] <= time() ) {
        continue;
      }

      $list[] = array(
        'id'      => self::id( $device ),
        'agent'   => isset( $device['agent'] ) ? $device['agent'] : '',
        'created' => isset( $device['created'] ) ? $device['created'] : '',
        'expires' => (int) $device['expires'],
        'current' => ( '' !== $current && hash_equals( (string) $device['hash'], $current ) ),
      );
    }

    return array_reverse( $list );
  }

  public static function count( $user_id ) {
    return count( self::all( $user_id ) );
  }

  /** Recuerda el equipo actual y deja la cookie preparada. */
  public static function remember( $user_id ) {
    if ( ! self::is_enabled() ) {
      return;
    }

    $token   = wp_generate_password( 40, false, false );
    $expires = time() + self::days() * DAY_IN_SECONDS;

    $devices = self::purge_expired( (array) get_user_meta( $user_id, self::META, true ) );

    $devices[] = array(
      'hash'    => wp_hash( $token ),
      'expires' => $expires,
      'agent'   => LM2FA_Util::user_agent(),
      'created' => LM2FA_Util::now_gmt(),
    );

    update_user_meta( $user_id, self::META, array_values( $devices ) );
    LM2FA_Util::set_cookie( self::cookie_name(), $user_id . '|' . $token, $expires );
  }

  public static function is_trusted( $user_id ) {
    if ( ! self::is_enabled() ) {
      return false;
    }

    $target = self::current_token_hash( $user_id );
    if ( '' === $target ) {
      return false;
    }

    foreach ( (array) get_user_meta( $user_id, self::META, true ) as $device ) {
      if ( isset( $device['hash'], $device['expires'] )
        && $device['expires'] > time()
        && hash_equals( (string) $device['hash'], $target ) ) {
        return true;
      }
    }

    return false;
  }

  public static function forget_all( $user_id ) {
    delete_user_meta( $user_id, self::META );
    LM2FA_Util::clear_cookie( self::cookie_name() );
  }

  /** Revoca un equipo concreto de la lista del usuario. */
  public static function forget_one( $user_id, $id ) {
    $devices = self::purge_expired( (array) get_user_meta( $user_id, self::META, true ) );
    $kept    = array();
    $removed = false;

    foreach ( $devices as $device ) {
      if ( ! $removed && hash_equals( self::id( $device ), (string) $id ) ) {
        $removed = true;

        // Si se revoca este mismo equipo, la cookie sobra.
        if ( hash_equals( (string) $device['hash'], self::current_token_hash( $user_id ) ) ) {
          LM2FA_Util::clear_cookie( self::cookie_name() );
        }
        continue;
      }
      $kept[] = $device;
    }

    update_user_meta( $user_id, self::META, array_values( $kept ) );

    return $removed;
  }

  /* --------------------------- Equipos conocidos ------------------------- */

  /**
   * Anota la huella del navegador actual y dice si era la primera vez.
   *
   * No es un identificador fuerte —un navegador actualizado cambia de
   * huella— y no se usa para conceder acceso: solo para decidir si merece
   * la pena avisar al usuario por correo de que alguien entró.
   *
   * @return bool True si este navegador no se había visto antes.
   */
  public static function note_fingerprint( $user_id ) {
    $print = substr( wp_hash( LM2FA_Util::user_agent() ), 0, 20 );
    $seen  = array_values( array_filter( (array) get_user_meta( $user_id, self::META_SEEN, true ) ) );

    if ( in_array( $print, $seen, true ) ) {
      return false;
    }

    $seen[] = $print;
    if ( count( $seen ) > self::MAX_SEEN ) {
      $seen = array_slice( $seen, -self::MAX_SEEN );
    }

    update_user_meta( $user_id, self::META_SEEN, $seen );

    // En el primer acceso verificado no hay nada anterior con lo que comparar.
    return count( $seen ) > 1;
  }

  /* ------------------------------ Internos ------------------------------ */

  /** Hash del token que trae la cookie de este navegador, si es de este usuario. */
  private static function current_token_hash( $user_id ) {
    $raw = LM2FA_Util::read_cookie( self::cookie_name() );
    if ( '' === $raw ) {
      return '';
    }

    $parts = explode( '|', $raw );
    if ( 2 !== count( $parts ) || (int) $parts[0] !== (int) $user_id ) {
      return '';
    }

    return wp_hash( $parts[1] );
  }

  private static function purge_expired( array $devices ) {
    return array_filter(
      $devices,
      static function ( $device ) {
        return isset( $device['expires'] ) && $device['expires'] > time();
      }
    );
  }
}
