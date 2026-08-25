<?php
/**
 * Paso 1 del alta: registrar el celular.
 *
 * @var string $redirect
 * @var string $phone    Último número escrito, sin enmascarar.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<form method="post" action="<?php echo LM2FA_Manager::form_action(); // phpcs:ignore WordPress.Security.EscapeOutput -- ya escapado. ?>" class="lm2fa-panel">
  <?php LM2FA_Manager::fields( 'send', $redirect ); ?>

  <h3><?php esc_html_e( 'Activar la verificación', 'lmsms-2fa' ); ?></h3>
  <p class="lm2fa-hint"><?php esc_html_e( 'Registra tu celular. Te enviaremos un código para confirmarlo.', 'lmsms-2fa' ); ?></p>

  <div class="lm2fa-field">
    <label for="lm2fa_phone"><?php esc_html_e( 'Celular a 10 dígitos', 'lmsms-2fa' ); ?></label>
    <input type="tel" id="lm2fa_phone" name="lm2fa_phone" inputmode="numeric" maxlength="14"
      autocomplete="tel-national" value="<?php echo esc_attr( $phone ); ?>" required>
  </div>

  <button type="submit" class="button"><?php esc_html_e( 'Enviar código de confirmación', 'lmsms-2fa' ); ?></button>
</form>
