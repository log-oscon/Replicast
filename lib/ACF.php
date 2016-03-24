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

		\add_filter( 'acf/update_value/type=relationship',     array( $this, 'relationship_persistence' ), 10, 3 );
		\add_filter( 'replicast_expose_object_protected_meta', array( $this, 'expose_protected_meta' ), 10 );
		\add_filter( 'replicast_get_object_post_meta',         array( $this, 'get_post_meta' ), 10, 3 );
		\add_filter( 'replicast_suppress_meta_from_update',    array( $this, 'suppress_meta_from_update' ), 10 );

		foreach ( Admin\SiteAdmin::get_post_types() as $post_type ) {
			\add_filter( "replicast_prepare_{$post_type}_for_create", array( $this, 'prepare_post_meta' ), 10, 2 );
			\add_filter( "replicast_prepare_{$post_type}_for_update", array( $this, 'prepare_post_meta' ), 10, 2 );
			\add_filter( "replicast_prepare_{$post_type}_for_create", array( $this, 'prepare_post_relationship_persistence' ), 10, 2 );
			\add_filter( "replicast_prepare_{$post_type}_for_update", array( $this, 'prepare_post_relationship_persistence' ), 10, 2 );
		};

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
	public function relationship_persistence( $value, $post_id, $field ) {

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

		$field_name     = $field['name'];
		$prev_selection = \get_field( $field_name, $post_id );
		$next_selection = ! empty( $value ) ? $value : array(); // This only contains object ID's
		$ids_to_remove  = array();

		if ( $prev_selection ) {
			foreach ( $prev_selection as $key => $selected_post ) {

				$selected_post_id = $selected_post;

				if ( is_object( $selected_post ) ) {
					$selected_post_id = $selected_post->ID;
				}

				if ( ! in_array( $selected_post_id, $next_selection ) ) {
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
	 * Expose \Replicast\ACF protected meta keys.
	 *
	 * @since     1.0.0
	 * @return    array    Exposed meta keys.
	 */
	public function expose_protected_meta() {
		return array( static::REPLICAST_ACF_INFO );
	}

	/**
	 * Retrieve post ACF meta.
	 *
	 * @since     1.0.0
	 * @param     array     $values       Object meta.
	 * @param     string    $meta_type    The object meta type.
	 * @param     int       $post_id      The object ID.
	 * @return    array                   Possibly-modified object meta.
	 */
	public function get_post_meta( $values, $meta_type, $post_id ) {

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
	 * Prepare post ACF meta.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared post data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified post data.
	 */
	public function prepare_post_meta( $data, $site ) {

		if ( empty( $data['replicast'] ) ) {
			return $data;
		}

		if ( empty( $data['replicast']['meta'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['meta'] as $key => $meta ) {

			if ( empty( $meta['raw'] ) ) {
				continue;
			}

			$meta_value = '';

			// In theory, the $meta['rendered'] field has no more than one element, but you never know :-)
			if ( ! empty( $meta['rendered'] ) && sizeof( $meta['rendered'] ) === 1 ) {
				$meta_value = $meta['rendered'][0];
			}

			$field_type  = \acf_extract_var( $meta['raw'], 'type' );
			$field_value = \acf_extract_var( $meta['raw'], 'value' );

			switch ( $field_type ) {
				case 'image':
					$meta_value = $this->prepare_related( array( $field_value['ID'] ), $site );
					break;
				case 'relationship':
					$meta_value = $this->prepare_related( $field_value, $site );
					break;
			}

			unset( $data['replicast']['meta'][ $key ] );
			$data['replicast']['meta'][ $key ][] = $meta_value;
		}

		return $data;
	}

	/**
	 * Prepare ACF related fields.
	 *
	 * @since     1.0.0
	 * @param     array                $field_value    The meta value.
	 * @param     \Replicast\Client    $site           Site object.
	 * @return    string                               Possibly-modified meta value.
	 */
	private function prepare_related( $field_value, $site ) {
		$meta_value = '';

		if ( empty( $field_value ) ) {
			$field_value = array();
		}

		foreach ( $field_value as $related_post ) {

			if ( is_numeric( $related_post ) ) {
				$related_post = \get_post( $related_post );
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
	 * Prepare removed relations.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared post data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified post data.
	 */
	public function prepare_post_relationship_persistence( $data, $site ) {

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
	 * Suppress \Replicast\ACF meta keys from update.
	 *
	 * @since     1.0.0
	 * @return    array     Suppressed meta keys.
	 */
	public function suppress_meta_from_update() {
		return array( static::REPLICAST_ACF_INFO );
	}

}
