<?php
/**
 * Estado actual del segundo factor.
 *
 * @var bool   $active
 * @var bool   $enforced
 * @var string $phone      Enmascarado.
 * @var int    $codes_left
 * @var bool   $low_codes
 * @var string $last_auth
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<div class="lm2fa-panel">
  <h3><?php esc_html_e( 'Estado del servicio', 'lmsms-2fa' ); ?></h3>

  <?php if ( $active ) : ?>
    <p>
      <span class="lm2fa-badge is-on"><?php esc_html_e( 'Activa', 'lmsms-2fa' ); ?></span>
      <?php
      printf(
        /* translators: %s teléfono enmascarado. */
        esc_html__( 'Los códigos llegan al %s.', 'lmsms-2fa' ),
        '<strong>' . esc_html( $phone ) . '</strong>'
      );
      ?>
    </p>

    <p class="lm2fa-hint<?php echo $low_codes ? ' is-warning' : ''; ?>">
      <?php
      printf(
        /* translators: %d códigos de respaldo disponibles. */
        esc_html( _n( 'Te queda %d código de respaldo.', 'Te quedan %d códigos de respaldo.', $codes_left, 'lmsms-2fa' ) ),
        (int) $codes_left
      );

      if ( '—' !== $last_auth ) {
        echo ' ';
        printf(
          /* translators: %s fecha del último acceso verificado. */
          esc_html__( 'Último acceso verificado: %s.', 'lmsms-2fa' ),
          esc_html( $last_auth )
        );
      }
      ?>
    </p>

  <?php else : ?>
    <p>
      <span class="lm2fa-badge is-off"><?php esc_html_e( 'Inactiva', 'lmsms-2fa' ); ?></span>
      <?php esc_html_e( 'Tu cuenta se protege solo con la contraseña.', 'lmsms-2fa' ); ?>
    </p>

    <?php if ( $enforced ) : ?>
      <p class="lm2fa-hint is-warning"><?php esc_html_e( 'El administrador exige activarla para tu rol.', 'lmsms-2fa' ); ?></p>
    <?php endif; ?>
  <?php endif; ?>
</div>
