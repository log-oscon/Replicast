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

		\add_filter( 'replicast_get_object_terms',          array( $this, 'get_object_terms_translations' ), 10 );
		\add_filter( 'replicast_get_object_terms',          array( $this, 'get_object_terms_language' ), 20 );
		\add_filter( 'replicast_prepare_object_for_create', array( $this, 'prepare_object_terms_translations' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update', array( $this, 'prepare_object_terms_translations' ), 10, 2 );
		\add_action( 'replicast_update_object_terms',       array( $this, 'update_object_terms_language' ) );

	}

	/**
	 * Retrieve Polylang terms translations.
	 *
	 * @since     1.0.0
	 * @param     array    $terms    Object terms.
	 * @return    array              Possibly-modified object terms.
	 */
	public function get_object_terms_translations( $terms ) {

		foreach ( $terms as $term ) {

			$term_translations = \wp_get_object_terms( $term->term_id, 'term_translations' );

			foreach ( $term_translations as $term_translation ) {

				if ( array_key_exists( $term_translation->term_id, $terms ) ) {
					continue;
				}

				$terms[ $term_translation->term_id ] = $term_translation;
			}

		}

		return $terms;
	}

	/**
	 * Retrieve Polylang terms language.
	 *
	 * @since     1.0.0
	 * @param     array    $terms    Object terms.
	 * @return    array              Possibly-modified object terms.
	 */
	public function get_object_terms_language( $terms ) {

		if ( ! function_exists( 'pll_get_term_language' ) ) {
			return $terms;
		}

		foreach ( $terms as $term ) {
			$term->polylang = \pll_get_term_language( $term->term_id );
		}

		return $terms;
	}

	/**
	 * Prepare object translations.
	 *
	 * @since     1.0.0
	 * @param     array                $data    Prepared data.
	 * @param     \Replicast\Client    $site    Site object.
	 * @return    array                         Possibly-modified data.
	 */
	public function prepare_object_terms_translations( $data, $site ) {

		if ( empty( $data['replicast']['terms'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['terms'] as $term ) {

			if ( ! in_array( $term->taxonomy, array( 'post_translations', 'term_translations' ) ) ) {
				continue;
			}

			$translations = $this->get_translations( $term->description );

			foreach ( $translations as $lang => $translated_object_id ) {

				$remote_info = array();
				if ( $term->taxonomy === 'post_translations' ) {
					$remote_info = API::get_remote_info( \get_post( $translated_object_id ) );
				} elseif ( $term->taxonomy === 'term_translations' ) {
					$remote_info = API::get_remote_info( \get_term( $translated_object_id ) );
				}

				// Update object ID
				unset( $translations[ $lang ] );
				if ( ! empty( $remote_info ) ) {
					$translations[ $lang ] = $remote_info[ $site->get_id() ]['id'];
				}

			}

			$term->description = $this->set_translations( $translations );

		}

		return $data;
	}

	/**
	 * Update Polylang terms language.
	 *
	 * @since    1.0.0
	 * @param    array    $terms    Object terms.
	 */
	public function update_object_terms_language( $terms ) {

		if ( ! function_exists( 'pll_set_term_language' ) ) {
			return $terms;
		}

		foreach ( $terms as $term_data ) {

			if ( empty( $term_data['polylang'] ) ) {
				continue;
			}

			\pll_set_term_language( $term_data['term_id'], $term_data['polylang'] );

		}

	}

	/**
	 * Get object translations.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     string    $description    Object translations serialized.
	 * @return    array                     Object translations unserialized.
	 */
	private function get_translations( $description ) {
		return unserialize( $description );
	}

	/**
	 * Set object translations.
	 *
	 * @since     1.0.0
	 * @access    private
	 * @param     array    $translations    Object translations unserialized.
	 * @return    string                    Object translations serialized.
	 */
	private function set_translations( $translations ) {
		return serialize( $translations );
	}

}
