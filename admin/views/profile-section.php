<?php
/**
 * Bloque del segundo factor dentro del perfil de usuario.
 *
 * @var bool   $is_own
 * @var bool   $active
 * @var bool   $verified
 * @var string $phone      Enmascarado.
 * @var int    $codes_left
 * @var string $last_auth
 * @var bool   $can_edit
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<h2 id="lm2fa"><?php esc_html_e( 'Verificación en dos pasos por SMS', 'lmsms-2fa' ); ?></h2>

<table class="form-table" role="presentation">
  <tr>
    <th scope="row"><?php esc_html_e( 'Estado', 'lmsms-2fa' ); ?></th>
    <td>
      <?php if ( $active ) : ?>
        <p>
          <strong class="lm2fa-on"><?php esc_html_e( 'Activa', 'lmsms-2fa' ); ?></strong> — <?php echo esc_html( $phone ); ?>
        </p>
        <p class="description">
          <?php
          printf(
            /* translators: 1: códigos de respaldo, 2: fecha del último acceso. */
            esc_html__( 'Códigos de respaldo disponibles: %1$d. Último acceso verificado: %2$s.', 'lmsms-2fa' ),
            (int) $codes_left,
            esc_html( $last_auth )
          );
          ?>
        </p>
      <?php else : ?>
        <p><strong class="lm2fa-off"><?php esc_html_e( 'Inactiva', 'lmsms-2fa' ); ?></strong></p>
      <?php endif; ?>

      <?php if ( $is_own ) : ?>
        <p>
          <a href="<?php echo esc_url( LM2FA_Admin::me_url() ); ?>" class="button">
            <?php esc_html_e( 'Administrar mi verificación', 'lmsms-2fa' ); ?>
          </a>
        </p>
      <?php elseif ( $can_edit && $verified ) : ?>
        <p class="description">
          <?php
          printf(
            /* translators: %s enlace a la pantalla de usuarios del plugin. */
            esc_html__( 'Puedes restablecerla desde %s.', 'lmsms-2fa' ),
            '<a href="' . esc_url( LM2FA_Admin::users_url() ) . '">' . esc_html__( 'LabWP 2FA → Usuarios', 'lmsms-2fa' ) . '</a>'
          );
          ?>
        </p>
      <?php endif; ?>
    </td>
  </tr>
</table>
