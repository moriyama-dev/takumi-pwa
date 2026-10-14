<?php
/**
 * Puts the installed app in the phone's share sheet. Sharing a link or a
 * piece of text to it opens a new draft post with that content filled in.
 *
 * The share target points at the ordinary post-new.php screen, so login,
 * capability checks and the editor itself are all WordPress's own; this
 * class only fills in the title and content of the draft. Text and links
 * only - receiving image files would need a POST endpoint and the service
 * worker to hand the files over.
 */

defined( 'ABSPATH' ) || exit;

class TKPwa_Share_Target {

	const ARG_TITLE = 'tkpwa_share_title';
	const ARG_TEXT  = 'tkpwa_share_text';
	const ARG_URL   = 'tkpwa_share_url';

	public static function init() {
		if ( ! TKPwa_Settings::value( 'share_target' ) ) {
			return;
		}
		add_filter( 'default_title', array( __CLASS__, 'default_title' ), 10, 2 );
		add_filter( 'default_content', array( __CLASS__, 'default_content' ), 10, 2 );
	}

	/**
	 * @return array The manifest's share_target member.
	 */
	public static function manifest_entry() {
		return array(
			'action' => (string) wp_parse_url( admin_url( 'post-new.php' ), PHP_URL_PATH ),
			'method' => 'GET',
			'params' => array(
				'title' => self::ARG_TITLE,
				'text'  => self::ARG_TEXT,
				'url'   => self::ARG_URL,
			),
		);
	}

	/**
	 * @param string  $title Default title.
	 * @param WP_Post $post  The auto-draft.
	 * @return string
	 */
	public static function default_title( $title, $post ) {
		if ( ! self::applies( $post ) ) {
			return $title;
		}
		$shared = self::shared();
		return '' !== $shared['title'] ? $shared['title'] : $title;
	}

	/**
	 * @param string  $content Default content.
	 * @param WP_Post $post    The auto-draft.
	 * @return string Block markup.
	 */
	public static function default_content( $content, $post ) {
		if ( ! self::applies( $post ) ) {
			return $content;
		}
		$shared = self::shared();
		$blocks = '';
		if ( '' !== $shared['text'] ) {
			$blocks .= "<!-- wp:paragraph -->\n<p>" . esc_html( $shared['text'] ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		}
		if ( '' !== $shared['url'] ) {
			$blocks .= "<!-- wp:paragraph -->\n<p><a href=\"" . esc_url( $shared['url'] ) . '">' . esc_html( $shared['url'] ) . "</a></p>\n<!-- /wp:paragraph -->\n";
		}
		return '' !== $blocks ? $blocks : $content;
	}

	/**
	 * Only for a fresh post created by someone who may write posts, and only
	 * when the request actually came from the share sheet.
	 *
	 * @param WP_Post $post The auto-draft.
	 * @return bool
	 */
	private static function applies( $post ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only pre-fills an unsaved draft; nothing is stored until the user saves.
		$from_share = isset( $_GET[ self::ARG_TITLE ] ) || isset( $_GET[ self::ARG_TEXT ] ) || isset( $_GET[ self::ARG_URL ] );
		return $from_share && $post instanceof WP_Post && 'post' === $post->post_type && current_user_can( 'edit_posts' );
	}

	/**
	 * Android apps disagree on where the link goes: some fill "url", most
	 * put it at the end of "text". A trailing link in the text is moved to
	 * "url" so it ends up as a link either way.
	 *
	 * @return array{ title: string, text: string, url: string }
	 */
	private static function shared() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- See applies().
		$title = isset( $_GET[ self::ARG_TITLE ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::ARG_TITLE ] ) ) : '';
		$text  = isset( $_GET[ self::ARG_TEXT ] ) ? sanitize_textarea_field( wp_unslash( $_GET[ self::ARG_TEXT ] ) ) : '';
		$url   = isset( $_GET[ self::ARG_URL ] ) ? esc_url_raw( wp_unslash( $_GET[ self::ARG_URL ] ) ) : '';
		// phpcs:enable

		if ( '' === $url && preg_match( '~\s*(https?://\S+)\s*$~', $text, $m ) ) {
			$url  = esc_url_raw( $m[1] );
			$text = trim( substr( $text, 0, - strlen( $m[0] ) ) );
		}

		return array(
			'title' => $title,
			'text'  => $text,
			'url'   => $url,
		);
	}
}
