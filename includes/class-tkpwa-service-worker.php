<?php
/**
 * Serves the service worker and the offline page, and marks responses that
 * must never be cached.
 *
 * A service worker can only control pages at or below the path it is served
 * from, so it is served from the home URL with a query argument rather than
 * from the plugin directory. That gives it the whole site as scope without
 * writing a file to the site root.
 *
 * The caching rules are fixed rather than configurable ("おまかせ"): pages
 * are network-first with the cached copy as a fallback, theme and plugin
 * assets are served from cache and refreshed in the background, and
 * anything personal is never stored. What counts as personal is decided
 * here on the server, where login state and shop pages are known, and
 * passed to the worker in a response header.
 */

defined( 'ABSPATH' ) || exit;

class TKPwa_Service_Worker {

	const QUERY_ARG         = 'tkpwa_sw';
	const OFFLINE_QUERY_ARG = 'tkpwa_offline';
	const BYPASS_HEADER     = 'X-TKPWA-Cache';

	public static function init() {
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'mark_private_response' ), 0 );
	}

	/**
	 * @return string
	 */
	public static function url() {
		return add_query_arg( self::QUERY_ARG, TKPwa_Settings::fingerprint(), home_url( '/' ) );
	}

	/**
	 * @return string
	 */
	public static function offline_url() {
		return add_query_arg( self::OFFLINE_QUERY_ARG, 1, home_url( '/' ) );
	}

	/**
	 * @return string Path the worker controls.
	 */
	public static function scope() {
		return trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	}

	public static function maybe_serve() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only public documents.
		if ( isset( $_GET[ self::QUERY_ARG ] ) ) {
			self::serve_worker();
		}
		if ( isset( $_GET[ self::OFFLINE_QUERY_ARG ] ) ) {
			self::serve_offline_page();
		}
		// phpcs:enable
	}

	/**
	 * Tells the worker not to store this page. Logged-in views, previews and
	 * shop pages carry the header; everything else may be cached.
	 */
	public static function mark_private_response() {
		if ( TKPwa_Integrations::is_private_request() && ! headers_sent() ) {
			header( self::BYPASS_HEADER . ': bypass' );
		}
	}

	private static function serve_worker() {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . self::scope() );
		// Browsers revalidate the worker script themselves; this only stops
		// a proxy holding on to an old copy.
		header( 'Cache-Control: no-cache' );
		header( 'X-Robots-Tag: noindex' );

		$config = array(
			'version'    => TKPwa_Settings::fingerprint(),
			'scope'      => self::scope(),
			'offlineUrl' => self::relative( self::offline_url() ),
			'precache'   => self::offline_asset_urls(),
			'startUrl'   => self::relative( TKPwa_Integrations::home_url( '' ) ),
			'bypass'     => strtolower( self::BYPASS_HEADER ),
			'skipPaths'  => self::excluded_paths(),
			'skipArgs'   => array( 'rest_route', 'preview', 'customize_changeset_uuid', 'add-to-cart', 'wc-ajax', 'remove_item', 'nonce', '_wpnonce', TKPwa_Manifest::QUERY_ARG, self::QUERY_ARG ),
		);

		echo 'self.TKPWA = ' . wp_json_encode( $config, JSON_UNESCAPED_SLASHES ) . ";\n";
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Local file shipped with the plugin.
		readfile( TKPWA_PLUGIN_DIR . 'assets/js/tkpwa-sw.js' );
		exit;
	}

	/**
	 * Path prefixes the worker leaves entirely to the network.
	 *
	 * @return string[]
	 */
	private static function excluded_paths() {
		$admin = (string) wp_parse_url( admin_url(), PHP_URL_PATH );
		$site  = trailingslashit( (string) wp_parse_url( site_url( '/' ), PHP_URL_PATH ) );

		$core = array(
			$admin,
			$site . 'wp-login.php',
			$site . 'wp-json/',
			$site . 'xmlrpc.php',
			$site . 'wp-cron.php',
			$site . 'wp-comments-post.php',
		);
		// With plain permalinks the REST API is "/index.php?rest_route=",
		// caught by the rest_route argument instead; its path must not be
		// excluded, or every /index.php/... page would be.
		if ( ! wp_parse_url( rest_url(), PHP_URL_QUERY ) ) {
			$core[] = (string) wp_parse_url( rest_url(), PHP_URL_PATH );
		}

		$paths = array_merge(
			$core,
			TKPwa_Integrations::private_paths(),
			TKPwa_Settings::exclude_paths()
		);

		/**
		 * Filters the path prefixes the service worker never caches.
		 *
		 * @param string[] $paths Root-relative path prefixes.
		 */
		$paths = apply_filters( 'tkpwa_excluded_paths', $paths );

		return array_values( array_unique( array_filter( array_map( 'strval', $paths ) ) ) );
	}

	/**
	 * Stylesheet and script of the offline page, by handle.
	 *
	 * @return array<string, string> Handle => URL without the version argument.
	 */
	private static function offline_assets() {
		return array(
			'style'  => TKPWA_PLUGIN_URL . 'assets/css/tkpwa-offline.css',
			'script' => TKPWA_PLUGIN_URL . 'assets/js/tkpwa-offline.js',
		);
	}

	/**
	 * The offline page is shown when there is no network, so the worker must
	 * already hold its stylesheet, script and icon. The stylesheet and script
	 * URLs are exactly as WordPress prints them, version argument included,
	 * so the cache matches.
	 *
	 * @return string[] Root-relative URLs. Assets served from another host
	 *                  (a CDN) are left out; the worker only handles its own.
	 */
	private static function offline_asset_urls() {
		$candidates = array();
		foreach ( self::offline_assets() as $url ) {
			$candidates[] = add_query_arg( 'ver', TKPWA_VERSION, $url );
		}
		$icons = TKPwa_Icons::urls();
		if ( ! empty( $icons['any-192'] ) ) {
			$candidates[] = $icons['any-192'];
		}

		$home = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$urls = array();
		foreach ( $candidates as $url ) {
			if ( wp_parse_url( $url, PHP_URL_HOST ) === $home ) {
				$urls[] = self::relative( $url );
			}
		}
		return $urls;
	}

	/**
	 * A standalone page: the theme is not loaded, so it renders the same
	 * whatever the theme does. Its own stylesheet and script are precached by
	 * the worker at install, so the page works without the network.
	 */
	private static function serve_offline_page() {
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );

		$assets = self::offline_assets();
		wp_register_style( 'tkpwa-offline', $assets['style'], array(), TKPWA_VERSION );
		$background = sanitize_hex_color( TKPwa_Settings::background_color() );
		if ( $background ) {
			wp_add_inline_style( 'tkpwa-offline', ':root { --bg: ' . $background . '; }' );
		}
		wp_register_script( 'tkpwa-offline', $assets['script'], array(), TKPWA_VERSION, true );

		$icons = TKPwa_Icons::urls();
		$data  = array(
			'name'       => TKPwa_Settings::app_name(),
			'background' => TKPwa_Settings::background_color(),
			'theme'      => TKPwa_Settings::theme_color(),
			'icon'       => isset( $icons['any-192'] ) ? $icons['any-192'] : '',
			'home'       => TKPwa_Integrations::home_url( '' ),
		);

		include TKPWA_PLUGIN_DIR . 'includes/views/offline.php';
		exit;
	}

	/**
	 * @param string $url Absolute URL.
	 * @return string Path with query.
	 */
	private static function relative( $url ) {
		$parts = wp_parse_url( $url );
		$path  = isset( $parts['path'] ) ? $parts['path'] : '/';
		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}
		return $path;
	}
}
