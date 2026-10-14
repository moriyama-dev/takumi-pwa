<?php
/**
 * Plugin Name:       Takumi PWA
 * Plugin URI:        https://github.com/moriyama-dev/takumi-pwa
 * Description:       Turn your WordPress site into an installable app with no setup. Manifest, service worker, offline support, install prompt and app-style navigation are generated from your existing site settings.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Yoshiro Moriyama (Takumi Web Services)
 * Author URI:        https://takumi.ca
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       takumi-pwa
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'TKPWA_VERSION', '1.0.0' );
define( 'TKPWA_PLUGIN_FILE', __FILE__ );
define( 'TKPWA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TKPWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TKPWA_PLUGIN_DIR . 'includes/class-tkpwa-settings.php';
require_once TKPWA_PLUGIN_DIR . 'includes/class-tkpwa-icons.php';
require_once TKPWA_PLUGIN_DIR . 'includes/class-tkpwa-menu.php';
require_once TKPWA_PLUGIN_DIR . 'includes/class-tkpwa-integrations.php';
require_once TKPWA_PLUGIN_DIR . 'includes/class-tkpwa-manifest.php';
require_once TKPWA_PLUGIN_DIR . 'includes/class-tkpwa-service-worker.php';
require_once TKPWA_PLUGIN_DIR . 'includes/class-tkpwa-frontend.php';
require_once TKPWA_PLUGIN_DIR . 'includes/class-tkpwa-share-target.php';
require_once TKPWA_PLUGIN_DIR . 'includes/class-tkpwa-health.php';
require_once TKPWA_PLUGIN_DIR . 'includes/class-tkpwa-admin.php';

/**
 * Generates the icon set up front so the very first visitor already gets an
 * installable manifest, and records that the welcome notice is due.
 */
function tkpwa_activate() {
	add_option( TKPwa_Settings::OPTION, array() );
	TKPwa_Icons::regenerate();
	set_transient( 'tkpwa_activated', 1, MINUTE_IN_SECONDS * 5 );
}
register_activation_hook( TKPWA_PLUGIN_FILE, 'tkpwa_activate' );

/**
 * Upgrading in place does not fire the activation hook. The service worker
 * cache name carries the version, so nothing else needs migrating yet.
 */
function tkpwa_maybe_upgrade() {
	if ( get_option( 'tkpwa_version' ) !== TKPWA_VERSION ) {
		TKPwa_Icons::regenerate();
		update_option( 'tkpwa_version', TKPWA_VERSION );
	}
}
add_action( 'admin_init', 'tkpwa_maybe_upgrade' );

function tkpwa_init() {
	TKPwa_Icons::init();
	TKPwa_Menu::init();
	TKPwa_Manifest::init();
	TKPwa_Service_Worker::init();
	TKPwa_Frontend::init();
	TKPwa_Share_Target::init();
	TKPwa_Integrations::init();
	TKPwa_Admin::init();
}
add_action( 'plugins_loaded', 'tkpwa_init' );
