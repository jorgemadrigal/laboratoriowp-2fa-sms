<?php
/**
 * Formularios del administrador (Post/Redirect/Get).
 *
 * Para añadir una acción: una entrada en handlers() y un método con ese
 * nombre que devuelva array( 'ok'|'error', mensaje ). El nonce se llama
 * siempre igual que la acción.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Admin_Actions {

  const RESULT = 'lm2fa_admin_result';

  /** @return array<string,array{0:string,1:string}> acción => [capacidad, método]. */
  private static function handlers() {
    return array(
      'lm2fa_test'       => array( 'manage_options', 'test_connection' ),
      'lm2fa_refresh'    => array( 'manage_options', 'refresh_quota' ),
      'lm2fa_clear_log'  => array( 'manage_options', 'clear_log' ),
      'lm2fa_reset_user' => array( 'edit_users', 'reset_user' ),
    );
  }

  public static function init() {
    foreach ( array_keys( self::handlers() ) as $action ) {
      add_action( 'admin_post_' . $action, array( __CLASS__, 'dispatch' ) );
    }
  }

  public static function dispatch() {
    $action   = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
    $handlers = self::handlers();

    if ( ! isset( $handlers[ $action ] ) ) {
      wp_die( esc_html__( 'Acción no reconocida.', 'lmsms-2fa' ) );
    }

    list( $capability, $method ) = $handlers[ $action ];

    if ( ! current_user_can( $capability ) ) {
      wp_die( esc_html__( 'Sin permisos.', 'lmsms-2fa' ) );
    }

    check_admin_referer( $action );

    list( $status, $message, $redirect ) = call_user_func( array( __CLASS__, $method ) );

    if ( $message ) {
      set_transient( self::RESULT, array( $status, $message ), MINUTE_IN_SECONDS );
    }

    wp_safe_redirect( $redirect );
    exit;
  }

  /** @return array{0:string,1:string}|null */
  public static function take_result() {
    $result = get_transient( self::RESULT );

    if ( $result ) {
      delete_transient( self::RESULT );
    }

    return is_array( $result ) ? $result : null;
  }

  /* ------------------------------ Acciones ------------------------------- */

  /** @return array{0:string,1:string,2:string} */
  private static function refresh_quota() {
    LM2FA_Client::flush_cache();
    $quota = LM2FA_Client::quota( true );

    if ( is_wp_error( $quota ) ) {
      return array( 'error', LM2FA_Errors::message( $quota ), LM2FA_Admin::settings_url( 'connection' ) );
    }

    // El propio cliente avisa a LM2FA_Monitor con la lectura fresca, así que
    // una recarga reciente ya ha resuelto aquí el aviso de saldo bajo.
    return array( 'ok', __( 'Saldo actualizado.', 'lmsms-2fa' ), LM2FA_Admin::settings_url( 'connection' ) );
  }

  private static function test_connection() {
    LM2FA_Client::flush_cache();
    $account = LM2FA_Client::account();

    if ( is_wp_error( $account ) ) {
      return array( 'error', LM2FA_Errors::message( $account ), LM2FA_Admin::settings_url( 'connection' ) );
    }

    $otp = isset( $account['otp'] ) ? (array) $account['otp'] : array();

    $message = sprintf(
      /* translators: 1: créditos, 2: verificaciones gratuitas restantes, 3: capacidad total, 4: versión del servidor. */
      __( 'Conexión correcta con el servidor %4$s. Créditos: %1$s. Verificaciones gratuitas restantes: %2$s. Capacidad total: %3$s.', 'lmsms-2fa' ),
      isset( $account['credits'] ) ? $account['credits'] : '?',
      isset( $otp['free_remaining'] ) ? $otp['free_remaining'] : '?',
      isset( $otp['total_capacity'] ) ? $otp['total_capacity'] : '?',
      isset( $account['version'] ) ? $account['version'] : '?'
    );

    // La conexión funciona; lo que puede fallar es el detalle del contrato.
    if ( ! LM2FA_Client::is_supported_server() ) {
      $message .= ' ' . sprintf(
        /* translators: %s versión mínima recomendada del servidor. */
        __( 'Aviso: este plugin está preparado para la versión %s o superior del servidor.', 'lmsms-2fa' ),
        LM2FA_Client::MIN_SERVER
      );
    }

    // El servicio puede estar apagado en el servidor aunque haya saldo.
    if ( isset( $otp['enabled'] ) && ! $otp['enabled'] ) {
      $message .= ' ' . __( 'Ojo: el proveedor tiene desactivado el servicio de verificación.', 'lmsms-2fa' );
    }

    return array( 'ok', $message, LM2FA_Admin::settings_url( 'connection' ) );
  }

  private static function clear_log() {
    LM2FA_Log::clear();

    return array( 'ok', __( 'Registro vaciado.', 'lmsms-2fa' ), LM2FA_Admin::settings_url( 'maintenance' ) );
  }

  private static function reset_user() {
    $user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;

    if ( ! $user_id || ! get_userdata( $user_id ) ) {
      return array( 'error', __( 'Ese usuario ya no existe.', 'lmsms-2fa' ), LM2FA_Admin::users_url() );
    }

    LM2FA_User::reset( $user_id );
    LM2FA_Log::add( 'admin_reset', 'por:' . get_current_user_id(), $user_id );

    return array( 'ok', '', add_query_arg( 'reset', '1', LM2FA_Admin::users_url() ) );
  }
}
