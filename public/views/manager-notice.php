<?php
/**
 * Resultado de la última acción del usuario.
 *
 * @var string $status  LM2FA_Notices::SUCCESS | LM2FA_Notices::ERROR
 * @var string $message
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<div class="lm2fa-alert <?php echo LM2FA_Notices::SUCCESS === $status ? '' : 'is-error'; ?>" role="status">
  <?php echo esc_html( $message ); ?>
</div>
