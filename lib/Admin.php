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
	 * Returns an array of sites.
	 *
	 * @since     1.0.0
	 * @param     \WP_Post    $post    The post object.
	 * @return    array                List of sites.
	 */
	public function get_sites( $post ) {

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
			$sites[ $term->term_id ] = $this->get_site( $term );
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
	public function get_site( $term ) {

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
	 * @param     int       $object_id    The object ID.
	 * @param     string    $meta_type    Type of object metadata.
	 * @return    mixed                   Single metadata value, or array of values.
	 *                                    If the $meta_type or $object_id parameters are invalid, false is returned.
	 */
	public function get_remote_info( $object_id, $meta_type = 'post' ) {
		return \get_metadata( $meta_type, $object_id, Plugin::REPLICAST_OBJECT_INFO, true );
	}

	/**
	 * Set admin notices.
	 *
	 * @since     1.0.0
	 * @param     array    $notices    Array of notices.
	 */
	public function set_admin_notice( $notices ) {

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
