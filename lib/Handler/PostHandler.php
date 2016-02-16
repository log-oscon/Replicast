<?php

/**
 * Handles ´post´ content type replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 */

namespace Replicast\Handler;

use \Replicast\Handler\CategoryHandler;
use \Replicast\Handler\TagHandler;
use \GuzzleHttp\Exception\RequestException;

/**
 * Handles ´post´ content type replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class PostHandler extends Handler {

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
			$response = $this->do_request( Handler::CREATABLE, $site );

			// Get the remote object data
			$remote_object = json_decode( $response->getBody()->getContents() );

			if ( $remote_object ) {

				// Update replicast info
				$this->update_replicast_info( $site, $remote_object );

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
							\esc_url( $remote_object->link ),
							\esc_attr( $site->get_name() ),
							\__( 'View post', 'replicast' )
						)
					)
				);

			}

		} catch ( RequestException $ex ) {
			if ( $ex->hasResponse() ) {
				return array(
					'status_code'   => $ex->getResponse()->getStatusCode(),
					'reason_phrase' => $ex->getResponse()->getReasonPhrase(),
					'message'       => $ex->getMessage()
				);
			}
		} catch ( \Exception $ex ) {
			return array(
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

			// $this->handle_post_terms( $site );

			// Do request
			$response = $this->do_request( Handler::EDITABLE, $site );

			// Get the remote object data
			$remote_object = json_decode( $response->getBody()->getContents() );

			if ( $remote_object ) {

				// Update replicast info
				$this->update_replicast_info( $site, $remote_object );

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
							\esc_url( $remote_object->link ),
							\esc_attr( $site->get_name() ),
							\__( 'View post', 'replicast' )
						)
					)
				);

			}

		} catch ( RequestException $ex ) {
			if ( $ex->hasResponse() ) {
				return array(
					'status_code'   => $ex->getResponse()->getStatusCode(),
					'reason_phrase' => $ex->getResponse()->getReasonPhrase(),
					'message'       => $ex->getMessage()
				);
			}
		} catch ( \Exception $ex ) {
			return array(
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
			$response = $this->do_request( Handler::DELETABLE, $site );

			// Get the remote object data
			$remote_object = json_decode( $response->getBody()->getContents() );

			if ( $remote_object ) {

				// The API returns 'publish' but we force the status to be 'trash' for better
				// management of the next actions over the object. Like, recovering (PUT request)
				// or permanently delete the object from remote location.
				$remote_object->status = 'trash';

				// Update replicast info
				$this->update_replicast_info( $site, $remote_object );

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
				return array(
					'status_code'   => $ex->getResponse()->getStatusCode(),
					'reason_phrase' => $ex->getResponse()->getReasonPhrase(),
					'message'       => $ex->getMessage()
				);
			}
		} catch ( \Exception $ex ) {
			return array(
				'message' => $ex->getMessage()
			);
		}

		return $result;

	}

}
