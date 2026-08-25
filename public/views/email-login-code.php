<?php
/**
 * Código de acceso enviado por correo (canal alternativo al SMS).
 *
 * @var WP_User $user
 * @var string  $code
 * @var int     $minutes
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<p style="margin:0 0 16px;font-size:15px;">
  <?php
  printf(
    /* translators: %s nombre del usuario. */
    esc_html__( 'Hola %s:', 'lmsms-2fa' ),
    esc_html( $user->display_name )
  );
  ?>
</p>

<p style="margin:0 0 20px;font-size:15px;line-height:1.6;">
  <?php esc_html_e( 'Pediste un código para terminar de iniciar sesión. Escríbelo en la pantalla de verificación:', 'lmsms-2fa' ); ?>
</p>

<p style="margin:0 0 20px;padding:18px;text-align:center;background:#f6f7f7;border-radius:4px;font-family:Menlo,Consolas,monospace;font-size:30px;letter-spacing:.3em;font-weight:600;">
  <?php echo esc_html( $code ); ?>
</p>

<p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#50575e;">
  <?php
  printf(
    /* translators: %d minutos de vigencia del código. */
    esc_html( _n( 'Caduca en %d minuto y solo sirve una vez.', 'Caduca en %d minutos y solo sirve una vez.', $minutes, 'lmsms-2fa' ) ),
    (int) $minutes
  );
  ?>
</p>

<p style="margin:0;font-size:14px;line-height:1.6;color:#50575e;">
  <?php esc_html_e( 'Si no fuiste tú, alguien conoce tu contraseña: cámbiala en cuanto puedas.', 'lmsms-2fa' ); ?>
</p>
