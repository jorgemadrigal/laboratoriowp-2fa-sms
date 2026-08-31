<?php
/**
 * Vigilancia del saldo del servicio.
 *
 * Sin saldo en el servidor central, los usuarios con segundo factor activo
 * no reciben su código y se quedan fuera. Antes eso solo se descubría
 * entrando a mano en la pantalla de ajustes; ahora se comprueba a diario y
 * se avisa al administrador.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Monitor {

  const OPTION_STATE = 'lm2fa_balance_state';

  /** Días entre recordatorios mientras el saldo siga bajo. */
  const REMINDER_DAYS = 3;

  /** Evita que una lectura disparada desde dentro se evalúe dos veces. */
  private static $running = false;

  public static function init() {
    add_action( 'admin_notices', array( __CLASS__, 'notice' ) );

    // Toda lectura fresca del saldo —incluida la que viaja dentro del error
    // 402 de una solicitud OTP— se evalúa al momento. Así el aviso aparece
    // cuando el servidor lo dice y no en la siguiente pasada diaria.
    add_action( 'lm2fa_quota_updated', array( __CLASS__, 'run' ) );
  }

  /**
   * Tarea diaria.
   *
   * @param array|null $quota Saldo ya consultado, para no pedirlo dos veces.
   */
  public static function run( $quota = null ) {
    if ( self::$running ) {
      return;
    }

    self::$running = true;
    self::evaluate( $quota );
    self::$running = false;
  }

  private static function evaluate( $quota ) {
    if ( ! LM2FA_Client::is_configured() ) {
      return;
    }

    if ( ! is_array( $quota ) ) {
      $quota = LM2FA_Client::quota( true );
    }

    if ( is_wp_error( $quota ) ) {
      return;
    }

    $previous = self::state();
    $capacity = isset( $quota['total_capacity'] ) ? (int) $quota['total_capacity'] : 0;
    $can_send = ! isset( $quota['can_send'] ) || (bool) $quota['can_send'];

    // can_send ya incluye "el proveedor apagó el servicio", pero el motivo
    // cambia por completo el consejo que se le da al administrador: recargar
    // créditos no arregla un servicio desactivado.
    $enabled = ! isset( $quota['enabled'] ) || (bool) $quota['enabled'];

    $is_low = ( ! $can_send || $capacity <= LM2FA_Settings::int( 'lm2fa_low_balance' ) );

    update_option(
      self::OPTION_STATE,
      array(
        'low'        => $is_low,
        'can_send'   => $can_send,
        'enabled'    => $enabled,
        'capacity'   => $capacity,
        'checked_at' => LM2FA_Util::now_gmt(),
        'notified'   => $is_low ? self::notified_at( $previous ) : '',
      ),
      false
    );

    if ( ! $is_low ) {
      return;
    }

    LM2FA_Log::add( 'low_balance', $enabled ? 'capacidad:' . $capacity : 'servicio desactivado en el servidor' );

    if ( self::should_notify( $previous ) ) {
      self::notify( $quota );
    }
  }

  /** @return array{low:bool,can_send:bool,enabled:bool,capacity:int,checked_at:string,notified:string} */
  public static function state() {
    $defaults = array(
      'low'        => false,
      'can_send'   => true,
      'enabled'    => true,
      'capacity'   => 0,
      'checked_at' => '',
      'notified'   => '',
    );

    return wp_parse_args( (array) get_option( self::OPTION_STATE, array() ), $defaults );
  }

  public static function clear_state() {
    delete_option( self::OPTION_STATE );
  }

  /* ------------------------------- Avisos -------------------------------- */

  private static function notified_at( array $previous ) {
    return $previous['notified'] ? $previous['notified'] : '';
  }

  private static function should_notify( array $previous ) {
    if ( ! LM2FA_Settings::is_yes( 'lm2fa_low_balance_email' ) ) {
      return false;
    }

    // Primera vez que se detecta: avisa ya.
    if ( ! $previous['low'] || ! $previous['notified'] ) {
      return true;
    }

    $elapsed = time() - strtotime( $previous['notified'] . ' UTC' );

    return $elapsed >= self::REMINDER_DAYS * DAY_IN_SECONDS;
  }

  private static function notify( array $quota ) {
    $sent = LM2FA_Mailer::low_balance( get_option( 'admin_email' ), $quota );

    if ( ! $sent ) {
      return;
    }

    $state             = self::state();
    $state['notified'] = LM2FA_Util::now_gmt();

    update_option( self::OPTION_STATE, $state, false );
  }

  /** Banda en el escritorio para quien puede recargar. */
  public static function notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    self::version_notice();

    $state = self::state();

    if ( ! $state['low'] ) {
      return;
    }

    // Servicio apagado en el servidor: no hay crédito que comprar.
    if ( empty( $state['enabled'] ) ) {
      self::print_notice(
        'error',
        __( 'El proveedor tiene desactivado el servicio de verificación por SMS: los usuarios con segundo factor no reciben su código. Los créditos no lo reactivan.', 'lmsms-2fa' ),
        __( 'Revisar el estado del servicio', 'lmsms-2fa' )
      );
      return;
    }

    if ( ! $state['can_send'] ) {
      self::print_notice(
        'error',
        __( 'El servicio de verificación por SMS se quedó sin saldo: los usuarios con segundo factor no pueden entrar.', 'lmsms-2fa' ),
        __( 'Comprar créditos', 'lmsms-2fa' )
      );
      return;
    }

    self::print_notice(
      'warning',
      sprintf(
        /* translators: %d verificaciones restantes. */
        _n(
          'Queda %d verificación por SMS: cuando se agote, los usuarios con segundo factor no podrán entrar.',
          'Quedan %d verificaciones por SMS: cuando se agoten, los usuarios con segundo factor no podrán entrar.',
          $state['capacity'],
          'lmsms-2fa'
        ),
        $state['capacity']
      ),
      __( 'Comprar créditos', 'lmsms-2fa' )
    );
  }

  /**
   * El servidor es más antiguo que el contrato que habla este plugin.
   *
   * No se bloquea nada —lo esencial sigue funcionando— pero conviene que el
   * administrador sepa por qué ve comportamientos raros en los tiempos de
   * caducidad o en los mensajes de error.
   */
  private static function version_notice() {
    if ( ! LM2FA_Client::is_configured() || LM2FA_Client::is_supported_server() ) {
      return;
    }

    self::print_notice(
      'warning',
      sprintf(
        /* translators: 1: versión del servidor, 2: versión mínima recomendada. */
        __( 'El servidor de verificación declara la versión %1$s y este plugin está preparado para la %2$s o superior. Puede que algunos avisos y tiempos de caducidad no sean exactos.', 'lmsms-2fa' ),
        LM2FA_Client::server_version(),
        LM2FA_Client::MIN_SERVER
      ),
      __( 'Abrir el panel de cliente', 'lmsms-2fa' )
    );
  }

  private static function print_notice( $type, $message, $link_text ) {
    printf(
      '<div class="notice notice-%1$s"><p><strong>%2$s</strong> %3$s <a href="%4$s" target="_blank" rel="noopener">%5$s</a></p></div>',
      esc_attr( $type ),
      esc_html__( 'Verificación en dos pasos:', 'lmsms-2fa' ),
      esc_html( $message ),
      esc_url( LM2FA_Client::panel_url( 'otp' ) ),
      esc_html( $link_text )
    );
  }
}
