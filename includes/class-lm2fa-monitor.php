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

  public static function init() {
    add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
  }

  /**
   * Tarea diaria.
   *
   * @param array|null $quota Saldo ya consultado, para no pedirlo dos veces.
   */
  public static function run( $quota = null ) {
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
    $is_low    = ( ! $can_send || $capacity <= LM2FA_Settings::int( 'lm2fa_low_balance' ) );

    update_option(
      self::OPTION_STATE,
      array(
        'low'        => $is_low,
        'can_send'   => $can_send,
        'capacity'   => $capacity,
        'checked_at' => LM2FA_Util::now_gmt(),
        'notified'   => $is_low ? self::notified_at( $previous ) : '',
      ),
      false
    );

    if ( ! $is_low ) {
      return;
    }

    LM2FA_Log::add( 'low_balance', 'capacidad:' . $capacity );

    if ( self::should_notify( $previous ) ) {
      self::notify( $quota );
    }
  }

  /** @return array{low:bool,can_send:bool,capacity:int,checked_at:string,notified:string} */
  public static function state() {
    $defaults = array(
      'low'        => false,
      'can_send'   => true,
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

    $state = self::state();

    if ( ! $state['low'] ) {
      return;
    }

    $message = $state['can_send']
      ? sprintf(
        /* translators: %d verificaciones restantes. */
        _n(
          'Queda %d verificación por SMS: cuando se agote, los usuarios con segundo factor no podrán entrar.',
          'Quedan %d verificaciones por SMS: cuando se agoten, los usuarios con segundo factor no podrán entrar.',
          $state['capacity'],
          'lmsms-2fa'
        ),
        $state['capacity']
      )
      : __( 'El servicio de verificación por SMS se quedó sin saldo: los usuarios con segundo factor no pueden entrar.', 'lmsms-2fa' );

    printf(
      '<div class="notice notice-%1$s"><p><strong>%2$s</strong> %3$s <a href="%4$s" target="_blank" rel="noopener">%5$s</a></p></div>',
      esc_attr( $state['can_send'] ? 'warning' : 'error' ),
      esc_html__( 'Verificación en dos pasos:', 'lmsms-2fa' ),
      esc_html( $message ),
      esc_url( LM2FA_Client::panel_url( 'otp' ) ),
      esc_html__( 'Comprar créditos', 'lmsms-2fa' )
    );
  }
}
