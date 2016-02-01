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

/**
 * Handles object replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
abstract class Request {

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
	 * Post type object.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       object
	 */
	protected $object;

	/**
	 * Post with a REST API compliant schema.
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
	 * Request path.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       string
	 */
	protected $path = '/wp/v2/posts/';

	/**
	 * Attributes for the request.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       array
	 */
	protected $attributes = array();

	/**
	 * Save object handler.
	 *
	 * @since    1.0.0
	 * @param    array    $sites    Array of \Replicast\Model\Site objects.
	 */
	public function handle_save( $sites = array() ) {

		$notices = array();

		// Get replicast object info
		$replicast_ids = $this->get_replicast_info();

		foreach ( $sites as $site_id => $site ) {

			if ( array_key_exists( $site_id, $replicast_ids ) ) {
				$notices[] = $this->put( $site );
			}
			else {
				$notices[] = $this->post( $site );
			}

		}

		// Set admin notices
		if ( ! empty( $notices ) ) {
			$this->set_admin_notice( $notices );
		}

	}

	/**
	 * Delete object handler.
	 *
	 * @since    1.0.0
	 * @param    array    $sites    Array of \Replicast\Model\Site objects.
	 */
	public function handle_delete( $sites = array() ) {

		$notices = array();

		// Get replicast object info
		$replicast_ids = $this->get_replicast_info();

		foreach ( $sites as $site_id => $site ) {

			if ( empty( $replicast_ids ) ) {
				break;
			}

			if ( array_key_exists( $site_id, $replicast_ids ) ) {
				$notices[] = $this->delete( $site );
			}

		}

		// Set admin notices
		if ( ! empty( $notices ) ) {
			$this->set_admin_notice( $notices );
		}

	}

	/**
	 * Get object from a site.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Model\Site    $site    Site object.
	 */
	abstract public function get( $site );

	/**
	 * Create object on a site.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Model\Site    $site    Site object.
	 */
	abstract public function post( $site );

	/**
	 * Update object on a site.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Model\Site    $site    Site object.
	 */
	abstract public function put( $site );

	/**
	 * Delete object from a site.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Model\Site    $site    Site object.
	 */
	abstract public function delete( $site );

	/**
	 * Prepares a object for a given method.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @param     string                   $method    Request method.
	 * @param     \Replicast\Model\Site    $site      Site object.
	 * @return    array|null                          Prepared object data.
	 */
	protected function prepare_body( $method, $site ) {

		switch ( $method ) {
			case Request::READABLE:
				return;

			case Request::CREATABLE:
				return $this->prepare_body_for_create( $site );

			case Request::EDITABLE:
			case Request::DELETABLE:
				return $this->prepare_body_for_update( $site );

		}

		return;
	}

	/**
	 * Prepares an object for creation.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Prepared object data.
	 */
	protected function prepare_body_for_create( $site ) {

		// Get object data
		$data = $this->data;

		// Remove object ID
		unset( $data['id'] );

		return \apply_filters( "replicast_prepare_{$data['type']}_for_create", $data );
	}

	/**
	 * Prepares an object for update or deletion.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Prepared object data.
	 */
	protected function prepare_body_for_update( $site ) {

		// Get object data
		$data = $this->data;

		// Get replicast object info
		$replicast_ids = $this->get_replicast_info();

		// Get remote object ID
		$object_id = $replicast_ids[ $site->get_id() ];

		// Update object ID
		$data['id'] = $object_id;

		return \apply_filters( "replicast_prepare_{$data['type']}_for_update", $data );
	}

	/**
	 * Wrap an object in a REST API compliant schema.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @return    array    Post data.
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
		$request = new \WP_REST_Request( $this->method, \trailingslashit( $this->path ) . $this->object->ID );
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

		return hash( \apply_filters( 'replicast_key_auth_signature_algo', 'sha256' ), json_encode( $args ) . $config['api_secret'] );
	}

	/**
	 * Do a REST request.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @param     string    $method                      Request method.
	 * @param     \Replicast\Model\Site    $site         Site object.
	 * @return    \Psr\Http\Message\ResponseInterface    Response.
	 */
	protected function do_request( $method = 'GET', $site ) {

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
		if ( $method !== Request::CREATABLE && empty( $data['id'] ) ) {
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

		// Build endpoint for GET, PUT and DELETE
		// FIXME: this has to be more bulletproof!
		if ( $method !== Request::CREATABLE ) {
			$config['api_url'] = \trailingslashit( $config['api_url'] ) . $data['id'];
		}

		// WP REST API doesn't expect a PUT
		if ( $method === Request::EDITABLE ) {
			$method = 'POST';
		}

		// Generate request signature
		$signature = $this->generate_signature( $method, $config, $timestamp );

		// Send a request
		return $site->get_client()->request( $method, $config['api_url'], array(
			'headers' => array(
				'X-API-KEY'       => $config['apy_key'],
				'X-API-TIMESTAMP' => $timestamp,
				'X-API-SIGNATURE' => $signature,
			),
			'json' => $data
		) );

	}

	/**
	 * Set admin notices.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @param     array    $notices    Array of notices.
	 */
	protected function set_admin_notice( $notices ) {

		$current_user = \wp_get_current_user();
		$rendered     = array();

		foreach ( $notices as $notice ) {

			$status_code   = ! empty( $notice['status_code'] )   ? $notice['status_code']   : '';
			$reason_phrase = ! empty( $notice['reason_phrase'] ) ? $notice['reason_phrase'] : '';
			$message       = ! empty( $notice['message'] )       ? $notice['message']       : \__( 'Something went wrong.', 'replicast' );

			if ( defined( 'REPLICAST_DEBUG' ) && REPLICAST_DEBUG &&
				! empty( $status_code ) && ! empty( $reason_phrase ) ) {
				$message = sprintf(
					'%s<br>%s: %s',
					$message,
					$status_code,
					$reason_phrase
				);
			}

			$rendered[] = array(
				'type'    => $this->get_notice_type_by_status_code( $status_code ),
				'message' => $message
			);

		}

		\set_transient( 'replicast_notices_' . $current_user->ID, $rendered, 180 );

	}

	/**
	 * Get the admin notice type based on a HTTP request/response status code.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     string    $status_code    HTTP request/response status code.
	 * @return    string                   Possible values: error | success | warning.
	 */
	private function get_notice_type_by_status_code( $status_code ) {

		$type = 'error';

		if ( defined( 'REPLICAST_DEBUG' ) && REPLICAST_DEBUG ) {
			error_log( 'Status code: ' . $status_code );
		}

		// FIXME
		// Maybe this should be more simpler. For instance, all 2xx status codes should be treated as success.
		// What happens with a 3xx status code?

		switch ( $status_code ) {
			case '200': // Update
			case '201': // Create
				$type = 'success';
				break;
		}

		return $type;
	}

	/**
	 * Retrieve replicast info from an object.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @return    array|bool    The replicast info meta field.
	 */
	protected function get_replicast_info() {
		return \get_metadata( $this->object->post_type, $this->object->ID, Plugin::REPLICAST_IDS, true );
	}

	/**
	 * Update current object with replication info.
	 *
	 * This replication info consists in a pair <site_id, remote_object_id>.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @param     int          $site_id                    Site term ID to where the object was replicated.
	 * @param     int|false    $object_id    (optional)    Post ID on remote site. False if it's for removal (trash).
	 * @return    mixed                                    Returns meta ID if the meta doesn't exist, otherwise
	 *                                                     returns true on success and false on failure.
	 */
	protected function update_replicast_info( $site_id, $object_id = false ) {

		// Get replicast object info
		$replicast_ids = $this->get_replicast_info();

		// Save or delete the remote object info
		if ( $object_id ) {
			$replicast_ids[ $site_id ] = $object_id;
		}
		else {
			unset( $replicast_ids[ $site_id ] );
		}

		return \update_metadata( $this->object->post_type, $this->object->ID, Plugin::REPLICAST_IDS, $replicast_ids );
	}

}
