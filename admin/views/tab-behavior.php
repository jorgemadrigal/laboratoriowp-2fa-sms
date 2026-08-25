<?php
/**
 * Pestaña Comportamiento: a quién se le exige, cuánto se recuerda un equipo
 * y qué canales alternativos existen.
 *
 * @var array<string,string> $roles
 * @var string[]             $enforced
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<form method="post" action="options.php">
  <?php settings_fields( LM2FA_Settings::GROUP_BEHAVIOR ); ?>

  <h2><?php esc_html_e( 'Obligación por rol', 'lmsms-2fa' ); ?></h2>
  <table class="form-table" role="presentation">
    <tr>
      <th scope="row"><?php esc_html_e( 'Roles obligados', 'lmsms-2fa' ); ?></th>
      <td>
        <fieldset>
          <?php foreach ( $roles as $role => $label ) : ?>
            <label class="lm2fa-check">
              <input type="checkbox" name="lm2fa_enforced_roles[]" value="<?php echo esc_attr( $role ); ?>"
                <?php checked( in_array( $role, $enforced, true ) ); ?>>
              <?php echo esc_html( translate_user_role( $label ) ); ?>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <p class="description"><?php esc_html_e( 'A estos roles se les exigirá el segundo factor y no podrán desactivarlo.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><?php esc_html_e( 'Mientras no lo activen', 'lmsms-2fa' ); ?></th>
      <td>
        <label>
          <input type="checkbox" name="lm2fa_enforce_lock" value="yes" <?php checked( 'yes', LM2FA_Settings::get( 'lm2fa_enforce_lock' ) ); ?>>
          <?php esc_html_e( 'Llevarlos a la pantalla de alta y no dejarles usar el escritorio', 'lmsms-2fa' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Sin esta casilla solo verán un aviso que pueden ignorar indefinidamente.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>
  </table>

  <h2><?php esc_html_e( 'Acceso', 'lmsms-2fa' ); ?></h2>
  <table class="form-table" role="presentation">
    <tr>
      <th scope="row"><label for="lm2fa_trust_days"><?php esc_html_e( 'Equipos de confianza', 'lmsms-2fa' ); ?></label></th>
      <td>
        <input type="number" min="0" max="365" id="lm2fa_trust_days" name="lm2fa_trust_days" class="small-text"
          value="<?php echo esc_attr( LM2FA_Settings::int( 'lm2fa_trust_days' ) ); ?>">
        <?php esc_html_e( 'días', 'lmsms-2fa' ); ?>
        <p class="description"><?php esc_html_e( 'Permite omitir el código en el mismo equipo durante ese periodo. Usa 0 para pedirlo siempre: es más seguro y no consume SMS de más.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><?php esc_html_e( 'Código por correo', 'lmsms-2fa' ); ?></th>
      <td>
        <label>
          <input type="checkbox" name="lm2fa_email_fallback" value="yes" <?php checked( 'yes', LM2FA_Settings::get( 'lm2fa_email_fallback' ) ); ?>>
          <?php esc_html_e( 'Permitir recibir el código por correo si el SMS no llega', 'lmsms-2fa' ); ?>
        </label>
        <p class="description">
          <?php esc_html_e( 'El código de correo lo genera y comprueba este sitio: no gasta saldo. Es más débil que el SMS —quien controle el buzón entra—, pero evita que alguien se quede fuera por una avería de la pasarela. El alta sigue siendo siempre por SMS.', 'lmsms-2fa' ); ?>
        </p>
      </td>
    </tr>
    <tr>
      <th scope="row"><?php esc_html_e( 'Accesos heredados', 'lmsms-2fa' ); ?></th>
      <td>
        <label>
          <input type="checkbox" name="lm2fa_block_legacy_auth" value="yes" <?php checked( 'yes', LM2FA_Settings::get( 'lm2fa_block_legacy_auth' ) ); ?>>
          <?php esc_html_e( 'Bloquear XML-RPC y contraseñas de aplicación en las cuentas protegidas', 'lmsms-2fa' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Esas dos vías no pueden pedir un código, así que se saltan el segundo factor. Desactívalo solo si una integración tuya depende de ellas.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>
  </table>

  <h2><?php esc_html_e( 'Avisos al usuario', 'lmsms-2fa' ); ?></h2>
  <table class="form-table" role="presentation">
    <tr>
      <th scope="row"><?php esc_html_e( 'Equipo nuevo', 'lmsms-2fa' ); ?></th>
      <td>
        <label>
          <input type="checkbox" name="lm2fa_new_device_alert" value="yes" <?php checked( 'yes', LM2FA_Settings::get( 'lm2fa_new_device_alert' ) ); ?>>
          <?php esc_html_e( 'Avisar por correo cuando se verifique un acceso desde un navegador desconocido', 'lmsms-2fa' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Se envía con el correo del propio sitio. No consume saldo.', 'lmsms-2fa' ); ?></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><?php esc_html_e( 'Sección en Mi cuenta', 'lmsms-2fa' ); ?></th>
      <td>
        <label>
          <input type="checkbox" name="lm2fa_account_tab" value="yes" <?php checked( 'yes', LM2FA_Settings::get( 'lm2fa_account_tab' ) ); ?>>
          <?php esc_html_e( 'Mostrar la pestaña "Seguridad" en Mi cuenta de WooCommerce', 'lmsms-2fa' ); ?>
        </label>
        <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
          <p class="description"><?php esc_html_e( 'WooCommerce no está activo: esta opción no tiene efecto por ahora.', 'lmsms-2fa' ); ?></p>
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <?php submit_button(); ?>
</form>
