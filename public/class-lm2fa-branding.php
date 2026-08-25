<?php
/**
 * Apariencia de la pantalla de acceso.
 *
 * Se aplica a todo wp-login.php, incluida la pantalla del segundo factor:
 * si el usuario ve el logo de WordPress justo cuando le piden un código,
 * la pantalla parece ajena al sitio.
 *
 * @package LaboratorioWP_2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

final class LM2FA_Branding {

  const MIN_WIDTH  = 60;
  const MAX_WIDTH  = 600;
  const MIN_HEIGHT = 40;
  const MAX_HEIGHT = 400;

  public static function init() {
    add_action( 'login_enqueue_scripts', array( __CLASS__, 'styles' ), 20 );
    add_filter( 'login_headerurl', array( __CLASS__, 'logo_url' ) );
    add_filter( 'login_headertext', array( __CLASS__, 'logo_text' ) );
  }

  public static function is_enabled() {
    return LM2FA_Settings::is_yes( 'lm2fa_branding' );
  }

  /**
   * Datos del logo para la vista de ajustes.
   *
   * @return array{id:int,src:string}
   */
  public static function logo_preview() {
    $id  = LM2FA_Settings::int( 'lm2fa_logo_id' );
    $src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';

    return array(
      'id'  => $src ? $id : 0,
      'src' => $src ? $src : '',
    );
  }

  public static function logo_url( $url ) {
    return self::is_enabled() ? home_url( '/' ) : $url;
  }

  public static function logo_text( $text ) {
    return self::is_enabled() ? LM2FA_Util::site_name() : $text;
  }

  public static function styles() {
    if ( ! self::is_enabled() ) {
      return;
    }

    $css = self::build_css();

    if ( '' === $css ) {
      return;
    }

    wp_register_style( 'lm2fa-login-brand', false, array(), LM2FA_VERSION );
    wp_enqueue_style( 'lm2fa-login-brand' );
    wp_add_inline_style( 'lm2fa-login-brand', $css );
  }

  /** Depende de opciones del sitio, así que no puede ser un .css estático. */
  private static function build_css() {
    $id  = LM2FA_Settings::int( 'lm2fa_logo_id' );
    $src = $id ? wp_get_attachment_image_url( $id, 'full' ) : '';

    $width  = LM2FA_Settings::int( 'lm2fa_logo_width', self::MIN_WIDTH, self::MAX_WIDTH );
    $height = LM2FA_Settings::int( 'lm2fa_logo_height', self::MIN_HEIGHT, self::MAX_HEIGHT );
    $bg     = sanitize_hex_color( (string) LM2FA_Settings::get( 'lm2fa_login_bg' ) );

    $css = '';

    if ( $src ) {
      // Se cubren el marcado clásico (#login h1 a) y el actual (.wp-login-logo a).
      $css .= sprintf(
        '#login h1 a, .login h1 a, .wp-login-logo a{
          background-image:url(%1$s);
          background-size:contain;
          background-position:center center;
          background-repeat:no-repeat;
          width:%2$dpx;
          height:%3$dpx;
          margin-bottom:24px;
          padding-bottom:0;
        }',
        esc_url_raw( $src ),
        $width,
        $height
      );
    }

    if ( $bg ) {
      $css .= sprintf( 'body.login{background-color:%s;}', $bg );
    }

    return $css;
  }
}
