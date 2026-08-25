<?php
/**
 * Códigos de respaldo recién generados. Solo se ven una vez.
 *
 * @var string[] $codes
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<div class="lm2fa-panel">
  <h3><?php esc_html_e( 'Tus códigos de respaldo', 'lmsms-2fa' ); ?></h3>

  <p class="lm2fa-hint">
    <?php esc_html_e( 'Guárdalos en un lugar seguro. Cada uno sirve una sola vez y te permiten entrar si no recibes el SMS. No volverán a mostrarse.', 'lmsms-2fa' ); ?>
  </p>

  <ul class="lm2fa-codes" id="lm2fa-codes">
    <?php foreach ( $codes as $code ) : ?>
      <li><?php echo esc_html( $code ); ?></li>
    <?php endforeach; ?>
  </ul>

  <div class="lm2fa-buttons">
    <button type="button" class="button" data-lm2fa-copy="#lm2fa-codes">
      <?php esc_html_e( 'Copiar', 'lmsms-2fa' ); ?>
    </button>
    <button type="button" class="button" data-lm2fa-download="#lm2fa-codes"
      data-filename="<?php echo esc_attr( sanitize_file_name( LM2FA_Util::site_name() . '-codigos-respaldo.txt' ) ); ?>">
      <?php esc_html_e( 'Descargar .txt', 'lmsms-2fa' ); ?>
    </button>
    <button type="button" class="button" onclick="window.print()">
      <?php esc_html_e( 'Imprimir', 'lmsms-2fa' ); ?>
    </button>
  </div>
</div>
