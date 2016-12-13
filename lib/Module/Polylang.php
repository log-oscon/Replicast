<?php
/**
 * Add Polylang support
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Module
 */

namespace Replicast\Module;

use Replicast\API;

/**
 * Add Polylang support.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Module
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Polylang {

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

		\add_filter( 'replicast_suppress_object_taxonomies', array( $this, 'suppress_taxonomies' ) );

		\add_action( 'replicast_update_object_terms',        array( $this, 'update_object_translations' ), 10, 2 );

		\add_filter( 'replicast_prepare_object_for_create',  array( $this, 'prepare_object_translations' ), 10, 2 );
		\add_filter( 'replicast_prepare_object_for_update',  array( $this, 'prepare_object_translations' ), 10, 2 );

		\add_filter( 'replicast_get_object_terms',           array( $this, 'get_object_terms_translations' ) );
		\add_filter( 'replicast_prepare_object_for_create',  array( $this, 'prepare_object_terms_translations' ), 20, 2 );
		\add_filter( 'replicast_prepare_object_for_update',  array( $this, 'prepare_object_terms_translations' ), 20, 2 );
		\add_action( 'replicast_update_object_terms',        array( $this, 'update_object_terms_translations' ), 20 );
	}

	/**
	 * Suppress taxonomies.
	 *
	 * @since  1.0.0
	 * @param  array $suppressed Name(s) of the suppressed taxonomies.
	 * @return array             Possibly-modified name(s) of the suppressed taxonomies.
	 */
	public function suppress_taxonomies( $suppressed = array() ) {
		return array_merge( array( 'term_translations' ), $suppressed );
	}

	/**
	 * Retrieve object terms translations.
	 *
	 * @since  1.0.0
	 * @param  array $terms Object terms.
	 * @return array        Possibly-modified object terms.
	 */
	public function get_object_terms_translations( $terms ) {

		foreach ( $terms as $term ) {

			if ( in_array( $term->taxonomy, array( 'post_translations', 'language' ), true ) ) {
				continue;
			}

			$term->polylang = array(
				'language'     => '',
				'translations' => array(),
			);

			if ( function_exists( '\pll_get_term_language' ) ) {
				$term->polylang['language'] = \pll_get_term_language( $term->term_id );
			}

			if ( function_exists( '\pll_get_term_translations' ) ) {
				$term->polylang['translations'] = \pll_get_term_translations( $term->term_id );
			}
		}

		return $terms;
	}

	/**
	 * Prepare object translations.
	 *
	 * @since  1.0.0
	 * @param  array             $data Prepared data.
	 * @param  \Replicast\Client $site Site object.
	 * @return array                   Possibly-modified data.
	 */
	public function prepare_object_translations( $data, $site ) {

		if ( empty( $data['replicast']['terms'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['terms'] as $term ) {

			if ( $term->taxonomy !== 'post_translations' ) {
				continue;
			}

			$translations = $this->get_translations( $term->description );
			if ( empty( $translations ) ) {
				continue;
			}

			foreach ( $translations as $lang => $translated_object_id ) {

				// Update object ID.
				$remote_info = API::get_remote_info( \get_post( $translated_object_id ) );

				unset( $translations[ $lang ] );
				if ( ! empty( $remote_info[ $site->get_id() ]['id'] ) ) {
					$translations[ $lang ] = $remote_info[ $site->get_id() ]['id'];
				}
			}

			$term->description = $this->set_translations( $translations );
		}

		return $data;
	}

	/**
	 * Prepare object terms translations.
	 *
	 * @since  1.0.0
	 * @param  array             $data Prepared data.
	 * @param  \Replicast\Client $site Site object.
	 * @return array                   Possibly-modified data.
	 */
	public function prepare_object_terms_translations( $data, $site ) {

		if ( empty( $data['replicast']['terms'] ) ) {
			return $data;
		}

		foreach ( $data['replicast']['terms'] as $term_id => $term ) {

			if ( empty( $term->polylang['translations'] ) ) {
				continue;
			}

			foreach ( $term->polylang['translations'] as $lang => $translated_object_id ) {

				unset( $data['replicast']['terms'][ $term_id ]->polylang['translations'][ $lang ] );

				$translated_term = \get_term( $translated_object_id, $term->taxonomy );
				if ( ! $translated_term ) {
					continue;
				}

				// Update object ID's.
				$remote_info = API::get_remote_info( $translated_term );
				if ( empty( $remote_info[ $site->get_id() ]['id'] ) ) {
					continue;
				}

				$data['replicast']['terms'][ $term_id ]->polylang['translations'][ $lang ] = $remote_info[ $site->get_id() ]['id'];
			}
		}

		return $data;
	}

	/**
	 * Update object translations.
	 *
	 * @since 1.4.1 Execute \pll_save_post_translations for all posts.
	 * @since 1.0.0
	 *
	 * @param array  $terms  Object terms.
	 * @param object $object The object from the response.
	 */
	public function update_object_translations( $terms, $object ) {

		if ( ! function_exists( '\pll_languages_list' ) ) {
			return;
		}

		if ( ! function_exists( '\pll_save_post_translations' ) ) {
			return;
		}

		// Get local available languages.
		$available_langs = \pll_languages_list();

		// Get post translations.
		$post_translations = array();
		foreach ( $terms as $term_data ) {

			if ( $term_data['taxonomy'] !== 'post_translations' ) {
				continue;
			}

			if ( empty( $term_data['description'] ) ) {
				continue;
			}

			$post_translations = $this->get_translations( $term_data['description'] );
		}

		// Get post language and add it, if not exists, to the post translations set.
		$post_language = \pll_get_post_language( $object->ID );
		if ( empty( $post_translations[ $post_language ] ) ) {
			$post_translations[ $post_language ] = $object->ID;
		}

		/**
		 * Polylang's \pll_save_post_translations() function makes use of reset() in the
		 * `post_translations` array, which means that, specially in the posts creation,
		 * the translated posts are only associated in the post with the language that
		 * is in the first position of the array.
		 * What is being done is running the \pll_save_post_translations() function on
		 * all posts that are translated.
		 *
		 * @see \pll_save_post_translations()
		 *
		 * @since 1.4.1
		 */
		foreach ( $post_translations as $lang => $post_id ) {

			// Only import post translations for available languages.
			if ( ! in_array( $lang, $available_langs, true ) ) {
				continue;
			}

			// Change index order.
			$current_lang = $post_translations[ $lang ];
			unset( $post_translations[ $lang ] );
			$post_translations[ $lang ] = $current_lang;

			\pll_save_post_translations( $post_translations );
		}
	}

	/**
	 * Update object terms translations.
	 *
	 * @since 1.0.0
	 * @param array $terms Object terms.
	 */
	public function update_object_terms_translations( $terms ) {

		// Get local available languages.
		$available_langs = \pll_languages_list();

		foreach ( $terms as $term_data ) {

			if ( empty( $term_data['polylang']['language'] ) ) {
				continue;
			}

			$term_id       = $term_data['term_id'];
			$term_language = $term_data['polylang']['language'];

			// Only import post translations for available languages.
			if ( ! in_array( $term_language, $available_langs, true ) ) {
				continue;
			}

			\pll_set_term_language( $term_id, $term_language );

			if ( ! empty( $term_data['polylang']['translations'] ) ) {
				$translations = $term_data['polylang']['translations'];
				$translations[ $term_language ] = $term_id;
				uksort( $translations, array( $this, 'sort_by_language' ) );
				\pll_save_term_translations( $translations );
			}
		}
	}

	/**
	 * Get object translations.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $description Object translations serialized.
	 * @return array               Object translations unserialized.
	 */
	private function get_translations( $description ) {
		return unserialize( $description );
	}

	/**
	 * Set object translations.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $translations Object translations unserialized.
	 * @return string              Object translations serialized.
	 */
	private function set_translations( $translations ) {
		return serialize( $translations );
	}

	/**
	 * Comparison function for array sorting by language.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $lang         Language slug.
	 * @param  string $current_lang Current language slug.
	 * @return int                  Integer less than, equal to, or greater than zero
	 *                              if the first argument is considered to be respectively
	 *                              less than, equal to, or greater than the second.
	 */
	private function sort_by_language( $lang, $current_lang ) {

		if ( empty( $current_lang ) && function_exists( '\pll_current_language' ) ) {
			$current_lang = \pll_current_language();
		}

		return strcasecmp( $lang, $current_lang );
	}
}
