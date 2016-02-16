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

use \GuzzleHttp\Exception\RequestException;

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
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Response object.
	 */
	public function get( $site ) {}

	/**
	 * Create category on a site.
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

error_log(print_r($response,true));

			error_log('2');

			// Get the remote object data
			$remote_object = json_decode( $response->getBody()->getContents() );

			if ( $remote_object ) {

				// Update replicast info
				// $this->update_replicast_info( $site, $remote_object );

				// $result = array(
				// 	'status_code'   => $response->getStatusCode(),
				// 	'reason_phrase' => $response->getReasonPhrase(),
				// 	'message'       => sprintf(
				// 		'%s %s',
				// 		sprintf(
				// 			\__( 'Post published on %s.', 'replicast' ),
				// 			$site->get_name()
				// 		),
				// 		sprintf(
				// 			'<a href="%s" title="%s" target="_blank">%s</a>',
				// 			\esc_url( $replicated_data->link ),
				// 			\esc_attr( $site->get_name() ),
				// 			\__( 'View post', 'replicast' )
				// 		)
				// 	)
				// );

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
	 * Update category on a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Response object.
	 */
	public function put( $site ) {}

	/**
	 * Delete category from a site.
	 *
	 * @since     1.0.0
	 * @param     \Replicast\Model\Site    $site    Site object.
	 * @return    array                             Response object.
	 */
	public function delete( $site ) {}

}
