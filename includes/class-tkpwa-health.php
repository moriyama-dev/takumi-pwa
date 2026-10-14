<?php
/**
 * The status checklist on the settings screen.
 *
 * Other PWA plugins leave the owner guessing whether anything works. This
 * runs the checks a browser would make before offering to install the site,
 * and says in plain words what to do about each one that fails.
 */

defined( 'ABSPATH' ) || exit;

class TKPwa_Health {

	const TRANSIENT = 'tkpwa_loopback';

	/**
	 * @param bool $fresh Skip the cached loopback result.
	 * @return array[] Each: array( 'label', 'status' => ok|warn|fail|info, 'message' ).
	 */
	public static function checks( $fresh = false ) {
		$checks = array();

		$checks[] = self::check_https();
		$checks[] = self::check_icons();
		$checks[] = self::check_loopback( $fresh );

		$conflicts = TKPwa_Integrations::conflicting_plugins();
		$checks[]  = $conflicts
			? array(
				'label'   => __( 'Other PWA plugins', 'takumi-pwa' ),
				'status'  => 'fail',
				/* translators: %s: comma-separated plugin names */
				'message' => sprintf( __( 'Deactivate %s. A site can run only one service worker, so two PWA or push notification plugins will break each other.', 'takumi-pwa' ), implode( ', ', $conflicts ) ),
			)
			: array(
				'label'   => __( 'Other PWA plugins', 'takumi-pwa' ),
				'status'  => 'ok',
				'message' => __( 'None found.', 'takumi-pwa' ),
			);

		$forms = TKPwa_Integrations::form_plugins();
		if ( $forms ) {
			$checks[] = array(
				'label'   => __( 'Form plugins', 'takumi-pwa' ),
				'status'  => 'info',
				'message' => TKPwa_Settings::value( 'offline_forms' )
					/* translators: %s: comma-separated plugin names */
					? sprintf( __( 'Offline sending is on for %s.', 'takumi-pwa' ), implode( ', ', $forms ) )
					/* translators: %s: comma-separated plugin names */
					: sprintf( __( 'Found %s. Turn on "Offline forms" below to keep messages sent without a connection.', 'takumi-pwa' ), implode( ', ', $forms ) ),
			);
		}

		$multilingual = TKPwa_Integrations::multilingual_plugin();
		if ( $multilingual ) {
			$checks[] = array(
				'label'   => __( 'Languages', 'takumi-pwa' ),
				'status'  => 'info',
				/* translators: %s: Polylang or WPML */
				'message' => sprintf( __( '%s found. Each language gets its own manifest; translate the app name under its string translations.', 'takumi-pwa' ), 'polylang' === $multilingual ? 'Polylang' : 'WPML' ),
			);
		}

		return $checks;
	}

	/**
	 * @return bool True when nothing is in the fail state.
	 */
	public static function all_passing() {
		foreach ( self::checks() as $check ) {
			if ( 'fail' === $check['status'] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return array
	 */
	private static function check_https() {
		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$local = in_array( $host, array( 'localhost', '127.0.0.1', '[::1]' ), true );

		if ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
			return array(
				'label'   => 'HTTPS',
				'status'  => 'ok',
				'message' => __( 'The site is served over HTTPS.', 'takumi-pwa' ),
			);
		}
		if ( $local ) {
			return array(
				'label'   => 'HTTPS',
				'status'  => 'warn',
				'message' => __( 'Browsers allow apps on localhost without HTTPS, but your live site will need it.', 'takumi-pwa' ),
			);
		}
		return array(
			'label'   => 'HTTPS',
			'status'  => 'fail',
			'message' => __( 'The site address starts with http://. Browsers only install apps from https:// sites. Ask your host to enable a free SSL certificate, then change the site address under Settings > General.', 'takumi-pwa' ),
		);
	}

	/**
	 * @return array
	 */
	private static function check_icons() {
		$label = __( 'App icon', 'takumi-pwa' );
		$urls  = TKPwa_Icons::urls();

		if ( empty( $urls['any-512'] ) ) {
			return array(
				'label'   => $label,
				'status'  => 'fail',
				'message' => __( 'Icons could not be generated. The GD image extension for PHP may be missing; ask your host.', 'takumi-pwa' ),
			);
		}
		if ( TKPwa_Icons::is_placeholder() ) {
			return array(
				'label'   => $label,
				'status'  => 'warn',
				'message' => __( 'A placeholder icon is in use. Choose an image below, or set a Site Icon, so the app shows your logo.', 'takumi-pwa' ),
			);
		}

		$meta = wp_get_attachment_metadata( TKPwa_Icons::source_id() );
		if ( is_array( $meta ) && isset( $meta['width'], $meta['height'] ) && min( (int) $meta['width'], (int) $meta['height'] ) < 512 ) {
			return array(
				'label'   => $label,
				'status'  => 'warn',
				/* translators: 1: width in pixels, 2: height in pixels */
				'message' => sprintf( __( 'The icon image is %1$d x %2$d pixels and will look blurry when enlarged. Use one at least 512 x 512.', 'takumi-pwa' ), (int) $meta['width'], (int) $meta['height'] ),
			);
		}

		return array(
			'label'   => $label,
			'status'  => 'ok',
			'message' => __( 'All sizes generated, including the Android adaptive icon and the iPhone icon.', 'takumi-pwa' ),
		);
	}

	/**
	 * Fetches the manifest and the service worker the way a visitor's
	 * browser would. Cached briefly because it is two HTTP requests.
	 *
	 * @param bool $fresh Skip the cache.
	 * @return array
	 */
	private static function check_loopback( $fresh ) {
		$label  = __( 'Manifest and service worker', 'takumi-pwa' );
		$result = $fresh ? false : get_transient( self::TRANSIENT );

		if ( false === $result ) {
			$result = array(
				'manifest' => self::probe( TKPwa_Manifest::url(), 'json' ),
				'worker'   => self::probe( TKPwa_Service_Worker::url(), 'javascript' ),
			);
			set_transient( self::TRANSIENT, $result, 10 * MINUTE_IN_SECONDS );
		}

		$failed = array();
		foreach ( array( 'manifest', 'worker' ) as $key ) {
			if ( true !== $result[ $key ] ) {
				$failed[] = $result[ $key ];
			}
		}

		if ( ! $failed ) {
			return array(
				'label'   => $label,
				'status'  => 'ok',
				'message' => __( 'Both load correctly for visitors.', 'takumi-pwa' ),
			);
		}

		return array(
			'label'   => $label,
			'status'  => 'warn',
			'message' => implode( ' ', array_unique( $failed ) ),
		);
	}

	/**
	 * @param string $url      Address to fetch.
	 * @param string $expected Fragment the Content-Type must contain.
	 * @return true|string True on success, otherwise an explanation.
	 */
	private static function probe( $url, $expected ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				// The site checking itself; a self-signed certificate on a
				// staging copy should not make every check fail.
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter, applied as core does for loopbacks.
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log( 'Loopback to ' . $url . ' failed: ' . $response->get_error_message() );
			/* translators: %s: error message */
			return sprintf( __( 'The site could not reach itself to check (%s). This is often a hosting firewall; the app may still work for visitors.', 'takumi-pwa' ), $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$type = (string) wp_remote_retrieve_header( $response, 'content-type' );

		if ( $code >= 300 && $code < 400 ) {
			self::log( 'Loopback to ' . $url . ' redirected (' . $code . ') to ' . wp_remote_retrieve_header( $response, 'location' ) );
			return __( 'Visitors who are not logged in are redirected away from the site (for example by a maintenance or private-site plugin), so they cannot install it.', 'takumi-pwa' );
		}
		if ( 200 !== $code || false === strpos( $type, $expected ) ) {
			self::log( 'Loopback to ' . $url . ' returned ' . $code . ' ' . $type );
			/* translators: %d: HTTP status code */
			return sprintf( __( 'The app files returned an unexpected response (HTTP %d). A caching or security plugin may be rewriting them.', 'takumi-pwa' ), $code );
		}
		return true;
	}

	/**
	 * @param string $message Diagnostic.
	 */
	private static function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[takumi-pwa] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only diagnostics.
		}
	}
}
