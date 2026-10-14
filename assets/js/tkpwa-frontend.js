/**
 * Takumi PWA front-end.
 *
 * Configuration arrives in `tkpwaConfig` from TKPwa_Frontend. Each feature
 * is independent and switched on by its own flag.
 */

/* global tkpwaConfig */
( function () {
	'use strict';

	var cfg = window.tkpwaConfig;
	if ( ! cfg ) {
		return;
	}
	var t = cfg.i18n;
	var root = document.documentElement;
	var standalone = root.classList.contains( 'tkpwa-standalone' );

	/* ------------------------------------------------------------------
	 * Storage that never throws (private mode, blocked site data).
	 * ------------------------------------------------------------------ */
	var store = {
		get: function ( key ) {
			try {
				return window.localStorage.getItem( 'tkpwa:' + key );
			} catch ( e ) {
				return null;
			}
		},
		set: function ( key, value ) {
			try {
				window.localStorage.setItem( 'tkpwa:' + key, value );
			} catch ( e ) {}
		}
	};

	/* ------------------------------------------------------------------
	 * Service worker
	 * ------------------------------------------------------------------ */
	if ( 'serviceWorker' in navigator && window.isSecureContext ) {
		window.addEventListener( 'load', function () {
			navigator.serviceWorker.register( cfg.sw, { scope: cfg.scope } ).catch( function ( error ) {
				if ( window.console ) {
					window.console.warn( '[Takumi PWA] Service worker registration failed:', error );
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------
	 * Toast, shared by the features below.
	 * ------------------------------------------------------------------ */
	function toast( message ) {
		var el = document.createElement( 'div' );
		el.className = 'tkpwa-toast';
		el.setAttribute( 'role', 'status' );
		el.textContent = message;
		document.body.appendChild( el );
		window.setTimeout( function () {
			el.classList.add( 'is-visible' );
		}, 20 );
		window.setTimeout( function () {
			el.classList.remove( 'is-visible' );
			window.setTimeout( function () {
				el.remove();
			}, 400 );
		}, 6000 );
	}

	/* ------------------------------------------------------------------
	 * Install prompt
	 *
	 * Chrome and Edge fire beforeinstallprompt; the event is held and
	 * replayed from our own banner. iOS Safari has no such API, so the
	 * banner shows the two steps instead. Shown from the second page view,
	 * and not again for two weeks after being closed.
	 * ------------------------------------------------------------------ */
	var deferredPrompt = null;
	var ua = navigator.userAgent;
	var isIOS = /iPad|iPhone|iPod/.test( ua ) || ( 'MacIntel' === navigator.platform && navigator.maxTouchPoints > 1 );
	var isIOSSafari = isIOS && /Safari/.test( ua ) && ! /CriOS|FxiOS|EdgiOS|OPiOS/.test( ua );

	function installButtons() {
		return Array.prototype.slice.call( document.querySelectorAll( '.tkpwa-install-button' ) );
	}

	function showInstallButtons() {
		installButtons().forEach( function ( button ) {
			button.hidden = false;
		} );
	}

	function runInstall() {
		if ( deferredPrompt ) {
			deferredPrompt.prompt();
			deferredPrompt.userChoice.then( function () {
				deferredPrompt = null;
				closeBanner( false );
			} );
		} else if ( isIOSSafari ) {
			openBanner( true );
		}
	}

	function bannerDue() {
		var views = parseInt( store.get( 'views' ) || '0', 10 ) + 1;
		store.set( 'views', String( views ) );
		var dismissed = parseInt( store.get( 'dismissed' ) || '0', 10 );
		return views >= 2 && Date.now() - dismissed > 14 * 24 * 3600 * 1000;
	}

	var banner = null;

	function openBanner( force ) {
		if ( banner ) {
			return;
		}
		if ( ! force && ( ! cfg.installPrompt || ! bannerDue() ) ) {
			return;
		}

		banner = document.createElement( 'div' );
		banner.className = 'tkpwa-install';
		banner.setAttribute( 'role', 'dialog' );
		banner.setAttribute( 'aria-label', t.installTitle );

		var html = '';
		if ( cfg.icon ) {
			html += '<img class="tkpwa-install__icon" alt="" width="48" height="48">';
		}
		html += '<div class="tkpwa-install__body"><strong class="tkpwa-install__title"></strong>';
		if ( deferredPrompt ) {
			html += '<p class="tkpwa-install__text"></p></div>';
			html += '<button type="button" class="tkpwa-install__action"></button>';
		} else {
			html += '<ol class="tkpwa-install__steps">' +
				'<li><span class="tkpwa-install__glyph" aria-hidden="true">' +
				'<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 2.5 7.8 6.7l1.1 1.1L11.2 5.5V15h1.6V5.5l2.3 2.3 1.1-1.1zM5 10h3v1.6H6.6v8.8h10.8v-8.8H16V10h3v12H5z"/></svg>' +
				'</span><span class="tkpwa-step1"></span></li>' +
				'<li><span class="tkpwa-install__glyph" aria-hidden="true">' +
				'<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M4 4h16v16H4zm1.6 1.6v12.8h12.8V5.6zM11.2 8h1.6v3.2H16v1.6h-3.2V16h-1.6v-3.2H8v-1.6h3.2z"/></svg>' +
				'</span><span class="tkpwa-step2"></span></li></ol></div>';
		}
		html += '<button type="button" class="tkpwa-install__close"><span aria-hidden="true">&times;</span></button>';
		banner.innerHTML = html;

		// Text is set through textContent, never parsed as HTML.
		if ( cfg.icon ) {
			banner.querySelector( '.tkpwa-install__icon' ).src = cfg.icon;
		}
		banner.querySelector( '.tkpwa-install__title' ).textContent = t.installTitle;
		banner.querySelector( '.tkpwa-install__close' ).setAttribute( 'aria-label', t.close );
		if ( deferredPrompt ) {
			banner.querySelector( '.tkpwa-install__text' ).textContent = t.installText;
			var action = banner.querySelector( '.tkpwa-install__action' );
			action.textContent = t.install;
			action.addEventListener( 'click', runInstall );
		} else {
			banner.querySelector( '.tkpwa-step1' ).textContent = t.iosStep1;
			banner.querySelector( '.tkpwa-step2' ).textContent = t.iosStep2;
			banner.classList.add( 'tkpwa-install--ios' );
		}
		banner.querySelector( '.tkpwa-install__close' ).addEventListener( 'click', function () {
			closeBanner( true );
		} );

		document.body.appendChild( banner );
		window.setTimeout( function () {
			banner.classList.add( 'is-visible' );
		}, 20 );
	}

	function closeBanner( remember ) {
		if ( remember ) {
			store.set( 'dismissed', String( Date.now() ) );
		}
		if ( ! banner ) {
			return;
		}
		var el = banner;
		banner = null;
		el.classList.remove( 'is-visible' );
		window.setTimeout( function () {
			el.remove();
		}, 400 );
	}

	if ( ! standalone ) {
		window.addEventListener( 'beforeinstallprompt', function ( event ) {
			event.preventDefault();
			deferredPrompt = event;
			showInstallButtons();
			openBanner( false );
		} );
		window.addEventListener( 'appinstalled', function () {
			deferredPrompt = null;
			closeBanner( false );
			installButtons().forEach( function ( button ) {
				button.hidden = true;
			} );
		} );
		document.addEventListener( 'DOMContentLoaded', function () {
			installButtons().forEach( function ( button ) {
				button.addEventListener( 'click', runInstall );
			} );
			if ( isIOSSafari ) {
				showInstallButtons();
				openBanner( false );
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Loading screen (app mode only)
	 *
	 * Covers the gap between tapping a link and the next page painting,
	 * which in a browser is hidden by the address bar's progress line but
	 * in an app looks like a frozen screen. Appears only if the next page
	 * takes longer than a moment.
	 * ------------------------------------------------------------------ */
	if ( standalone && cfg.loadingScreen ) {
		var overlay = null;
		var overlayTimer = null;

		var showOverlay = function () {
			if ( ! overlay ) {
				overlay = document.createElement( 'div' );
				overlay.className = 'tkpwa-loading';
				overlay.setAttribute( 'role', 'progressbar' );
				overlay.setAttribute( 'aria-label', t.loading );
				if ( cfg.icon ) {
					var img = document.createElement( 'img' );
					img.src = cfg.icon;
					img.alt = '';
					overlay.appendChild( img );
				}
				var spinner = document.createElement( 'span' );
				spinner.className = 'tkpwa-loading__spinner';
				overlay.appendChild( spinner );
				document.body.appendChild( overlay );
			}
			overlay.classList.add( 'is-visible' );
		};

		var hideOverlay = function () {
			window.clearTimeout( overlayTimer );
			if ( overlay ) {
				overlay.classList.remove( 'is-visible' );
			}
		};

		document.addEventListener( 'click', function ( event ) {
			if ( event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}
			var link = event.target.closest ? event.target.closest( 'a[href]' ) : null;
			if ( ! link || ( link.target && '_self' !== link.target ) || link.hasAttribute( 'download' ) ) {
				return;
			}
			var url = new URL( link.href, location.href );
			if ( url.origin !== location.origin || ( url.pathname === location.pathname && url.search === location.search ) ) {
				return;
			}
			overlayTimer = window.setTimeout( showOverlay, 250 );
		} );

		// Coming back with the back button restores the page from memory
		// with the overlay still on it.
		window.addEventListener( 'pageshow', hideOverlay );
	}

	/* ------------------------------------------------------------------
	 * Pull to refresh (app mode only; browsers have their own)
	 * ------------------------------------------------------------------ */
	if ( standalone && cfg.pullToRefresh && 'ontouchstart' in window ) {
		var THRESHOLD = 80;
		var startY = null;
		var distance = 0;
		var indicator = document.createElement( 'div' );
		indicator.className = 'tkpwa-ptr';
		indicator.setAttribute( 'aria-hidden', 'true' );
		indicator.innerHTML = '<span class="tkpwa-ptr__arrow">&#8595;</span><span class="tkpwa-ptr__label"></span>';
		var label = indicator.querySelector( '.tkpwa-ptr__label' );

		document.addEventListener( 'DOMContentLoaded', function () {
			document.body.appendChild( indicator );
		} );

		window.addEventListener( 'touchstart', function ( event ) {
			startY = ( window.scrollY <= 0 && 1 === event.touches.length ) ? event.touches[ 0 ].clientY : null;
			distance = 0;
		}, { passive: true } );

		window.addEventListener( 'touchmove', function ( event ) {
			if ( null === startY ) {
				return;
			}
			distance = Math.max( 0, event.touches[ 0 ].clientY - startY );
			var pulled = Math.min( distance, THRESHOLD * 1.5 );
			indicator.style.transform = 'translate(-50%,' + ( pulled - 48 ) + 'px)';
			indicator.classList.toggle( 'is-ready', distance > THRESHOLD );
			label.textContent = distance > THRESHOLD ? t.releaseToRefresh : t.pullToRefresh;
		}, { passive: true } );

		window.addEventListener( 'touchend', function () {
			if ( null !== startY && distance > THRESHOLD ) {
				indicator.classList.add( 'is-loading' );
				location.reload();
				return;
			}
			startY = null;
			indicator.style.transform = '';
			indicator.classList.remove( 'is-ready' );
		} );
	}

	/* ------------------------------------------------------------------
	 * Scroll progress
	 * ------------------------------------------------------------------ */
	if ( cfg.scrollProgress ) {
		var bar = document.createElement( 'div' );
		bar.className = 'tkpwa-progress';
		bar.setAttribute( 'aria-hidden', 'true' );
		var ticking = false;
		var update = function () {
			var max = document.documentElement.scrollHeight - window.innerHeight;
			bar.style.transform = 'scaleX(' + ( max > 0 ? Math.min( 1, window.scrollY / max ) : 0 ) + ')';
			ticking = false;
		};
		document.addEventListener( 'DOMContentLoaded', function () {
			document.body.appendChild( bar );
			update();
		} );
		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( update );
			}
		}, { passive: true } );
	}

	/* ------------------------------------------------------------------
	 * Offline forms
	 *
	 * When a supported form is submitted with no connection, its fields are
	 * kept in localStorage and posted to the form's own action once the
	 * connection returns - on the 'online' event, or on the next page view.
	 * The form plugins all accept a plain (non-AJAX) POST, which is what is
	 * replayed. File inputs cannot be stored, so those forms are left alone.
	 * ------------------------------------------------------------------ */
	if ( cfg.offlineForms && cfg.offlineForms.length ) {
		var QUEUE_KEY = 'formQueue';

		var readQueue = function () {
			try {
				return JSON.parse( store.get( QUEUE_KEY ) || '[]' );
			} catch ( e ) {
				return [];
			}
		};

		var writeQueue = function ( queue ) {
			store.set( QUEUE_KEY, JSON.stringify( queue ) );
		};

		var hasFiles = function ( form ) {
			return Array.prototype.some.call( form.querySelectorAll( 'input[type="file"]' ), function ( input ) {
				return input.files && input.files.length > 0;
			} );
		};

		var flushing = false;
		var flush = function () {
			if ( flushing || ! navigator.onLine ) {
				return;
			}
			var queue = readQueue();
			if ( ! queue.length ) {
				return;
			}
			flushing = true;
			var remaining = [];
			var sent = 0;
			queue.reduce( function ( chain, entry ) {
				return chain.then( function () {
					var body = new FormData();
					entry.fields.forEach( function ( pair ) {
						body.append( pair[ 0 ], pair[ 1 ] );
					} );
					return fetch( entry.action, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( response ) {
						if ( response.ok ) {
							sent++;
						} else {
							remaining.push( entry );
						}
					} ).catch( function () {
						remaining.push( entry );
					} );
				} );
			}, Promise.resolve() ).then( function () {
				writeQueue( remaining );
				flushing = false;
				if ( sent ) {
					toast( t.formSent );
				}
			} );
		};

		document.addEventListener( 'submit', function ( event ) {
			if ( navigator.onLine ) {
				return;
			}
			var form = event.target;
			if ( ! cfg.offlineForms.some( function ( selector ) {
				return form.matches( selector );
			} ) ) {
				return;
			}
			event.preventDefault();
			event.stopImmediatePropagation();

			if ( hasFiles( form ) ) {
				toast( t.formHasFiles );
				return;
			}

			var fields = [];
			new FormData( form ).forEach( function ( value, name ) {
				if ( 'string' === typeof value ) {
					fields.push( [ name, value ] );
				}
			} );
			var queue = readQueue();
			queue.push( {
				action: form.getAttribute( 'action' ) ? new URL( form.getAttribute( 'action' ), location.href ).href : location.href,
				fields: fields,
				time: Date.now()
			} );
			writeQueue( queue );
			form.reset();
			toast( t.formQueued );
		}, true );

		window.addEventListener( 'online', flush );
		window.addEventListener( 'load', flush );
	}
}() );
