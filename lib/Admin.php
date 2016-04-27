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
	 * Register hooks.
	 *
	 * @since    1.0.0
	 */
	public function register() {

		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		\add_action( 'admin_notices',         array( $this, 'display_admin_notices' ) );

	}

	/**
	 * Register the stylesheets for the Dashboard.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		\wp_enqueue_style(
			$this->plugin->get_name(),
			\plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/replicast.css',
			array(),
			$this->plugin->get_version(),
			'all'
		);

	}

	/**
	 * Register the scripts for the Dashboard.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		if ( \is_super_admin() ) {
			return;
		}

		\wp_enqueue_script(
			$this->plugin->get_name(),
			\plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/replicast.js',
			array( 'jquery' ),
			$this->plugin->get_version(),
			true
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

			$url    = \parse_url( \get_term_meta( $term->term_id, 'api_url', true ) );
			$client = new \GuzzleHttp\Client( array(
				'base_uri' => sprintf( '%s://%s', $url['scheme'], $url['host'] ),
				'debug'    => \apply_filters( 'replicast_client_debug', defined( 'REPLICAST_DEBUG' ) && REPLICAST_DEBUG )
			) );

			$site = new Client( $term, $client );

			\wp_cache_set( $term->term_id, $site, 'replicast_sites', 600 );
		}

		return $site;
	}

	/**
	 * Set admin notices.
	 *
	 * @since     1.0.0
	 */
	public function set_admin_notice( $status_code = '', $message = '' ) {

		if ( ! function_exists( 'DNH' ) ) {
			return;
		}

		$current_user = \wp_get_current_user();

		if ( empty( $message ) ) {
			$message = \__( 'Something went wrong.', 'replicast' );
		}

		error_log(print_r($message,true));

		// $args = array(
		// 	'cap' => 'manage_sites'
		// );

		\dnh_register_notice(
			uniqid( 'replicast_notices_' . $current_user->ID ),
			$this->get_notice_type_by_status_code( $status_code ),
			$message
			// $args
		);


					// 			$notices[] = array(
			// 				'status_code'   => $response->getStatusCode(),
			// 				'reason_phrase' => $response->getReasonPhrase(),
			// 				'message'       => sprintf(
			// 					'%s %s',
			// 					sprintf(
			// 						$response->getStatusCode() === 201 ? \__( 'PostHandler published on %s.', 'replicast' ) : \__( 'PostHandler updated on %s.', 'replicast' ),
			// 						$site->get_name()
			// 					),
			// 					sprintf(
			// 						'<a href="%s" title="%s" target="_blank">%s</a>',
			// 						\esc_url( $remote_data->link ),
			// 						\esc_attr( $site->get_name() ),
			// 						\__( 'View post', 'replicast' )
			// 					)
			// 				)
			// 			);




		/*

		$rendered     = array();

		foreach ( $notices as $notice ) {

			$status_code   = ! empty( $notice['status_code'] )   ? $notice['status_code']   : '';
			$reason_phrase = ! empty( $notice['reason_phrase'] ) ? $notice['reason_phrase'] : '';
			$message       = ! empty( $notice['message'] )       ? $notice['message']       : \__( 'Something went wrong.', 'replicast' );

			$rendered[] = array(
				'type'    => ,
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

		\set_transient( , $rendered, 180 );
		*/
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
				return 'updated';
			default:
				return 'error';
		}

	}

}
