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

use Replicast\API;
use GuzzleHttp\Promise\FulfilledPromise;

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
		$post_type         = \get_post_type_object( $post->post_type );
		$this->rest_base   = ! empty( $post_type->rest_base ) ? $post_type->rest_base : $post_type->name;
		$this->object_type = $post_type;
		$this->object      = $post;
		$this->data        = $this->get_object_data();
	}

	/**
	 * Handle post terms.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     int            $site_id        Site ID.
	 * @param     object|null    $remote_data    Remote object data.
	 */
	public function update_post_terms( $site_id, $remote_data ) {

		if ( empty( $remote_data->replicast ) ) {
			return;
		}

		if ( empty( $remote_data->replicast->terms ) ) {
			return;
		}

		foreach ( $remote_data->replicast->terms as $term_data ) {

			// Get term object
			$term = \get_term_by( 'slug', $term_data->slug, $term_data->taxonomy );

			if ( ! $term ) {
				return;
			}

			// Update replicast info
			API::update_replicast_info( $term, $site_id, $term_data );

		}

	}

}
