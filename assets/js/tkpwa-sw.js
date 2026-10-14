/**
 * Takumi PWA service worker.
 *
 * Served by TKPwa_Service_Worker, which prepends `self.TKPWA` with the
 * site-specific configuration.
 *
 * Pages:  network first. A copy is kept for offline use unless the server
 *         marked the response as personal. With no network, the copy is
 *         served; with no copy, the offline page.
 * Assets: theme and plugin CSS, JS, fonts and images are served from cache
 *         at once and refreshed in the background.
 * Other:  left to the browser - other origins, non-GET requests, the admin,
 *         login, REST API and anything excluded in settings.
 */

/* global TKPWA */
( function () {
	'use strict';

	var CFG = self.TKPWA;
	var PAGES = 'tkpwa-pages-' + CFG.version;
	var ASSETS = 'tkpwa-assets-' + CFG.version;
	var MAX_PAGES = 40;
	var MAX_ASSETS = 150;
	// When a cached copy exists, a slow network is abandoned after this long.
	var NETWORK_TIMEOUT = 4000;

	self.addEventListener( 'install', function ( event ) {
		event.waitUntil(
			caches.open( PAGES ).then( function ( cache ) {
				// Each one separately: a private site redirects the start URL
				// to the login screen, and that must not stop the install.
				return Promise.all( [ CFG.offlineUrl, CFG.startUrl ].map( function ( url ) {
					return fetch( url, { credentials: 'same-origin' } ).then( function ( response ) {
						if ( isCacheable( response ) ) {
							return cache.put( url, response );
						}
					} ).catch( function () {} );
				} ) );
			} ).then( function () {
				return self.skipWaiting();
			} )
		);
	} );

	self.addEventListener( 'activate', function ( event ) {
		event.waitUntil(
			caches.keys().then( function ( names ) {
				return Promise.all( names.filter( function ( name ) {
					return 0 === name.indexOf( 'tkpwa-' ) && name !== PAGES && name !== ASSETS;
				} ).map( function ( name ) {
					return caches.delete( name );
				} ) );
			} ).then( function () {
				if ( self.registration.navigationPreload ) {
					return self.registration.navigationPreload.enable();
				}
			} ).then( function () {
				return self.clients.claim();
			} )
		);
	} );

	self.addEventListener( 'message', function ( event ) {
		if ( event.data && 'tkpwa-clear' === event.data.type ) {
			event.waitUntil( caches.delete( PAGES ) );
		}
	} );

	self.addEventListener( 'fetch', function ( event ) {
		var request = event.request;
		if ( 'GET' !== request.method ) {
			return;
		}

		var url = new URL( request.url );
		if ( url.origin !== self.location.origin || isExcluded( url ) ) {
			return;
		}

		if ( 'navigate' === request.mode ) {
			event.respondWith( handlePage( event ) );
			return;
		}

		if ( isAsset( request, url ) ) {
			event.respondWith( handleAsset( event ) );
		}
	} );

	function isExcluded( url ) {
		for ( var i = 0; i < CFG.skipPaths.length; i++ ) {
			if ( 0 === url.pathname.indexOf( CFG.skipPaths[ i ] ) ) {
				return true;
			}
		}
		for ( var j = 0; j < CFG.skipArgs.length; j++ ) {
			if ( url.searchParams.has( CFG.skipArgs[ j ] ) ) {
				return true;
			}
		}
		return false;
	}

	function isAsset( request, url ) {
		var kinds = [ 'style', 'script', 'font', 'image' ];
		if ( -1 === kinds.indexOf( request.destination ) ) {
			return false;
		}
		return -1 !== url.pathname.indexOf( '/wp-content/' ) || -1 !== url.pathname.indexOf( '/wp-includes/' );
	}

	function isCacheable( response ) {
		return response &&
			200 === response.status &&
			'basic' === response.type &&
			! response.redirected &&
			'bypass' !== response.headers.get( CFG.bypass );
	}

	function handlePage( event ) {
		var request = event.request;
		var network = Promise.resolve( event.preloadResponse ).then( function ( preloaded ) {
			return preloaded || fetch( request );
		} ).then( function ( response ) {
			if ( isCacheable( response ) ) {
				var copy = response.clone();
				event.waitUntil( caches.open( PAGES ).then( function ( cache ) {
					return cache.put( request, copy ).then( function () {
						return trim( PAGES, MAX_PAGES );
					} );
				} ) );
			}
			return response;
		} );

		return caches.match( request, { cacheName: PAGES } ).then( function ( cached ) {
			if ( ! cached ) {
				return network.catch( offline );
			}
			// A cached copy exists: prefer the network, but do not leave the
			// visitor staring at a blank screen on a bad connection.
			var timeout = new Promise( function ( resolve ) {
				setTimeout( function () {
					resolve( cached );
				}, NETWORK_TIMEOUT );
			} );
			return Promise.race( [ network.catch( function () {
				return cached;
			} ), timeout ] );
		} );
	}

	function offline() {
		return caches.match( CFG.offlineUrl, { cacheName: PAGES } ).then( function ( page ) {
			return page || new Response( 'Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } } );
		} );
	}

	function handleAsset( event ) {
		var request = event.request;
		return caches.open( ASSETS ).then( function ( cache ) {
			return cache.match( request ).then( function ( cached ) {
				var refresh = fetch( request ).then( function ( response ) {
					if ( isCacheable( response ) ) {
						return cache.put( request, response.clone() ).then( function () {
							trim( ASSETS, MAX_ASSETS );
							return response;
						} );
					}
					return response;
				} );
				if ( cached ) {
					event.waitUntil( refresh.catch( function () {} ) );
					return cached;
				}
				return refresh;
			} );
		} );
	}

	// Oldest entries go first; Cache keys come back in insertion order.
	function trim( name, max ) {
		return caches.open( name ).then( function ( cache ) {
			return cache.keys().then( function ( keys ) {
				var excess = keys.length - max;
				var doomed = [];
				for ( var i = 0; i < keys.length && doomed.length < excess; i++ ) {
					if ( -1 === keys[ i ].url.indexOf( CFG.offlineUrl ) ) {
						doomed.push( cache.delete( keys[ i ] ) );
					}
				}
				return Promise.all( doomed );
			} );
		} );
	}
}() );
