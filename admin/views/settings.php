<?php
/**
 * Settings screen.
 *
 * @var array    $settings Current settings, defaults merged.
 * @var array[]  $checks   From TKPwa_Health::checks().
 * @var string[] $icons    Generated icon URLs.
 * @var WP_Term[] $menus   Navigation menus.
 * @var string[] $forms    Detected form plugins.
 */

defined( 'ABSPATH' ) || exit;

$tkpwa_name = TKPwa_Settings::OPTION;

$tkpwa_status_labels = array(
	'ok'   => __( 'OK', 'takumi-pwa' ),
	'warn' => __( 'Check', 'takumi-pwa' ),
	'fail' => __( 'Action needed', 'takumi-pwa' ),
	'info' => __( 'Info', 'takumi-pwa' ),
);

/**
 * One on/off row.
 *
 * @param string $key         Setting key.
 * @param string $label       Switch label.
 * @param string $description What it does.
 * @param bool   $disabled    Whether the switch is unavailable.
 */
$tkpwa_toggle = static function ( $key, $label, $description, $disabled = false ) use ( $settings, $tkpwa_name ) {
	$id = 'tkpwa-' . str_replace( '_', '-', $key );
	?>
	<div class="tkpwa-toggle<?php echo $disabled ? ' is-disabled' : ''; ?>">
		<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $tkpwa_name . '[' . $key . ']' ); ?>" value="1" <?php checked( $settings[ $key ] ); ?> <?php disabled( $disabled ); ?>>
		<label for="<?php echo esc_attr( $id ); ?>">
			<strong><?php echo esc_html( $label ); ?></strong>
			<span><?php echo esc_html( $description ); ?></span>
		</label>
	</div>
	<?php
};

$tkpwa_preview_icon = isset( $icons['any-192'] ) ? $icons['any-192'] : '';
?>
<div class="wrap tkpwa">
	<h1><?php esc_html_e( 'Takumi PWA', 'takumi-pwa' ); ?></h1>

	<div class="tkpwa-top">
		<section class="tkpwa-card tkpwa-status">
			<h2><?php esc_html_e( 'Status', 'takumi-pwa' ); ?></h2>
			<table class="tkpwa-checks">
				<tbody>
					<?php foreach ( $checks as $tkpwa_check ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $tkpwa_check['label'] ); ?></th>
							<td><span class="tkpwa-badge tkpwa-badge--<?php echo esc_attr( $tkpwa_check['status'] ); ?>"><?php echo esc_html( $tkpwa_status_labels[ $tkpwa_check['status'] ] ); ?></span></td>
							<td><?php echo esc_html( $tkpwa_check['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tkpwa_recheck">
				<?php wp_nonce_field( 'tkpwa_recheck' ); ?>
				<?php submit_button( __( 'Check again', 'takumi-pwa' ), 'secondary', 'submit', false ); ?>
			</form>
		</section>

		<section class="tkpwa-card tkpwa-qr">
			<h2><?php esc_html_e( 'Install from a phone', 'takumi-pwa' ); ?></h2>
			<p><?php esc_html_e( 'Scan to open the site on a phone, then add it to the home screen. Print it for your shop counter or flyers.', 'takumi-pwa' ); ?></p>
			<div class="tkpwa-qr__code" data-url="<?php echo esc_url( home_url( '/' ) ); ?>"></div>
			<p class="tkpwa-qr__url"><code><?php echo esc_html( home_url( '/' ) ); ?></code></p>
			<button type="button" class="button tkpwa-qr__download"><?php esc_html_e( 'Download PNG', 'takumi-pwa' ); ?></button>
		</section>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( TKPwa_Admin::OPTION_GROUP ); ?>

		<section class="tkpwa-card">
			<h2><?php esc_html_e( 'App', 'takumi-pwa' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Leave a field empty to use your site settings. The grey text shows what is used now.', 'takumi-pwa' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Icon', 'takumi-pwa' ); ?></th>
					<td>
						<div class="tkpwa-icon">
							<img class="tkpwa-icon__preview" src="<?php echo esc_url( $tkpwa_preview_icon ); ?>" alt="" width="72" height="72" <?php echo $tkpwa_preview_icon ? '' : 'hidden'; ?>>
							<div>
								<input type="hidden" name="<?php echo esc_attr( $tkpwa_name ); ?>[icon_id]" value="<?php echo esc_attr( $settings['icon_id'] ); ?>" class="tkpwa-icon__id">
								<button type="button" class="button tkpwa-icon__choose"><?php esc_html_e( 'Choose image', 'takumi-pwa' ); ?></button>
								<button type="button" class="button-link tkpwa-icon__reset" <?php echo $settings['icon_id'] ? '' : 'hidden'; ?>><?php esc_html_e( 'Use Site Icon', 'takumi-pwa' ); ?></button>
								<p class="description"><?php esc_html_e( 'One square image of at least 512 x 512 pixels. Every size the phones need is made from it. Uses the Site Icon when none is chosen.', 'takumi-pwa' ); ?></p>
							</div>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tkpwa-app-name"><?php esc_html_e( 'App name', 'takumi-pwa' ); ?></label></th>
					<td><input type="text" id="tkpwa-app-name" class="regular-text" name="<?php echo esc_attr( $tkpwa_name ); ?>[app_name]" value="<?php echo esc_attr( $settings['app_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="tkpwa-short-name"><?php esc_html_e( 'Name under the icon', 'takumi-pwa' ); ?></label></th>
					<td>
						<input type="text" id="tkpwa-short-name" class="regular-text" maxlength="30" name="<?php echo esc_attr( $tkpwa_name ); ?>[short_name]" value="<?php echo esc_attr( $settings['short_name'] ); ?>" placeholder="<?php echo esc_attr( '' === $settings['short_name'] ? TKPwa_Settings::short_name() : '' ); ?>">
						<p class="description"><?php esc_html_e( 'Home screens cut off names longer than about 12 characters.', 'takumi-pwa' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tkpwa-description"><?php esc_html_e( 'Description', 'takumi-pwa' ); ?></label></th>
					<td><input type="text" id="tkpwa-description" class="large-text" name="<?php echo esc_attr( $tkpwa_name ); ?>[description]" value="<?php echo esc_attr( $settings['description'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="tkpwa-theme-color"><?php esc_html_e( 'Status bar color', 'takumi-pwa' ); ?></label></th>
					<td>
						<input type="text" id="tkpwa-theme-color" class="tkpwa-color" name="<?php echo esc_attr( $tkpwa_name ); ?>[theme_color]" value="<?php echo esc_attr( $settings['theme_color'] ); ?>" data-default-color="">
						<p class="description">
							<?php
							/* translators: %s: hex color */
							echo esc_html( sprintf( __( 'Empty uses your theme background (%s).', 'takumi-pwa' ), TKPwa_Settings::site_background_color() ) );
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tkpwa-background-color"><?php esc_html_e( 'Start screen color', 'takumi-pwa' ); ?></label></th>
					<td><input type="text" id="tkpwa-background-color" class="tkpwa-color" name="<?php echo esc_attr( $tkpwa_name ); ?>[background_color]" value="<?php echo esc_attr( $settings['background_color'] ); ?>" data-default-color=""></td>
				</tr>
				<tr>
					<th scope="row"><label for="tkpwa-display"><?php esc_html_e( 'Window', 'takumi-pwa' ); ?></label></th>
					<td>
						<select id="tkpwa-display" name="<?php echo esc_attr( $tkpwa_name ); ?>[display]">
							<option value="standalone" <?php selected( $settings['display'], 'standalone' ); ?>><?php esc_html_e( 'Like an app, without the address bar (recommended)', 'takumi-pwa' ); ?></option>
							<option value="minimal-ui" <?php selected( $settings['display'], 'minimal-ui' ); ?>><?php esc_html_e( 'With minimal browser controls', 'takumi-pwa' ); ?></option>
							<option value="fullscreen" <?php selected( $settings['display'], 'fullscreen' ); ?>><?php esc_html_e( 'Full screen, hiding the status bar', 'takumi-pwa' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</section>

		<section class="tkpwa-card">
			<h2><?php esc_html_e( 'App features', 'takumi-pwa' ); ?></h2>
			<div class="tkpwa-toggles">
				<?php
				$tkpwa_toggle( 'install_prompt', __( 'Install banner', 'takumi-pwa' ), __( 'Invites visitors to add the site to their home screen from their second visit. iPhone users get step-by-step instructions.', 'takumi-pwa' ) );
				$tkpwa_toggle( 'loading_screen', __( 'Loading screen', 'takumi-pwa' ), __( 'Shows your icon while the next page loads, instead of a frozen screen.', 'takumi-pwa' ) );
				$tkpwa_toggle( 'shortcuts', __( 'Icon shortcuts', 'takumi-pwa' ), __( 'Long-press the app icon to jump to your main pages. Taken from your menu.', 'takumi-pwa' ) );

				$tkpwa_menu_source = TKPwa_Menu::source_label();
				$tkpwa_toggle(
					'bottom_nav',
					__( 'Bottom navigation bar', 'takumi-pwa' ),
					'' !== $tkpwa_menu_source
						/* translators: %s: where the links come from, e.g. Menu "Main" */
						? sprintf( __( 'App-style tabs at the bottom of the screen, built from %s.', 'takumi-pwa' ), $tkpwa_menu_source )
						: __( 'App-style tabs at the bottom of the screen. Create a menu first; none was found.', 'takumi-pwa' )
				);
				$tkpwa_toggle( 'pull_to_refresh', __( 'Pull to refresh', 'takumi-pwa' ), __( 'Pull down at the top of a page to reload it, as in other apps.', 'takumi-pwa' ) );
				$tkpwa_toggle( 'scroll_progress', __( 'Reading progress bar', 'takumi-pwa' ), __( 'A thin line at the top that fills as the reader scrolls. Shown in the browser too.', 'takumi-pwa' ) );
				?>
			</div>
			<p class="description">
				<?php esc_html_e( 'App features appear only when the site is opened from the home screen. Add the class tkpwa-hide-in-app to any block to hide it there, or tkpwa-only-in-app to show it only there. The shortcode [tkpwa_install_button] adds an install button anywhere.', 'takumi-pwa' ); ?>
			</p>
		</section>

		<section class="tkpwa-card">
			<h2><?php esc_html_e( 'Offline and sharing', 'takumi-pwa' ); ?></h2>
			<div class="tkpwa-toggles">
				<div class="tkpwa-toggle is-fixed">
					<input type="checkbox" id="tkpwa-cache" checked disabled>
					<label for="tkpwa-cache">
						<strong><?php esc_html_e( 'Offline reading (automatic)', 'takumi-pwa' ); ?></strong>
						<span><?php esc_html_e( 'Pages visitors have opened stay readable without a connection. Logged-in views, the admin, carts and checkouts are never stored.', 'takumi-pwa' ); ?></span>
					</label>
				</div>
				<?php
				$tkpwa_toggle(
					'offline_forms',
					__( 'Offline forms', 'takumi-pwa' ),
					$forms
						/* translators: %s: comma-separated plugin names */
						? sprintf( __( 'Messages submitted without a connection are kept and sent once it returns. Works with %s.', 'takumi-pwa' ), implode( ', ', $forms ) )
						: __( 'Needs Contact Form 7, WPForms or Gravity Forms. None is active.', 'takumi-pwa' ),
					! $forms && ! $settings['offline_forms']
				);
				$tkpwa_toggle( 'share_target', __( 'Share to this site', 'takumi-pwa' ), __( 'On Android, the installed app appears in the Share menu. Sharing a link or text starts a new draft post with it.', 'takumi-pwa' ) );
				?>
			</div>
		</section>

		<details class="tkpwa-card tkpwa-advanced">
			<summary><?php esc_html_e( 'Advanced', 'takumi-pwa' ); ?></summary>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="tkpwa-bottom-nav-menu"><?php esc_html_e( 'Menu for the app', 'takumi-pwa' ); ?></label></th>
					<td>
						<select id="tkpwa-bottom-nav-menu" name="<?php echo esc_attr( $tkpwa_name ); ?>[bottom_nav_menu]">
							<option value="0"><?php esc_html_e( 'Automatic', 'takumi-pwa' ); ?></option>
							<?php foreach ( $menus as $tkpwa_menu ) : ?>
								<option value="<?php echo esc_attr( $tkpwa_menu->term_id ); ?>" <?php selected( (int) $settings['bottom_nav_menu'], (int) $tkpwa_menu->term_id ); ?>><?php echo esc_html( $tkpwa_menu->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Used for the bottom bar and icon shortcuts. Automatic uses the menu in the "App bottom bar" location, then your main menu.', 'takumi-pwa' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tkpwa-exclude-paths"><?php esc_html_e( 'Never store offline', 'takumi-pwa' ); ?></label></th>
					<td>
						<textarea id="tkpwa-exclude-paths" class="large-text code" rows="4" name="<?php echo esc_attr( $tkpwa_name ); ?>[exclude_paths]" placeholder="/members/"><?php echo esc_textarea( $settings['exclude_paths'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One path per line. Pages starting with these paths always come from the network. Shop and account pages of WooCommerce and Easy Digital Downloads are already excluded.', 'takumi-pwa' ); ?></p>
					</td>
				</tr>
			</table>
		</details>

		<?php submit_button(); ?>
	</form>
</div>
