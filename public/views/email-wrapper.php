<?php
/**
 * Marco común de los correos del plugin.
 *
 * Estilos en línea a propósito: los clientes de correo ignoran las hojas
 * externas y muchos recortan el <style> del <head>.
 *
 * @var string $title
 * @var string $content HTML ya montado por la vista concreta.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo esc_html( $title ); ?></title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#2c3338;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:6px;">
    <tr>
      <td style="padding:28px 32px;">
        <p style="margin:0 0 20px;font-size:13px;letter-spacing:.06em;text-transform:uppercase;color:#787c82;">
          <?php echo esc_html( LM2FA_Util::site_name() ); ?>
        </p>

        <?php echo wp_kses_post( $content ); ?>
      </td>
    </tr>
    <tr>
      <td style="padding:0 32px 28px;">
        <p style="margin:0;font-size:12px;color:#787c82;">
          <?php
          printf(
            /* translators: %s dirección del sitio. */
            esc_html__( 'Este mensaje se envió automáticamente desde %s.', 'lmsms-2fa' ),
            esc_html( home_url( '/' ) )
          );
          ?>
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
