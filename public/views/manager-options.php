<?php
/**
 * Opciones cuando la verificación ya está activa.
 *
 * @var string $redirect
 * @var bool   $enforced Obligada por rol: no se puede desactivar.
 * @var bool   $trust    Los equipos de confianza están habilitados.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<div class="lm2fa-panel">
  <h3><?php esc_html_e( 'Opciones', 'lmsms-2fa' ); ?></h3>

  <div class="lm2fa-buttons">
    <form method="post" action="<?php echo LM2FA_Manager::form_action(); // phpcs:ignore WordPress.Security.EscapeOutput -- ya escapado. ?>">
      <?php LM2FA_Manager::fields( 'regenerate', $redirect ); ?>
      <button type="submit" class="button"><?php esc_html_e( 'Generar códigos de respaldo', 'lmsms-2fa' ); ?></button>
    </form>

    <form method="post" action="<?php echo LM2FA_Manager::form_action(); // phpcs:ignore WordPress.Security.EscapeOutput -- ya escapado. ?>">
      <?php LM2FA_Manager::fields( 'change_phone', $redirect ); ?>
      <button type="submit" class="button"><?php esc_html_e( 'Cambiar de número', 'lmsms-2fa' ); ?></button>
    </form>

    <?php if ( $trust ) : ?>
      <form method="post" action="<?php echo LM2FA_Manager::form_action(); // phpcs:ignore WordPress.Security.EscapeOutput -- ya escapado. ?>">
        <?php LM2FA_Manager::fields( 'forget_devices', $redirect ); ?>
        <button type="submit" class="button"><?php esc_html_e( 'Olvidar todos los equipos', 'lmsms-2fa' ); ?></button>
      </form>
    <?php endif; ?>

    <?php if ( ! $enforced ) : ?>
      <form method="post" action="<?php echo LM2FA_Manager::form_action(); // phpcs:ignore WordPress.Security.EscapeOutput -- ya escapado. ?>"
        data-lm2fa-confirm="<?php esc_attr_e( '¿Desactivar la verificación en dos pasos?', 'lmsms-2fa' ); ?>">
        <?php LM2FA_Manager::fields( 'disable', $redirect ); ?>
        <button type="submit" class="lm2fa-link is-danger"><?php esc_html_e( 'Desactivar', 'lmsms-2fa' ); ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
