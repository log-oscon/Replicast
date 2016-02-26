<?php

/**
 * Extend the API functionality
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 */

namespace Replicast;

use Replicast\Admin;
use Replicast\Admin\Site;

/**
 * Extend the API functionality.
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class API {

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
	 * Registers a new field on a set of existing object types.
	 *
	 * @since    1.0.0
	 */
	public function register_rest_fields() {

		foreach ( Site::get_post_types() as $post_type ) {
			\register_rest_field(
				$post_type,
				'replicast',
				array(
					'get_callback'    => array( __CLASS__, 'get_rest_fields' ),
					'update_callback' => array( __CLASS__, 'update_rest_fields' ),
					'schema'          => null,
				)
			);
		}

	}

	/**
	 * Retrieve the field value.
	 *
	 * @since     1.0.0
	 * @param     array               $object        Details of current content object.
	 * @param     string              $field_name    Name of field.
	 * @param     \WP_REST_Request    $request       Current \WP_REST_Request request.
	 * @return    array                              Custom fields.
	 */
	public static function get_rest_fields( $object, $field_name, $request ) {
		return array(
			'meta' => static::get_object_meta( $object, $request ),
			'term' => static::get_object_term( $object, $request ),
		);
	}

	/**
	 * Retrieve object meta.
	 *
	 * @since     1.0.0
	 * @param     array               $object     Details of current content object.
	 * @param     \WP_REST_Request    $request    Current \WP_REST_Request request.
	 * @return    array               Object meta.
	 */
	public static function get_object_meta( $object, $request ) {

		// Get object meta type
		$meta_type = static::get_meta_type( $object );

		/**
		 * Filter for exposing specific protected meta keys.
		 *
		 * @since     1.0.0
		 * @param     array               Name(s) of the exposed meta keys.
		 * @param     array    $object    Details of current content object.
		 * @return    array               Possibly-modified name(s) of the exposed meta keys.
		 */
		$whitelist = \apply_filters( 'replicast_expose_object_protected_meta', array(), $object );

		// Get object metadata
		$metadata = \get_metadata( $meta_type, $object['id'] );

		if ( ! $metadata ) {
			return array();
		}

		if ( ! is_array( $metadata ) ) {
			$metadata = (array) $metadata;
		}

		$prepared_metadata = array();
		foreach ( $metadata as $meta_key => $meta_value ) {

			if ( \is_protected_meta( $meta_key ) && ! in_array( $meta_key, $whitelist ) ) {
				continue;
			}

			$prepared_metadata[ $meta_key ] = $meta_value;

		}

		// Add object REST route to meta
		$prepared_metadata[ Plugin::REPLICAST_REMOTE ] = array( \maybe_serialize( array(
			'ID'        => $object['id'],
			'edit_link' => \get_edit_post_link( $object['id'] ),
			'rest_url'  => \rest_url( $request->get_route() ),
		) ) );

		return $prepared_metadata;
	}

	/**
	 * Retrieve object terms.
	 *
	 * @since     1.0.0
	 * @param     array               $object     Details of current content object.
	 * @param     \WP_REST_Request    $request    Current \WP_REST_Request request.
	 * @return    array               Object terms.
	 */
	public static function get_object_term( $object, $request ) {

		// Get a list of registered taxonomies
		$taxonomies = \get_taxonomies();

		/**
		 * Filter for suppressing taxonomies.
		 *
		 * @since     1.0.0
		 * @param     array                    Name(s) of the suppressed taxonomies.
		 * @param     array    $taxonomies     List of registered taxonomies.
		 * @param     array    $object         The object from the response.
		 * @return    array                    Possibly-modified name(s) of the suppressed taxonomies.
		 */
		$taxonomies_blacklist = \apply_filters( 'replicast_suppress_object_taxonomies', array(), $taxonomies, $object );

		$prepared_taxonomies = array();
		foreach ( $taxonomies as $taxonomy_key => $taxonomy_key ) {

			if ( in_array( $taxonomy_key, array( Plugin::TAXONOMY_SITE ) ) ) {
				continue;
			}

			if ( in_array( $taxonomy_key, $taxonomies_blacklist ) ) {
				continue;
			}

			$prepared_taxonomies[ $taxonomy_key ] = $taxonomy_key;

		}

		// Get a list of object terms
		// FIXME: we should soft cache this
		$terms = \wp_get_object_terms( $object['id'], $prepared_taxonomies );

		$prepared_terms = array();
		foreach ( $terms as $term ) {

			if ( in_array( $term->slug, $terms ) ) {
				continue;
			}

			if ( in_array( $term->slug, array( 'uncategorized', 'untagged' ) ) ) {
				continue;
			}

			$prepared_terms[] = $term;

		}

		return $prepared_terms;
	}

	/**
	 * Set and update the field value.
	 *
	 * @since     1.0.0
	 * @param     array     $values    The values of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_rest_fields( $values, $object ) {

		// Update object meta
		if ( ! empty( $values['meta'] ) ) {
			static::update_object_meta( $values['meta'], $object );
		}

		// Update object terms
		if ( ! empty( $values['term'] ) ) {
			static::update_object_term( $values['term'], $object );
		}

	}

	/**
	 * Update object meta.
	 *
	 * @since     1.0.0
	 * @param     array     $values    The values of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_object_meta( $values, $object ) {

		// Get object meta type
		$meta_type = static::get_meta_type( $object );

		/**
		 * Filter for suppressing specific meta keys from update.
		 *
		 * @since     1.0.0
		 * @param     array                Name(s) of the suppressed meta keys.
		 * @param     array     $values    The values of the field.
		 * @param     object    $object    The object from the response.
		 * @return    array                Possibly-modified name(s) of the suppressed meta keys.
		 */
		$blacklist = \apply_filters( 'replicast_suppress_object_meta_from_update', array(), $values, $object );

		// Update metadata
		foreach ( $values as $meta_key => $meta_values ) {

			if ( in_array( $meta_key, $blacklist ) ) {
				continue;
			}

			\delete_metadata( $meta_type, $object->ID, $meta_key );
			foreach ( $meta_values as $meta_value ) {
				\add_metadata( $meta_type, $object->ID, $meta_key, \maybe_unserialize( $meta_value ) );
			}

		}

	}

	/**
	 * Update object meta.
	 *
	 * @since     1.0.0
	 * @param     array     $values    The values of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_object_term( $values, $object ) {

		$prepared_terms = array();

		// Update terms
		foreach ( $values as $term_data ) {

			$taxonomy = $term_data['taxonomy'];
			$parent   = $term_data['parent'];

			// Check if taxonomy exists
			if ( ! \taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			// Check if term exists
			if ( $term = \get_term_by( 'slug', $term_data['slug'], $taxonomy ) ) {
				$prepared_terms[ $taxonomy ][] = $term->term_id;
				continue;
			}

			$term = \wp_insert_term( $term_data['name'], $taxonomy, array(
				'description' => $term_data['description'],
				'parent'      => $parent,
			) );

			if ( \is_wp_error( $term ) ) {
				continue;
			}

			$prepared_terms[ $taxonomy ][] = $term['term_id'];

		}

		if ( ! empty( $prepared_terms ) ) {
			foreach ( $prepared_terms as $taxonomy => $terms ) {
				\wp_set_object_terms(
					$object->ID,
					$terms,
					$taxonomy
				);
			}
		}

	}

	/**
	 * Get object ID.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    string                     The object ID.
	 */
	public static function get_object_id( $object ) {

		if ( isset( $object->term_id ) ) {
			return $object->term_id;
		} elseif ( isset( $object->ID ) ) {
			return $object->ID;
		}

		return $object->id;
	}

	/**
	 * Get object type.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    string                     The object type.
	 */
	public static function get_object_type( $object ) {

		// FIXME: revisit later

		if ( static::is_term( $object ) ) {
			return $object->taxonomy;
		}

		return $object->post_type;
	}

	/**
	 * Get meta type based on the object class or array data.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    string                     Possible values: user, comment, post, meta
	 */
	public static function get_meta_type( $object ) {

		// FIXME: revisit later

		if ( static::is_term( $object ) ) {
			return 'term';
		}

		return 'post';
	}

	/**
	 * Check if object is a post/page.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    bool                       True if it's a post/page. False, otherwise.
	 */
	public static function is_post( $object ) {

		// TODO: continuous improvement

		if ( $object instanceof \WP_Post ) {
			return true;
		}

		if ( is_object( $object ) && isset( $object->post_type ) ) {
			return true;
		}

		if ( is_array( $object ) && isset( $object['type'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if object is a term.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    bool                       True if it's a term. False, otherwise.
	 */
	public static function is_term( $object ) {

		// TODO: continuous improvement

		if ( $object instanceof \WP_Term ) {
			return true;
		}

		if ( is_object( $object ) && isset( $object->term_id ) ) {
			return true;
		}

		if ( is_array( $object ) && isset( $object['term_id'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Retrieve replicast info from object.
	 *
	 * @since     1.0.0
	 * @param     object|array    $object    The object.
	 * @return    array                      The replicast info meta data.
	 */
	public static function get_replicast_info( $object ) {

		$replicast_info = \get_metadata( static::get_meta_type( $object ), static::get_object_id( $object ), Plugin::REPLICAST_IDS, true );

		if ( ! $replicast_info ) {
			return array();
		}

		if ( ! is_array( $replicast_info ) ) {
			$replicast_info = (array) $replicast_info;
		}

		return $replicast_info;
	}

	/**
	 * Update object with replication info.
	 *
	 * This replication info consists in a pair <site_id, remote_object_id>.
	 *
	 * @since     1.0.0
	 * @param     object|array         $object                       The object.
	 * @param     int                  $site_id                      Site ID.
	 * @param     object|null          $remote_data    (optional)    Remote object data. Null if it's for permanent delete.
	 * @return    mixed                                              Returns meta ID if the meta doesn't exist, otherwise
	 *                                                               returns true on success and false on failure.
	 */
	public static function update_replicast_info( $object, $site_id, $remote_data = null ) {

		// Get replicast object info
		$replicast_info = static::get_replicast_info( $object );

		// Save or delete the remote object info
		if ( $remote_data ) {
			$replicast_info[ $site_id ] = array(
				'id'               => static::get_object_id( $remote_data ),
				'status'           => isset( $remote_data->status )           ? $remote_data->status           : '',
				'term_taxonomy_id' => isset( $remote_data->term_taxonomy_id ) ? $remote_data->term_taxonomy_id : '',
			);
		}
		else {
			unset( $replicast_info[ $site_id ] );
		}

		return \update_metadata( static::get_meta_type( $object ), static::get_object_id( $object ), Plugin::REPLICAST_IDS, $replicast_info );
	}

}
