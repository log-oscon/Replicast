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
	 * @param     array                $data    Prepared attachment data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified attachment data.
	 */
	public function prepare_post_terms( $data, $site ) {

		if ( empty( $data['_embedded'] ) ) {
			return $data;
		}

		if ( empty( $data['_embedded']['https://api.w.org/term'] ) ) {
			return $data;
		}

		foreach ( $data['_embedded']['https://api.w.org/term'] as $term_link_key => $term_link ) {
			foreach ( $term_link as $term_data_key => $term_data ) {

				// Get term object
				$term = \get_term( $term_data['id'], $term_data['taxonomy'] );

				if ( ! API::is_term( $term ) ) {
					continue;
				}

				if ( in_array( $term->slug, array( 'uncategorized', 'untagged' ) ) ) {
					unset( $data['_embedded']['https://api.w.org/term'][ $term_link_key ][ $term_data_key ] );
				}

				// Get replicast info
				$replicast_info = API::get_replicast_info( $term );

				// Update object ID
				$data['_embedded']['https://api.w.org/term'][ $term_link_key ][ $term_data_key ]['id'] = '';
				if ( ! empty( $replicast_info ) ) {
					$data['_embedded']['https://api.w.org/term'][ $term_link_key ][ $term_data_key ]['id'] = $replicast_info[ $site->get_id() ]['id'];
				}

			}
		}

		return $data;
	}

}
