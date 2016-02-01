<?php

/**
 * The dashboard-specific functionality of the plugin.
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 */

namespace Replicast;

/**
 * The dashboard-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the dashboard-specific stylesheet and JavaScript.
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Admin {

	/**
	 * The plugin's instance.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @var       \Replicast\Plugin    This plugin's instance.
	 */
	private $plugin;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Plugin    $plugin    This plugin's instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Display admin notices.
	 *
	 * @since    1.0.0
	 */
	public function display_admin_notices() {

		$current_user = \wp_get_current_user();

		// Get notices
		$notices = (array) \get_transient( 'replicast_notices_' . $current_user->ID );

		/**
		 * Notices format:
		 *   array(
		 *     array(
		 *       'type'    => '', // Possible values: success, error or warning
		 *       'message' => ''
		 *     )
		 *   )
		 */
		foreach ( $notices as $notice ) {
			if ( ! empty( $notice['message'] ) ) {
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					\esc_attr( $notice['type'] ),
					$notice['message']
				);
			}
		}

		// Delete notices
		\delete_transient( 'replicast_notices_' . $current_user->ID );

	}

	/**
	 * Triggered whenever a post or page is created or updated.
	 *
	 * @since    1.0.0
	 * @param    int         $post_id    The post ID.
	 * @param    \WP_Post    $post       The \WP_Post object.
	 */
	public function on_save_post( $post_id, \WP_Post $post ) {

		// If post is an autosave, return
		if ( \wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// If post is a revision, return
		if ( \wp_is_post_revision( $post_id ) ) {
			return;
		}

		// If current user can't edit posts, return
		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Double check post status
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		// Get sites for replication
		$sites = $this->get_sites( $post );

		// If no sites were selected what I'm doing here? fail
		if ( ! $sites ) {
			return;
		}

		// Prepares post data for replication
		$data = new Request\Post( $post );
		$data->handle_save( $sites );

	}

	/**
	 * Fired when a post (or page) is about to be trashed.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    The post ID.
	 */
	public function on_trash_post( $post_id ) {

		// If current user can't delete posts, return
		if ( ! \current_user_can( 'delete_posts' ) ) {
			return;
		}

		// Retrieves post data given a post ID
		$post = \get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		// Get sites for replication
		$sites = $this->get_sites( $post );

		// If no sites were selected what I'm doing here? fail
		if ( ! $sites ) {
			return;
		}

		// Prepares data for replication
		$data = new Request\Post( $post );
		$data->handle_delete( $sites );

	}

	/**
	 * Returns an array of sites.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     \WP_Post    $post    The post object.
	 * @return    array|null           List of post terms or null.
	 */
	private function get_sites( $post ) {

		$terms = \wp_get_post_terms( $post->ID, Plugin::TAXONOMY_SITE );
		$sites = array();

		if ( \is_wp_error( $terms ) ) {
			return;
		}

		if ( empty( $terms ) ) {
			return;
		}

		if ( ! is_array( $terms ) ) {
			$terms = (array) $terms;
		}

		foreach ( $terms as $term ) {

			$term_id = $term->term_id;

			$sites[ $term_id ] = \wp_cache_get( $term_id, 'replicast_sites' );

			if ( ! $sites[ $term_id ] || ! $sites[ $term_id ] instanceof \Replicast\Model\Site ) {
				$client = new \GuzzleHttp\Client( array(
					'base_uri' => \untrailingslashit( \get_term_meta( $term_id, 'site_url', true ) ),
					'debug'    => \apply_filters( 'replicast_client_debug', defined( 'REPLICAST_DEBUG' ) && REPLICAST_DEBUG )
				) );

				$sites[ $term_id ] = new Model\Site( $term, $client );

				\wp_cache_set( $term_id, $sites[ $term_id ], 'replicast_sites', 600 );
			}

		}

		return $sites;
	}

}
