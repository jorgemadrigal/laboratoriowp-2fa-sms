<?php
/**
 * Aviso de acceso verificado desde un equipo que no se había visto antes.
 *
 * @var WP_User $user
 * @var string  $ip
 * @var string  $agent
 * @var string  $when
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
  <?php esc_html_e( 'Alguien completó la verificación en dos pasos de tu cuenta desde un equipo nuevo.', 'lmsms-2fa' ); ?>
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 20px;font-size:14px;color:#50575e;">
  <tr>
    <td style="padding:6px 0;width:110px;"><?php esc_html_e( 'Fecha', 'lmsms-2fa' ); ?></td>
    <td style="padding:6px 0;"><strong><?php echo esc_html( $when ); ?></strong></td>
  </tr>
  <?php if ( $ip ) : ?>
    <tr>
      <td style="padding:6px 0;"><?php esc_html_e( 'Dirección IP', 'lmsms-2fa' ); ?></td>
      <td style="padding:6px 0;"><strong><?php echo esc_html( $ip ); ?></strong></td>
    </tr>
  <?php endif; ?>
  <?php if ( $agent ) : ?>
    <tr>
      <td style="padding:6px 0;vertical-align:top;"><?php esc_html_e( 'Navegador', 'lmsms-2fa' ); ?></td>
      <td style="padding:6px 0;"><?php echo esc_html( $agent ); ?></td>
    </tr>
  <?php endif; ?>
</table>

<p style="margin:0;font-size:14px;line-height:1.6;color:#50575e;">
  <?php esc_html_e( 'Si fuiste tú, no tienes que hacer nada. Si no, cambia tu contraseña y revisa los equipos de confianza de tu cuenta.', 'lmsms-2fa' ); ?>
</p>
