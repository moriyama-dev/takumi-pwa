/**
 * Takumi PWA settings screen: icon picker, color pickers and the install
 * QR code.
 */

/* global jQuery, wp, qrcode, tkpwaAdmin */
( function ( $ ) {
	'use strict';

	$( function () {
		$( '.tkpwa-color' ).wpColorPicker();

		/* ---------- Icon picker ---------- */
		var frame = null;
		var $id = $( '.tkpwa-icon__id' );
		var $preview = $( '.tkpwa-icon__preview' );
		var $reset = $( '.tkpwa-icon__reset' );

		$( '.tkpwa-icon__choose' ).on( 'click', function () {
			if ( ! frame ) {
				frame = wp.media( {
					title: tkpwaAdmin.chooseIcon,
					button: { text: tkpwaAdmin.useIcon },
					library: { type: 'image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var image = frame.state().get( 'selection' ).first().toJSON();
					var thumb = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;
					$id.val( image.id );
					$preview.attr( 'src', thumb ).prop( 'hidden', false );
					$reset.prop( 'hidden', false );
				} );
			}
			frame.open();
		} );

		$reset.on( 'click', function () {
			$id.val( '0' );
			$reset.prop( 'hidden', true );
		} );

		/* ---------- QR code ---------- */
		var $qr = $( '.tkpwa-qr__code' );
		if ( ! $qr.length || 'function' !== typeof qrcode ) {
			return;
		}

		// Non-ASCII paths (Japanese slugs, for one) need UTF-8 encoding.
		qrcode.stringToBytes = qrcode.stringToBytesFuncs[ 'UTF-8' ];
		var code = qrcode( 0, 'M' );
		code.addData( $qr.data( 'url' ) );
		code.make();

		// Drawn on a canvas at print resolution and scaled down by CSS, so
		// the downloaded PNG is sharp enough for a flyer.
		var count = code.getModuleCount();
		var cell = 16;
		var margin = 4 * cell;
		var size = count * cell + margin * 2;
		var canvas = document.createElement( 'canvas' );
		canvas.width = size;
		canvas.height = size;
		var ctx = canvas.getContext( '2d' );
		ctx.fillStyle = '#ffffff';
		ctx.fillRect( 0, 0, size, size );
		ctx.fillStyle = '#000000';
		for ( var row = 0; row < count; row++ ) {
			for ( var col = 0; col < count; col++ ) {
				if ( code.isDark( row, col ) ) {
					ctx.fillRect( margin + col * cell, margin + row * cell, cell, cell );
				}
			}
		}
		canvas.setAttribute( 'role', 'img' );
		canvas.setAttribute( 'aria-label', $qr.data( 'url' ) );
		$qr.append( canvas );

		$( '.tkpwa-qr__download' ).on( 'click', function () {
			var link = document.createElement( 'a' );
			link.href = canvas.toDataURL( 'image/png' );
			link.download = tkpwaAdmin.fileName;
			document.body.appendChild( link );
			link.click();
			link.remove();
		} );
	} );
}( jQuery ) );
