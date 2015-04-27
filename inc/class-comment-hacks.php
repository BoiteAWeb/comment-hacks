<?php
/**
 * @package YoastCommentHacks
 */

/**
 * Class YoastCommentHacks
 *
 * @since 1.0
 */
class YoastCommentHacks {

	/**
	 * @var string Holds the plugins option name
	 */
	private $option_name = 'yoast_comment_hacks';

	/**
	 * @var array Holds the plugins options
	 */
	private $options = array();

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->options = get_option( $this->option_name );
		if ( ! is_array( $this->options ) ) {
			$this->set_defaults();
		}
		$this->upgrade();

		// Process the comment and check it for length
		add_filter( 'preprocess_comment', array( $this, 'check_comment_length' ) );

		// Filter the redirect URL
		add_filter( 'comment_post_redirect', array( $this, 'comment_redirect' ), 10, 2 );

		if ( $this->options['clean_emails'] ) {
			new YoastCleanEmails();
		}

		if ( is_admin() ) {
			new YoastCommentHacksAdmin();
		}
	}

	/**
	 * Check the length of the comment and if it's too short: die.
	 *
	 * @since 1.0
	 *
	 * @param array $comment_data all the data for the comment.
	 *
	 * @return array $comment_data all the data for the comment (only returned when the comment is long enough).
	 */
	public function check_comment_length( $comment_data ) {
		// Bail early for editors and admins, they can leave short comments if they want.
		if ( current_user_can( 'edit_posts' ) ) {
			return $comment_data;
		}

		// Check for comment length and die if to short.
		if ( strlen( trim( $comment_data['comment_content'] ) ) < $this->options['mincomlength'] ) {
			wp_die( esc_html( $this->options['mincomlengtherror'] ) );
		}

		return $comment_data;
	}

	/**
	 * Check whether the current commenter is a first time commenter, if so, redirect them to the specified settings.
	 *
	 * @since 1.0
	 *
	 * @param string $url     the original redirect URL
	 * @param object $comment the comment object
	 *
	 * @return string $url the URL to be redirected to, altered if this was a first time comment.
	 */
	public function comment_redirect( $url, $comment ) {
		$comment_count = get_comments( array( 'author_email' => $comment->comment_author_email, 'count' => true ) );

		if ( 1 == $comment_count ) {
			// Only change $url when the page option is actually set and not zero
			if ( isset( $this->options['redirect_page'] ) && 0 != $this->options['redirect_page'] ) {
				$url = get_permalink( $this->options['redirect_page'] );

				// Allow other plugins to hook when the user is being redirected, for analytics calls or even to change the target URL.
				$url = apply_filters( 'yoast_comment_redirect', $url, $comment );
			}
		}

		return $url;
	}

	/**
	 * Check whether any old options are in there and if so upgrade them
	 *
	 * @since 1.0
	 */
	private function upgrade() {
		foreach ( array( 'MinComLengthOptions', 'min_comment_length_option', 'CommentRedirect' ) as $old_option ) {
			$old_option_values = get_option( $old_option );
			if ( is_array( $old_option_values ) ) {
				if ( isset( $old_option_values['page'] ) ) {
					$old_option_values['redirect_page'] = $old_option_values['page'];
					unset( $old_option_values['page'] );
				}
				$this->options = wp_parse_args( $this->options, $old_option_values );
				delete_option( $old_option );
			}
		}

		if ( ! isset( $this->options['version'] ) ) {
			$this->options['clean_emails'] = true;
			$this->options['version']      = YOAST_COMMENT_HACKS_VERSION;
		}

		update_option( $this->option_name, $this->options );
	}

	/**
	 * Set default values for the plugin. If old, as in pre 1.0, settings are there, use them and then delete them.
	 *
	 * @since 1.0
	 */
	public function set_defaults() {
		$defaults = array(
			'redirect_page'     => 0,
			'mincomlength'      => 15,
			'clean_emails'      => true,
			'mincomlengtherror' => __( 'Error: Your comment is too short. Please try to say something useful.', 'yoast-comment-hacks' ),
		);

		$this->options = wp_parse_args( $this->options, $defaults );

		update_option( $this->option_name, $this->options );
	}

}