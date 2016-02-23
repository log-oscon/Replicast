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

use Replicast\Client;
use Replicast\Admin\Site;
use Replicast\Handler\PostHandler;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

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
	 * Show admin column contents.
	 *
	 * @since    1.0.0
	 * @param    string    $column       The name of the column to display.
	 * @param    int       $object_id    The current object ID.
	 */
	function manage_custom_column( $column, $object_id ) {

		if ( $column !== 'replicast' ) {
			return;
		}

		$remote_info = static::get_remote_info( $object_id );

		$html = sprintf(
			'<span class="dashicons dashicons-%s"></span>',
			$remote_info ? 'yes' : 'no'
		);

		if ( ! empty( $remote_info['edit_link'] ) ) {
			$html = sprintf(
				'<a href="%s" title="%s">%s</a>',
				\esc_url( $remote_info['edit_link'] ),
				\esc_attr__( 'Edit', 'replicast' ),
				$html
			);
		}

		/**
		 * Filter the column contents.
		 *
		 * @since     1.0.0
		 * @param     mixed       $remote_info    Single metadata value, or array of values.
		 *                                        If the $meta_type or $object_id parameters are invalid, false is returned.
		 * @param     \WP_Post    $object         The current object ID.
		 * @return    string                      Possibly-modified column contents.
		 */
		echo \apply_filters( 'manage_custom_column_html', $html, $remote_info, $object_id );

	}

	/**
	 * Show admin column.
	 *
	 * @since     1.0.0
	 * @param     array     $columns      An array of column names.
	 * @param     string    $post_type    The post type slug.
	 * @return    array                   Possibly-modified array of column names.
	 */
	public function manage_columns( $columns, $post_type = 'page' ) {

		if ( ! in_array( $post_type, Site::get_post_types() ) ) {
			return $columns;
		}

		/**
		 * Filter the column header title.
		 *
		 * @since     1.0.0
		 * @param     string    Column header title.
		 * @return    string    Possibly-modified column header title.
		 */
		$title = \apply_filters( 'replicast_manage_columns_title', \__( 'Replicast', 'replicast' ) );

		/**
		 * Filter the columns displayed.
		 *
		 * @since     1.0.0
		 * @param     array     $columns      An array of column names.
		 * @param     string    $post_type    The object type slug.
		 * @return    array                   Possibly-modified array of column names.
		 */
		return \apply_filters(
			'replicast_manage_columns',
			array_merge( $columns, array( 'replicast' => $title ) ),
			$post_type
		);
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

		// Bail out if not admin and bypass REST API requests
		if ( ! \is_admin() ) {
			return $allcaps;
		}

		// Bail out if we're not asking about a post
		if ( $args[0] !== 'edit_post' ) {
			return $allcaps;
		}

		// Check if the current object is an original or a duplicate
		if ( ! static::get_remote_info( $args[2] ) ) {
			return $allcaps;
		}

		// Disable 'edit_posts', 'edit_published_posts' and 'edit_others_posts'
		if ( in_array( $cap, array( 'edit_posts', 'edit_published_posts', 'edit_others_posts' ) ) ) {
			$allcaps[ $cap ] = false;
		}

		return $allcaps;
	}

	/**
	 * Filter the list of row action links.
	 *
	 * @param     array       $defaults    An array of row actions.
	 * @param     \WP_Post    $object      The current object.
	 * @return    array                    Possibly-modified array of row actions.
	 */
	public function hide_row_actions( $defaults, $object ) {

		// Check if the current object is an original or a duplicate
		if ( ! $remote_info = static::get_remote_info( $object->ID ) ) {
			return $defaults;
		}

		/**
		 * Extend the list of unsupported row action links.
		 *
		 * @since     1.0.0
		 * @param     array       $defaults    An array of row actions.
		 * @param     \WP_Post    $object      The current object.
		 * @return    array                    Possibly-modified array of row actions.
		 */
		$defaults = \apply_filters( 'replicast_hide_row_actions', $defaults, $object );

		// Force the removal of unsupported default actions
		unset( $defaults['edit'] );
		unset( $defaults['inline hide-if-no-js'] );
		unset( $defaults['trash'] );

		// New set of actions
		$actions = array();

		// 'Edit link' points to the object original location
		$actions['edit'] = sprintf(
			'<a href="%s" title="%s">%s</a>',
			\esc_url( $remote_info['edit_link'] ),
			\esc_attr__( 'Edit', 'replicast' ),
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

		// Bail out if not admin and bypass REST API requests
		if ( ! \is_admin() ) {
			return;
		}

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
		if ( ! in_array( $post->post_status, Site::get_post_status() ) ) {
			return;
		}

		// Get sites for replication
		$sites = $this->get_sites( $post );

		// Prepares post data for replication
		$handler = new PostHandler( $post );

		$notices = array();

		foreach ( $sites as $site ) {

			try {

				$response = $handler->handle_update( $site )->wait();

				// Get the remote object data
				$remote_post = json_decode( $response->getBody()->getContents() );

				if ( $remote_post ) {

					// Update post replicast info
					$handler->handle_replicast_info( $site, $remote_post );

					// Handle post terms
					// $handler->handle_terms( $site, $remote_post );

					$notices[] = array(
						'status_code'   => $response->getStatusCode(),
						'reason_phrase' => $response->getReasonPhrase(),
						'message'       => sprintf(
							'%s %s',
							sprintf(
								$response->getStatusCode() === 201 ? \__( 'Post published on %s.', 'replicast' ) : \__( 'Post updated on %s.', 'replicast' ),
								$site->get_name()
							),
							sprintf(
								'<a href="%s" title="%s" target="_blank">%s</a>',
								\esc_url( $remote_post->link ),
								\esc_attr( $site->get_name() ),
								\__( 'View post', 'replicast' )
							)
						)
					);

				}

			} catch ( \Exception $ex ) {
				if ( $ex->hasResponse() ) {
					$notices[] = array(
						'status_code'   => $ex->getResponse()->getStatusCode(),
						'reason_phrase' => $ex->getResponse()->getReasonPhrase(),
						'message'       => $ex->getMessage()
					);
				}
			}

		}

		// Set admin notices
		if ( ! empty( $notices ) ) {
			$this->set_admin_notice( $notices );
		}

	}

	/**
	 * Fired when a post (or page) is about to be trashed.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    The post ID.
	 */
	public function on_trash_post( $post_id ) {

		// Bail out if not admin and bypass REST API requests
		if ( ! \is_admin() ) {
			return;
		}

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

		// Prepares post data for replication
		$handler = new PostHandler( $post );

		$notices = array();

		foreach ( $sites as $site ) {

			try {

				$response = $handler->handle_delete( $site )->wait();

				// Get the remote object data
				$remote_post = json_decode( $response->getBody()->getContents() );

				if ( $remote_post ) {

					// The API returns 'publish' but we force the status to be 'trash' for better
					// management of the next actions over the object. Like, recovering (PUT request)
					// or permanently delete the object from remote location.
					$remote_post->status = 'trash';

					// Update post replicast info
					$handler->handle_replicast_info( $site, $remote_post );

					$notices[] = array(
						'status_code'   => $response->getStatusCode(),
						'reason_phrase' => $response->getReasonPhrase(),
						'message'       => sprintf(
							'%s %s',
							sprintf(
								\__( 'Post trashed on %s.', 'replicast' ),
								$site->get_name()
							),
							sprintf(
								'<a href="%s" title="%s" target="_blank">%s</a>',
								\esc_url( $remote_post->link ),
								\esc_attr( $site->get_name() ),
								\__( 'View post', 'replicast' )
							)
						)
					);

				}

			} catch ( \Exception $ex ) {
				if ( $ex->hasResponse() ) {
					$notices[] = array(
						'status_code'   => $ex->getResponse()->getStatusCode(),
						'reason_phrase' => $ex->getResponse()->getReasonPhrase(),
						'message'       => $ex->getMessage()
					);
				}
			}

		}

		// Set admin notices
		if ( ! empty( $notices ) ) {
			$this->set_admin_notice( $notices );
		}

	}

	/**
	 * Returns an array of sites.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     \WP_Post    $post    The post object.
	 * @return    array                List of sites.
	 */
	private function get_sites( $post ) {

		$terms = \get_the_terms( $post->ID, Plugin::TAXONOMY_SITE );

		if ( \is_wp_error( $terms ) ) {
			return array();
		}

		if ( empty( $terms ) ) {
			return array();
		}

		if ( ! is_array( $terms ) ) {
			$terms = (array) $terms;
		}

		$sites = array();
		foreach ( $terms as $term ) {
			$sites[ $term->term_id ] = static::get_site( $term );
		}

		return $sites;
	}

	/**
	 * Returns a site.
	 *
	 * @since     1.0.0
	 * @param     int|\WP_Term    $term    The term ID or the term object.
	 * @return    \Replicast\Client        A site object.
	 */
	public static function get_site( $term ) {

		if ( is_numeric( $term ) ) {
			$term = \get_term( $term );
		}

		$site = \wp_cache_get( $term->term_id, 'replicast_sites' );

		if ( ! $site || ! $site instanceof Client ) {

			$client = new \GuzzleHttp\Client( array(
				'base_uri' => \untrailingslashit( \get_term_meta( $term->term_id, 'site_url', true ) ),
				'debug'    => \apply_filters( 'replicast_client_debug', defined( 'REPLICAST_DEBUG' ) && REPLICAST_DEBUG )
			) );

			$site = new Client( $term, $client );

			\wp_cache_set( $term->term_id, $site, 'replicast_sites', 600 );
		}

		return $site;
	}

	/**
	 * Retrieve remote info from an object.
	 *
	 * @since     1.0.0
	 * @param     \WP_Post    $object    The object ID.
	 * @return    mixed                  Single metadata value, or array of values.
	 *                                   If the $meta_type or $object_id parameters are invalid, false is returned.
	 */
	public static function get_remote_info( $object_id ) {
		return \get_post_meta( $object_id, Plugin::REPLICAST_REMOTE, true );
	}

	/**
	 * Set admin notices.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     array    $notices    Array of notices.
	 */
	private function set_admin_notice( $notices ) {

		$current_user = \wp_get_current_user();
		$rendered     = array();

		foreach ( $notices as $notice ) {

			$status_code   = ! empty( $notice['status_code'] )   ? $notice['status_code']   : '';
			$reason_phrase = ! empty( $notice['reason_phrase'] ) ? $notice['reason_phrase'] : '';
			$message       = ! empty( $notice['message'] )       ? $notice['message']       : \__( 'Something went wrong.', 'replicast' );

			$rendered[] = array(
				'type'    => $this->get_notice_type_by_status_code( $status_code ),
				'message' => $message
			);

			if ( defined( 'REPLICAST_DEBUG' ) && REPLICAST_DEBUG ) {
				error_log( sprintf(
					"\n%s\n%s\n%s",
					sprintf( \__( 'Status Code: %s', 'replicast' ), $status_code ),
					sprintf( \__( 'Reason: %s', 'replicast' ), $reason_phrase ),
					sprintf( \__( 'Message: %s', 'replicast' ), $message )
				) );
			}

		}

		\set_transient( 'replicast_notices_' . $current_user->ID, $rendered, 180 );

	}

	/**
	 * Get the admin notice type based on a HTTP request/response status code.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     string    $status_code    HTTP request/response status code.
	 * @return    string                    Possible values: error | success | warning.
	 */
	private function get_notice_type_by_status_code( $status_code ) {

		// FIXME
		// Maybe this should be more simpler. For instance, all 2xx status codes should be treated as success.
		// What happens with a 3xx status code?

		switch ( $status_code ) {
			case '200': // Update
			case '201': // Create
				return 'success';
			default:
				return 'error';
		}

	}

}
