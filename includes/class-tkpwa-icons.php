<?php
/**
 * Builds every icon size the platforms ask for from a single image.
 *
 * Browsers want 192px and 512px icons, Android additionally wants a
 * "maskable" variant with padding so its launcher can crop it to a circle or
 * squircle, and iOS wants a 180px icon with no transparency. Asking the site
 * owner for four images is the first place other plugins lose people, so
 * this takes one image - the site icon unless another is chosen - and
 * produces the rest.
 *
 * When there is no image at all, a plain icon is drawn so the site is still
 * installable from the moment the plugin is activated. The status screen
 * points out that it is a placeholder.
 */

defined( 'ABSPATH' ) || exit;

class TKPwa_Icons {

	const OPTION      = 'tkpwa_icons';
	const HASH_OPTION = 'tkpwa_icons_hash';
	const DIR         = 'takumi-pwa';

	public static function init() {
		add_action( 'update_option_' . TKPwa_Settings::OPTION, array( __CLASS__, 'regenerate' ) );
		add_action( 'add_option_' . TKPwa_Settings::OPTION, array( __CLASS__, 'regenerate' ) );
		add_action( 'update_option_site_icon', array( __CLASS__, 'regenerate' ) );
	}

	/**
	 * The attachment the icons are cut from, or 0 when there is none.
	 *
	 * @return int
	 */
	public static function source_id() {
		$id = (int) TKPwa_Settings::value( 'icon_id' );
		if ( ! $id ) {
			$id = (int) get_option( 'site_icon' );
		}
		return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
	}

	/**
	 * Generated icons, keyed by role.
	 *
	 * @return array{ any-192?: string, any-512?: string, maskable-192?: string, maskable-512?: string, apple-180?: string } URLs.
	 */
	public static function urls() {
		$files = get_option( self::OPTION, array() );
		if ( ! is_array( $files ) || empty( $files ) ) {
			return array();
		}
		$uploads = wp_upload_dir( null, false );
		$urls    = array();
		foreach ( $files as $role => $relative ) {
			$urls[ $role ] = set_url_scheme( trailingslashit( $uploads['baseurl'] ) . $relative );
		}
		return $urls;
	}

	/**
	 * Whether the current icons were drawn as a placeholder.
	 *
	 * @return bool
	 */
	public static function is_placeholder() {
		return (bool) get_option( 'tkpwa_icons_placeholder', false );
	}

	/**
	 * Rebuilds the whole set. Hooked to option changes, so it accepts and
	 * ignores whatever arguments those hooks pass.
	 *
	 * @return bool True when a full set was written.
	 */
	public static function regenerate() {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			self::log( 'GD is not available; icons cannot be generated.' );
			update_option( self::OPTION, array(), false );
			return false;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			self::log( 'Uploads directory unavailable: ' . $uploads['error'] );
			return false;
		}

		$dir = trailingslashit( $uploads['basedir'] ) . self::DIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			self::log( 'Could not create ' . $dir );
			return false;
		}

		$source_id   = self::source_id();
		$source      = $source_id ? self::load_source( $source_id ) : false;
		$placeholder = false;
		if ( ! $source ) {
			$source      = self::draw_placeholder( 512 );
			$placeholder = true;
		}

		$background = self::hex_to_rgb( TKPwa_Settings::background_color() );
		$hash       = substr( md5( ( $source_id ? $source_id . '|' . get_post_modified_time( 'U', true, $source_id ) : 'placeholder' ) . '|' . TKPwa_Settings::background_color() . '|' . TKPwa_Settings::theme_color() ), 0, 8 );

		$plan = array(
			// role => array( size, padding ratio, flatten onto background ).
			'any-192'      => array( 192, 0, false ),
			'any-512'      => array( 512, 0, false ),
			// The maskable safe zone is a circle of 80% diameter; 0.72 keeps a
			// square logo's corners inside it with a little margin.
			'maskable-192' => array( 192, 0.72, true ),
			'maskable-512' => array( 512, 0.72, true ),
			// iOS renders transparency as black.
			'apple-180'    => array( 180, 0, true ),
		);

		self::delete_old_files( $dir );

		$written = array();
		foreach ( $plan as $role => $spec ) {
			list( $size, $scale, $flatten ) = $spec;
			$canvas = self::compose( $source, $size, $scale, $flatten ? $background : null );
			$name   = sprintf( '%s-%s.png', $role, $hash );
			if ( imagepng( $canvas, trailingslashit( $dir ) . $name ) ) {
				$written[ $role ] = self::DIR . '/' . $name;
			} else {
				self::log( 'Could not write ' . $name );
			}
			imagedestroy( $canvas );
		}
		imagedestroy( $source );

		update_option( self::OPTION, $written, false );
		update_option( self::HASH_OPTION, $hash, false );
		update_option( 'tkpwa_icons_placeholder', $placeholder ? 1 : 0, false );

		return count( $written ) === count( $plan );
	}

	/**
	 * Removes this plugin's directory under uploads. Used by uninstall.
	 */
	public static function delete_all() {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . self::DIR;
		self::delete_old_files( $dir );
		if ( is_dir( $dir ) ) {
			// Only empty directories are removed; anything a user put here survives.
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Best effort; a non-empty or missing directory is not an error.
		}
	}

	/**
	 * Only the files this class names are touched, so nothing else in the
	 * directory is at risk.
	 *
	 * @param string $dir Absolute directory path.
	 */
	private static function delete_old_files( $dir ) {
		// GLOB_BRACE is missing on some C libraries, so one pattern per prefix.
		foreach ( array( 'any', 'maskable', 'apple' ) as $prefix ) {
			$files = glob( trailingslashit( $dir ) . $prefix . '-*.png' );
			foreach ( (array) $files as $file ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Loads the largest available rendition of an attachment as a GD image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return \GdImage|resource|false
	 */
	private static function load_source( $attachment_id ) {
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			self::log( 'Icon source not readable: ' . $attachment_id );
			return false;
		}

		$type = wp_check_filetype( $path );
		switch ( $type['type'] ) {
			case 'image/png':
				$image = imagecreatefrompng( $path );
				break;
			case 'image/jpeg':
				$image = imagecreatefromjpeg( $path );
				break;
			case 'image/gif':
				$image = imagecreatefromgif( $path );
				break;
			case 'image/webp':
				$image = function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $path ) : false;
				break;
			default:
				$image = false;
		}

		if ( ! $image ) {
			self::log( 'Unsupported icon source type: ' . $type['type'] );
			return false;
		}

		if ( ! imageistruecolor( $image ) ) {
			imagepalettetotruecolor( $image );
		}
		imagealphablending( $image, true );
		imagesavealpha( $image, true );

		return $image;
	}

	/**
	 * Centers the source on a square canvas.
	 *
	 * @param \GdImage|resource $source     Source image.
	 * @param int               $size       Output edge in pixels.
	 * @param float             $scale      Share of the edge the image occupies; 0 = fill (cropped to square).
	 * @param int[]|null        $background RGB to flatten onto, or null to keep transparency.
	 * @return \GdImage|resource
	 */
	private static function compose( $source, $size, $scale, $background ) {
		$canvas = imagecreatetruecolor( $size, $size );
		imagealphablending( $canvas, false );
		imagesavealpha( $canvas, true );

		if ( null === $background ) {
			$fill = imagecolorallocatealpha( $canvas, 0, 0, 0, 127 );
		} else {
			$fill = imagecolorallocate( $canvas, $background[0], $background[1], $background[2] );
		}
		imagefilledrectangle( $canvas, 0, 0, $size, $size, $fill );
		imagealphablending( $canvas, true );

		$src_w = imagesx( $source );
		$src_h = imagesy( $source );

		if ( $scale > 0 ) {
			// Fit the whole image inside the padded area.
			$box   = (int) round( $size * $scale );
			$ratio = min( $box / $src_w, $box / $src_h );
			$dst_w = (int) round( $src_w * $ratio );
			$dst_h = (int) round( $src_h * $ratio );
			imagecopyresampled( $canvas, $source, (int) ( ( $size - $dst_w ) / 2 ), (int) ( ( $size - $dst_h ) / 2 ), 0, 0, $dst_w, $dst_h, $src_w, $src_h );
		} else {
			// Fill the square, cropping the longer side from the center.
			$edge = min( $src_w, $src_h );
			imagecopyresampled( $canvas, $source, 0, 0, (int) ( ( $src_w - $edge ) / 2 ), (int) ( ( $src_h - $edge ) / 2 ), $size, $size, $edge, $edge );
		}

		return $canvas;
	}

	/**
	 * A house outline on the theme color. Drawn with polygons rather than
	 * text so it stays sharp without a font file.
	 *
	 * @param int $size Edge in pixels.
	 * @return \GdImage|resource
	 */
	private static function draw_placeholder( $size ) {
		$image = imagecreatetruecolor( $size, $size );
		$rgb   = self::hex_to_rgb( TKPwa_Settings::theme_color() );

		// White on a light theme color would vanish, so pick the contrasting ink.
		$luma = ( 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2] ) / 255;
		$ink  = $luma > 0.6 ? array( 33, 37, 41 ) : array( 255, 255, 255 );

		$bg = imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] );
		$fg = imagecolorallocate( $image, $ink[0], $ink[1], $ink[2] );
		imagefilledrectangle( $image, 0, 0, $size, $size, $bg );

		$u = $size / 100;
		// Roof.
		self::polygon( $image, self::points( array( 50, 24, 78, 48, 72, 48, 50, 30, 28, 48, 22, 48 ), $u ), $fg );
		// Walls with a door cut out.
		self::polygon( $image, self::points( array( 33, 52, 50, 37, 67, 52, 67, 76, 56, 76, 56, 60, 44, 60, 44, 76, 33, 76 ), $u ), $fg );

		return $image;
	}

	/**
	 * PHP 8.1 deprecated the point-count argument that PHP 7.4 still requires.
	 *
	 * @param \GdImage|resource $image  Image.
	 * @param int[]             $points Flat x,y list.
	 * @param int               $color  Allocated color.
	 */
	private static function polygon( $image, $points, $color ) {
		if ( PHP_VERSION_ID >= 80000 ) {
			imagefilledpolygon( $image, $points, $color );
		} else {
			imagefilledpolygon( $image, $points, (int) ( count( $points ) / 2 ), $color );
		}
	}

	/**
	 * @param float[] $coords Coordinates on a 100-unit grid.
	 * @param float   $unit   Pixels per unit.
	 * @return int[]
	 */
	private static function points( $coords, $unit ) {
		return array_map(
			static function ( $c ) use ( $unit ) {
				return (int) round( $c * $unit );
			},
			$coords
		);
	}

	/**
	 * @param string $hex #rrggbb or #rgb.
	 * @return int[]
	 */
	private static function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return array( 255, 255, 255 );
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * @param string $message What went wrong.
	 */
	private static function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[takumi-pwa] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only diagnostics.
		}
	}
}
