<?php

/**
 * Handles ´category´ content type replication
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 */

namespace Replicast\Handler;

use GuzzleHttp\Exception\RequestException;

/**
 * Handles ´category´ content type replication.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Handler
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class CategoryHandler extends Handler {

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \WP_Term    $term    Term object.
	 */
	public function __construct( \WP_Term $term ) {
		$this->rest_base  = 'categories';
		$this->object     = $term;
		$this->attributes = array( 'context' => 'embed' );
		$this->data       = $this->get_object_data();
	}

	/**
	 * Get category from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Response object.
	 */
	public function get( $site ) {}

	/**
	 * Create category on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Response object.
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
	 * Update category on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Response object.
	 */
	public function put( $site ) {

		$result = array();

		try {

			// Do request
			$response = $this->do_request( Handler::EDITABLE, $site );

			// Get the remote object data
			$remote_object = json_decode( $response->getBody()->getContents() );

			if ( $remote_object ) {

				// Update replicast info
				$this->update_replicast_info( $site, $remote_object );

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
	 * Delete category from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Response object.
	 */
	public function delete( $site ) {}

}
