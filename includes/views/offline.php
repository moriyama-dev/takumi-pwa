<?php
/**
 * The page the service worker shows when a page is requested offline and no
 * copy of it was cached.
 *
 * @var array $data Prepared by TKPwa_Service_Worker::serve_offline_page().
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="<?php echo esc_attr( $data['theme'] ); ?>">
<title><?php echo esc_html( $data['name'] ); ?></title>
<style>
	:root { --bg: <?php echo esc_html( $data['background'] ); ?>; }
	* { box-sizing: border-box; }
	body {
		margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
		padding: 24px; background: var(--bg); color: #1d2327;
		font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Hiragino Sans", "Noto Sans JP", sans-serif;
	}
	main {
		width: 100%; max-width: 420px; background: #fff; border-radius: 16px; padding: 32px 24px;
		box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 8px 24px rgba(0,0,0,.06); text-align: center;
	}
	img { width: 72px; height: 72px; border-radius: 16px; }
	h1 { font-size: 20px; margin: 16px 0 8px; }
	p { margin: 0 0 20px; color: #50575e; }
	button {
		font: inherit; font-weight: 600; border: 0; border-radius: 999px; padding: 10px 24px; cursor: pointer;
		background: #1d2327; color: #fff;
	}
	#tkpwa-cached { text-align: left; margin-top: 24px; }
	#tkpwa-cached h2 { font-size: 14px; color: #50575e; font-weight: 600; margin: 0 0 8px; }
	#tkpwa-cached ul { list-style: none; margin: 0; padding: 0; }
	#tkpwa-cached li a {
		display: block; padding: 10px 0; border-top: 1px solid #e0e0e0; color: #1d2327; text-decoration: none;
		overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
	}
</style>
</head>
<body>
<main>
	<?php if ( $data['icon'] ) : ?>
		<img src="<?php echo esc_url( $data['icon'] ); ?>" alt="">
	<?php endif; ?>
	<h1><?php esc_html_e( 'You are offline', 'takumi-pwa' ); ?></h1>
	<p><?php esc_html_e( 'This page has not been saved for offline reading. Check your connection and try again.', 'takumi-pwa' ); ?></p>
	<button type="button" id="tkpwa-retry"><?php esc_html_e( 'Try again', 'takumi-pwa' ); ?></button>
	<div id="tkpwa-cached" hidden>
		<h2><?php esc_html_e( 'Available offline', 'takumi-pwa' ); ?></h2>
		<ul></ul>
	</div>
</main>
<script>
( function () {
	document.getElementById( 'tkpwa-retry' ).addEventListener( 'click', function () {
		location.reload();
	} );
	window.addEventListener( 'online', function () {
		location.reload();
	} );

	// List pages the service worker already holds, so the visitor has
	// somewhere to go instead of a dead end.
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
</script>
</body>
</html>
