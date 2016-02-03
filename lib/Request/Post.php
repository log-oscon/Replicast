<?php

/**
 * Handles ´post´ content type replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Request
 */

namespace Replicast\Request;

use Replicast\Request;
use GuzzleHttp\Exception\RequestException;

/**
 * Handles ´post´ content type replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Request
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Post extends Request {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \WP_Post    $post    Post object.
	 */
	public function __construct( \WP_Post $post ) {
		$this->rest_base = 'posts';
		$this->object    = $post;
		$this->data      = $this->get_object_data();
	}

	/**
	 * Get post from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Response object.
	 */
	public function get( $site ) {}

	/**
	 * Create post on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Response object.
	 */
	public function post( $site ) {

		$result = array();

		try {

			// Do request
			$response = $this->do_request( Request::CREATABLE, $site );

			// Get the replicated data
			$replicated_data = json_decode( $response->getBody()->getContents() );

			if ( $replicated_data ) {

				// Add replicast info
				$this->update_replicast_info( $site, $replicated_data->id );

				$result = array(
					'status_code'   => $response->getStatusCode(),
					'reason_phrase' => $response->getReasonPhrase(),
					'message'       => sprintf(
						'%s %s',
						sprintf(
							\__( 'Post published on %s.', 'replicast' ),
							$site->get_name()
						),
						sprintf(
							'<a href="%s" title="%s" target="_blank">%s</a>',
							\esc_url( $replicated_data->link ),
							\esc_attr( $site->get_name() ),
							\__( 'View post', 'replicast' )
						)
					)
				);

			}

		} catch ( RequestException $ex ) {
			if ( $ex->hasResponse() ) {
				$result = array(
					'status_code'   => $ex->getResponse()->getStatusCode(),
					'reason_phrase' => $ex->getResponse()->getReasonPhrase(),
					'message'       => $ex->getMessage()
				);
			}
		} catch ( \Exception $ex ) {
			$result = array(
				'message' => $ex->getMessage()
			);
		}

		return $result;
	}

	/**
	 * Update post on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Response object.
	 */
	public function put( $site ) {

		$result = array();

		try {

			// Do request
			$response = $this->do_request( Request::EDITABLE, $site );

			// Get the replicated data
			$replicated_data = json_decode( $response->getBody()->getContents() );

			if ( $replicated_data ) {

				// Update replicast info
				$this->update_replicast_info( $site, $replicated_data->id );


				$result = array(
					'status_code'   => $response->getStatusCode(),
					'reason_phrase' => $response->getReasonPhrase(),
					'message'       => sprintf(
						'%s %s',
						sprintf(
							\__( 'Post updated on %s.', 'replicast' ),
							$site->get_name()
						),
						sprintf(
							'<a href="%s" title="%s" target="_blank">%s</a>',
							\esc_url( $replicated_data->link ),
							\esc_attr( $site->get_name() ),
							\__( 'View post', 'replicast' )
						)
					)
				);

			}

		} catch ( RequestException $ex ) {
			if ( $ex->hasResponse() ) {
				$result = array(
					'status_code'   => $ex->getResponse()->getStatusCode(),
					'reason_phrase' => $ex->getResponse()->getReasonPhrase(),
					'message'       => $ex->getMessage()
				);
			}
		} catch ( \Exception $ex ) {
			$result = array(
				'message' => $ex->getMessage()
			);
		}

		return $result;
	}

	/**
	 * Delete post from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Response object.
	 */
	public function delete( $site ) {

		$result = array();

		try {

			// Do request
			$response = $this->do_request( Request::DELETABLE, $site );

			// Get the replicated data
			$replicated_data = json_decode( $response->getBody()->getContents() );

			if ( $replicated_data ) {

				// Clear replicast info
				// FIXME: this only should be done on permanent delete
				// $this->update_replicast_info( $site );

				$result = array(
					'status_code'   => $response->getStatusCode(),
					'reason_phrase' => $response->getReasonPhrase(),
					'message'       => sprintf(
						\__( 'Post trashed on %s.', 'replicast' ),
						$site->get_name()
					)
				);

			}

		} catch ( RequestException $ex ) {
			if ( $ex->hasResponse() ) {
				$result = array(
					'status_code'   => $ex->getResponse()->getStatusCode(),
					'reason_phrase' => $ex->getResponse()->getReasonPhrase(),
					'message'       => $ex->getMessage()
				);
			}
		} catch ( \Exception $ex ) {
			$result = array(
				'message' => $ex->getMessage()
			);
		}

		return $result;

	}

}
