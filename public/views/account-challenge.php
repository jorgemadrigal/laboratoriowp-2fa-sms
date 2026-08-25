<?php
/**
 * Desafío del segundo factor dentro de Mi cuenta.
 *
 * Mismo contenido que la pantalla de wp-login.php, con el marcado y las
 * clases de WooCommerce para que lo estilice el tema.
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
 * @var bool   $email_offer
 * @var string $form_action
 * @var string $cancel_url
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<div class="lm2fa-wrap lm2fa-challenge">

  <h2><?php esc_html_e( 'Verificación en dos pasos', 'lmsms-2fa' ); ?></h2>

  <?php if ( $error ) : ?>
    <ul class="woocommerce-error" role="alert">
      <li><?php echo esc_html( $error ); ?></li>
    </ul>
  <?php elseif ( $notice ) : ?>
    <div class="woocommerce-message" role="status"><?php echo esc_html( $notice ); ?></div>
  <?php endif; ?>

  <?php if ( $fatal ) : ?>
    <p><?php esc_html_e( 'No pudimos continuar con la verificación.', 'lmsms-2fa' ); ?></p>
    <p>
      <a class="button" href="<?php echo esc_url( $cancel_url ); ?>">
        <?php esc_html_e( 'Volver al inicio de sesión', 'lmsms-2fa' ); ?>
      </a>
    </p>

  <?php else : ?>
    <form class="woocommerce-form lm2fa-form" method="post" action="<?php echo esc_url( $form_action ); ?>" data-lm2fa-challenge>

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

      <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="lm2fa_code"><?php esc_html_e( 'Código de verificación', 'lmsms-2fa' ); ?></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text lm2fa-code"
          name="lm2fa_code" id="lm2fa_code" inputmode="numeric" autocomplete="one-time-code"
          maxlength="8" autofocus>
      </p>

      <?php if ( $trust_enabled ) : ?>
        <p class="form-row">
          <label class="woocommerce-form__label woocommerce-form__label-for-checkbox">
            <input class="woocommerce-form__input woocommerce-form__input-checkbox"
              type="checkbox" name="lm2fa_trust" id="lm2fa_trust" value="1">
            <span>
              <?php
              printf(
                /* translators: %d días que se recuerda el equipo. */
                esc_html__( 'No volver a pedirlo en este equipo por %d días', 'lmsms-2fa' ),
                (int) $trust_days
              );
              ?>
            </span>
          </label>
        </p>
      <?php endif; ?>

      <p class="form-row">
        <button type="submit" class="woocommerce-button button lm2fa-submit<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>">
          <?php esc_html_e( 'Verificar y entrar', 'lmsms-2fa' ); ?>
        </button>
      </p>

      <div class="lm2fa-buttons">
        <?php if ( $can_resend ) : ?>
          <button type="submit" name="lm2fa_resend" value="1" class="lm2fa-link">
            <?php echo $by_email ? esc_html__( 'Intentar por SMS', 'lmsms-2fa' ) : esc_html__( 'Reenviar SMS', 'lmsms-2fa' ); ?>
          </button>
        <?php endif; ?>

        <?php if ( $email_offer ) : ?>
          <button type="submit" name="lm2fa_email" value="1" class="lm2fa-link">
            <?php esc_html_e( 'Enviarlo por correo', 'lmsms-2fa' ); ?>
          </button>
        <?php endif; ?>

        <a href="<?php echo esc_url( $cancel_url ); ?>" class="lm2fa-link"><?php esc_html_e( 'Cancelar', 'lmsms-2fa' ); ?></a>
      </div>

      <?php if ( $codes_left > 0 ) : ?>
        <details class="lm2fa-alt">
          <summary><?php esc_html_e( 'No recibo el código: usar uno de respaldo', 'lmsms-2fa' ); ?></summary>

          <p class="woocommerce-form-row form-row form-row-wide">
            <label for="lm2fa_recovery"><?php esc_html_e( 'Código de respaldo', 'lmsms-2fa' ); ?></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
              name="lm2fa_recovery" id="lm2fa_recovery" placeholder="XXXX-XXXX" autocomplete="off">
          </p>

          <p class="lm2fa-hint">
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
  <?php endif; ?>
</div>
