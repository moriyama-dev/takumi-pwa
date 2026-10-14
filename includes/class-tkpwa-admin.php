<?php
/**
 * The plugin's single admin screen: status checklist, install QR code and
 * settings.
 *
 * Every setting already has a working value on activation, so the screen
 * leads with the checklist - "is it working?" - and keeps the settings
 * below it, with the rarely needed ones folded away under Advanced.
 */

defined( 'ABSPATH' ) || exit;

class TKPwa_Admin {

	const OPTION_GROUP = 'tkpwa_settings_group';
	const PAGE_SLUG    = 'takumi-pwa';

	private static $page_hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
		add_action( 'admin_post_tkpwa_recheck', array( __CLASS__, 'handle_recheck' ) );
		add_action( 'update_option_' . TKPwa_Settings::OPTION, array( __CLASS__, 'forget_checks' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TKPWA_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * A top-level entry, like the other Takumi plugins. The position is a
	 * fraction because two plugins asking for the same integer collide and
	 * WordPress silently drops one of the menus.
	 */
	public static function register_menu() {
		self::$page_hook = add_menu_page(
			__( 'Takumi PWA', 'takumi-pwa' ),
			__( 'Takumi PWA', 'takumi-pwa' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-smartphone',
			80.7
		);
	}

	/**
	 * Not menu_page_url(): admin-post.php never fires admin_menu, so that
	 * function returns an empty string on the requests that redirect here.
	 *
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'takumi-pwa' ) . '</a>' );
		return $links;
	}

	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			TKPwa_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'TKPwa_Settings', 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public static function forget_checks() {
		delete_transient( TKPwa_Health::TRANSIENT );
	}

	/**
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( $hook_suffix !== self::$page_hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'tkpwa-admin', TKPWA_PLUGIN_URL . 'admin/css/tkpwa-admin.css', array(), TKPWA_VERSION );

		wp_register_script( 'tkpwa-qrcode', TKPWA_PLUGIN_URL . 'admin/js/vendor/qrcode-generator.js', array(), '1.4.4', true );
		wp_enqueue_script( 'tkpwa-admin', TKPWA_PLUGIN_URL . 'admin/js/tkpwa-admin.js', array( 'jquery', 'wp-color-picker', 'tkpwa-qrcode' ), TKPWA_VERSION, true );
		wp_localize_script(
			'tkpwa-admin',
			'tkpwaAdmin',
			array(
				'chooseIcon' => __( 'Choose app icon', 'takumi-pwa' ),
				'useIcon'    => __( 'Use this image', 'takumi-pwa' ),
				'fileName'   => sanitize_file_name( TKPwa_Settings::short_name() ) . '-qr.png',
			)
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = TKPwa_Settings::get();
		$checks   = TKPwa_Health::checks();
		$icons    = TKPwa_Icons::urls();
		$menus    = wp_get_nav_menus();
		$forms    = TKPwa_Integrations::form_plugins();

		include TKPWA_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Runs the loopback checks again on request rather than waiting for the
	 * cached result to expire.
	 */
	public static function handle_recheck() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'takumi-pwa' ), 403 );
		}
		check_admin_referer( 'tkpwa_recheck' );

		TKPwa_Icons::regenerate();
		TKPwa_Health::checks( true );

		wp_safe_redirect( add_query_arg( 'tkpwa_rechecked', 1, self::page_url() ) );
		exit;
	}

	public static function render_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();

		// Once, right after activation: say it is already working and where to look.
		if ( get_transient( 'tkpwa_activated' ) ) {
			delete_transient( 'tkpwa_activated' );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Takumi PWA is active. Your site can now be installed as an app - no setup needed.', 'takumi-pwa' ),
				esc_url( self::page_url() ),
				esc_html__( 'Check the status', 'takumi-pwa' )
			);
		}

		// A competing service worker breaks both plugins, so this one is shown
		// on the Plugins screen too, where it can be acted on directly.
		$conflicts = TKPwa_Integrations::conflicting_plugins();
		if ( $conflicts && $screen && 'plugins' === $screen->id ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: comma-separated plugin names */
						__( 'Takumi PWA: %s also adds a service worker. Only one can run on a site - deactivate one of them.', 'takumi-pwa' ),
						implode( ', ', $conflicts )
					)
				)
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag set by our own redirect.
		if ( $screen && $screen->id === self::$page_hook && isset( $_GET['tkpwa_rechecked'] ) ) {
			printf( '<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html__( 'Checks run again and icons regenerated.', 'takumi-pwa' ) );
		}
	}
}
