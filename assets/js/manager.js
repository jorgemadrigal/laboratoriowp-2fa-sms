/*
 * Gestor del usuario: confirmaciones y guardado de los códigos de respaldo.
 * Se carga igual en el escritorio y en Mi cuenta, así que no depende de
 * jQuery ni de nada de WordPress.
 */
( function () {
  'use strict';

  document.addEventListener( 'submit', function ( event ) {
    var form = event.target.closest( '[data-lm2fa-confirm]' );

    if ( form && ! window.confirm( form.getAttribute( 'data-lm2fa-confirm' ) ) ) {
      event.preventDefault();
    }
  } );

  document.addEventListener( 'click', function ( event ) {
    var copy = event.target.closest( '[data-lm2fa-copy]' );
    if ( copy ) {
      event.preventDefault();
      copyCodes( copy );
      return;
    }

    var download = event.target.closest( '[data-lm2fa-download]' );
    if ( download ) {
      event.preventDefault();
      downloadCodes( download );
    }
  } );

  function readCodes( button, attribute ) {
    var list = document.querySelector( button.getAttribute( attribute ) );

    if ( ! list ) {
      return '';
    }

    return Array.prototype.map.call( list.querySelectorAll( 'li' ), function ( item ) {
      return item.textContent.trim();
    } ).join( '\n' );
  }

  function copyCodes( button ) {
    var text = readCodes( button, 'data-lm2fa-copy' );

    if ( ! text || ! navigator.clipboard ) {
      return;
    }

    navigator.clipboard.writeText( text ).then( function () {
      flash( button );
    } );
  }

  function downloadCodes( button ) {
    var text = readCodes( button, 'data-lm2fa-download' );

    if ( ! text ) {
      return;
    }

    var url = URL.createObjectURL( new Blob( [ text + '\n' ], { type: 'text/plain' } ) );
    var link = document.createElement( 'a' );

    link.href = url;
    link.download = button.getAttribute( 'data-filename' ) || 'codigos-respaldo.txt';
    document.body.appendChild( link );
    link.click();
    document.body.removeChild( link );

    // Dar tiempo al navegador a empezar la descarga antes de soltar el blob.
    window.setTimeout( function () {
      URL.revokeObjectURL( url );
    }, 1000 );
  }

  /* Confirmación visual breve, sin tocar la traducción del texto original. */
  function flash( button ) {
    var original = button.textContent;

    button.textContent = '✓';
    window.setTimeout( function () {
      button.textContent = original;
    }, 1200 );
  }
} )();
