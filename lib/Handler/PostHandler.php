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
	 * Prepare page for create, update or delete.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared page data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified page data.
	 */
	public function prepare_page( $data, $site ) {

		// Unset page template if empty
		// @see https://github.com/WP-API/WP-API/blob/develop/lib/endpoints/class-wp-rest-posts-controller.php#L1553
		if ( empty( $data['template'] ) ) {
			unset( $data['template'] );
		}

		return $data;
	}

	/**
	 * Prepare attachment for create, update or delete.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared attachment data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified attachment data.
	 */
	public function prepare_attachment( $data, $site ) {

		// Update attachment status based on the "uploaded to" post status, if exists
		if ( ! empty( $data['status'] ) && $data['status'] === 'inherit' ) {
			$data['status'] = ! empty( $data['post'] ) ? \get_post_status( $data['post'] ) : 'publish';
		}

		// Update the "uploaded to" post ID with the associated remote post ID, if exists
		if ( ! empty( $data['post'] ) ) {

			// Get replicast info
			$replicast_info = API::get_replicast_info( \get_post( $data['post'] ) );

			$data['post'] = $replicast_info[ $site->get_id() ]['id'];

		}

		return $data;
	}

	/**
	 * Prepare post terms.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared page data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified terms.
	 */
	public function prepare_post_terms( $data, $site ) {

		unset( $data['categories'] );
		unset( $data['tags'] );

		if ( empty( $data['replicast'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['term'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['term'] as $key => $term ) {

			// Get replicast info
			$replicast_info = API::get_replicast_info( $term );

			// Update object ID
			$term->term_id = '';

			if ( ! empty( $replicast_info ) ) {
				$term->term_id = $replicast_info[ $site->get_id() ]['id'];
			}

			$data['replicast']['term'][ $key ] = $term;

			// Check if term has children
			if ( empty( $term->children ) ) {
				continue;
			}

			$this->prepare_post_child_terms( $term->term_id, $data['replicast']['term'][ $key ]->children, $site );

		}

		return $data;
	}

	/**
	 * Prepare post child terms.
	 *
	 * @since     1.0.0
	 * @param     int                  $parent_id    The parent term ID.
	 * @param     array                $terms        The term data.
	 * @param     \Replicast\Client    $site         Site object.
	 * @return    array                              Possibly-modified child terms.
	 */
	private function prepare_post_child_terms( $parent_id, &$terms, $site ) {

		foreach ( $terms as $key => $term ) {

			// Get replicast info
			$replicast_info = API::get_replicast_info( $term );

			// Update object ID's
			$term->term_id = '';
			$term->parent  = '';

			if ( ! empty( $replicast_info ) ) {
				$term->term_id = $replicast_info[ $site->get_id() ]['id'];
				$term->parent  = $parent_id;
			}

			$terms[ $key ] = $term;

			// Check if term has children
			if ( empty( $term->children ) ) {
				continue;
			}

			$this->prepare_post_child_terms( $term->term_id, $terms[ $key ]->children, $site );

		}

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

		if ( empty( $data->replicast->term ) ) {
			return;
		}

		foreach ( $data->replicast->term as $term_data ) {

			// Get term object
			$term = \get_term_by( 'id', $term_data->term_id, $term_data->taxonomy );

			if ( ! $term ) {
				return;
			}

			// Update replicast info
			API::update_replicast_info( $term, $site_id, $term_data );

		}

	}

}
