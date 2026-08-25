/*
 * Detalles de la pantalla del código. Sin dependencias: wp-login.php no
 * carga jQuery y no merece la pena obligarle.
 *
 * Nada de esto es imprescindible; si el JS falla, el formulario funciona.
 *
 * No se envía solo al llegar a N dígitos a propósito: el servidor decide la
 * longitud del código (entre 4 y 8) y no la comunica, así que adivinarla
 * gastaría intentos de verificación con códigos incompletos.
 */
( function () {
  'use strict';

  var form = document.getElementById( 'loginform' );
  var field = document.getElementById( 'lm2fa_code' );

  if ( ! form ) {
    return;
  }

  // El autorrelleno del SMS a veces arrastra texto alrededor del código.
  if ( field ) {
    field.addEventListener( 'input', function () {
      var clean = field.value.replace( /\D/g, '' );

      if ( clean !== field.value ) {
        field.value = clean;
      }
    } );
  }

  // Evita el doble envío: verificar dos veces gasta un intento de más.
  form.addEventListener( 'submit', function () {
    var button = form.querySelector( '.lm2fa-submit' );

    if ( ! button ) {
      return;
    }

    window.setTimeout( function () {
      button.disabled = true;
    }, 0 );
  } );
} )();
