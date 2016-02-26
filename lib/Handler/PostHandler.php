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
	 * Prepare post terms.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared page data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified page data.
	 */
	public function prepare_post_terms( $data, $site ) {

		if ( empty( $data['replicast'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['term'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['term'] as $key => $term ) {

			if ( in_array( $term->slug, array( 'uncategorized', 'untagged' ) ) ) {
				unset( $data['replicast']['term'][ $key ] );
			}

			// Get replicast info
			$replicast_info = API::get_replicast_info( $term );

			// Update object ID
			$term->term_id = '';
			if ( ! empty( $replicast_info ) ) {
				$term->term_id = $replicast_info[ $site->get_id() ]['id'];
			}

			$data['replicast']['term'][ $key ] = $term;

		}

		return $data;
	}

	/**
	 * Update post terms.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     int            $site_id    Site ID.
	 * @param     object|null    $data       Object data.
	 */
	public function update_post_terms( $site_id, $data ) {

		if ( empty( $data->replicast ) ) {
			return;
		}

		if ( empty( $data->replicast->terms ) ) {
			return;
		}

		foreach ( $data->replicast->terms as $term_data ) {

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
