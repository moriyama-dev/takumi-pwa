<?php
/**
 * Everything the plugin adds to front-end pages: the head tags that make the
 * site installable, the script that registers the service worker, and the
 * app-style interface pieces (install banner, bottom bar, loading screen,
 * pull-to-refresh, scroll progress, offline forms).
 *
 * The interface pieces only appear when the site is running as an installed
 * app, except the install banner, which by definition appears in the
 * browser. The root element gets the class `tkpwa-standalone` before the
 * page paints, so CSS can switch on it without a flash.
 */

defined( 'ABSPATH' ) || exit;

class TKPwa_Frontend {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_head_tags' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_bottom_nav' ) );
		add_shortcode( 'tkpwa_install_button', array( __CLASS__, 'install_button_shortcode' ) );
	}

	public static function print_head_tags() {
		$lang  = TKPwa_Integrations::current_language();
		$icons = TKPwa_Icons::urls();
		$name  = TKPwa_Integrations::translate( 'App short name', TKPwa_Settings::short_name(), $lang );

		printf( '<link rel="manifest" href="%s">' . "\n", esc_url( TKPwa_Manifest::url( $lang ) ) );
		printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( TKPwa_Settings::theme_color() ) );
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		printf( '<meta name="apple-mobile-web-app-title" content="%s">' . "\n", esc_attr( $name ) );
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
		if ( ! empty( $icons['apple-180'] ) ) {
			printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( $icons['apple-180'] ) );
		}

		// Runs before first paint so app-only styles never flash in a browser tab.
		wp_print_inline_script_tag(
			"(function(d){var s=window.matchMedia&&(matchMedia('(display-mode: standalone)').matches||matchMedia('(display-mode: fullscreen)').matches||matchMedia('(display-mode: minimal-ui)').matches);if(s||navigator.standalone){d.documentElement.classList.add('tkpwa-standalone');}})(document);",
			array( 'id' => 'tkpwa-display-mode' )
		);
	}

	public static function enqueue_assets() {
		$settings = TKPwa_Settings::get();
		$icons    = TKPwa_Icons::urls();

		wp_enqueue_style( 'tkpwa-frontend', TKPWA_PLUGIN_URL . 'assets/css/tkpwa-frontend.css', array(), TKPWA_VERSION );
		wp_add_inline_style(
			'tkpwa-frontend',
			sprintf(
				':root{--tkpwa-theme:%1$s;--tkpwa-bg:%2$s;--tkpwa-accent:%3$s;--tkpwa-on-accent:%4$s;}',
				TKPwa_Settings::theme_color(),
				TKPwa_Settings::background_color(),
				self::accent_color(),
				self::ink_on( self::accent_color() )
			)
		);

		wp_enqueue_script( 'tkpwa-frontend', TKPWA_PLUGIN_URL . 'assets/js/tkpwa-frontend.js', array(), TKPWA_VERSION, true );
		wp_localize_script(
			'tkpwa-frontend',
			'tkpwaConfig',
			array(
				'sw'             => TKPwa_Service_Worker::url(),
				'scope'          => TKPwa_Service_Worker::scope(),
				'name'           => TKPwa_Settings::app_name(),
				'icon'           => isset( $icons['any-192'] ) ? $icons['any-192'] : '',
				'installPrompt'  => (bool) $settings['install_prompt'],
				'loadingScreen'  => (bool) $settings['loading_screen'],
				'pullToRefresh'  => (bool) $settings['pull_to_refresh'],
				'scrollProgress' => (bool) $settings['scroll_progress'],
				'offlineForms'   => $settings['offline_forms'] ? TKPwa_Integrations::form_selectors() : array(),
				'i18n'           => array(
					'install'          => __( 'Install', 'takumi-pwa' ),
					/* translators: %s: app name */
					'installTitle'     => sprintf( __( 'Add %s to your home screen', 'takumi-pwa' ), TKPwa_Settings::app_name() ),
					'installText'      => __( 'Open it like an app, even with a weak connection.', 'takumi-pwa' ),
					'iosStep1'         => __( 'Tap the Share button', 'takumi-pwa' ),
					'iosStep2'         => __( 'Choose "Add to Home Screen"', 'takumi-pwa' ),
					'close'            => __( 'Close', 'takumi-pwa' ),
					'pullToRefresh'    => __( 'Pull to refresh', 'takumi-pwa' ),
					'releaseToRefresh' => __( 'Release to refresh', 'takumi-pwa' ),
					'formQueued'       => __( 'You are offline. Your message has been saved and will be sent automatically when you are back online.', 'takumi-pwa' ),
					'formSent'         => __( 'Your saved message has been sent.', 'takumi-pwa' ),
					'formHasFiles'     => __( 'You are offline. Messages with attachments cannot be saved; please try again when you are back online.', 'takumi-pwa' ),
					'loading'          => __( 'Loading', 'takumi-pwa' ),
				),
			)
		);
	}

	/**
	 * The bar is printed on every page but only shown by CSS in app mode.
	 */
	public static function print_bottom_nav() {
		if ( ! TKPwa_Settings::value( 'bottom_nav' ) ) {
			return;
		}
		$items = TKPwa_Menu::items( 5 );
		if ( count( $items ) < 2 ) {
			return;
		}

		$current = TKPwa_Menu::key( self::current_uri() );

		echo '<nav class="tkpwa-bottom-nav" aria-label="' . esc_attr__( 'App navigation', 'takumi-pwa' ) . '"><ul>';
		foreach ( $items as $item ) {
			$active = TKPwa_Menu::key( $item['url'] ) === $current;
			printf(
				'<li><a href="%1$s"%2$s><span class="tkpwa-bottom-nav__icon" aria-hidden="true">%3$s</span><span class="tkpwa-bottom-nav__label">%4$s</span></a></li>',
				esc_url( $item['url'] ),
				$active ? ' aria-current="page"' : '',
				self::icon_for( $item ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG markup from self::icon_for().
				esc_html( $item['title'] )
			);
		}
		echo '</ul></nav>';
	}

	/**
	 * [tkpwa_install_button label="..."] - a button that opens the install
	 * dialog, or the iPhone instructions. Hidden when already installed.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function install_button_shortcode( $atts ) {
		$atts = shortcode_atts(
			array( 'label' => __( 'Install the app', 'takumi-pwa' ) ),
			$atts,
			'tkpwa_install_button'
		);
		return sprintf(
			'<button type="button" class="tkpwa-install-button wp-element-button" hidden>%s</button>',
			esc_html( $atts['label'] )
		);
	}

	/**
	 * An icon guessed from the link: home, search, contact, shop, cart,
	 * account, blog. Anything else gets a plain page icon. The menu needs
	 * no extra configuration this way.
	 *
	 * @param array $item Menu link.
	 * @return string SVG markup.
	 */
	private static function icon_for( $item ) {
		$paths = array(
			'home'    => 'M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z',
			'search'  => 'M10.5 3a7.5 7.5 0 0 1 5.9 12.1l4.8 4.8-1.4 1.4-4.8-4.8A7.5 7.5 0 1 1 10.5 3zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11z',
			'mail'    => 'M3 5h18v14H3zm2 2v.4l7 4.7 7-4.7V7zm14 2.8-7 4.6-7-4.6V17h14z',
			'cart'    => 'M3 4h2.4l2.2 10.2h10.6L20.6 7H7.6l-.4-2H22l-3 11.2H6L3.8 6H3zm5 15.5a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0zm8 0a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0z',
			'user'    => 'M12 3a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9zm0 11c4.4 0 8 2.2 8 5v2H4v-2c0-2.8 3.6-5 8-5z',
			'article' => 'M5 3h14v18H5zm2 2v14h10V5zm2 2h6v2H9zm0 4h6v2H9zm0 4h4v2H9z',
			'page'    => 'M6 2h8l5 5v15H6zm7 1.5V8h4.5zM8 12h8v1.5H8zm0 3h8v1.5H8z',
		);

		$haystack = strtolower( $item['title'] . ' ' . (string) wp_parse_url( $item['url'], PHP_URL_PATH ) );

		$rules = array(
			'search'  => array( 'search', '検索' ),
			'mail'    => array( 'contact', 'お問い合わせ', '問い合わせ', 'mail' ),
			'cart'    => array( 'cart', 'shop', 'store', 'カート', 'ショップ' ),
			'user'    => array( 'account', 'login', 'profile', 'マイページ', 'アカウント' ),
			'article' => array( 'blog', 'news', 'post', 'ブログ', 'ニュース', 'お知らせ' ),
		);

		$key = 'page';
		if ( TKPwa_Menu::is_home( $item['url'] ) ) {
			$key = 'home';
		} else {
			foreach ( $rules as $candidate => $words ) {
				foreach ( $words as $word ) {
					if ( false !== strpos( $haystack, $word ) ) {
						$key = $candidate;
						break 2;
					}
				}
			}
		}

		return '<svg viewBox="0 0 24 24" width="24" height="24" focusable="false"><path fill="currentColor" d="' . $paths[ $key ] . '"/></svg>';
	}

	/**
	 * A color that shows up against the page background: the theme color,
	 * unless it is too close to the background (a white theme color on a
	 * white site is common), in which case near-black.
	 *
	 * @return string Hex color.
	 */
	public static function accent_color() {
		$theme = TKPwa_Settings::theme_color();
		$bg    = TKPwa_Settings::background_color();
		$ratio = self::contrast( $theme, $bg );
		return $ratio >= 3 ? $theme : ( self::luminance( $bg ) > 0.4 ? '#1d2327' : '#ffffff' );
	}

	/**
	 * @param string $hex Background color.
	 * @return string Readable text color on it.
	 */
	public static function ink_on( $hex ) {
		return self::luminance( $hex ) > 0.4 ? '#1d2327' : '#ffffff';
	}

	/**
	 * @param string $a Hex color.
	 * @param string $b Hex color.
	 * @return float WCAG contrast ratio.
	 */
	private static function contrast( $a, $b ) {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * @param string $hex Hex color.
	 * @return float WCAG relative luminance.
	 */
	private static function luminance( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return 1.0;
		}
		$channels = array();
		foreach ( array( 0, 2, 4 ) as $offset ) {
			$c          = hexdec( substr( $hex, $offset, 2 ) ) / 255;
			$channels[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * @return string Path and query of the current request.
	 */
	private static function current_uri() {
		return isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	}
}
