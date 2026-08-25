<?php
/**
 * Pestaña Registro y mantenimiento: últimos eventos, aviso de saldo bajo y
 * qué hacer al desinstalar.
 *
 * @var array[] $log         Filas ya resueltas: time, label, user, detail, ip.
 * @var array   $state       Estado de la vigilancia del saldo.
 * @var string  $admin_email
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<form method="post" action="options.php">
  <?php settings_fields( LM2FA_Settings::GROUP_MAINTENANCE ); ?>

  <h2><?php esc_html_e( 'Vigilancia del saldo', 'lmsms-2fa' ); ?></h2>
  <table class="form-table" role="presentation">
    <tr>
      <th scope="row"><label for="lm2fa_low_balance"><?php esc_html_e( 'Avisar por debajo de', 'lmsms-2fa' ); ?></label></th>
      <td>
        <input type="number" min="0" max="10000" id="lm2fa_low_balance" name="lm2fa_low_balance" class="small-text"
          value="<?php echo esc_attr( LM2FA_Settings::int( 'lm2fa_low_balance' ) ); ?>">
        <?php esc_html_e( 'verificaciones disponibles', 'lmsms-2fa' ); ?>
        <p class="description">
          <?php
          esc_html_e( 'Se comprueba una vez al día.', 'lmsms-2fa' );

          if ( $state['checked_at'] ) {
            echo ' ';
            printf(
              /* translators: %s tiempo transcurrido desde la última comprobación. */
              esc_html__( 'Última comprobación hace %s.', 'lmsms-2fa' ),
              esc_html( LM2FA_Util::time_ago( $state['checked_at'] ) )
            );
          }
          ?>
        </p>
      </td>
    </tr>
    <tr>
      <th scope="row"><?php esc_html_e( 'Correo al administrador', 'lmsms-2fa' ); ?></th>
      <td>
        <label>
          <input type="checkbox" name="lm2fa_low_balance_email" value="yes" <?php checked( 'yes', LM2FA_Settings::get( 'lm2fa_low_balance_email' ) ); ?>>
          <?php
          printf(
            /* translators: %s dirección del administrador del sitio. */
            esc_html__( 'Escribir a %s cuando el saldo baje del umbral', 'lmsms-2fa' ),
            '<code>' . esc_html( $admin_email ) . '</code>'
          );
          ?>
        </label>
      </td>
    </tr>
  </table>

  <h2><?php esc_html_e( 'Desinstalación', 'lmsms-2fa' ); ?></h2>
  <table class="form-table" role="presentation">
    <tr>
      <th scope="row"><?php esc_html_e( 'Al borrar el plugin', 'lmsms-2fa' ); ?></th>
      <td>
        <label>
          <input type="checkbox" name="lm2fa_delete_on_uninstall" value="yes" <?php checked( 'yes', LM2FA_Settings::get( 'lm2fa_delete_on_uninstall' ) ); ?>>
          <?php esc_html_e( 'Borrar ajustes y datos de usuarios al desinstalar', 'lmsms-2fa' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Incluye teléfonos, códigos de respaldo y equipos de confianza. No se puede deshacer.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>
  </table>

  <?php submit_button(); ?>
</form>

<h2><?php esc_html_e( 'Últimos eventos', 'lmsms-2fa' ); ?></h2>

<table class="wp-list-table widefat striped lm2fa-log">
  <thead>
    <tr>
      <th><?php esc_html_e( 'Fecha', 'lmsms-2fa' ); ?></th>
      <th><?php esc_html_e( 'Evento', 'lmsms-2fa' ); ?></th>
      <th><?php esc_html_e( 'Usuario', 'lmsms-2fa' ); ?></th>
      <th><?php esc_html_e( 'Detalle', 'lmsms-2fa' ); ?></th>
      <th><?php esc_html_e( 'IP', 'lmsms-2fa' ); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php if ( empty( $log ) ) : ?>
      <tr><td colspan="5"><?php esc_html_e( 'Sin eventos registrados.', 'lmsms-2fa' ); ?></td></tr>
    <?php else : ?>
      <?php foreach ( $log as $entry ) : ?>
        <tr>
          <td><?php echo esc_html( $entry['time'] ); ?></td>
          <td><?php echo esc_html( $entry['label'] ); ?></td>
          <td><?php echo esc_html( $entry['user'] ); ?></td>
          <td><?php echo esc_html( $entry['detail'] ); ?></td>
          <td><code><?php echo esc_html( $entry['ip'] ); ?></code></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<?php if ( ! empty( $log ) ) : ?>
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lm2fa-log-actions">
    <?php wp_nonce_field( 'lm2fa_clear_log' ); ?>
    <input type="hidden" name="action" value="lm2fa_clear_log">
    <button type="submit" class="button"><?php esc_html_e( 'Vaciar el registro', 'lmsms-2fa' ); ?></button>
  </form>
<?php endif; ?>
