<?php
/**
 * Aviso al administrador: el saldo del servicio se está agotando.
 *
 * @var array  $quota     Respuesta de /otp/quota.
 * @var string $panel_url
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

$capacity = isset( $quota['total_capacity'] ) ? (int) $quota['total_capacity'] : 0;
?>
<p style="margin:0 0 20px;font-size:15px;line-height:1.6;">
  <?php
  if ( $capacity > 0 ) {
    printf(
      /* translators: %d verificaciones que quedan. */
      esc_html( _n( 'Queda %d verificación disponible para el segundo factor de este sitio.', 'Quedan %d verificaciones disponibles para el segundo factor de este sitio.', $capacity, 'lmsms-2fa' ) ),
      $capacity
    );
  } else {
    esc_html_e( 'Se agotaron las verificaciones disponibles. Los usuarios con verificación en dos pasos no pueden recibir su código y no podrán entrar.', 'lmsms-2fa' );
  }
  ?>
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 24px;font-size:14px;color:#50575e;">
  <tr>
    <td style="padding:6px 0;"><?php esc_html_e( 'Gratuitas del mes', 'lmsms-2fa' ); ?></td>
    <td style="padding:6px 0;"><strong><?php echo esc_html( ( isset( $quota['free_remaining'] ) ? $quota['free_remaining'] : '?' ) . ' / ' . ( isset( $quota['free_limit'] ) ? $quota['free_limit'] : '?' ) ); ?></strong></td>
  </tr>
  <tr>
    <td style="padding:6px 0;"><?php esc_html_e( 'Créditos SMS', 'lmsms-2fa' ); ?></td>
    <td style="padding:6px 0;"><strong><?php echo esc_html( isset( $quota['sms_credits'] ) ? $quota['sms_credits'] : '?' ); ?></strong></td>
  </tr>
  <tr>
    <td style="padding:6px 0;"><?php esc_html_e( 'Reinicio de cuota', 'lmsms-2fa' ); ?></td>
    <td style="padding:6px 0;"><strong><?php echo esc_html( isset( $quota['resets_at'] ) ? $quota['resets_at'] : '?' ); ?></strong></td>
  </tr>
</table>

<p style="margin:0;">
  <a href="<?php echo esc_url( $panel_url ); ?>"
    style="display:inline-block;padding:12px 22px;background:#2271b1;color:#ffffff;border-radius:4px;text-decoration:none;font-size:15px;">
    <?php esc_html_e( 'Abrir mi panel de cliente', 'lmsms-2fa' ); ?>
  </a>
</p>
