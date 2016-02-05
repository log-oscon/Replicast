<?php

/**
 * Define the RESTful functionality
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 */

namespace Replicast;

/**
 * Define the RESTful functionality.
 *
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class REST {

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

		foreach ( Admin\Site::get_object_types() as $object_type ) {
			\register_rest_field(
				$object_type,
				'replicast',
				array(
					'get_callback'    => '\Replicast\REST::get_rest_fields',
					'update_callback' => '\Replicast\REST::update_rest_fields',
					'schema'          => null,
				)
			);
		}

	}

	/**
	 * Get custom fields for a object type.
	 *
	 * @since     1.0.0
	 * @param     array               $object        Details of current content object.
	 * @param     string              $field_name    Name of field.
	 * @param     \WP_REST_Request    $request       Current \WP_REST_Request request.
	 * @return    array                              Custom fields.
	 */
	public static function get_rest_fields( $object, $field_name, $request ) {
		return array(
			'meta' => static::get_object_meta( $object, $request->get_route() ),
		);
	}

	/**
	 * Retrieve metadata for the specified object.
	 *
	 * @since     1.0.0
	 * @param     array    $object    Details of current content object.
	 * @param     string   $route     Object REST route.
	 * @return    array               Object metadata.
	 */
	public static function get_object_meta( $object, $route ) {
		return static::get_metadata( $object, $route );
	}

	/**
	 * Get custom fields for a post type.
	 *
	 * @since     1.0.0
	 * @param     array     $value     The value of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_rest_fields( $value, $object ) {

		// Update meta
		if ( ! empty( $value['meta'] ) ) {
			static::update_object_meta( $value['meta'], $object );
		}

	}

	/**
	 * Update metadata for the specified object.
	 *
	 * @since     1.0.0
	 * @param     array     $value     The value of the field.
	 * @param     object    $object    The object from the response.
	 */
	public static function update_object_meta( $value, $object ) {

		// TODO: should this be returning any kind of success/failure information?
		$meta_type = $object->post_type;
		$object_id = $object->ID;

		// Update metadata
		foreach ( $value as $meta_key => $meta_values ) {
			\delete_metadata( $meta_type, $object_id, $meta_key );
			foreach ( $meta_values as $meta_value ) {
				\add_metadata( $meta_type, $object_id, $meta_key, \maybe_unserialize( $meta_value ) );
			}
		}

	}

	/**
	 * Retrieve metadata for the specified object.
	 *
	 * @access    private
	 * @since     1.0.0
	 * @param     array    $object    Details of current content object.
	 * @param     string   $route     Object REST route.
	 * @return    array               Object metadata.
	 */
	private static function get_metadata( $object, $route ) {

		$meta_type = $object['type'];
		$object_id = $object['id'];

		/**
		 * Filter the whitelist of protected meta keys.
		 *
		 * @since    1.0.0
		 * @param    array|string    Name(s) of the whitelisted meta keys.
		 */
		$whitelist = \apply_filters( 'replicast_object_protected_meta', array(
			'_wp_page_template',
		) );

		$metadata = \get_metadata( $meta_type, $object_id );

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
		$prepared_metadata[ Plugin::REPLICAST_REMOTE ] = array( \rest_url( $route ) );

		return $prepared_metadata;
	}

}
