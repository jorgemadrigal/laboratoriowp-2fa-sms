<?php
/**
 * Formulario del segundo factor dentro de wp-login.php.
 *
 * @var int    $user_id
 * @var string $token
 * @var string $error
 * @var string $notice
 * @var bool   $fatal
 * @var bool   $by_email      El código vigente llegó por correo.
 * @var string $destination   Teléfono o correo enmascarado.
 * @var int    $codes_left
 * @var bool   $trust_enabled
 * @var int    $trust_days
 * @var bool   $can_resend
 * @var bool   $email_offer   Se puede ofrecer el envío por correo.
 * @var string $form_action
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( $error ) {
  echo '<div id="login_error">' . esc_html( $error ) . '</div>';
} elseif ( $notice ) {
  echo '<p class="message">' . esc_html( $notice ) . '</p>';
}

if ( $fatal ) :
  ?>
  <div class="lm2fa-box">
    <p><?php esc_html_e( 'No pudimos continuar con la verificación.', 'lmsms-2fa' ); ?></p>
    <p><a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Volver al inicio de sesión', 'lmsms-2fa' ); ?></a></p>
  </div>
  <?php
  return;
endif;
?>

<form name="lm2fa_form" id="loginform" action="<?php echo esc_url( $form_action ); ?>" method="post">
  <div class="lm2fa-box">
    <p>
      <?php
      if ( $by_email ) {
        printf(
          /* translators: %s correo enmascarado. */
          esc_html__( 'Escribe el código que enviamos por correo a %s.', 'lmsms-2fa' ),
          '<strong>' . esc_html( $destination ) . '</strong>'
        );
      } else {
        printf(
          /* translators: %s teléfono enmascarado. */
          esc_html__( 'Escribe el código que enviamos por SMS al %s.', 'lmsms-2fa' ),
          '<strong>' . esc_html( $destination ) . '</strong>'
        );
      }
      ?>
    </p>

    <label for="lm2fa_code" class="screen-reader-text"><?php esc_html_e( 'Código de verificación', 'lmsms-2fa' ); ?></label>
    <input type="text" name="lm2fa_code" id="lm2fa_code" class="lm2fa-code" inputmode="numeric"
      autocomplete="one-time-code" maxlength="8" autofocus>
  </div>

  <?php if ( $trust_enabled ) : ?>
    <p class="forgetmenot">
      <label for="lm2fa_trust">
        <input name="lm2fa_trust" type="checkbox" id="lm2fa_trust" value="1">
        <?php
        printf(
          /* translators: %d días que se recuerda el equipo. */
          esc_html__( 'No volver a pedirlo en este equipo por %d días', 'lmsms-2fa' ),
          (int) $trust_days
        );
        ?>
      </label>
    </p>
  <?php endif; ?>

  <p class="submit">
    <button type="submit" class="button button-primary button-large lm2fa-submit">
      <?php esc_html_e( 'Verificar y entrar', 'lmsms-2fa' ); ?>
    </button>
  </p>

  <div class="lm2fa-actions">
    <?php if ( $can_resend ) : ?>
      <button type="submit" name="lm2fa_resend" value="1" class="button button-secondary">
        <?php echo $by_email ? esc_html__( 'Intentar por SMS', 'lmsms-2fa' ) : esc_html__( 'Reenviar SMS', 'lmsms-2fa' ); ?>
      </button>
    <?php endif; ?>

    <?php if ( $email_offer ) : ?>
      <button type="submit" name="lm2fa_email" value="1" class="button button-secondary">
        <?php esc_html_e( 'Enviarlo por correo', 'lmsms-2fa' ); ?>
      </button>
    <?php endif; ?>

    <a href="<?php echo esc_url( wp_login_url() ); ?>" class="lm2fa-muted"><?php esc_html_e( 'Cancelar', 'lmsms-2fa' ); ?></a>
  </div>

  <?php if ( $codes_left > 0 ) : ?>
    <details class="lm2fa-alt">
      <summary><?php esc_html_e( 'No recibo el código: usar uno de respaldo', 'lmsms-2fa' ); ?></summary>
      <input type="text" name="lm2fa_recovery" placeholder="XXXX-XXXX" autocomplete="off">
      <p class="lm2fa-muted">
        <?php
        printf(
          /* translators: %d códigos de respaldo disponibles. */
          esc_html( _n( 'Te queda %d código de respaldo.', 'Te quedan %d códigos de respaldo.', $codes_left, 'lmsms-2fa' ) ),
          (int) $codes_left
        );
        ?>
      </p>
    </details>
  <?php endif; ?>

  <input type="hidden" name="lm2fa_user" value="<?php echo esc_attr( $user_id ); ?>">
  <input type="hidden" name="lm2fa_token" value="<?php echo esc_attr( $token ); ?>">
</form>
