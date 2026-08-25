/* global jQuery, wp, lm2faL10n */
( function ( $ ) {
	'use strict';

	$( function () {
		// Selector de color para el fondo del login.
		if ( $.fn.wpColorPicker ) {
			$( '.lm2fa-color' ).wpColorPicker();
		}

		var $field   = $( '#lm2fa_logo_id' );
		var $preview = $( '#lm2fa-logo-preview' );
		var $pick    = $( '#lm2fa-logo-pick' );
		var $clear   = $( '#lm2fa-logo-clear' );
		var frame;

		if ( ! $pick.length ) {
			return;
		}

		// Sin la biblioteca de medios no se puede continuar.
		if ( typeof wp === 'undefined' || ! wp.media ) {
			$pick.prop( 'disabled', true );
			return;
		}

		$pick.on( 'click', function ( event ) {
			event.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: lm2faL10n.frameTitle,
				button: { text: lm2faL10n.frameButton },
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var src = attachment.url;

				if ( attachment.sizes && attachment.sizes.medium ) {
					src = attachment.sizes.medium.url;
				}

				$field.val( attachment.id );
				$preview.html( $( '<img>', { src: src, alt: '' } ) );
				$clear.show();
			} );

			frame.open();
		} );

		$clear.on( 'click', function ( event ) {
			event.preventDefault();
			$field.val( '' );
			$preview.html( $( '<span>', { 'class': 'description', text: lm2faL10n.emptyLabel } ) );
			$( this ).hide();
		} );
	} );
} )( jQuery );