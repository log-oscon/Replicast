<?php
/**
 * Add ACF support
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Module
 */

namespace Replicast\Module;

use Replicast\Admin;
use Replicast\API;

/**
 * Add ACF support.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Module
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class ACF {

	/**
	 * Identifies the meta variable that is sent to the remote site and
	 * that contains information regarding the remote object ACF meta.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const REPLICAST_ACF_INFO = '_replicast_acf_info';

	/**
	 * The plugin's instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    \Replicast\Plugin
	 */
	private $plugin;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param \Replicast\Plugin $plugin This plugin's instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register() {

		\add_filter( 'replicast_expose_object_protected_meta', array( $this, 'expose_object_protected_meta' ) );
		\add_filter( 'replicast_suppress_object_meta',         array( $this, 'suppress_object_meta' ) );

		\add_filter( 'acf/update_value/type=relationship',  array( $this, 'get_relations' ), 10, 3 );
		\add_filter( 'replicast_prepare_object_for_create', array( $this, 'prepare_relations' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update', array( $this, 'prepare_relations' ), 10, 2 );

		\add_filter( 'replicast_get_object_meta',           array( $this, 'get_object_meta' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_create', array( $this, 'prepare_object_meta' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update', array( $this, 'prepare_object_meta' ), 10, 2 );

		\add_filter( 'replicast_get_object_media',    array( $this, 'get_object_media' ), 10, 2 );
		\add_action( 'replicast_update_object_media', array( $this, 'update_object_media' ), 10, 2 );

		\add_filter( 'replicast_get_object_terms',          array( $this, 'get_object_terms_meta' ) );
		\add_filter( 'replicast_prepare_object_for_create', array( $this, 'prepare_object_term_meta' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update', array( $this, 'prepare_object_term_meta' ), 10, 2 );
		\add_action( 'replicast_update_object_terms',       array( $this, 'update_object_terms_meta' ) );

		\add_filter( 'replicast_get_object_media',    array( $this, 'get_object_term_media' ), 20, 2 );
		\add_action( 'replicast_update_object_media', array( $this, 'update_object_term_media' ), 20, 2 );
	}

	/**
	 * Expose \Replicast\ACF protected meta keys.
	 *
	 * @since  1.0.0
	 * @return array Exposed meta keys.
	 */
	public function expose_object_protected_meta() {
		return array( static::REPLICAST_ACF_INFO );
	}

	/**
	 * Suppress \Replicast\ACF meta keys.
	 *
	 * @since  1.0.0
	 * @return array Suppressed meta keys.
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
	 * @since  1.4.0 Check for `REST_REQUEST` constant.
	 * @since  1.0.0
	 *
	 * @param  mixed $value     The value of the field.
	 * @param  int   $object_id The object ID.
	 * @param  array $field     The field object.
	 * @return mixed            Possibly-modified value of the field.
	 */
	public function get_relations( $value, $object_id, $field ) {

		// Bypass REST API requests.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $value;
		}

		// If post is an autosave, return.
		if ( \wp_is_post_autosave( $object_id ) ) {
			return $value;
		}

		// If post is a revision, return.
		if ( \wp_is_post_revision( $object_id ) ) {
			return $value;
		}

		if ( ! $field ) {
			return $value;
		}

		$field_name    = $field['name'];
		$prev_relation = \get_field( $field_name, $object_id ); // FIXME: consider replacing it by get_post_meta
		$next_relation = ! empty( $value ) ? $value : array(); // This only contains object ID's.
		$ids_to_remove = array();

		if ( $prev_relation ) {
			foreach ( $prev_relation as $key => $selected_post ) {

				$selected_post_id = is_object( $selected_post ) ? $selected_post->ID : $selected_post;

				if ( ! in_array( $selected_post_id, $next_relation, true ) ) {
					$ids_to_remove[] = $selected_post_id;
				}

			}
		}

		// Get meta.
		$meta = \get_post_meta( $object_id, static::REPLICAST_ACF_INFO, true );

		if ( ! $meta ) {
			$meta = array();
		}

		// Add meta persistence.
		\update_post_meta(
			$object_id,
			static::REPLICAST_ACF_INFO,
			array_merge( $meta, array(
				$field_name => $ids_to_remove,
			) ),
			$meta
		);

		return $value;
	}

	/**
	 * Prepare removed relations.
	 *
	 * @since  1.0.0
	 * @param  array             $data Prepared data.
	 * @param  \Replicast\Client $site Site object.
	 * @return array                   Possibly-modified data.
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

					$remote_info = API::get_remote_info( \get_post( $related_post_id ) );

					// Update object ID.
					if ( ! empty( $remote_info ) ) {
						$prepared_meta[ $meta_key ][] = $remote_info[ $site->get_id() ]['id'];
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
	 * @since  1.0.0
	 * @param  array $values Object meta.
	 * @param  array $object The object.
	 * @return array         Possibly-modified object meta.
	 */
	public function get_object_meta( $values, $object ) {

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
			if ( $field = \get_field_object( $meta_key, $object['id'] ) ) {
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
	 * @since  1.0.0
	 * @param  array             $data Prepared data.
	 * @param  \Replicast\Client $site Site object.
	 * @return array                   Possibly-modified data.
	 */
	public function prepare_object_meta( $data, $site ) {

		if ( empty( $data['replicast']['meta'] ) ) {
			return $data;
		}

		/**
		 * Filter for suppressing ACF meta by field type.
		 *
		 * @since  1.0.0
		 * @param  array Name of the suppressed field type(s).
		 * @param  array Object meta.
		 * @return array Possibly-modified name of the suppressed field type(s).
		 */
		$suppressed_meta = \apply_filters( 'replicast_acf_suppress_meta', array(), $data['replicast']['meta'] );

		foreach ( $data['replicast']['meta'] as $key => $meta ) {

			if ( empty( $meta['raw'] ) || empty( $meta['rendered'] ) ) {
				continue;
			}

			$field_type = \acf_extract_var( $meta['raw'], 'type' );
			$field_key  = \acf_extract_var( $meta['raw'], 'key' );

			if ( in_array( $field_type, $suppressed_meta, true ) ) {
				continue;
			}

			$meta_value = '';

			// In theory, the $meta['rendered'] field has no more than one element, but you never know :-).
			if ( ! empty( $meta['rendered'] ) && sizeof( $meta['rendered'] ) === 1 ) {
				$meta_value = $meta['rendered'][0];
			}

			$field_value = \acf_extract_var( $meta['raw'], 'value' );

			switch ( $field_type ) {
				case 'taxonomy':
					$meta_value = $this->prepare_taxonomy( $field_value, $site );
					break;
				case 'image':
					$meta_value = $this->prepare_image( $field_value, $site );
					break;
				case 'gallery':
					$meta_value = $this->prepare_gallery( $field_value, $site );
					break;
				case 'relationship':
					$meta_value = $this->prepare_relationship( $field_value, $site );
					break;
			}

			unset( $data['replicast']['meta'][ $key ] );

			$data['replicast']['meta'][ $key ][]       = $meta_value;
			$data['replicast']['meta'][ '_' . $key ][] = $field_key;
		}

		return $data;
	}

	/**
	 * Retrieve ACF media.
	 *
	 * @since  1.0.0
	 * @param  array $data   Object media.
	 * @param  array $object The object.
	 * @return array         Possibly-modified object media.
	 */
	public function get_object_media( $data, $object ) {

		$fields = \get_field_objects( $object['id'] );

		if ( ! $fields ) {
			return $data;
		}

		foreach ( $fields as $field ) {

			$field_type  = $field['type'];
			$field_value = $field['value'];
			$field_name  = $field['name'];

			if ( ! in_array( $field_type, array( 'gallery', 'image' ), true ) ) {
				continue;
			}

			if ( empty( $field_value ) ) {
				continue;
			}

			$relations = array(
				'post' => array(
					$object['id'] => array(
						$field_type => $field_name,
					),
				),
			);

			// Image.
			if ( $field_type === 'image' ) {

				$field_id  = $field_value['id'];
				$source_id = API::get_source_id( $field_id );

				$data[ $source_id ] = API::get_media( $source_id, $field_id, $relations, $data );

				continue;
			}

			// Gallery.
			foreach ( $field_value as $image ) {

				$field_id  = $image['id'];
				$source_id = API::get_source_id( $field_id );

				$data[ $source_id ] = API::get_media( $source_id, $field_id, $relations, $data );
			}
		}

		return $data;
	}

	/**
	 * Update ACF media.
	 *
	 * @since 1.0.0
	 * @param array  $media  The values of the field.
	 * @param object $object The object.
	 */
	public function update_object_media( $media, $object ) {

		foreach ( $media as $media_id => $media_data ) {

			if ( empty( $media_data['_relations']['post'] ) ) {
				continue;
			}

			foreach ( $media_data['_relations']['post'] as $source_post_id => $relations ) {

				foreach ( $relations as $field_type => $field_key ) {

					if ( ! in_array( $field_type, array( 'gallery', 'image' ), true ) ) {
						continue;
					}

					$value = $media[ $media_id ]['id'];

					// Image.
					if ( $field_type === 'image' ) {
						\update_field( $field_key, $value, $object->ID );
						continue;
					}

					// Gallery.
					$previous_values = \get_field( $field_key, $object->ID, false );

					if ( ! is_array( $previous_values ) ) {
						$previous_values = array( $previous_values );
					}

					\update_field( $field_key, array_merge( $previous_values, array( $value ) ), $object->ID );
				}
			}
		}
	}

	/**
	 * Retrieve ACF terms "meta".
	 *
	 * The reason why we are using a separate object field to save term meta is because
	 * ACF uses options (`wp_options` table) instead of using real term meta (`wp_termmeta` table).
	 *
	 * @since  1.0.0
	 * @param  array $terms Object terms.
	 * @return array        Possibly-modified object terms.
	 */
	public function get_object_terms_meta( $terms ) {

		// FIXME: and how about child terms?
		foreach ( $terms as $term ) {

			$fields = \get_field_objects( "{$term->taxonomy}_{$term->term_id}" );

			if ( ! $fields ) {
				continue;
			}

			$term->acf = array();
			foreach ( $fields as $field_key => $field_value ) {
				$term->acf[ $field_key ] = array(
					'key'   => $field_value['key'],
					'value' => $field_value['value'],
				);
			}
		}

		return $terms;
	}

	/**
	 * Prepare ACF terms "meta".
	 *
	 * @since  1.0.0
	 * @param  array             $data Prepared data.
	 * @param  \Replicast\Client $site Site object.
	 * @return array                   Possibly-modified data.
	 */
	public function prepare_object_term_meta( $data, $site ) {

		if ( empty( $data['replicast']['terms'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['terms'] as $term_id => $term ) {

			if ( empty( $term->acf ) ) {
				continue;
			}

			foreach ( $term->acf as $field_key => $field_value ) {

				$field_type = \acf_extract_var( $field_value['value'], 'type' );

				if ( ! in_array( $field_type, array( 'gallery', 'image' ), true ) ) {
					continue;
				}

				// Image.
				if ( $field_type === 'image' ) {
					$media_id = $this->prepare_image( $field_value['value'], $site );
					$data['replicast']['terms'][ $term_id ]->acf[ $field_key ]['value']['id'] = $media_id;
					continue;
				}

				// Gallery.
				// TODO: prepare gallery term meta.
			}
		}

		return $data;
	}

	/**
	 * Update ACF terms "meta".
	 *
	 * @since 1.0.0
	 * @param array $terms Object terms.
	 */
	public function update_object_terms_meta( $terms ) {

		foreach ( $terms as $term_data ) {

			if ( empty( $term_data['acf'] ) ) {
				continue;
			}

			foreach ( $term_data['acf'] as $field_key => $field_value ) {
				\update_field( $field_value['key'], $field_value['value'], "{$term_data['taxonomy']}_{$term_data['term_id']}" );
			}
		}
	}

	/**
	 * Retrieve ACF terms media.
	 *
	 * @since  1.0.0
	 * @param  array $data   Object media.
	 * @param  array $object The object.
	 * @return array         Possibly-modified object media.
	 */
	public function get_object_term_media( $data, $object ) {

		// Retrieve the terms.
		$terms = API::get_terms( $object['id'], $object['type'] );

		foreach ( $terms as $term ) {

			$fields = \get_fields( "{$term->taxonomy}_{$term->term_id}" );

			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field_key => $field_value ) {

				$field_type = \acf_extract_var( $field_value, 'type' );

				if ( ! in_array( $field_type, array( 'gallery', 'image' ), true ) ) {
					continue;
				}

				$relations = array(
					'term' => array(
						$term->term_id => array(
							$field_type => $field_key,
						),
					),
				);

				// Image.
				if ( $field_type === 'image' ) {

					$field_id  = $field_value['id'];
					$source_id = API::get_source_id( $field_id );

					$data[ $source_id ] = API::get_media( $source_id, $field_id, $relations, $data );

					continue;
				}

				// Gallery
				// TODO: get gallery media.
			}
		}

		return $data;
	}

	/**
	 * Update ACF terms media.
	 *
	 * @since 1.0.0
	 * @param array  $media  The values of the field.
	 * @param object $object The object.
	 */
	public function update_object_term_media( $media, $object ) {

		// Retrieve the terms.
		$terms = API::get_terms( $object->ID, $object->post_type );

		$prepared_terms = array();
		foreach ( $terms as $term ) {
			$prepared_terms[ API::get_source_id( $term->term_id, 'term' ) ] = $term;
		}

		foreach ( $media as $media_id => $media_data ) {

			if ( empty( $media_data['_relations']['term'] ) ) {
				continue;
			}

			foreach ( $media_data['_relations']['term'] as $source_term_id => $relations ) {

				if ( ! array_key_exists( $source_term_id, $prepared_terms ) ) {
					continue;
				}

				foreach ( $relations as $field_type => $field_key ) {

					if ( ! in_array( $field_type, array( 'gallery', 'image' ), true ) ) {
						continue;
					}

					$value = $media[ $media_id ]['id'];
					$term  = $prepared_terms[ $source_term_id ];

					// Image.
					if ( $field_type === 'image' ) {
						\update_field( $field_key, $value, "{$term->taxonomy}_{$term->term_id}" );
						continue;
					}

					// Gallery.
					// TODO: update gallery term media.
				}
			}
		}
	}

	/**
	 * Prepare ACF taxonomy fields.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array             $field_value The field value.
	 * @param  \Replicast\Client $site        Site object.
	 * @return string                         Possibly-modified serialized field value.
	 */
	private function prepare_taxonomy( $field_value, $site ) {

		$value = '';

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

			$remote_info = API::get_remote_info( $term );

			// Update object ID.
			if ( ! empty( $remote_info ) ) {
				$value[] = $remote_info[ $site->get_id() ]['id'];
			}
		}

		if ( ! empty( $value ) && is_array( $value ) ) {
			$value = \maybe_serialize( $value );
		}

		return $value;
	}

	/**
	 * Prepare ACF image fields.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array             $field_value The field value.
	 * @param  \Replicast\Client $site        Site object.
	 * @return string                         Possibly-modified non-serialized field value.
	 */
	private function prepare_image( $field_value, $site ) {

		$value = '';

		if ( empty( $field_value['id'] ) ) {
			return $value;
		}

		$image = \get_post( $field_value['id'] );

		if ( ! $image ) {
			return $value;
		}

		$remote_info = API::get_remote_info( $image );

		// Update object ID.
		if ( ! empty( $remote_info ) ) {
			return $remote_info[ $site->get_id() ]['id'];
		}

		return $value;
	}

	/**
	 * Prepare ACF gallery fields.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array             $field_value The field value.
	 * @param  \Replicast\Client $site        Site object.
	 * @return string                         Possibly-modified non-serialized field value.
	 */
	private function prepare_gallery( $field_value, $site ) {

		$value = '';

		if ( empty( $field_value ) ) {
			$field_value = array();
		}

		if ( ! is_array( $field_value ) ) {
			$field_value = array( $field_value );
		}

		foreach ( $field_value as $related_image ) {
			$image_meta = $this->prepare_image( $related_image, $site );
			if ( ! empty( $image_meta ) ) {
				$value[] = $image_meta;
			}
		}

		if ( ! empty( $value ) && is_array( $value ) ) {
			$value = \maybe_serialize( $value );
		}

		return $value;
	}

	/**
	 * Prepare ACF relationship fields.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array             $field_value The field value.
	 * @param  \Replicast\Client $site        Site object.
	 * @return string                         Possibly-modified and serialized field value.
	 */
	private function prepare_relationship( $field_value, $site ) {

		$value = '';

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

			$remote_info = API::get_remote_info( $related_post );

			// Update object ID.
			if ( ! empty( $remote_info ) ) {
				$value[] = $remote_info[ $site->get_id() ]['id'];
			}
		}

		if ( ! empty( $value ) && is_array( $value ) ) {
			$value = \maybe_serialize( $value );
		}

		return $value;
	}
}
