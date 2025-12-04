<?php
/**
 * API_Request_Manager class file.
 *
 * @package WPE_Update_Source_Selector
 */

namespace WPE_Update_Source_Selector;

use WP_Error;

/**
 * Class: API_Request_Manager
 *
 * Manages filtering of API requests.
 */
class API_Request_Manager {
	/**
	 * Instance of the main class for easy access.
	 *
	 * @var WPE_Update_Source_Selector
	 */
	private $wpe_uss;

	/**
	 * Raw (unfiltered) search/core domains to be searched for in requests. Note that
	 * these will be filtered by the `wpe_uss_search_domains` filter before being used.
	 *
	 * @var array<string,string>
	 */
	private $raw_search_domains = array();

	/**
	 * Raw (unfiltered) alt/replace domains to be used for rewriting requests. Note that
	 * these will be filtered by the `wpe_uss_replace_domains` filter before being used.
	 *
	 * @var array<string,string>
	 */
	private $raw_replace_domains = array();

	/**
	 * Flag to indicate whether search domains have been initialized.
	 *
	 * @var boolean
	 */
	private $raw_replace_domains_initialized = false;

	/**
	 * API Request Manager constructor.
	 *
	 * @param WPE_Update_Source_Selector $wpe_uss Instance of the main class.
	 *
	 * @return void
	 */
	public function __construct( WPE_Update_Source_Selector $wpe_uss ) {
		$this->wpe_uss = $wpe_uss;

		// Initialize search domains early.
		$this->init_raw_search_domains();

		// Set up filtering of requests.
		add_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 10, 3 );
		add_filter( 'wpe_uss_pre_http_request', array( $this, 'skip_url_rewrites' ) );
	}

	/**
	 * Retrieve and initialize the search domains that may need to be rewritten.
	 *
	 * @return void
	 */
	private function init_raw_search_domains() {
		/**
		 * Set up the search domains that potentially need to be rewritten.
		 *
		 * @var array<string, string> $search_domains
		 * */
		$search_domains = $this->wpe_uss->get_core_source()::get_domains();

		if ( is_array( $search_domains ) && ! empty( $search_domains ) ) {
			$this->raw_search_domains = $search_domains;
		}
	}

	/**
	 * Retrieve and initialize the replace domains.
	 *
	 * @param boolean $force Whether to force re-initialization of replace domains.
	 *
	 * @return void
	 */
	private function init_raw_replace_domains( $force = false ) {
		// If the replace domains have already been initialized, skip.
		if ( $this->raw_replace_domains_initialized && ! $force ) {
			return;
		}

		// Get the alt source domains (may be expensive due to network requests).
		$this->raw_replace_domains = $this->wpe_uss->get_alt_source()::get_domains();

		// Set the initialized flag to true.
		$this->raw_replace_domains_initialized = true;
	}

	/**
	 * Check if URL rewrites should be skipped for this HTTP request.
	 *
	 * Unless forced, this function will only initialize the replace domains
	 * once per request. As this filter is only applied in a request once it's
	 * performing a remote HTTP call, this means that the initialization is delayed
	 * until the first request is made that requires rewriting.
	 *
	 * For example, frontend only processes that do not perform a remote http request,
	 * replace domains will not be initialized at all.
	 *
	 * @handles wpe_uss_pre_http_request
	 *
	 * @param bool $skip  Whether filtering should be skipped.
	 * @param bool $force Whether initialization should be forced if previously done, default false.
	 *
	 * @return bool Whether filtering should be skipped.
	 */
	public function skip_url_rewrites( bool $skip, bool $force = false ): bool {
		$this->init_raw_replace_domains( $force );

		// If the alt/replace domain key is the same as the core/search source key, we can skip safely.
		if ( $this->wpe_uss->get_core_source()::get_key() === $this->wpe_uss->get_alt_source()::get_key() ) {
			return true;
		}

		// If no core or alt source domains are configured, we can't rewrite.
		if ( empty( $this->raw_search_domains ) || empty( $this->raw_replace_domains ) ) {
			return true;
		}

		// If the search and replace domain keys do not match, we can't rewrite.
		if ( ! empty( array_diff_key( $this->raw_search_domains, $this->raw_replace_domains ) ) ) {
			return true;
		}

		// If here, rewrites are indeed possible, and skip hasn't been requested.
		return false;
	}

	/**
	 * Returns the filtered array of search domains.
	 *
	 * For performance, this function is set up to only fire its filter once,
	 * when a request is made, assuming there are search domains returned.
	 *
	 * @return array<string,string>
	 */
	protected function get_search_domains(): array {
		static $search_domains;

		if ( ! empty( $search_domains ) ) {
			return $search_domains;
		}

		/**
		 * Filter enables modifying the associative array of domains
		 * to be searched for replacement.
		 *
		 * See the `wpe_uss_replace_domains` for where replacements must be specified
		 * using the same array keys. If they do not match, no replacement will happen.
		 *
		 * @param array<string,string> $search_domains Associative array of domains to replace.
		 */
		$search_domains = apply_filters( 'wpe_uss_search_domains', $this->raw_search_domains );

		if ( empty( $search_domains ) || ! is_array( $search_domains ) ) {
			return array();
		}

		// Make sure filtered domains have valid keys and values.
		$valid = true;
		foreach ( $search_domains as $type => $domain ) {
			if ( ! is_string( $type ) || ! is_string( $domain ) ) {
				$valid = false;
				break;
			}
		}

		if ( ! $valid ) {
			return array();
		}

		// Ensure search domains are all lowercase for consistency.
		$search_domains = array_map( 'strtolower', $search_domains );

		// Ensure domains are in a consistent order based on unique type.
		ksort( $search_domains );

		return $search_domains;
	}

	/**
	 * Returns the filtered array of replace domains.
	 *
	 * For performance, this function is set up to only fire its filter once,
	 * when a request is made, assuming there are replace domains returned.
	 *
	 * @return array<string,string>
	 */
	protected function get_replace_domains(): array {
		static $replace_domains;

		if ( ! empty( $replace_domains ) ) {
			return $replace_domains;
		}

		/**
		 * Filter enables modifying the associative array of domains
		 * to be used for replacement.
		 *
		 * See the `wpe_uss_search_domains` for where search domains must be specified
		 * using the same array keys. If they do not match, no replacement will happen.
		 *
		 * @param array<string,string> $replace_domains Associative array of domains to use for replacement.
		 */
		$replace_domains = apply_filters( 'wpe_uss_replace_domains', $this->raw_replace_domains );

		if ( empty( $replace_domains ) || ! is_array( $replace_domains ) ) {
			return array();
		}

		// Ensure domains are in a consistent order based on unique type.
		ksort( $replace_domains );

		return $replace_domains;
	}

	/**
	 * Returns the given domains each prefixed to improve search/replace accuracy.
	 *
	 * @param array<string,string> $domains Bare domains to be prefixed.
	 *
	 * @return array<string,string>
	 */
	protected static function prefix_domains( array $domains ): array {
		return array_map(
			function ( $domain ): string {
				return "://$domain";
			},
			$domains
		);
	}

	/**
	 * Filters the preemptive return value of an HTTP request.
	 *
	 * If request is for a core source domain to be rewritten, request is made with rewritten URLs.
	 *
	 * @handles pre_http_request
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of a HTTP request. Default false.
	 * @param string|array         $parsed_args HTTP request arguments.
	 * @param mixed                $url         The request URL.
	 *
	 * @return false|array|WP_Error Response array if URLs rewritten, otherwise passed in response.
	 */
	public function filter_pre_http_request( $response, $parsed_args = array(), $url = null ) {
		// Already handled.
		if ( false !== $response ) {
			return $response;
		}

		// If there's no URL, bail.
		if ( empty( $url ) || ! is_string( $url ) ) {
			return false;
		}

		// If the URL isn't for one of the core source domains, bail.
		if ( ! $this->is_host_rewritable( $url ) ) {
			return false;
		}

		// Enable skipping of rewrites, e.g. during source status checks,
		// or if current settings dictate we'll never rewrite core requests.
		if ( apply_filters( 'wpe_uss_pre_http_request', false ) ) {
			return false;
		}

		$new_url = $this->rewrite_url( $url );

		if ( $url !== $new_url ) {
			// Perform the request with the updated URL.
			return wp_remote_request(
				$new_url,
				$parsed_args
			);
		}

		return false;
	}

	/**
	 * Rewrite URL, if needed.
	 *
	 * @param string $url The request URL.
	 *
	 * @return string URL, possibly with rewritten domain.
	 */
	protected function rewrite_url( string $url ): string {
		if ( empty( $url ) ) {
			return $url;
		}

		// Get the search domains first to see if we can bail out early.
		$search_domains = $this->get_search_domains();

		// Bail if the domain isn't one of the search domains.
		if ( ! $this->is_host_rewritable( $url ) ) {
			return $url;
		}

		// Get the replace domains.
		$replace_domains = $this->get_replace_domains();

		// Check that all search domains are covered by replace domains.
		if ( ! empty( array_diff_key( $search_domains, $replace_domains ) ) ) {
			return $url;
		}

		// Rewrite URL.
		return str_ireplace(
			static::prefix_domains( $search_domains ),
			static::prefix_domains( $replace_domains ),
			$url
		);
	}


	/**
	 * Check if the given URL's host is one of the search domains.
	 *
	 * @param string $url The request URL.
	 *
	 * @return bool
	 */
	protected function is_host_rewritable( string $url ): bool {
		$search_domains = $this->get_search_domains();
		$host           = wp_parse_url( $url, PHP_URL_HOST );

		// If we can't parse the host, bail.
		if ( ! is_string( $host ) || empty( $host ) ) {
			return false;
		}

		if ( ! in_array( strtolower( $host ), $search_domains, true ) ) {
			return false;
		}

		return true;
	}
}
