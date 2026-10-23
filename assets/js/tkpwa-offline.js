/**
 * Offline page: retry, and list the pages the service worker already holds
 * so the visitor has somewhere to go instead of a dead end.
 */
( function () {
	'use strict';

	document.getElementById( 'tkpwa-retry' ).addEventListener( 'click', function () {
		location.reload();
	} );
	window.addEventListener( 'online', function () {
		location.reload();
	} );

	if ( ! ( 'caches' in window ) ) {
		return;
	}
	caches.keys().then( function ( names ) {
		var pages = names.filter( function ( n ) { return 0 === n.indexOf( 'tkpwa-pages-' ); } );
		return Promise.all( pages.map( function ( n ) {
			return caches.open( n ).then( function ( c ) { return c.keys(); } );
		} ) );
	} ).then( function ( lists ) {
		var seen = {}, list = document.querySelector( '#tkpwa-cached ul' );
		lists.forEach( function ( requests ) {
			requests.forEach( function ( req ) {
				var url = new URL( req.url );
				if ( url.search.indexOf( 'tkpwa_offline' ) !== -1 || seen[ url.href ] ) {
					return;
				}
				seen[ url.href ] = true;
				var li = document.createElement( 'li' ), a = document.createElement( 'a' );
				a.href = url.href;
				a.textContent = decodeURIComponent( url.pathname + url.search );
				li.appendChild( a );
				list.appendChild( li );
			} );
		} );
		if ( list.children.length ) {
			document.getElementById( 'tkpwa-cached' ).hidden = false;
		}
	} );
}() );
