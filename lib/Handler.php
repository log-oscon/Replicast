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

namespace Replicast;

use Replicast\API;
use Replicast\Admin;
use Replicast\Client;
use Replicast\Plugin;

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
	 * @var       string
	 */
	protected $object_type;

	/**
	 * Object data.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       object
	 */
	protected $object;

	/**
	 * Object data in a REST API compliant schema.
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
	 * @param     array                $args    Query string parameters.
	 * @return    \GuzzleHttp\Promise
	 */
	public function get( $site, $args = array() ) {}

	/**
	 * Create object on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @param     array                $args    Query string parameters.
	 * @return    \GuzzleHttp\Promise
	 */
	public function post( $site, $args = array() ) {
		return $this->do_request( Handler::CREATABLE, $site, $args );
	}

	/**
	 * Update object on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @param     array                $args    Query string parameters.
	 * @return    \GuzzleHttp\Promise
	 */
	public function put( $site, $args = array() ) {
		return $this->do_request( Handler::EDITABLE, $site, $args );
	}

	/**
	 * Delete object from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @param     array                $args    Query string parameters.
	 * @return    \GuzzleHttp\Promise
	 */
	public function delete( $site, $args = array() ) {
		return $this->do_request( Handler::DELETABLE, $site, $args );
	}

	/**
	 * Create/update object handler.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    \GuzzleHttp\Promise
	 */
	public function handle_save( $site ) {

		// Get replicast info
		// FIXME: maybe this should be part of the handler to avoid multiple calls
		$replicast_info = API::get_replicast_info( $this->object );

		if ( array_key_exists( $site->get_id(), $replicast_info ) ) {
			return $this->put( $site );
		}

		return $this->post( $site );

	}

	/**
	 * Delete object handler.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @param     bool                 $force   Flag for bypass trash or force deletion.
	 * @return    \GuzzleHttp\Promise
	 */
	public function handle_delete( $site, $force = false ) {
		return $this->delete( $site, array(
			'force' => $force
		) );
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

		// Get object data
		$data = $this->data;

		if ( \is_wp_error( $data ) ) {
			return array();
		}

		// Remove object ID
		if ( ! empty( $data['id'] ) ) {
			unset( $data['id'] );
		}

		// Remove author
		if ( ! empty( $data['author'] ) ) {
			unset( $data['author'] );
		}

		// Check for date_gmt presence
		// Note: date_gmt is necessary for post update and it's zeroed upon deletion
		if ( empty( $data['date_gmt'] ) && ! empty( $data['date'] ) ) {
			$data['date_gmt'] = \mysql_to_rfc3339( $data['date'] );
		}

		// Update featured media ID
		if ( ! empty( $data['featured_media'] ) ) {
			$data = $this->prepare_featured_media( $data, $site );
		}

		// Prepare terms
		$data = $this->prepare_terms( $data, $site );

		// Prepare media
		$data = $this->prepare_media( $data, $site );

		// Prepare data by object type
		switch ( $this->object_type ) {
			case 'page':
				$data = $this->prepare_page( $data, $site );
				break;
			case 'attachment':
				$data = $this->prepare_attachment( $data, $site );
				break;
		}

		/**
		 * Extend data for creation by object type.
		 *
		 * @since     1.0.0
		 * @param     array                Prepared object data.
		 * @param     \Replicast\Client    Site object.
		 * @return    array                Possibly-modified object data.
		 */
		$data = \apply_filters( "replicast_prepare_{$this->object_type}_for_create", $data, $site );

		/**
		 * Extend data for creation.
		 *
		 * @since     1.0.0
		 * @param     array                Prepared object data.
		 * @param     \Replicast\Client    Site object.
		 * @return    array                Possibly-modified object data.
		 */
		return \apply_filters( "replicast_prepare_object_for_create", $data, $site );
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

		// Get object data
		$data = $this->data;

		if ( \is_wp_error( $data ) ) {
			return array();
		}

		// Get replicast info
		$replicast_info = API::get_replicast_info( $this->object );

		if ( empty( $replicast_info ) ) {
			return array();
		}

		// Update object ID
		$data['id'] = $replicast_info[ $site->get_id() ]['id'];

		// Remove author
		if ( ! empty( $data['author'] ) ) {
			unset( $data['author'] );
		}

		// Check for date_gmt presence
		// Note: date_gmt is necessary for post update and it's zeroed upon deletion
		if ( empty( $data['date_gmt'] ) && ! empty( $data['date'] ) ) {
			$data['date_gmt'] = \mysql_to_rfc3339( $data['date'] );
		}

		// Update featured media ID
		if ( ! empty( $data['featured_media'] ) ) {
			$data = $this->prepare_featured_media( $data, $site );
		}

		// Prepare terms
		$data = $this->prepare_terms( $data, $site );

		// Prepare media
		$data = $this->prepare_media( $data, $site );

		// Prepare data by object type
		switch ( $this->object_type ) {
			case 'page':
				$data = $this->prepare_page( $data, $site );
				break;
			case 'attachment':
				$data = $this->prepare_attachment( $data, $site );
				break;
		}

		/**
		 * Extend data for update by object type.
		 *
		 * @since     1.0.0
		 * @param     array                Prepared object data.
		 * @param     \Replicast\Client    Site object.
		 * @return    array                Possibly-modified object data.
		 */
		$data = \apply_filters( "replicast_prepare_{$this->object_type}_for_update", $data, $site );

		/**
		 * Extend data for update.
		 *
		 * @since     1.0.0
		 * @param     array                Prepared object data.
		 * @param     \Replicast\Client    Site object.
		 * @return    array                Possibly-modified object data.
		 */
		return \apply_filters( "replicast_prepare_object_for_update", $data, $site );
	}

	/**
	 * Wrap an object in a REST API compliant schema.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @return    array    The object data.
	 */
	protected function get_object_data() {
		return $this->_do_request();
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
	private function _do_request() {

		global $wp_rest_server;

		if ( empty( $wp_rest_server ) ) {

			/**
			 * Filter the REST Server Class.
			 *
			 * @since    1.0.0
			 * @param    string    The name of the server class. Default '\WP_REST_Server'.
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
	 * @param     array     $args         Query string parameters.
	 * @return    string                  Return hash of the secret.
	 */
	private function generate_signature( $method = 'GET', $config, $timestamp, $args ) {

		$request_uri = $config['api_url'];
		if ( ! empty( $args ) ) {
			$request_uri = sprintf( '%s?%s', $request_uri, http_build_query( $args, null, '&', PHP_QUERY_RFC3986 ) );
		}

		/**
		 * Arguments used for generating the signature.
		 *
		 * They should be in the following order:
		 * 'api_key', 'ip', 'request_method', 'request_post', 'request_uri', 'timestamp'
		 */
		$args = array(
			'api_key'        => $config['apy_key'],
			// 'ip'             => $_SERVER['SERVER_ADDR'],
			'request_method' => $method,
			'request_post'   => array(),
			'request_uri'    => $request_uri,
			'timestamp'      => $timestamp,
		);

		// TODO: find a proper way to use IP in local development
		// if ( defined( 'WP_ENV' ) && WP_ENV === 'development' ) {
		// 	unset( $args['ip'] );
		// }

		error_log(print_r('--- generate_signature', true));
		error_log(print_r($args,true));
		error_log(print_r(hash( 'sha256', json_encode( $args ) . $config['api_secret'] ),true));

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
	 * @param     array                $args      Query string parameters.
	 * @return    \GuzzleHttp\Promise
	 */
	protected function do_request( $method, $site, $args ) {

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
			$config['api_url'] = $config['api_url'] . \trailingslashit( $data['id'] );
		}

		$headers = array();
		$body    = array();

		// Asynchronous request
		if ( $method === static::CREATABLE && $this->object_type === 'attachment' ) {

			$file_path = \get_attached_file( API::get_object_id( $this->object ) );
			$file_name = basename( $file_path );

			$headers['Content-Type']        = $data['mime_type'];
			$headers['Content-Disposition'] = sprintf( 'attachment; filename=%s', $file_name );
			$headers['Content-MD5']         = md5_file( $file_path );

			$body['body'] = file_get_contents( $file_path );

		} else {
			$body['json'] = $data;
		}

		// The WP REST API doesn't expect a PUT
		if ( $method === static::EDITABLE ) {
			$method = 'POST';
		}

		// Generate request signature
		$signature = $this->generate_signature( $method, $config, $timestamp, $args );

		// Auth headers
		$headers['X-API-KEY']       = $config['apy_key'];
		$headers['X-API-TIMESTAMP'] = $timestamp;
		$headers['X-API-SIGNATURE'] = $signature;

		return $site->get_client()->requestAsync(
			$method,
			$config['api_url'],
			array_merge(
				array(
					'headers' => $headers,
					'query'   => $args,
				),
				$body
			)
		);

	}

	/**
	 * Update object with remote ID.
	 *
	 * @since     1.0.0
	 * @param     int       $site_id    Site ID.
	 * @param     object    $data       Object data.
	 */
	abstract protected function update_object( $site_id, $data = null );

	/**
	 * Update terms with remote IDs.
	 *
	 * @since     1.0.0
	 * @param     int       $site_id    Site ID.
	 * @param     object    $data       Object data.
	 */
	abstract protected function update_terms( $site_id, $data = null );

	/**
	 * Update media with remote IDs.
	 *
	 * @since     1.0.0
	 * @param     int       $site_id    Site ID.
	 * @param     object    $data       Object data.
	 */
	abstract protected function update_media( $site_id, $data = null );

}
