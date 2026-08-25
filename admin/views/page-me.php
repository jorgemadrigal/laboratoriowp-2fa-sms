<?php
/**
 * Ajustes → Mi verificación 2FA. La misma pantalla que en Mi cuenta.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<div class="wrap lm2fa-admin lm2fa-me">
  <h1><?php esc_html_e( 'Mi verificación en dos pasos', 'lmsms-2fa' ); ?></h1>

  <p class="lm2fa-hint">
    <?php esc_html_e( 'Al iniciar sesión pediremos, además de tu contraseña, un código enviado por SMS a tu celular.', 'lmsms-2fa' ); ?>
  </p>

  <?php LM2FA_Manager::render( LM2FA_Manager::CONTEXT_ADMIN ); ?>
</div>
