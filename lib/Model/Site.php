<?php

/**
 * Site term wrapper
 *
 * @link       http://log.pt/
 * @since      1.0.0
 *
 * @package    Replicast
 * @subpackage Replicast/lib/Model
 */

namespace Replicast\Model;

/**
 * Site term wrapper.
 *
 * @since      1.0.0
 * @package    Replicast
 * @subpackage Replicast/lib/Model
 * @author     log.OSCON, Lda. <engenharia@log.pt>
 */
class Site {

	/**
	 * Term object.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       \WP_Term
	 */
	protected $term;

	/**
	 * Term meta.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       array
	 */
	protected $term_meta;

	/**
	 * HTTP client.
	 *
	 * @since     1.0.0
	 * @access    protected
	 * @var       \GuzzleHttp\Client
	 */
	protected $client;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \WP_Term              $term      The term object.
	 * @param    \GuzzleHttp\Client    $client    The HTTP client.
	 */
	public function __construct( $term, $client ) {
		$this->term      = $term;
		$this->client    = $client;
		$this->term_meta = $this->get_meta();
	}

	/**
	 * Get site ID.
	 *
	 * @since     1.0.0
	 * @return    int    Term ID.
	 */
	public function get_id() {
		return $this->term->term_id;
	}

	/**
	 * Get site name.
	 *
	 * @since     1.0.0
	 * @return    string    Term name.
	 */
	public function get_name() {
		return $this->term->name;
	}

	/**
	 * Get site config.
	 *
	 * @since     1.0.0
	 * @return    array    Site url, and API url, key and secret.
	 */
	public function get_config() {
		return array(
			'site_url'   => \untrailingslashit( $this->term_meta['site_url'][0] ),
			'api_url'    => \trailingslashit( $this->term_meta['api_url'][0] ),
			'apy_key'    => $this->term_meta['api_key'][0],
			'api_secret' => $this->term_meta['api_secret'][0],
		);
	}

	/**
	 * Get site meta data.
	 *
	 * @since     1.0.0
	 * @return    array    The meta data from a site term.
	 */
	public function get_meta() {
		return \get_term_meta( $this->term->term_id );
	}

	/**
	 * Get site HTTP client.
	 *
	 * @since     1.0.0
	 * @return    \GuzzleHttp\Client    The HTTP client.
	 */
	public function get_client() {
		return $this->client;
	}

	/**
	 * Check if site is valid.
	 *
	 * @since     1.0.0
	 * @return    bool    True if all the required fields are filled. False, otherwise.
	 */
	public function is_valid() {

		$required_keys = array(
			'site_url',
			'api_url',
			'api_key',
			'api_secret'
		);

		foreach( $required_keys as $key ) {
			if ( empty( $this->term_meta[ $key ] ) || empty( $this->term_meta[ $key ][0] ) ) {
				return false;
			}
		}

		return true;
	}

}
