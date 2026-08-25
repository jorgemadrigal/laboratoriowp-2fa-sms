<?php
/**
 * Equipos en los que no se vuelve a pedir el código.
 *
 * @var string  $redirect
 * @var array[] $devices  id, agent, created, expires, current.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<div class="lm2fa-panel">
  <h3><?php esc_html_e( 'Equipos de confianza', 'lmsms-2fa' ); ?></h3>

  <?php if ( empty( $devices ) ) : ?>
    <p class="lm2fa-hint"><?php esc_html_e( 'Ahora mismo se pide el código en todos tus equipos.', 'lmsms-2fa' ); ?></p>
  <?php else : ?>
    <ul class="lm2fa-devices">
      <?php foreach ( $devices as $device ) : ?>
        <li>
          <div>
            <strong><?php echo esc_html( $device['agent'] ? $device['agent'] : __( 'Equipo sin identificar', 'lmsms-2fa' ) ); ?></strong>
            <?php if ( $device['current'] ) : ?>
              <span class="lm2fa-badge is-on"><?php esc_html_e( 'Este equipo', 'lmsms-2fa' ); ?></span>
            <?php endif; ?>
            <span class="lm2fa-hint">
              <?php
              printf(
                /* translators: 1: fecha en que se recordó, 2: tiempo que le queda. */
                esc_html__( 'Recordado el %1$s · caduca en %2$s', 'lmsms-2fa' ),
                esc_html( LM2FA_Util::local_date( $device['created'] ) ),
                esc_html( human_time_diff( time(), $device['expires'] ) )
              );
              ?>
            </span>
          </div>

          <form method="post" action="<?php echo LM2FA_Manager::form_action(); // phpcs:ignore WordPress.Security.EscapeOutput -- ya escapado. ?>">
            <?php LM2FA_Manager::fields( 'forget_device', $redirect ); ?>
            <input type="hidden" name="lm2fa_device" value="<?php echo esc_attr( $device['id'] ); ?>">
            <button type="submit" class="lm2fa-link is-danger"><?php esc_html_e( 'Revocar', 'lmsms-2fa' ); ?></button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
