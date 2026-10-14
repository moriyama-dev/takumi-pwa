<?php
/**
 * Everything that depends on another plugin being present.
 *
 * Each check is wrapped in function_exists()/class_exists()/defined(), so a
 * site without the other plugin never reaches its functions. Nothing here
 * needs configuring: the plugins are detected and handled.
 */

defined( 'ABSPATH' ) || exit;

class TKPwa_Integrations {

	/**
	 * Plugins that register a service worker or a web app manifest of their
	 * own. A page can be controlled by only one service worker per scope, so
	 * running two of these means one silently loses.
	 *
	 * Keyed by plugin directory.
	 */
	const CONFLICTS = array(
		'super-progressive-web-apps' => 'SuperPWA',
		'pwa-for-wp'                 => 'PWA for WP',
		'pwa'                        => 'PWA',
		'daftplug-progressify'       => 'Progressify',
		'progressier'                => 'Progressier',
		'onesignal-free-web-push-notifications' => 'OneSignal',
		'webpushr-web-push-notifications'       => 'Webpushr',
		'pushengage'                 => 'PushEngage',
		'perfecty-push-notifications' => 'Perfecty Push',
		'wp-web-push'                => 'Web Push',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_strings' ), 20 );
	}

	/* ------------------------------------------------------------------
	 * Multilingual
	 * ------------------------------------------------------------------ */

	/**
	 * @return string 'polylang', 'wpml' or ''.
	 */
	public static function multilingual_plugin() {
		if ( function_exists( 'pll_current_language' ) && function_exists( 'pll_languages_list' ) ) {
			return 'polylang';
		}
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && has_filter( 'wpml_current_language' ) ) {
			return 'wpml';
		}
		return '';
	}

	/**
	 * Language slug of the current request, '' on a single-language site.
	 *
	 * @return string
	 */
	public static function current_language() {
		switch ( self::multilingual_plugin() ) {
			case 'polylang':
				$lang = pll_current_language( 'slug' );
				return $lang ? (string) $lang : '';
			case 'wpml':
				$lang = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
				return $lang ? (string) $lang : '';
		}
		return '';
	}

	/**
	 * Validates a language slug taken from a request.
	 *
	 * @param string $lang Candidate slug.
	 * @return string The slug when the site has that language, otherwise ''.
	 */
	public static function known_language( $lang ) {
		if ( '' === $lang ) {
			return '';
		}
		switch ( self::multilingual_plugin() ) {
			case 'polylang':
				return in_array( $lang, (array) pll_languages_list( array( 'fields' => 'slug' ) ), true ) ? $lang : '';
			case 'wpml':
				$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
				return is_array( $languages ) && isset( $languages[ $lang ] ) ? $lang : '';
		}
		return '';
	}

	/**
	 * The front page in a given language.
	 *
	 * @param string $lang Language slug.
	 * @return string
	 */
	public static function home_url( $lang ) {
		if ( '' !== $lang ) {
			switch ( self::multilingual_plugin() ) {
				case 'polylang':
					if ( function_exists( 'pll_home_url' ) ) {
						return pll_home_url( $lang );
					}
					break;
				case 'wpml':
					return apply_filters( 'wpml_permalink', home_url( '/' ), $lang, true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
			}
		}
		return home_url( '/' );
	}

	/**
	 * Locale code for the manifest's "lang" member.
	 *
	 * @param string $lang Language slug.
	 * @return string BCP 47 tag.
	 */
	public static function bcp47( $lang ) {
		$locale = '';
		if ( '' !== $lang && 'polylang' === self::multilingual_plugin() && function_exists( 'PLL' ) ) {
			$language = PLL()->model->get_language( $lang );
			$locale   = $language ? $language->locale : '';
		} elseif ( '' !== $lang && 'wpml' === self::multilingual_plugin() ) {
			$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
			$locale    = isset( $languages[ $lang ]['default_locale'] ) ? $languages[ $lang ]['default_locale'] : '';
		}
		if ( '' === $locale ) {
			$locale = get_locale();
		}
		return str_replace( '_', '-', $locale );
	}

	/**
	 * Exposes the app name and description to the multilingual plugin's
	 * string translation screen, so they are translated where everything
	 * else on the site is.
	 */
	public static function register_strings() {
		$strings = array(
			'App name'        => TKPwa_Settings::app_name(),
			'App short name'  => TKPwa_Settings::short_name(),
			'App description' => TKPwa_Settings::description(),
		);
		foreach ( $strings as $label => $value ) {
			if ( '' === $value ) {
				continue;
			}
			if ( function_exists( 'pll_register_string' ) ) {
				pll_register_string( $label, $value, 'Takumi PWA' );
			}
			do_action( 'wpml_register_single_string', 'takumi-pwa', $label, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
		}
	}

	/**
	 * @param string $label Registered label.
	 * @param string $value Source text.
	 * @param string $lang  Language slug.
	 * @return string
	 */
	public static function translate( $label, $value, $lang ) {
		if ( '' === $lang || '' === $value ) {
			return $value;
		}
		switch ( self::multilingual_plugin() ) {
			case 'polylang':
				return function_exists( 'pll_translate_string' ) ? pll_translate_string( $value, $lang ) : $value;
			case 'wpml':
				return apply_filters( 'wpml_translate_single_string', $value, 'takumi-pwa', $label, $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
		}
		return $value;
	}

	/* ------------------------------------------------------------------
	 * Forms
	 * ------------------------------------------------------------------ */

	/**
	 * Form plugins whose submissions can be held while offline.
	 *
	 * @return array Map of key => display name. Keys double as the CSS hook
	 *               the front-end script uses to find the forms.
	 */
	public static function form_plugins() {
		$found = array();
		if ( defined( 'WPCF7_VERSION' ) ) {
			$found['cf7'] = 'Contact Form 7';
		}
		if ( function_exists( 'wpforms' ) ) {
			$found['wpforms'] = 'WPForms';
		}
		if ( class_exists( 'GFForms' ) ) {
			$found['gravityforms'] = 'Gravity Forms';
		}
		return $found;
	}

	/**
	 * CSS selectors for the forms of the detected plugins.
	 *
	 * @return string[]
	 */
	public static function form_selectors() {
		$map = array(
			'cf7'          => 'form.wpcf7-form',
			'wpforms'      => 'form.wpforms-form',
			'gravityforms' => '.gform_wrapper form',
		);
		return array_values( array_intersect_key( $map, self::form_plugins() ) );
	}

	/* ------------------------------------------------------------------
	 * Pages that must never come from cache
	 * ------------------------------------------------------------------ */

	/**
	 * Paths of shop pages whose content depends on the visitor's session.
	 *
	 * @return string[]
	 */
	public static function private_paths() {
		$paths = array();

		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
				$id = wc_get_page_id( $page );
				if ( $id > 0 ) {
					$paths[] = wp_parse_url( get_permalink( $id ), PHP_URL_PATH );
				}
			}
		}

		if ( function_exists( 'edd_get_option' ) ) {
			foreach ( array( 'purchase_page', 'success_page', 'purchase_history_page' ) as $option ) {
				$id = (int) edd_get_option( $option, 0 );
				if ( $id > 0 ) {
					$paths[] = wp_parse_url( get_permalink( $id ), PHP_URL_PATH );
				}
			}
		}

		return array_values( array_filter( array_unique( $paths ) ) );
	}

	/**
	 * Whether the current front-end request shows something personal.
	 *
	 * @return bool
	 */
	public static function is_private_request() {
		if ( is_user_logged_in() || is_preview() || is_customize_preview() ) {
			return true;
		}
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return true;
		}
		if ( function_exists( 'edd_is_checkout' ) && edd_is_checkout() ) {
			return true;
		}
		return false;
	}

	/* ------------------------------------------------------------------
	 * Conflicts
	 * ------------------------------------------------------------------ */

	/**
	 * Active plugins that would fight this one over the service worker.
	 *
	 * @return string[] Display names.
	 */
	public static function conflicting_plugins() {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		$found = array();
		foreach ( $active as $basename ) {
			$dir = strtok( $basename, '/' );
			if ( isset( self::CONFLICTS[ $dir ] ) ) {
				$found[] = self::CONFLICTS[ $dir ];
			}
		}
		return array_values( array_unique( $found ) );
	}
}
