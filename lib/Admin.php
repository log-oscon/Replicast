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
	 * @since  1.0.0
	 * @access protected
	 * @var    \Replicast\Plugin
	 */
	protected $plugin;

	/**
	 * The logger's instance.
	 *
	 * @since  1.2.0
	 * @access protected
	 * @var    \Monolog\Logger
	 */
	protected $logger;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param \Replicast\Plugin $plugin This plugin's instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->logger = new Logger( Handler::class );
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		\add_action( 'admin_notices',         array( $this, 'display_notices' ) );
	}

	/**
	 * Register the stylesheets for the Dashboard.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * Returns an array of sites.
	 *
	 * @since  1.0.0
	 * @param  \WP_Post $post The post object.
	 * @return array          List of sites.
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
	 * @since  1.0.0
	 * @param  int|\WP_Term $term The term ID or the term object.
	 * @return \Replicast\Client  A site object.
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
			) );

			$site = new Client( $term, $client );

			\wp_cache_set( $term->term_id, $site, 'replicast_sites', 600 );
		}

		return $site;
	}

	/**
	 * Display admin notices.
	 *
	 * @since    1.0.0
	 */
	public function display_notices() {

		$notices = $this->get_notices();

		foreach ( $notices as $notice_id => $notice ) {
			if ( ! empty( $notice['message'] ) ) {
				printf(
					'<div id="%s" class="%s notice is-dismissible"><p>%s</p></div>',
					\esc_attr( $notice_id ),
					\esc_attr( $notice['type'] ),
					$notice['message']
				);
			}
		}

		$this->delete_notices();
	}

	/**
	 * Set admin notice.
	 *
	 * Notice format:
	 *   array(
	 *     'type'    => '', // Possible values: success, error or warning
	 *     'message' => ''
	 *   )
	 *
	 * @since 1.0.0
	 * @param string $id      The unique ID of the notice.
	 * @param string $type    The type of notice to display.
	 *                        Currently it can be 'error' for an error notice or
	 *                        'updated' for a success/update notice.
	 * @param string $content The content of the admin notice.
	 */
	public function register_notice( $id, $type = 'error', $content = '' ) {

		if ( empty( $content ) ) {
			/**
			 * Filter the default admin notice message.
			 *
			 * @since  1.0.0
			 * @param  string Default admin notice text.
			 * @return string Possibly-modified admin notice text.
			 */
			$content = \apply_filters( 'replicast_default_admin_notice_message', \__( 'Something went wrong.', 'replicast' ) );
		}

		$notices = $this->get_notices();

		if ( array_key_exists( $id, $notices ) ) {
			error_log( sprintf(
				\__( 'A notice with the ID %s has already been registered.', 'replicast' ),
				$id
			) );
			return;
		}

		$notices[ $id ] = array(
			'type'    => $type,
			'message' => $content,
		);

		\set_transient( $this->get_notices_unique_id(), $notices, 180 );
	}

	/**
	 * Get the admin notice type based on a HTTP request/response status code.
	 *
	 * @since  1.4.0 Exception proper handling.
	 * @since  1.0.0
	 *
	 * @param  int|\Exception $status_code HTTP request/response status code, or a
	 *                                     \Exception instance object.
	 * @return string                      Possible values: error | success | warning.
	 */
	public function get_notice_type_by_status_code( $status_code = 0 ) {

		if ( $status_code instanceof \GuzzleHttp\Exception ) {
			if ( $status_code->hasResponse() ) {
				$status_code = $status_code->getResponse()->getStatusCode();
			}
		}

		$status_code = intval( $status_code );

		/**
		 * FIXME: Maybe this should be more simpler.
		 * For instance, all 2xx status codes should be treated as success.
		 * What happens with a 3xx status code?
		 */

		switch ( $status_code ) {
			case '200': // Update.
			case '201': // Create.
				return 'updated';
			default:
				return 'error';
		}
	}

	/**
	 * Get admin notices unique ID.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return string Admin notices unique ID.
	 */
	private function get_notices_unique_id() {
		return sprintf(
			'replicast_notices_user_%s',
			\wp_get_current_user()->ID
		);
	}

	/**
	 * Get admin notices.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array Admin notices.
	 */
	private function get_notices() {

		$notices = \get_transient( $this->get_notices_unique_id() );

		if ( false === $notices ) {
			return array();
		}

		return (array) $notices;
	}

	/**
	 * Delete admin notices.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return bool True if successful, false otherwise.
	 */
	private function delete_notices() {
		return \delete_transient( $this->get_notices_unique_id() );
	}
}
