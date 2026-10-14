<?php
/**
 * Stores every setting in one option and fills the gaps from the site itself.
 *
 * An empty value means "work it out from the site": the app name follows the
 * site title, the colors follow the theme, the icon follows the site icon.
 * That way a fresh install needs no input at all, and a later change to the
 * site title or theme carries through without a trip to this plugin's screen.
 */

defined( 'ABSPATH' ) || exit;

class TKPwa_Settings {

	const OPTION = 'tkpwa_settings';

	const DISPLAY_MODES = array( 'standalone', 'minimal-ui', 'fullscreen' );

	/**
	 * The keys every other class may rely on being present.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Identity. Empty = taken from the site.
			'app_name'         => '',
			'short_name'       => '',
			'description'      => '',
			'theme_color'      => '',
			'background_color' => '',
			'icon_id'          => 0,
			'display'          => 'standalone',

			// Look and feel.
			'install_prompt'   => 1,
			'loading_screen'   => 1,
			'shortcuts'        => 1,
			'bottom_nav'       => 0,
			'bottom_nav_menu'  => 0,
			'pull_to_refresh'  => 0,
			'scroll_progress'  => 0,

			// Under the hood.
			'offline_forms'    => 0,
			'share_target'     => 0,
			'exclude_paths'    => '',
		);
	}

	/**
	 * Stored values merged over the defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );
	}

	/**
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function value( $key ) {
		$settings = self::get();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * The name shown under the icon on the home screen.
	 *
	 * @return string
	 */
	public static function app_name() {
		$name = self::value( 'app_name' );
		if ( '' === $name ) {
			$name = get_bloginfo( 'name' );
		}
		if ( '' === $name ) {
			$name = wp_parse_url( home_url(), PHP_URL_HOST );
		}
		return (string) $name;
	}

	/**
	 * Launchers truncate anything past about 12 characters, so an automatic
	 * short name cuts the full name rather than letting the launcher do it
	 * mid-word.
	 *
	 * @return string
	 */
	public static function short_name() {
		$short = self::value( 'short_name' );
		if ( '' !== $short ) {
			return $short;
		}
		$name = self::app_name();
		if ( self::length( $name ) <= 12 ) {
			return $name;
		}
		$first_word = strtok( $name, ' ' );
		if ( false !== $first_word && self::length( $first_word ) <= 12 ) {
			return $first_word;
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 12 ) : substr( $name, 0, 12 );
	}

	/**
	 * @return string
	 */
	public static function description() {
		$description = self::value( 'description' );
		if ( '' === $description ) {
			$description = get_bloginfo( 'description' );
		}
		return (string) $description;
	}

	/**
	 * The color of the browser bar and status bar.
	 *
	 * @return string Hex color.
	 */
	public static function theme_color() {
		$color = self::value( 'theme_color' );
		return '' !== $color ? $color : self::site_background_color();
	}

	/**
	 * The color behind the splash screen while the app starts.
	 *
	 * @return string Hex color.
	 */
	public static function background_color() {
		$color = self::value( 'background_color' );
		return '' !== $color ? $color : self::site_background_color();
	}

	/**
	 * The page background the theme declares, so the splash screen and status
	 * bar match what loads underneath them instead of flashing white.
	 *
	 * Block themes expose it through global styles; classic themes through
	 * the custom-background theme mod. Anything that is not a plain hex
	 * value (a CSS variable, a gradient) is no use in a manifest, so it
	 * falls back to white.
	 *
	 * @return string Hex color.
	 */
	public static function site_background_color() {
		$candidates = array();

		if ( function_exists( 'wp_get_global_styles' ) ) {
			$styles = wp_get_global_styles( array( 'color' ) );
			if ( ! empty( $styles['background'] ) && is_string( $styles['background'] ) ) {
				$candidates[] = self::resolve_preset( $styles['background'] );
			}
		}

		$mod = get_theme_mod( 'background_color' );
		if ( $mod ) {
			$candidates[] = '#' . ltrim( $mod, '#' );
		}

		foreach ( $candidates as $candidate ) {
			$hex = sanitize_hex_color( $candidate );
			if ( $hex ) {
				return $hex;
			}
		}
		return '#ffffff';
	}

	/**
	 * Turns var(--wp--preset--color--base) into the hex value of that preset.
	 *
	 * @param string $value Raw value from global styles.
	 * @return string
	 */
	private static function resolve_preset( $value ) {
		if ( ! preg_match( '/^var\(--wp--preset--color--([a-z0-9-]+)\)$/', $value, $m ) ) {
			return $value;
		}
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return '';
		}
		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		foreach ( array( 'custom', 'theme', 'default' ) as $origin ) {
			if ( empty( $palette[ $origin ] ) || ! is_array( $palette[ $origin ] ) ) {
				continue;
			}
			foreach ( $palette[ $origin ] as $entry ) {
				if ( isset( $entry['slug'], $entry['color'] ) && $entry['slug'] === $m[1] ) {
					return $entry['color'];
				}
			}
		}
		return '';
	}

	/**
	 * Paths the service worker must never serve from cache, one per line.
	 *
	 * @return string[]
	 */
	public static function exclude_paths() {
		$lines = preg_split( '/\r\n|\r|\n/', (string) self::value( 'exclude_paths' ) );
		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}

	/**
	 * A short fingerprint of everything that changes the generated files.
	 * The service worker cache name includes it, so saving the settings
	 * retires the old cache on every device.
	 *
	 * @return string
	 */
	public static function fingerprint() {
		return substr( md5( TKPWA_VERSION . wp_json_encode( self::get() ) . get_option( 'tkpwa_icons_hash', '' ) ), 0, 10 );
	}

	/**
	 * register_setting() sanitize callback.
	 *
	 * @param mixed $input Raw form values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$clean    = array();

		foreach ( array( 'app_name', 'short_name', 'description' ) as $key ) {
			$clean[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
		}

		foreach ( array( 'theme_color', 'background_color' ) as $key ) {
			$raw           = isset( $input[ $key ] ) ? trim( $input[ $key ] ) : '';
			$clean[ $key ] = '' === $raw ? '' : (string) sanitize_hex_color( $raw );
		}

		$clean['icon_id'] = isset( $input['icon_id'] ) ? absint( $input['icon_id'] ) : 0;
		if ( $clean['icon_id'] && ! wp_attachment_is_image( $clean['icon_id'] ) ) {
			$clean['icon_id'] = 0;
		}

		$display          = isset( $input['display'] ) ? sanitize_key( $input['display'] ) : '';
		$clean['display'] = in_array( $display, self::DISPLAY_MODES, true ) ? $display : $defaults['display'];

		foreach ( array( 'install_prompt', 'loading_screen', 'shortcuts', 'bottom_nav', 'pull_to_refresh', 'scroll_progress', 'offline_forms', 'share_target' ) as $key ) {
			$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$clean['bottom_nav_menu'] = isset( $input['bottom_nav_menu'] ) ? absint( $input['bottom_nav_menu'] ) : 0;

		$paths = isset( $input['exclude_paths'] ) ? preg_split( '/\r\n|\r|\n/', (string) $input['exclude_paths'] ) : array();
		$paths = array_filter( array_map( 'sanitize_text_field', $paths ) );
		$paths = array_map(
			static function ( $path ) {
				return '/' . ltrim( $path, '/' );
			},
			$paths
		);
		$clean['exclude_paths'] = implode( "\n", array_unique( $paths ) );

		return $clean;
	}

	/**
	 * @param string $text Text to measure.
	 * @return int
	 */
	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}
}
