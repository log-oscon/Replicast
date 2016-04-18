<?php

/**
 * Add Polylang support
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
 * Add Polylang support.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Polylang {

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

		\add_filter( 'replicast_prepare_object_for_create', array( $this, 'prepare_object_translations' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update', array( $this, 'prepare_object_translations' ), 10, 2 );

	}


	/**
	 * Prepare Polylang translations.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified data.
	 */
	public function prepare_object_translations( $data, $site ) {

		if ( empty( $data['replicast']['term'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['term'] as $term ) {

			if ( ! in_array( $term->taxonomy, array( 'post_translations', 'term_translations' ) ) ) {
				continue;
			}

			$translations = unserialize( $term->description );

			foreach ( $translations as $lang => $object_id ) {

				if ( $term->taxonomy === 'post_translations' ) {
					$remote_info = API::get_remote_info( \get_post( $object_id ) );
				} elseif ( $term->taxonomy === 'term_translations' ) {
					// TODO
					continue;
				}

				// Update object ID
				unset( $translations[ $lang ] );
				if ( ! empty( $remote_info ) ) {
					$translations[ $lang ] = $remote_info[ $site->get_id() ]['id'];
				}

			}

			$term->description = serialize( $translations );

		}

		return $data;
	}

}
