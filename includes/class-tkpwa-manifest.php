<?php
/**
 * Serves the web app manifest.
 *
 * The manifest is generated on request from the settings rather than written
 * to disk, so there is no file to go stale and nothing that needs write
 * access to the plugin or site root. It is served from a query argument on
 * the home URL, which avoids rewrite rules and therefore works the moment
 * the plugin is activated, on any permalink setting.
 */

defined( 'ABSPATH' ) || exit;

class TKPwa_Manifest {

	const QUERY_ARG = 'tkpwa_manifest';

	public static function init() {
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ), 0 );
	}

	/**
	 * @param string $lang Language slug, '' for the default.
	 * @return string
	 */
	public static function url( $lang = '' ) {
		$args = array( self::QUERY_ARG => 1 );
		if ( '' !== $lang ) {
			$args['lang'] = $lang;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	public static function maybe_serve() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public document.
		if ( ! isset( $_GET[ self::QUERY_ARG ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public document.
		$lang = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '';
		$lang = TKPwa_Integrations::known_language( $lang );

		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		// Short enough that a settings change shows up on the next launch,
		// long enough that each page view does not refetch it.
		header( 'Cache-Control: public, max-age=300' );
		header( 'X-Robots-Tag: noindex' );

		echo wp_json_encode( self::build( $lang ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * @param string $lang Language slug, '' for the default.
	 * @return array
	 */
	public static function build( $lang = '' ) {
		$start = TKPwa_Integrations::home_url( $lang );
		$scope = trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

		$manifest = array(
			'id'               => $scope,
			'name'             => TKPwa_Integrations::translate( 'App name', TKPwa_Settings::app_name(), $lang ),
			'short_name'       => TKPwa_Integrations::translate( 'App short name', TKPwa_Settings::short_name(), $lang ),
			'description'      => TKPwa_Integrations::translate( 'App description', TKPwa_Settings::description(), $lang ),
			'lang'             => TKPwa_Integrations::bcp47( $lang ),
			'dir'              => is_rtl() ? 'rtl' : 'ltr',
			'start_url'        => self::relative( $start ),
			'scope'            => $scope,
			'display'          => TKPwa_Settings::value( 'display' ),
			'background_color' => TKPwa_Settings::background_color(),
			'theme_color'      => TKPwa_Settings::theme_color(),
			'icons'            => self::icons(),
		);

		if ( '' === $manifest['description'] ) {
			unset( $manifest['description'] );
		}

		if ( TKPwa_Settings::value( 'shortcuts' ) ) {
			$shortcuts = self::shortcuts( $start );
			if ( $shortcuts ) {
				$manifest['shortcuts'] = $shortcuts;
			}
		}

		if ( TKPwa_Settings::value( 'share_target' ) ) {
			$manifest['share_target'] = TKPwa_Share_Target::manifest_entry();
		}

		/**
		 * Filters the generated web app manifest.
		 *
		 * @param array  $manifest Manifest members.
		 * @param string $lang     Language slug, '' on a single-language site.
		 */
		return apply_filters( 'tkpwa_manifest', $manifest, $lang );
	}

	/**
	 * @return array[]
	 */
	private static function icons() {
		$urls  = TKPwa_Icons::urls();
		$icons = array();
		$plan  = array(
			'any-192'      => array( '192x192', 'any' ),
			'any-512'      => array( '512x512', 'any' ),
			'maskable-192' => array( '192x192', 'maskable' ),
			'maskable-512' => array( '512x512', 'maskable' ),
		);
		foreach ( $plan as $role => $spec ) {
			if ( empty( $urls[ $role ] ) ) {
				continue;
			}
			$icons[] = array(
				'src'     => $urls[ $role ],
				'sizes'   => $spec[0],
				'type'    => 'image/png',
				'purpose' => $spec[1],
			);
		}
		return $icons;
	}

	/**
	 * The first few menu links, skipping the front page since the app
	 * already opens there.
	 *
	 * @param string $start Start URL.
	 * @return array[]
	 */
	private static function shortcuts( $start ) {
		$urls      = TKPwa_Icons::urls();
		$start_key = TKPwa_Menu::key( $start );
		$shortcuts = array();

		foreach ( TKPwa_Menu::items( 6 ) as $item ) {
			if ( TKPwa_Menu::key( $item['url'] ) === $start_key || TKPwa_Menu::is_home( $item['url'] ) ) {
				continue;
			}
			$shortcut = array(
				'name' => $item['title'],
				'url'  => self::relative( $item['url'] ),
			);
			if ( ! empty( $urls['any-192'] ) ) {
				$shortcut['icons'] = array(
					array(
						'src'   => $urls['any-192'],
						'sizes' => '192x192',
						'type'  => 'image/png',
					),
				);
			}
			$shortcuts[] = $shortcut;
			if ( count( $shortcuts ) >= 4 ) {
				break;
			}
		}
		return $shortcuts;
	}

	/**
	 * Root-relative form of a same-site URL, so the manifest stays valid when
	 * the site moves between http and https or between hostnames.
	 *
	 * @param string $url Absolute or relative URL.
	 * @return string
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
