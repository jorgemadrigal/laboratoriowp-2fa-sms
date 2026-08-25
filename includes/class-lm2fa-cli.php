<?php
/**
 * Comandos de WP-CLI.
 *
 * Existen sobre todo por un motivo: cuando alguien pierde el teléfono y no
 * guardó los códigos de respaldo, no puede entrar al escritorio para
 * arreglarlo. Desde el servidor sí.
 *
 *   wp lm2fa status [<usuario>]
 *   wp lm2fa disable <usuario>
 *   wp lm2fa reset <usuario>
 *   wp lm2fa quota
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_CLI {

  public static function init() {
    if ( ! class_exists( 'WP_CLI' ) ) {
      return;
    }

    WP_CLI::add_command( 'lm2fa status', array( __CLASS__, 'status' ) );
    WP_CLI::add_command( 'lm2fa disable', array( __CLASS__, 'disable' ) );
    WP_CLI::add_command( 'lm2fa reset', array( __CLASS__, 'reset' ) );
    WP_CLI::add_command( 'lm2fa quota', array( __CLASS__, 'quota' ) );
  }

  /**
   * Estado del segundo factor: de un usuario o del sitio entero.
   *
   * ## OPTIONS
   *
   * [<usuario>]
   * : ID, login o correo. Sin él se resume todo el sitio.
   */
  public static function status( $args ) {
    if ( empty( $args[0] ) ) {
      self::site_status();
      return;
    }

    $user = self::resolve( $args[0] );

    WP_CLI::log( sprintf( 'Usuario:    %s (#%d)', $user->user_login, $user->ID ) );
    WP_CLI::log( sprintf( 'Estado:     %s', LM2FA_User::is_active( $user->ID ) ? 'activa' : 'inactiva' ) );
    WP_CLI::log( sprintf( 'Teléfono:   %s', LM2FA_User::masked_phone( $user->ID ) ?: '—' ) );
    WP_CLI::log( sprintf( 'Obligada:   %s', LM2FA_User::is_enforced( $user ) ? 'sí (por rol)' : 'no' ) );
    WP_CLI::log( sprintf( 'Respaldos:  %d', LM2FA_Recovery::left( $user->ID ) ) );
    WP_CLI::log( sprintf( 'Equipos:    %d', LM2FA_Devices::count( $user->ID ) ) );
    WP_CLI::log( sprintf( 'Último ok:  %s', LM2FA_Util::local_date( LM2FA_User::last_auth( $user->ID ) ) ) );
  }

  private static function site_status() {
    $active = new WP_User_Query(
      array(
        'meta_key'   => LM2FA_User::META_ENABLED,
        'meta_value' => 'yes',
        'fields'     => 'ID',
        'number'     => 1,
      )
    );

    WP_CLI::log( sprintf( 'Servidor:      %s', LM2FA_Client::server_url() ) );
    WP_CLI::log( sprintf( 'Configurado:   %s', LM2FA_Client::is_configured() ? 'sí' : 'no' ) );
    WP_CLI::log( sprintf( 'Roles obligados: %s', implode( ', ', LM2FA_Settings::enforced_roles() ) ?: '—' ) );
    WP_CLI::log( sprintf( 'Usuarios con 2FA activa: %d', (int) $active->get_total() ) );
  }

  /**
   * Apaga el segundo factor de un usuario sin borrar su teléfono.
   *
   * ## OPTIONS
   *
   * <usuario>
   * : ID, login o correo.
   */
  public static function disable( $args ) {
    $user = self::resolve( $args[0] );

    LM2FA_User::disable( $user->ID );
    LM2FA_Log::add( 'disabled', 'wp-cli', $user->ID );

    WP_CLI::success( sprintf( 'Verificación desactivada para %s.', $user->user_login ) );
  }

  /**
   * Borra teléfono, códigos y equipos: el usuario empieza de cero.
   *
   * ## OPTIONS
   *
   * <usuario>
   * : ID, login o correo.
   *
   * [--yes]
   * : No preguntar.
   */
  public static function reset( $args, $assoc_args ) {
    $user = self::resolve( $args[0] );

    WP_CLI::confirm(
      sprintf( '¿Restablecer la verificación de %s? Tendrá que registrar su número otra vez.', $user->user_login ),
      $assoc_args
    );

    LM2FA_User::reset( $user->ID );
    LM2FA_Log::add( 'admin_reset', 'wp-cli', $user->ID );

    WP_CLI::success( sprintf( 'Verificación restablecida para %s.', $user->user_login ) );
  }

  /** Saldo del servicio, consultado en vivo al servidor central. */
  public static function quota() {
    if ( ! LM2FA_Client::is_configured() ) {
      WP_CLI::error( 'Falta la clave API: configúrala en LabWP 2FA → Ajustes.' );
    }

    $quota = LM2FA_Client::quota( true );

    if ( is_wp_error( $quota ) ) {
      WP_CLI::error( $quota->get_error_message() );
    }

    foreach ( $quota as $key => $value ) {
      WP_CLI::log( sprintf( '%-16s %s', $key . ':', is_bool( $value ) ? ( $value ? 'sí' : 'no' ) : $value ) );
    }
  }

  /* ------------------------------ Internos ------------------------------ */

  private static function resolve( $reference ) {
    $user = is_numeric( $reference )
      ? get_user_by( 'id', (int) $reference )
      : ( get_user_by( 'login', $reference ) ?: get_user_by( 'email', $reference ) );

    if ( ! $user ) {
      WP_CLI::error( sprintf( 'No encuentro al usuario "%s".', $reference ) );
    }

    return $user;
  }
}
