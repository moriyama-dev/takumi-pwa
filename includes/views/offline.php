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
<?php wp_print_styles( 'tkpwa-offline' ); ?>
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
<?php wp_print_scripts( 'tkpwa-offline' ); ?>
</body>
</html>
