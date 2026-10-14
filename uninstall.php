<?php
/**
 * Removes everything the plugin stored: its options and the generated icons.
 *
 * Service workers already installed on visitors' devices cannot be removed
 * from here. They fetch pages from the network first, so visitors keep
 * seeing the live site; stored copies only appear when they are offline.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * @param string $dir Absolute path of the plugin's uploads directory.
 */
function tkpwa_uninstall_site( $dir ) {
	foreach ( array( 'tkpwa_settings', 'tkpwa_icons', 'tkpwa_icons_hash', 'tkpwa_icons_placeholder', 'tkpwa_version' ) as $option ) {
		delete_option( $option );
	}
	delete_transient( 'tkpwa_loopback' );
	delete_transient( 'tkpwa_activated' );

	foreach ( array( 'any', 'maskable', 'apple' ) as $prefix ) {
		foreach ( (array) glob( $dir . '/' . $prefix . '-*.png' ) as $file ) {
			wp_delete_file( $file );
		}
	}
	if ( is_dir( $dir ) ) {
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Only removed when empty.
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $tkpwa_site_id ) {
		switch_to_blog( $tkpwa_site_id );
		$tkpwa_uploads = wp_upload_dir( null, false );
		tkpwa_uninstall_site( trailingslashit( $tkpwa_uploads['basedir'] ) . 'takumi-pwa' );
		restore_current_blog();
	}
} else {
	$tkpwa_uploads = wp_upload_dir( null, false );
	tkpwa_uninstall_site( trailingslashit( $tkpwa_uploads['basedir'] ) . 'takumi-pwa' );
}
