<?php

/**
 * The dashboard-specific functionality of the plugin
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
	 * Register the stylesheets for the Dashboard.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		\wp_enqueue_style(
			$this->plugin->get_name(),
			\plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/admin.css',
			array(),
			$this->plugin->get_version(),
			'all'
		);
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
	 * Dynamically filter a user's capabilities.
	 *
	 * @since      1.0.0
	 * @param      array       $allcaps    An array of all the user's capabilities.
	 * @param      array       $caps       Actual capabilities for meta capability.
	 * @param      array       $args       Optional parameters passed to has_cap(), typically object ID.
	 * @param      \WP_User    $user       The user object.
	 * @return     array                   Possibly-modified array of all the user's capabilities.
	 */
	public function hide_edit_link( $allcaps, $caps, $args, $user ) {

		// Bail out if we're not asking about a post
		if ( $args[0] !== 'edit_post' ) {
			return $allcaps;
		}

		// Load the object data
		// Note: index 2 only exists on 'edit_post'
		$object = \get_post( $args[2] );

		if ( ! $object ) {
			return $allcaps;
		}

		// Check if the current object is an original or a duplicate
		if ( ! $this->is_remote_object( $object ) ) {
			return $allcaps;
		}

		// Disable 'edit_posts' and 'edit_others_posts'
		foreach ( $caps as $cap ) {
			$allcaps[ $cap ] = false;
		}

		return $allcaps;
	}

	/**
	 * Filter the list of row action links.
	 *
	 * @param     array       $defaults    An array of row actions.
	 * @param     \WP_Post    $post        The post object.
	 * @return    array                    Possibly-modified array of row actions.
	 */
	public function hide_row_actions( $defaults, $post ) {

		// Check if the current object is an original or a duplicate
		if ( ! $remote_info = $this->is_remote_object( $post ) ) {
			return $defaults;
		}

		/**
		 * Extend the list of unsupported row action links.
		 *
		 * @since     1.0.0
		 * @param     array       $defaults    An array of row actions.
		 * @param     \WP_Post    $post        The post object.
		 * @return    array                    Possibly-modified array of row actions.
		 */
		$defaults = \apply_filters( 'replicast_hide_row_actions', $defaults, $post );

		// Force the removal of unsupported default actions
		unset( $defaults['edit'] );
		unset( $defaults['inline hide-if-no-js'] );
		unset( $defaults['trash'] );

		// New set of actions
		$actions = array();

		// 'Edit link' points to the object original location
		$actions['edit'] = sprintf(
			'<a href="%s">%s</a>',
			\esc_url( $remote_info['edit_link'] ),
			\__( 'Edit', 'replicast' )
		);

		// Re-order actions
		foreach ( $defaults as $key => $value ) {
			$actions[ $key ] = $value;
		}

		return $actions;
	}

	/**
	 * Triggered whenever a post is published, or if it is edited and
	 * the status is changed to publish.
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

		// Posts with trash status are processed in \Request\Admin on_trash_post
		if ( $post->post_status === 'trash' ) {
			return;
		}

		// Double check post status
		if ( ! in_array( $post->post_status, Admin\Site::get_object_status() ) ) {
			return;
		}

		// Get sites for replication
		$sites = $this->get_sites( $post );

		// If no sites were selected what I'm doing here? fail
		if ( ! $sites ) {
			return;
		}

		// Prepares post data for replication
		$request = new Request\Post( $post );
		$request->handle_update( $sites );

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

		// Double check post status
		if ( $post->post_status !== 'trash' ) {
			return;
		}

		// Get sites for replication
		$sites = $this->get_sites( $post );

		// If no sites were selected what I'm doing here? fail
		if ( ! $sites ) {
			return;
		}

		// Prepares data for replication
		$request = new Request\Post( $post );
		$request->handle_delete( $sites );

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

		$terms = \get_the_terms( $post->ID, Plugin::TAXONOMY_SITE );
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

	/**
	 * Registers a new field on a set of existing object types.
	 *
	 * @since    1.0.0
	 */
	public function register_rest_fields() {

		foreach ( Admin\Site::get_object_types() as $object_type ) {
			\register_rest_field(
				$object_type,
				'replicast',
				array(
					'get_callback'    => '\Replicast\Request::get_rest_fields',
					'update_callback' => '\Replicast\Request::update_rest_fields',
					'schema'          => null,
				)
			);
		}

	}

	/**
	 * Check if the current object is an original or a duplicate.
	 *
	 * @since     1.0.0
	 * @param     \WP_Post|object    $object    The current object.
	 * @return    mixed                         Single metadata value, or array of values.
	 *                                          If the $meta_type or $object_id parameters are invalid, false is returned.
	 *                                          If the meta value isn't set, an empty string or array is returned, respectively.
	 */
	private function is_remote_object( $object ) {
		return \get_metadata( $object->post_type, $object->ID, Plugin::REPLICAST_REMOTE, true );
	}

}
