<?php
/**
 * Ajustes del plugin. Cada pestaña es un formulario independiente con su
 * propio grupo de opciones.
 *
 * @var string                $tab
 * @var array<string,string>  $tabs
 * @var array|null            $result Resultado de la última acción.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<div class="wrap lm2fa-admin">
  <h1><?php esc_html_e( 'Verificación en dos pasos por SMS', 'lmsms-2fa' ); ?></h1>

  <?php if ( $result ) : ?>
    <div class="notice notice-<?php echo 'ok' === $result[0] ? 'success' : 'error'; ?> is-dismissible">
      <p><?php echo esc_html( $result[1] ); ?></p>
    </div>
  <?php endif; ?>

  <nav class="nav-tab-wrapper">
    <?php foreach ( $tabs as $slug => $label ) : ?>
      <a href="<?php echo esc_url( LM2FA_Admin::settings_url( $slug ) ); ?>"
        class="nav-tab <?php echo $slug === $tab ? 'nav-tab-active' : ''; ?>">
        <?php echo esc_html( $label ); ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php LM2FA_Util::view( 'admin/views/tab-' . $tab, get_defined_vars() ); ?>
</div>
