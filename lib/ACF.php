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
	 * Retrieve post ACF meta.
	 *
	 * @since     1.0.0
	 * @param     array    Object meta.
	 * @param     int      Object ID.
	 * @return    array    Possibly-modified object meta.
	 */
	public function get_post_acf_meta( $meta, $post_id ) {

		$prepared_meta = array();

		foreach ( $meta as $meta_key => $meta_value ) {

			/**
			 * If it's an ACF field, add more information regarding the field.
			 *
			 * The raw/rendered keys are used just to identify what's an ACF meta value ahead in the
			 * replication process.
			 *
			 * FIXME: Maybe we should do this using a filter?!
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
	public function prepare_post_acf_meta( $data, $site ) {

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

			$field_value = \acf_extract_var( $meta['raw'], 'value' );

			if ( empty( $field_value ) ) {
				continue;
			}

			$meta_value  = array();
			$field_type  = \acf_extract_var( $meta['raw'], 'type' );

			if ( $field_type === 'text' ) {
				$meta_value = $field_value;
			}

			if ( $field_type === 'relationship' ) {

				foreach ( $field_value as $related_object ) {

					if ( is_numeric( $related_object ) ) {
						$related_object = \get_post( $related_object );
					}

					// Get replicast info
					$replicast_info = API::get_replicast_info( $related_object );

					// Update object ID
					if ( ! empty( $replicast_info ) ) {
						$meta_value[] = $replicast_info[ $site->get_id() ]['id'];
					}

				}

				if ( ! empty( $meta_value ) ) {
					$meta_value = maybe_serialize( $meta_value );
				}

			}

			unset( $data['replicast']['meta'][ $key ] );

			if ( empty( $meta_value ) ) {
				continue;
			}

			$data['replicast']['meta'][ $key ][] = $meta_value;

		}

		return $data;
	}

}
