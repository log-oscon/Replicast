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

		\add_filter( 'replicast_expose_object_protected_meta', array( $this, 'expose_object_protected_meta' ) );
		\add_filter( 'replicast_suppress_object_meta',         array( $this, 'suppress_object_meta' ) );

		\add_filter( 'acf/update_value/type=relationship',  array( $this, 'get_relations' ), 10, 3 );
		\add_filter( 'replicast_prepare_object_for_create', array( $this, 'prepare_relations' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update', array( $this, 'prepare_relations' ), 10, 2 );

		\add_filter( 'replicast_get_object_meta',           array( $this, 'get_meta' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_create', array( $this, 'prepare_meta' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update', array( $this, 'prepare_meta' ), 10, 2 );

		\add_filter( 'replicast_get_object_term',           array( $this, 'get_term' ), 10, 2 );
		\add_action( 'replicast_update_object_term',        array( $this, 'update_term' ), 10, 2 );

		\add_filter( 'replicast_get_object_media',    array( $this, 'get_media' ), 10, 2 );
		\add_action( 'replicast_update_object_media', array( $this, 'update_media' ), 10, 2 );

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
	 * @param     mixed    $value        The value of the field.
	 * @param     int      $object_id    The object ID.
	 * @param     array    $field        The field object.
	 * @return    mixed                  Possibly-modified value of the field.
	 */
	public function get_relations( $value, $object_id, $field ) {

		// Bail out if not admin and bypass REST API requests
		if ( ! \is_admin() ) {
			return $value;
		}

		// If post is an autosave, return
		if ( \wp_is_post_autosave( $object_id ) ) {
			return $value;
		}

		// If post is a revision, return
		if ( \wp_is_post_revision( $object_id ) ) {
			return $value;
		}

		if ( ! $field ) {
			return $value;
		}

		$field_name    = $field['name'];
		$prev_relation = \get_field( $field_name, $object_id ); // FIXME: consider replacing it by get_post_meta
		$next_relation = ! empty( $value ) ? $value : array(); // This only contains object ID's
		$ids_to_remove = array();

		if ( $prev_relation ) {
			foreach ( $prev_relation as $key => $selected_post ) {

				$selected_post_id = is_object( $selected_post ) ? $selected_post->ID : $selected_post;

				if ( ! in_array( $selected_post_id, $next_relation ) ) {
					$ids_to_remove[] = $selected_post_id;
				}

			}
		}

		// Get meta
		$meta = \get_post_meta( $object_id, static::REPLICAST_ACF_INFO, true );

		if ( ! $meta ) {
			$meta = array();
		}

		// Add meta persistence
		\update_post_meta(
			$object_id,
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

		if ( empty( $data['replicast']['meta'][ static::REPLICAST_ACF_INFO ] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['meta'][ static::REPLICAST_ACF_INFO ] as $meta_values ) {

			$meta_values   = \maybe_unserialize( $meta_values );
			$prepared_meta = array();

			foreach ( $meta_values as $meta_key => $meta_value ) {
				foreach ( $meta_value as $related_post_id ) {

					// Get replicast info
					$replicast_info = API::get_remote_info( \get_post( $related_post_id ) );

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

			if ( ! empty( $prepared_meta ) ) {
				$data['replicast']['meta'][ static::REPLICAST_ACF_INFO ][] = $prepared_meta;
			}

		}

		return $data;
	}

	/**
	 * Retrieve ACF meta.
	 *
	 * @since     1.0.0
	 * @param     array     $values       Object meta.
	 * @param     int       $object_id    The object ID.
	 * @return    array                   Possibly-modified object meta.
	 */
	public function get_meta( $values, $object_id ) {

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
			if ( $field = \get_field_object( $meta_key, $object_id ) ) {
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

			if ( empty( $meta['raw'] ) || empty( $meta['rendered'] ) ) {
				continue;
			}

			$field_type = \acf_extract_var( $meta['raw'], 'type' );
			$field_key  = \acf_extract_var( $meta['raw'], 'key' );

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

			$data['replicast']['meta'][ $key ][]       = $meta_value;
			$data['replicast']['meta'][ '_' . $key ][] = $field_key;
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
			$replicast_info = API::get_remote_info( $term );

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
		$replicast_info = API::get_remote_info( $image );

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
			$image_meta = $this->prepare_image_meta( $related_image, $site );
			if ( ! empty( $image_meta ) ) {
				$meta_value[] = $image_meta;
			}
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
			$replicast_info = API::get_remote_info( $related_post );

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
	 * Retrieve ACF terms "meta".
	 *
	 * The reason why we are using a separate object field to save term meta is because
	 * ACF uses options (`wp_options` table) instead of using real term meta (`wp_termmeta` table).
	 *
	 * @since     1.0.0
	 * @param     array     $terms        Object terms.
	 * @param     int       $object_id    The object ID.
	 * @return    array                   Possibly-modified object terms.
	 */
	public function get_term( $terms, $object_id ) {

		// FIXME: and how about child terms?

		foreach ( $terms as $term ) {
			$term->acf = \get_fields( "{$term->taxonomy}_{$term->term_id}" );
		}

		return $terms;
	}

	/**
	 * Update ACF terms "meta".
	 *
	 * @since     1.0.0
	 * @param     array     $terms        Object terms.
	 * @param     int       $object_id    The object ID.
	 * @return    array                   Possibly-modified object terms.
	 */
	public function update_term( $terms, $object_id ) {

		foreach ( $terms as $term_data ) {

			if ( empty( $term_data['acf'] ) ) {
				continue;
			}

			foreach ( $term_data['acf'] as $key => $value ) {

				// FIXME: this should be enabled when the media sync is implemented
				if ( $key === 'image_thumbnail' || $key === 'image_hero' ) {
					continue;
				}

				\update_field( $key, $value, "{$term_data['taxonomy']}_{$term_data['term_id']}" );
			}

		}

	}

	/**
	 * Retrieve ACF media.
	 *
	 * @since     1.0.0
	 * @param     array    $data         Object media.
	 * @param     int      $object_id    The object ID.
	 * @return    array                  Possibly-modified object media.
	 */
	public function get_media( $data, $object_id ) {

		$fields = \get_field_objects( $object_id );

		if ( ! $fields ) {
			return $data;
		}

		foreach( $fields as $field ) {

			$field_type  = $field['type'];
			$field_value = $field['value'];
			$field_name  = $field['name'];

			if ( ! in_array( $field_type, array( 'gallery', 'image' ) ) ) {
				continue;
			}

			if ( empty( $field_value ) ) {
				continue;
			}

			// Image
			if ( $field_type === 'image' ) {
				$object_id = $field_value['ID'];
				$ref       = API::get_object_id( $object_id );
				$data[ $ref ] = API::get_media( $ref, $object_id, $data, array( $field_type => $field_name ) );
				continue;
			}

			// Gallery
			foreach ( $field_value as $image ) {
				$object_id = $image['ID'];
				$ref       = API::get_object_id( $object_id );
				$data[ $ref ] = API::get_media( $ref, $object_id, $data, array( $field_type => $field_name ) );
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
	public function update_media( $media, $object_id ) {

		foreach ( $media as $media_id => $media_data ) {

			if ( ! array_intersect( array( 'gallery', 'image' ), $media_data['fields'] ) ) {
				continue;
			}

			foreach ( $media_data['fields'] as $field_type => $field_key ) {

				$value = $media[ $media_id ]['id'];

				// Image
				if ( $field_type === 'image' ) {
					\update_field( $field_key, $value, $object_id );
					continue;
				}

				// Gallery
				$previous_values = \get_field( $field_key, $object_id );

				if ( ! is_array( $previous_values ) ) {
					$previous_values = array( $previous_values );
				}

				\update_field( $field_key, array_merge( $previous_values, array( $value ) ), $object_id );
			}

		}

	}

}
