<?php
/**
 * Reads the site's existing navigation so the bottom bar and the app
 * shortcuts need no menu of their own.
 *
 * Classic themes keep menus in nav_menu terms assigned to theme locations.
 * Block themes keep them in wp_navigation posts as block markup and have no
 * locations at all. Both are read here into the same flat list of links.
 */

defined( 'ABSPATH' ) || exit;

class TKPwa_Menu {

	const LOCATION = 'tkpwa-bottom-nav';

	public static function init() {
		add_action( 'after_setup_theme', array( __CLASS__, 'register_location' ), 20 );
	}

	/**
	 * Offers a dedicated location for anyone who wants a different set of
	 * links in the app than in the header. Nothing requires using it.
	 */
	public static function register_location() {
		register_nav_menus(
			array(
				self::LOCATION => __( 'App bottom bar (Takumi PWA)', 'takumi-pwa' ),
			)
		);
	}

	/**
	 * Top-level links of the menu the app should use.
	 *
	 * @param int $limit Maximum number of links.
	 * @return array[] Each item: array( 'title' => string, 'url' => string ).
	 */
	public static function items( $limit ) {
		$menu_id = self::menu_id();
		$items   = $menu_id ? self::from_nav_menu( $menu_id ) : self::from_block_navigation();

		$home  = wp_parse_url( home_url(), PHP_URL_HOST );
		$items = array_filter(
			$items,
			static function ( $item ) use ( $home ) {
				// The app can only open its own pages; external links would
				// drop the user out of it into the browser.
				$host = wp_parse_url( $item['url'], PHP_URL_HOST );
				return '' !== $item['title'] && ( null === $host || $host === $home );
			}
		);

		// Apps put Home in the first tab; menus often do not. Partitioned
		// rather than sorted, because usort() is not stable before PHP 8.
		$first = array();
		$rest  = array();
		foreach ( $items as $item ) {
			if ( self::is_home( $item['url'] ) ) {
				$first[] = $item;
			} else {
				$rest[] = $item;
			}
		}
		$items = array_merge( $first, $rest );

		return array_slice( array_values( $items ), 0, $limit );
	}

	/**
	 * Path and query of a URL in a comparable form. The query matters: with
	 * plain permalinks every page is "/?page_id=N", so the path alone would
	 * make every page look like the front page.
	 *
	 * @param string $url Absolute or root-relative URL.
	 * @return string
	 */
	public static function key( $url ) {
		$path  = untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		return '' !== $query ? $path . '?' . $query : $path;
	}

	/**
	 * @param string $url URL to test.
	 * @return bool Whether it points at the front page.
	 */
	public static function is_home( $url ) {
		return self::key( $url ) === self::key( home_url( '/' ) );
	}

	/**
	 * Picks a classic menu: the one chosen in settings, then the one in the
	 * plugin's own location, then whatever sits in the theme's main slot.
	 *
	 * @return int Menu term ID, 0 when none.
	 */
	public static function menu_id() {
		$chosen = (int) TKPwa_Settings::value( 'bottom_nav_menu' );
		if ( $chosen && is_nav_menu( $chosen ) ) {
			return $chosen;
		}

		$locations = get_nav_menu_locations();
		$preferred = array( self::LOCATION, 'primary', 'main', 'menu-1', 'header', 'top' );
		foreach ( $preferred as $location ) {
			if ( ! empty( $locations[ $location ] ) && is_nav_menu( $locations[ $location ] ) ) {
				return (int) $locations[ $location ];
			}
		}
		foreach ( $locations as $menu_id ) {
			if ( $menu_id && is_nav_menu( $menu_id ) ) {
				return (int) $menu_id;
			}
		}
		return 0;
	}

	/**
	 * Where the links come from, in words, for the settings screen.
	 *
	 * @return string
	 */
	public static function source_label() {
		$menu_id = self::menu_id();
		if ( $menu_id ) {
			$menu = wp_get_nav_menu_object( $menu_id );
			/* translators: %s: menu name */
			return $menu ? sprintf( __( 'Menu "%s"', 'takumi-pwa' ), $menu->name ) : '';
		}
		if ( self::navigation_post() ) {
			return __( 'Site navigation block', 'takumi-pwa' );
		}
		return '';
	}

	/**
	 * @param int $menu_id Menu term ID.
	 * @return array[]
	 */
	private static function from_nav_menu( $menu_id ) {
		$items = wp_get_nav_menu_items( $menu_id );
		if ( ! $items ) {
			return array();
		}
		$links = array();
		foreach ( $items as $item ) {
			if ( (int) $item->menu_item_parent ) {
				continue;
			}
			$links[] = array(
				'title' => wp_strip_all_tags( $item->title ),
				'url'   => $item->url,
			);
		}
		return $links;
	}

	/**
	 * The most recently edited navigation of a block theme.
	 *
	 * @return WP_Post|null
	 */
	private static function navigation_post() {
		if ( ! post_type_exists( 'wp_navigation' ) ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		return $posts ? $posts[0] : null;
	}

	/**
	 * @return array[]
	 */
	private static function from_block_navigation() {
		$post = self::navigation_post();
		if ( ! $post ) {
			return array();
		}
		$links = array();
		foreach ( parse_blocks( $post->post_content ) as $block ) {
			// Top level only; submenus would not fit in a bottom bar anyway.
			if ( in_array( $block['blockName'], array( 'core/navigation-link', 'core/navigation-submenu' ), true )
				&& ! empty( $block['attrs']['url'] ) && isset( $block['attrs']['label'] ) ) {
				$links[] = array(
					'title' => wp_strip_all_tags( $block['attrs']['label'] ),
					'url'   => $block['attrs']['url'],
				);
			} elseif ( 'core/page-list' === $block['blockName'] ) {
				// The block's default: every published top-level page.
				$pages = get_pages(
					array(
						'parent'      => 0,
						'sort_column' => 'menu_order,post_title',
						'number'      => 10,
					)
				);
				foreach ( $pages as $page ) {
					$links[] = array(
						'title' => wp_strip_all_tags( get_the_title( $page ) ),
						'url'   => get_permalink( $page ),
					);
				}
			} elseif ( 'core/home-link' === $block['blockName'] ) {
				$links[] = array(
					'title' => isset( $block['attrs']['label'] ) ? wp_strip_all_tags( $block['attrs']['label'] ) : __( 'Home', 'takumi-pwa' ),
					'url'   => home_url( '/' ),
				);
			}
		}
		return $links;
	}
}
