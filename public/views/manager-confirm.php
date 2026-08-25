<?php
/**
 * Paso 2 del alta: confirmar el código recibido.
 *
 * Los tres formularios van uno detrás de otro, nunca anidados: HTML no
 * admite un <form> dentro de otro y el navegador se comía el segundo botón.
 *
 * @var string $redirect
 * @var string $phone    Enmascarado.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<div class="lm2fa-panel">
  <h3><?php esc_html_e( 'Confirma tu teléfono', 'lmsms-2fa' ); ?></h3>

  <p class="lm2fa-hint">
    <?php
    printf(
      /* translators: %s teléfono enmascarado. */
      esc_html__( 'Escribe el código que enviamos al %s.', 'lmsms-2fa' ),
      '<strong>' . esc_html( $phone ) . '</strong>'
    );
    ?>
  </p>

  <form method="post" action="<?php echo LM2FA_Manager::form_action(); // phpcs:ignore WordPress.Security.EscapeOutput -- ya escapado. ?>">
    <?php LM2FA_Manager::fields( 'confirm', $redirect ); ?>

    <div class="lm2fa-field">
      <label for="lm2fa_code"><?php esc_html_e( 'Código recibido', 'lmsms-2fa' ); ?></label>
      <input type="text" id="lm2fa_code" name="lm2fa_code" inputmode="numeric" maxlength="8"
        autocomplete="one-time-code" required>
    </div>

    <button type="submit" class="button button-primary"><?php esc_html_e( 'Confirmar y activar', 'lmsms-2fa' ); ?></button>
  </form>

  <div class="lm2fa-buttons">
    <form method="post" action="<?php echo LM2FA_Manager::form_action(); // phpcs:ignore WordPress.Security.EscapeOutput -- ya escapado. ?>">
      <?php LM2FA_Manager::fields( 'resend', $redirect ); ?>
      <button type="submit" class="lm2fa-link"><?php esc_html_e( 'Reenviar el código', 'lmsms-2fa' ); ?></button>
    </form>

    <form method="post" action="<?php echo LM2FA_Manager::form_action(); // phpcs:ignore WordPress.Security.EscapeOutput -- ya escapado. ?>">
      <?php LM2FA_Manager::fields( 'change_phone', $redirect ); ?>
      <button type="submit" class="lm2fa-link"><?php esc_html_e( 'Usar otro número', 'lmsms-2fa' ); ?></button>
    </form>
  </div>
</div>
