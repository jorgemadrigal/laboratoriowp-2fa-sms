<?php
/**
 * Pestaña Apariencia: logo y fondo de la pantalla de acceso.
 *
 * @var array{id:int,src:string} $logo
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<form method="post" action="options.php">
  <?php settings_fields( LM2FA_Settings::GROUP_BRANDING ); ?>

  <table class="form-table" role="presentation">
    <tr>
      <th scope="row"><?php esc_html_e( 'Personalizar', 'lmsms-2fa' ); ?></th>
      <td>
        <label>
          <input type="checkbox" name="lm2fa_branding" value="yes" <?php checked( 'yes', LM2FA_Settings::get( 'lm2fa_branding' ) ); ?>>
          <?php esc_html_e( 'Reemplazar el logo de WordPress en la pantalla de inicio de sesión', 'lmsms-2fa' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Se aplica a todo el login, incluida la pantalla del código de verificación.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>

    <tr>
      <th scope="row"><?php esc_html_e( 'Logo', 'lmsms-2fa' ); ?></th>
      <td>
        <div id="lm2fa-logo-preview">
          <?php if ( $logo['src'] ) : ?>
            <img src="<?php echo esc_url( $logo['src'] ); ?>" alt="">
          <?php else : ?>
            <span class="description"><?php esc_html_e( 'Sin imagen seleccionada.', 'lmsms-2fa' ); ?></span>
          <?php endif; ?>
        </div>

        <input type="hidden" name="lm2fa_logo_id" id="lm2fa_logo_id" value="<?php echo esc_attr( $logo['id'] ); ?>">
        <button type="button" class="button" id="lm2fa-logo-pick"><?php esc_html_e( 'Seleccionar imagen', 'lmsms-2fa' ); ?></button>
        <button type="button" class="lm2fa-link is-danger lm2fa-inline" id="lm2fa-logo-clear"
          <?php echo $logo['id'] ? '' : 'style="display:none"'; ?>>
          <?php esc_html_e( 'Quitar', 'lmsms-2fa' ); ?>
        </button>
        <p class="description"><?php esc_html_e( 'Recomendado: PNG con fondo transparente, de al menos 480 px de ancho.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>

    <tr>
      <th scope="row"><?php esc_html_e( 'Tamaño del logo', 'lmsms-2fa' ); ?></th>
      <td>
        <label>
          <?php esc_html_e( 'Ancho', 'lmsms-2fa' ); ?>
          <input type="number" min="<?php echo esc_attr( LM2FA_Branding::MIN_WIDTH ); ?>" max="<?php echo esc_attr( LM2FA_Branding::MAX_WIDTH ); ?>"
            name="lm2fa_logo_width" class="small-text" value="<?php echo esc_attr( LM2FA_Settings::int( 'lm2fa_logo_width' ) ); ?>"> px
        </label>
        &nbsp;
        <label>
          <?php esc_html_e( 'Alto', 'lmsms-2fa' ); ?>
          <input type="number" min="<?php echo esc_attr( LM2FA_Branding::MIN_HEIGHT ); ?>" max="<?php echo esc_attr( LM2FA_Branding::MAX_HEIGHT ); ?>"
            name="lm2fa_logo_height" class="small-text" value="<?php echo esc_attr( LM2FA_Settings::int( 'lm2fa_logo_height' ) ); ?>"> px
        </label>
        <p class="description"><?php esc_html_e( 'La imagen se ajusta dentro de esa caja sin deformarse.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>

    <tr>
      <th scope="row"><label for="lm2fa_login_bg"><?php esc_html_e( 'Fondo del login', 'lmsms-2fa' ); ?></label></th>
      <td>
        <input type="text" id="lm2fa_login_bg" name="lm2fa_login_bg" class="lm2fa-color"
          value="<?php echo esc_attr( LM2FA_Settings::get( 'lm2fa_login_bg' ) ); ?>" placeholder="#f0f0f1">
        <p class="description"><?php esc_html_e( 'Opcional. Déjalo vacío para conservar el fondo predeterminado.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>
  </table>

  <?php submit_button(); ?>
</form>
