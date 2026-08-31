<?php
/**
 * Pestaña Conexión: saldo del servicio y credenciales de la API.
 *
 * @var bool           $configured
 * @var array|WP_Error $quota
 * @var string         $updated
 * @var string         $server
 * @var string         $host
 * @var bool           $has_key
 * @var string         $panel_api
 * @var string         $panel_otp
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>

<?php if ( ! $configured ) : ?>
  <div class="notice notice-warning">
    <p>
      <?php
      printf(
        /* translators: %s enlace al panel de cliente. */
        esc_html__( 'Pega tu clave API para activar el servicio. La generas en la pestaña "Claves API" de tu panel en %s.', 'lmsms-2fa' ),
        '<a href="' . esc_url( $panel_api ) . '" target="_blank" rel="noopener">' . esc_html( $host ) . '</a>'
      );
      ?>
    </p>
  </div>

<?php else : ?>
  <div class="lm2fa-panel">
    <h2><?php esc_html_e( 'Saldo del servicio', 'lmsms-2fa' ); ?></h2>

    <?php if ( is_wp_error( $quota ) ) : ?>
      <div class="lm2fa-alert is-error"><?php echo esc_html( LM2FA_Errors::message( $quota ) ); ?></div>

    <?php elseif ( is_array( $quota ) ) : ?>
      <div class="lm2fa-balance">
        <div>
          <span><?php esc_html_e( 'Verificaciones disponibles', 'lmsms-2fa' ); ?></span>
          <strong><?php echo esc_html( isset( $quota['total_capacity'] ) ? number_format_i18n( $quota['total_capacity'] ) : '?' ); ?></strong>
        </div>
        <div>
          <span><?php esc_html_e( 'Gratuitas del mes', 'lmsms-2fa' ); ?></span>
          <strong><?php echo esc_html( ( isset( $quota['free_remaining'] ) ? $quota['free_remaining'] : '?' ) . ' / ' . ( isset( $quota['free_limit'] ) ? $quota['free_limit'] : '?' ) ); ?></strong>
        </div>
        <div>
          <span><?php esc_html_e( 'Créditos SMS', 'lmsms-2fa' ); ?></span>
          <strong><?php echo esc_html( isset( $quota['sms_credits'] ) ? number_format_i18n( $quota['sms_credits'] ) : '?' ); ?></strong>
        </div>
        <div>
          <span><?php esc_html_e( 'Reinicio de cuota', 'lmsms-2fa' ); ?></span>
          <strong class="is-small"><?php echo esc_html( isset( $quota['resets_at'] ) ? wp_date( 'd/m/Y', strtotime( $quota['resets_at'] ) ) : '?' ); ?></strong>
        </div>
      </div>

      <?php if ( isset( $quota['enabled'] ) && ! $quota['enabled'] ) : ?>
        <div class="lm2fa-alert is-error">
          <?php esc_html_e( 'El proveedor tiene desactivado el servicio de verificación: no se entregará ningún código, tengas o no saldo.', 'lmsms-2fa' ); ?>
        </div>
      <?php elseif ( isset( $quota['can_send'] ) && ! $quota['can_send'] ) : ?>
        <div class="lm2fa-alert is-error">
          <?php esc_html_e( 'Sin saldo: los usuarios con 2FA no podrán recibir códigos. Adquiere un paquete de SMS en tu panel de cliente.', 'lmsms-2fa' ); ?>
        </div>
      <?php endif; ?>

      <?php if ( $updated ) : ?>
        <p class="lm2fa-hint">
          <?php
          printf(
            /* translators: %s tiempo transcurrido desde la última consulta. */
            esc_html__( 'Dato obtenido hace %s.', 'lmsms-2fa' ),
            esc_html( LM2FA_Util::time_ago( $updated ) )
          );
          ?>
        </p>
      <?php endif; ?>
    <?php endif; ?>

    <div class="lm2fa-buttons">
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'lm2fa_refresh' ); ?>
        <input type="hidden" name="action" value="lm2fa_refresh">
        <button type="submit" class="button button-primary"><?php esc_html_e( 'Actualizar saldo', 'lmsms-2fa' ); ?></button>
      </form>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'lm2fa_test' ); ?>
        <input type="hidden" name="action" value="lm2fa_test">
        <button type="submit" class="button"><?php esc_html_e( 'Probar la conexión', 'lmsms-2fa' ); ?></button>
      </form>

      <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( $panel_otp ); ?>">
        <?php esc_html_e( 'Abrir mi panel de cliente', 'lmsms-2fa' ); ?>
      </a>
    </div>
  </div>
<?php endif; ?>

<form method="post" action="options.php">
  <?php settings_fields( LM2FA_Settings::GROUP_CONNECTION ); ?>

  <table class="form-table" role="presentation">
    <tr>
      <th scope="row"><label for="lm2fa_server_url"><?php esc_html_e( 'Servidor', 'lmsms-2fa' ); ?></label></th>
      <td>
        <input type="url" id="lm2fa_server_url" name="lm2fa_server_url" class="regular-text"
          value="<?php echo esc_attr( $server ); ?>">
        <p class="description"><?php esc_html_e( 'Dirección del servicio que atiende las peticiones. No la modifiques salvo indicación de soporte.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><label for="lm2fa_api_key"><?php esc_html_e( 'Clave API', 'lmsms-2fa' ); ?></label></th>
      <td>
        <input type="password" id="lm2fa_api_key" name="lm2fa_api_key" class="regular-text" value=""
          autocomplete="new-password"
          placeholder="<?php echo $has_key ? esc_attr__( 'Guardada — escribe para reemplazarla', 'lmsms-2fa' ) : 'lmk_...'; ?>">
        <p class="description"><?php esc_html_e( 'Déjala vacía para conservar la actual. No se muestra nunca.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>
  </table>

  <?php submit_button(); ?>
</form>
