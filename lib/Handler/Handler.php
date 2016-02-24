<?php

/**
 * Handles object replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 */

namespace Replicast\Handler;

use Replicast\Admin;
use Replicast\Client;
use Replicast\Plugin;
use Replicast\API;
use GuzzleHttp\Promise\FulfilledPromise;

/**
 * Handles object replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
abstract class Handler {

	/**
	 * Alias for GET method.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const READABLE = 'GET';

	/**
	 * Alias for POST method.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const CREATABLE = 'POST';

	/**
	 * Alias for PUT method.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const EDITABLE = 'PUT';

	/**
	 * Alias for DELETE method.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const DELETABLE = 'DELETE';

	/**
	 * Object type.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       object
	 */
	protected $object;

	/**
	 * Object with a REST API compliant schema.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       array
	 */
	protected $data = array();

	/**
	 * Request method.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       string
	 */
	protected $method = 'GET';

	/**
	 * The namespace of the request route.
	 *
	 * @var string
	 */
	protected $namespace = 'wp/v2';

	/**
	 * The base of the request route.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       string
	 */
	protected $rest_base = 'posts';

	/**
	 * Attributes for the request.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       array
	 */
	protected $attributes = array();

	/**
	 * Get object from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function get( $site ) {}

	/**
	 * Create object on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function post( $site ) {
		return $this->do_request( Handler::CREATABLE, $site );
	}

	/**
	 * Update object on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function put( $site ) {
		return $this->do_request( Handler::EDITABLE, $site );
	}

	/**
	 * Delete object from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function delete( $site ) {
		return $this->do_request( Handler::DELETABLE, $site );
	}

	/**
	 * Create/update object handler.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function handle_update( $site ) {

		// Get replicast object info
		$replicast_info = API::get_replicast_info( $this->object );

		// Verify that the current object has been "removed" (aka unchecked) from any site(s)
		// FIXME: review this later on
		// foreach ( $replicast_info as $site_id => $replicast_data ) {
		// 	if ( ! array_key_exists( $site_id, $sites ) && $replicast_data['status'] !== 'trash' ) {
		// 		$notices[] = $this->delete( Admin::get_site( $site_id ) );
		// 	}
		// }

		if ( array_key_exists( $site->get_id(), $replicast_info ) ) {
			return $this->put( $site );
		}

		return $this->post( $site );
	}

	/**
	 * Delete object handler.
	 *
	 * @since    1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function handle_delete( $site ) {

		// Get replicast object info
		$replicast_info = API::get_replicast_info( $this->object );

		if ( array_key_exists( $site->get_id(), $replicast_info ) ) {
			return $this->delete( $site );
		}

		// TODO: return message
		return new FulfilledPromise( \__( '', 'replicast' ) );
	}

	/**
	 * Prepares a object for a given method.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @param     string               $method    Request method.
	 * @param     \Replicast\Client    $site      Site object.
	 * @return    array|null                      Prepared object data.
	 */
	protected function prepare_body( $method, $site ) {

		switch ( $method ) {
			case static::READABLE:
				return;

			case static::CREATABLE:
				return $this->prepare_body_for_create( $site );

			case static::EDITABLE:
			case static::DELETABLE:
				return $this->prepare_body_for_update( $site );

		}

		return;
	}

	/**
	 * Prepares an object for creation.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Prepared object data.
	 */
	protected function prepare_body_for_create( $site ) {

		$object_type = API::get_object_type( $this->object );

		// Get object data
		$data = $this->data;

		if ( \is_wp_error( $data ) ) {
			return array();
		}

		// Remove object ID
		if ( ! empty( $data['id'] ) ) {
			unset( $data['id'] );
		}

		/**
		 * Filter the prepared object data for creation.
		 *
		 * @since    1.0.0
		 * @param    array    Prepared object data.
		 */
		return \apply_filters( "replicast_prepare_{$object_type}_for_create", $data );
	}

	/**
	 * Prepares an object for update or deletion.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Prepared object data.
	 */
	protected function prepare_body_for_update( $site ) {

		$object_type = API::get_object_type( $this->object );

		// Get object data
		$data = $this->data;

		if ( \is_wp_error( $data ) ) {
			return array();
		}

		// Get replicast object info
		$replicast_info = API::get_replicast_info( $this->object );

		// Get remote object
		$object = $replicast_info[ $site->get_id() ];

		// Update object ID
		$data['id'] = $object['id'];

		// Check for date_gmt presence
		// Note: date_gmt is necessary for post update and it's zeroed upon deletion
		if ( empty( $data['date_gmt'] ) && ! empty( $data['date'] ) ) {
			$data['date_gmt'] = \mysql_to_rfc3339( $data['date'] );
		}

		/**
		 * Filter the prepared object data for update.
		 *
		 * @since    1.0.0
		 * @param    array    Prepared object data.
		 */
		return \apply_filters( "replicast_prepare_{$object_type}_for_update", $data );
	}

	/**
	 * Wrap an object in a REST API compliant schema.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @return    array    The object data.
	 */
	protected function get_object_data() {
		return $this->rest_do_request();
	}

	/**
	 * Do an internal REST request.
	 *
	 * @global    \WP_REST_Server    $wp_rest_server    ResponseHandler instance (usually \WP_REST_Server).
	 *
	 * @since     1.0.0
	 * @access    private
	 * @return    \WP_REST_Response    Response object.
	 */
	private function rest_do_request() {

		global $wp_rest_server;

		if ( empty( $wp_rest_server ) ) {

			/**
			 * Filter the REST Server Class.
			 *
			 * @since    1.0.0
			 * @param    string    $class_name    The name of the server class. Default '\WP_REST_Server'.
			 */
			$wp_rest_server_class = \apply_filters( 'replicast_rest_server_class', '\WP_REST_Server' );
			$wp_rest_server       = new $wp_rest_server_class;

			/**
			 * Fires when preparing to serve an API request.
			 *
			 * @since    1.0.0
			 * @param    \WP_REST_Server    $wp_rest_server    Server object.
			 */
			\do_action( 'rest_api_init', $wp_rest_server );

		}

		// Request attributes
		$attributes = array_merge(
			array(
				'context' => 'edit',
				'_embed'  => true,
			),
			$this->attributes
		);

		// Build request
		$request = new \WP_REST_Request(
			$this->method,
			sprintf(
				'/%s/%s',
				$this->namespace,
				\trailingslashit( $this->rest_base ) . API::get_object_id( $this->object )
			)
		);

		foreach ( $attributes as $k => $v ) {
			$request->set_param( $k, $v );
		}

		// Make request
		$result = $wp_rest_server->dispatch( $request );

		if ( $result->is_error() ) {
			return $result->as_error();
		}

		// Force the return of embeddable data like featured image, terms, etc.
		return $wp_rest_server->response_to_data( $result, ! empty( $attributes['_embed'] ) );
	}

	/**
	 * Generate a hash signature.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     string    $method       Request method.
	 * @param     array     $config       Request config.
	 * @param     int       $timestamp    Request timestamp.
	 * @return    string                  Return hash of the secret.
	 */
	private function generate_signature( $method = 'GET', $config, $timestamp ) {

		/**
		 * Arguments used for generating the signature.
		 *
		 * They should be in the following order:
		 * 'api_key', 'ip', 'request_method', 'request_post', 'request_uri', 'timestamp'
		 */
		$args = array(
			'api_key'        => $config['apy_key'],
			'ip'             => $_SERVER['SERVER_ADDR'],
			'request_method' => $method,
			'request_post'   => array(),
			'request_uri'    => $config['api_url'],
			'timestamp'      => $timestamp,
		);

		// TODO: find a proper way to use IP in local development
		if ( defined( 'WP_ENV' ) && WP_ENV === 'development' ) {
			unset( $args['ip'] );
		}

		/**
		 * Filter the name of the selected hashing algorithm (e.g. "md5", "sha256", "haval160,4", etc..).
		 *
		 * @since    1.0.0
		 * @param    string    Name of the selected hashing algorithm.
		 */
		return hash( \apply_filters( 'replicast_key_auth_signature_algo', 'sha256' ), json_encode( $args ) . $config['api_secret'] );
	}

	/**
	 * Do a REST request.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @param     string               $method    Request method.
	 * @param     \Replicast\Client    $site      Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	protected function do_request( $method, $site ) {

		// Bail out if the site is invalid
		if ( ! $site->is_valid() ) {
			throw new \Exception( sprintf(
				\__( 'The site with ID %s is not valid. Check if all the required fields are filled.', 'replicast' ),
				$site->get_id()
			) );
		}

		// Prepare post for replication
		$data = $this->prepare_body( $method, $site );

		// Bail out if the object ID doesn't exist
		if ( $method !== static::CREATABLE && empty( $data['id'] ) ) {
			throw new \Exception( sprintf(
				\__( 'The %s request cannot be made for a content type without an ID.', 'replicast' ),
				$method
			) );
		}

		// Generate an API timestamp.
		// This timestamp is also used to generate the request signature.
		$timestamp = time();

		// Get site config
		$config = $site->get_config();

		// Add request path to endpoint
		$config['api_url'] = $config['api_url'] . \trailingslashit( $this->rest_base );

		// Build endpoint for GET, PUT and DELETE
		// FIXME: this has to be more bulletproof!
		if ( $method !== static::CREATABLE ) {
			$config['api_url'] = \trailingslashit( $config['api_url'] ) . $data['id'];
		}

		// WP REST API doesn't expect a PUT
		if ( $method === static::EDITABLE ) {
			$method = 'POST';
		}

		// Generate request signature
		$signature = $this->generate_signature( $method, $config, $timestamp );

		// Request headers
		$headers = array(
			'X-API-KEY'       => $config['apy_key'],
			'X-API-TIMESTAMP' => $timestamp,
			'X-API-SIGNATURE' => $signature,
		);


		// Asynchronous request
		return $site->get_client()->requestAsync(
			$method,
			$config['api_url'],
			array(
				'headers' => $headers,
				'json'    => $data
			)
		);

	}

	/**
	 * Update current object with replication info.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site                         Site object.
	 * @param     object|null          $remote_data    (optional)    Remote object data. Null if it's for permanent delete.
	 * @return    mixed                                              Returns meta ID if the meta doesn't exist, otherwise
	 *                                                               returns true on success and false on failure.
	 */
	public function handle_replicast_info( $site, $remote_post ) {
		return API::update_replicast_info( $this->object, $site->get_id(), $remote_post );
	}

}
