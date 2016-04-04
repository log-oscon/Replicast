<?php

/**
 * Add ACF support
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 */

namespace Replicast;

use Replicast\Admin;
use Replicast\API;

/**
 * Add ACF support.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class ACF {

	/**
	 * Identifies the meta variable that is sent to the remote site and
	 * that contains information regarding the remote object ACF meta.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const REPLICAST_ACF_INFO = '_replicast_acf_info';

	/**
	 * The plugin's instance.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @var       \Replicast\Plugin    This plugin's instance.
	 */
	private $plugin;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    \Replicast\Plugin    $plugin    This plugin's instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 *
	 * @since    1.0.0
	 */
	public function register() {

		\add_filter( 'replicast_expose_object_protected_meta', array( $this, 'expose_object_protected_meta' ), 10 );
		\add_filter( 'replicast_suppress_object_meta',         array( $this, 'suppress_object_meta' ), 10 );

		\add_filter( 'acf/update_value/type=relationship',  array( $this, 'get_relations' ), 10, 3 );
		\add_filter( 'replicast_prepare_object_for_create', array( $this, 'prepare_relations' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update', array( $this, 'prepare_relations' ), 10, 2 );

		\add_filter( 'replicast_get_object_meta',           array( $this, 'get_meta' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_create', array( $this, 'prepare_meta' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update', array( $this, 'prepare_meta' ), 10, 2 );

		\add_filter( 'replicast_get_object_media',          array( $this, 'get_media' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_create', array( $this, 'prepare_media' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update', array( $this, 'prepare_media' ), 10, 2 );
		\add_action( 'replicast_update_object_media',       array( $this, 'update_media' ), 10, 2 );

	}

	/**
	 * Expose \Replicast\ACF protected meta keys.
	 *
	 * @since     1.0.0
	 * @return    array    Exposed meta keys.
	 */
	public function expose_object_protected_meta() {
		return array( static::REPLICAST_ACF_INFO );
	}

	/**
	 * Suppress \Replicast\ACF meta keys.
	 *
	 * @since     1.0.0
	 * @return    array     Suppressed meta keys.
	 */
	public function suppress_object_meta() {
		return array( static::REPLICAST_ACF_INFO );
	}

	/**
	 * Adds persistence to removed relations.
	 *
	 * When a relationship between one or more objects is removed, this change is not synced via REST API.
	 * Leaving these same relationships remotely unchanged. To address this, we are sending the objects
	 * that were removed in a private meta variable which is then processed.
	 *
	 * @since     1.0.0
	 * @param     mixed    $value      The value of the field.
	 * @param     int      $post_id    The object ID.
	 * @param     array    $field      The field object.
	 * @return    mixed                Possibly-modified value of the field.
	 */
	public function get_relations( $value, $post_id, $field ) {

		// Bail out if not admin and bypass REST API requests
		if ( ! \is_admin() ) {
			return $value;
		}

		// If post is an autosave, return
		if ( \wp_is_post_autosave( $post_id ) ) {
			return $value;
		}

		// If post is a revision, return
		if ( \wp_is_post_revision( $post_id ) ) {
			return $value;
		}

		if ( ! $field ) {
			return $value;
		}

		$field_name    = $field['name'];
		$prev_relation = \get_field( $field_name, $post_id );
		$next_relation = ! empty( $value ) ? $value : array(); // This only contains object ID's
		$ids_to_remove = array();

		if ( $prev_relation ) {
			foreach ( $prev_relation as $key => $selected_post ) {

				$selected_post_id = $selected_post;

				if ( is_object( $selected_post ) ) {
					$selected_post_id = $selected_post->ID;
				}

				if ( ! in_array( $selected_post_id, $next_relation ) ) {
					$ids_to_remove[] = $selected_post_id;
				}

			}
		}

		// Get meta
		$meta = \get_post_meta( $post_id, static::REPLICAST_ACF_INFO, true );

		if ( ! $meta ) {
			$meta = array();
		}

		// Add meta persistence
		\update_post_meta(
			$post_id,
			static::REPLICAST_ACF_INFO,
			array_merge( $meta, array(
				$field_name => $ids_to_remove
			) ),
			$meta
		);

		return $value;
	}

	/**
	 * Prepare removed relations.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified data.
	 */
	public function prepare_relations( $data, $site ) {

		if ( empty( $data['replicast'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['meta'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['meta'][ static::REPLICAST_ACF_INFO ] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['meta'][ static::REPLICAST_ACF_INFO ] as $meta_values ) {

			$meta_values   = \maybe_unserialize( $meta_values );
			$prepared_meta = array();

			foreach ( $meta_values as $meta_key => $meta_value ) {
				foreach ( $meta_value as $related_post_id ) {

					// Get replicast info
					$replicast_info = API::get_replicast_info( \get_post( $related_post_id ) );

					// Update object ID
					if ( ! empty( $replicast_info ) ) {
						$prepared_meta[ $meta_key ][] = $replicast_info[ $site->get_id() ]['id'];
					}

				}

			}

			if ( ! empty( $prepared_meta ) && is_array( $prepared_meta ) ) {
				$prepared_meta = \maybe_serialize( $prepared_meta );
			}

			unset( $data['replicast']['meta'][ static::REPLICAST_ACF_INFO ] );
			$data['replicast']['meta'][ static::REPLICAST_ACF_INFO ][] = $prepared_meta;
		}

		return $data;
	}

	/**
	 * Retrieve ACF meta.
	 *
	 * @since     1.0.0
	 * @param     array     $values     Object meta.
	 * @param     int       $post_id    The object ID.
	 * @return    array                 Possibly-modified object meta.
	 */
	public function get_meta( $values, $post_id ) {

		$prepared_meta = array();

		foreach ( $values as $meta_key => $meta_value ) {

			/**
			 * If it's an ACF field, add more information regarding the field.
			 *
			 * The raw/rendered keys are used just to identify what's an ACF meta value ahead in the
			 * replication process.
			 *
			 * FIXME: I don't know if it's a good idea use the raw/rendered keys like the core uses
			 *        with posts and pages. Maybe we should use some key that relates to ACF?
			 */
			if ( $field = \get_field_object( $meta_key, $post_id ) ) {
				$prepared_meta[ $meta_key ] = array(
					'raw'      => $field,
					'rendered' => $meta_value,
				);
			} else {
				$prepared_meta[ $meta_key ] = $meta_value;
			}

		}

		return $prepared_meta;
	}

	/**
	 * Prepare ACF meta.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified data.
	 */
	public function prepare_meta( $data, $site ) {

		if ( empty( $data['replicast'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['meta'] ) ) {
			return $data;
		}

		/**
		 * Filter for suppressing ACF meta by field type.
		 *
		 * @since     1.0.0
		 * @param     array    Name of the suppressed field type(s).
		 * @param     array    Object meta.
		 * @return    array    Possibly-modified name of the suppressed field type(s).
		 */
		$suppressed_meta = \apply_filters( 'replicast_acf_suppress_meta', array(), $data['replicast']['meta'] );

		foreach ( $data['replicast']['meta'] as $key => $meta ) {

			if ( empty( $meta['raw'] ) ) {
				continue;
			}

			$field_type = \acf_extract_var( $meta['raw'], 'type' );

			if ( in_array( $field_type, $suppressed_meta ) ) {
				continue;
			}

			$meta_value = '';

			// In theory, the $meta['rendered'] field has no more than one element, but you never know :-)
			if ( ! empty( $meta['rendered'] ) && sizeof( $meta['rendered'] ) === 1 ) {
				$meta_value = $meta['rendered'][0];
			}

			$field_value = \acf_extract_var( $meta['raw'], 'value' );

			switch ( $field_type ) {
				case 'taxonomy':
					$meta_value = $this->prepare_taxonomy_meta( $field_value, $site );
					break;
				case 'image':
					$meta_value = $this->prepare_image_meta( $field_value, $site );
					break;
				case 'gallery':
					$meta_value = $this->prepare_gallery_meta( $field_value, $site );
					break;
				case 'relationship':
					$meta_value = $this->prepare_relationship_meta( $field_value, $site );
					break;
			}

			unset( $data['replicast']['meta'][ $key ] );
			$data['replicast']['meta'][ $key ][] = $meta_value;
		}

		return $data;
	}

	/**
	 * Prepare ACF taxonomy meta.
	 *
	 * @since     1.0.0
	 * @param     array                $field_value    The meta value.
	 * @param     \Replicast\Client    $site           Site object.
	 * @return    string                               Possibly-modified serialized meta value.
	 */
	private function prepare_taxonomy_meta( $field_value, $site ) {

		$meta_value = '';

		if ( empty( $field_value ) ) {
			$field_value = array();
		}

		if ( ! is_array( $field_value ) ) {
			$field_value = array( $field_value );
		}

		foreach ( $field_value as $term ) {

			if ( is_numeric( $term ) ) {
				$term = \get_term_by( 'id', $term['term_id'], $term['taxonomy'] );
			}

			if ( ! $term ) {
				continue;
			}

			// Get replicast info
			$replicast_info = API::get_replicast_info( $term );

			// Update object ID
			if ( ! empty( $replicast_info ) ) {
				$meta_value[] = $replicast_info[ $site->get_id() ]['id'];
			}

		}

		if ( ! empty( $meta_value ) && is_array( $meta_value ) ) {
			$meta_value = \maybe_serialize( $meta_value );
		}

		return $meta_value;
	}

	/**
	 * Prepare ACF image meta.
	 *
	 * @since     1.0.0
	 * @param     array                $field_value    The meta value.
	 * @param     \Replicast\Client    $site           Site object.
	 * @return    string                               Possibly-modified non-serialized meta value.
	 */
	private function prepare_image_meta( $field_value, $site ) {

		$meta_value = '';

		if ( empty( $field_value['ID'] ) ) {
			return $meta_value;
		}

		$image = \get_post( $field_value['ID'] );

		if ( ! $image ) {
			return $meta_value;
		}

		// Get replicast info
		$replicast_info = API::get_replicast_info( $image );

		// Update object ID
		if ( ! empty( $replicast_info ) ) {
			return $replicast_info[ $site->get_id() ]['id'];
		}

		return $meta_value;
	}

	/**
	 * Prepare ACF gallery meta.
	 *
	 * @since     1.0.0
	 * @param     array                $field_value    The meta value.
	 * @param     \Replicast\Client    $site           Site object.
	 * @return    string                               Possibly-modified non-serialized meta value.
	 */
	private function prepare_gallery_meta( $field_value, $site ) {
		$meta_value = '';

		if ( empty( $field_value ) ) {
			$field_value = array();
		}

		if ( ! is_array( $field_value ) ) {
			$field_value = array( $field_value );
		}

		foreach ( $field_value as $related_image ) {
			$meta_value[] = $this->prepare_image_meta( $related_image, $site );
		}

		if ( ! empty( $meta_value ) && is_array( $meta_value ) ) {
			$meta_value = \maybe_serialize( $meta_value );
		}

		return $meta_value;
	}

	/**
	 * Prepare ACF relationship meta.
	 *
	 * @since     1.0.0
	 * @param     array                $field_value    The meta value.
	 * @param     \Replicast\Client    $site           Site object.
	 * @return    string                               Possibly-modified and serialized meta value.
	 */
	private function prepare_relationship_meta( $field_value, $site ) {

		$meta_value = '';

		if ( empty( $field_value ) ) {
			$field_value = array();
		}

		if ( ! is_array( $field_value ) ) {
			$field_value = array( $field_value );
		}

		foreach ( $field_value as $related_post ) {

			if ( is_numeric( $related_post ) ) {
				$related_post = \get_post( $related_post );
			}

			if ( ! $related_post ) {
				continue;
			}

			// Get replicast info
			$replicast_info = API::get_replicast_info( $related_post );

			// Update object ID
			if ( ! empty( $replicast_info ) ) {
				$meta_value[] = $replicast_info[ $site->get_id() ]['id'];
			}

		}

		if ( ! empty( $meta_value ) && is_array( $meta_value ) ) {
			$meta_value = \maybe_serialize( $meta_value );
		}

		return $meta_value;
	}

	/**
	 * Get ACF media.
	 *
	 * @see  \Replicast\API::get_object_media
	 *
	 * @since     1.0.0
	 * @param     array    $data       Object media.
	 * @param     int      $post_id    The object ID.
	 * @return    array                Possibly-modified object media.
	 */
	public function get_media( $data, $post_id ) {

		$fields = \get_field_objects( $post_id );

		if ( ! $fields ) {
			return $data;
		}

		foreach( $fields as $field ) {

			$field_type = $field['type'];

			if ( ! in_array( $field_type, array( 'gallery', 'image' ) ) ) {
				continue;
			}

			if ( empty( $field['value'] ) ) {
				continue;
			}

			// Image
			if ( $field_type === 'image' ) {
				$data['image'] = API::get_media( $field['value']['ID'] );
				continue;
			}

			// Gallery
			foreach ( $field['value'] as $image ) {
				$data['gallery'][] = API::get_media( $image['ID'] );
			}

		}

		return $data;
	}

	/**
	 * Prepare ACF media.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified data.
	 */
	public function prepare_media( $data, $site ) {

		if ( empty( $data['replicast'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['media'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['media'] as $field_type => $values ) {

			if ( ! in_array( $field_type, array( 'gallery', 'image' ) ) ) {
				continue;
			}

			if ( empty( $values ) ) {
				continue;
			}

			// Image
			if ( $field_type === 'image' ) {
				$data['replicast']['media'][ $field_type ]['id'] = API::get_replicast_id( $values['id'], $site );
				continue;
			}

			// Gallery
			foreach ( $values as $key => $image ) {
				$data['replicast']['media'][ $field_type ][ $key ]['id'] = API::get_replicast_id( $image['id'], $site );
			}

		}

		return $data;

	}

	/**
	 * Update ACF media.
	 *
	 * @since     1.0.0
	 * @param     array     $values       The values of the field.
	 * @param     object    $object_id    The object ID.
	 */
	public static function update_media( $values, $object_id ) {
	}

}
