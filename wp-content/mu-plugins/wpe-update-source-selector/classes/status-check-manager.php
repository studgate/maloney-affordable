<?php
/**
 * Status_Check_Manager class file.
 *
 * @package WPE_Update_Source_Selector
 */

namespace WPE_Update_Source_Selector;

use WP_Error;

/**
 * Class: Status_Check_Manager
 *
 * Manages checking the status of source endpoints.
 */
class Status_Check_Manager {
	const CHECKING_STATUS_KEY = 'checking';
	const ERROR_STATUS_KEY    = 'error';
	const SUCCESS_STATUS_KEY  = 'success';

	const CHECK_SOURCE_STATUS_NONCE   = 'check_source_status';
	const SOURCE_STATUS_TRANSIENT_KEY = 'wpe_uss_source_status';

	/**
	 * Instance of the main class for easy access.
	 *
	 * @var WPE_Update_Source_Selector
	 */
	private $wpe_uss;

	/**
	 * Status Check Manager constructor.
	 *
	 * @param WPE_Update_Source_Selector $wpe_uss Instance of the main class.
	 *
	 * @return void
	 */
	public function __construct( WPE_Update_Source_Selector $wpe_uss ) {
		$this->wpe_uss = $wpe_uss;

		$this->init();
	}

	/**
	 * Initialize the Status Check Manager.
	 *
	 * @return void
	 */
	protected function init() {
		add_filter( 'wpe_uss_localize_script_args', array( $this, 'filter_wpe_uss_localize_script_args' ) );
		add_action( 'wp_ajax_wpe_uss_check_source_status', array( $this, 'ajax_check_source_status' ) );
		add_filter( 'wpe_uss_get_source_statuses', array( $this, 'filter_get_source_statuses' ), 10, 2 );
		add_filter( 'wpe_uss_check_source_statuses', array( $this, 'filter_check_source_statuses' ), 10, 3 );
	}

	/**
	 * Filter the script args to add nonce.
	 *
	 * @handles wpe_uss_localize_script_args
	 *
	 * @param array<string,string> $args Args to be localized for script.
	 *
	 * @return array<string,string>
	 */
	public function filter_wpe_uss_localize_script_args( $args ): array {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		$args['check_source_status_nonce'] = wp_create_nonce( self::CHECK_SOURCE_STATUS_NONCE );
		$args['current_source_key']        = $this->wpe_uss->get_alt_source()::get_key();

		return $args;
	}

	/**
	 * Get the status of given source keys.
	 *
	 * Unlike filter_check_source_statuses, this filter function will not attempt to perform the check.
	 * It will also return the "checking" status if there is no cached result yet.
	 *
	 * @handles wpe_uss_get_source_statuses
	 *
	 * @param array<string,array<string,mixed>>|mixed $statuses    Associative array keyed by source key.
	 * @param array<string>                           $source_keys Array of source keys to get the status for.
	 *
	 * @return array<string,array<string,mixed>>|mixed Associative array keyed by source key.
	 */
	public function filter_get_source_statuses( $statuses, array $source_keys ) {
		if ( ! is_array( $statuses ) ) {
			return $statuses;
		}

		foreach ( $source_keys as $source_key ) {
			$statuses[ $source_key ] = self::get_source_status( $source_key );
		}

		return $statuses;
	}

	/**
	 * Return's the source's status.
	 *
	 * @param string $source_key The source's key.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_source_status( string $source_key ): array {
		global $wpe_uss;

		// By default, assume we'll need to check.
		$checked = time();
		$expires = $checked + MINUTE_IN_SECONDS;

		$result = array(
			'status'  => self::CHECKING_STATUS_KEY,
			'checked' => $checked,
			'expires' => $expires,
			'title'   => self::get_status_title( $checked, $expires ),
		);

		// If we don't even know the source key, quickly fail to error.
		if ( ! $wpe_uss->valid_source( $source_key ) ) {
			$result['status'] = self::ERROR_STATUS_KEY;

			return $result;
		}

		// If the transient has the required status, and status hasn't expired, just use it.
		$statuses = get_site_transient( self::SOURCE_STATUS_TRANSIENT_KEY );

		if (
			is_array( $statuses ) &&
			isset( $statuses[ $source_key ] ) &&
			is_array( $statuses[ $source_key ] ) &&
			! empty( $statuses[ $source_key ]['status'] ) &&
			in_array(
				$statuses[ $source_key ]['status'],
				array( self::SUCCESS_STATUS_KEY, self::ERROR_STATUS_KEY ),
				true
			) &&
			! empty( $statuses[ $source_key ]['expires'] ) &&
			time() < (int) $statuses[ $source_key ]['expires']
		) {
			$result          = $statuses[ $source_key ];
			$result['title'] = self::get_status_title( $result['checked'], $result['expires'] );
		}

		return $result;
	}

	/**
	 * Get a formatted string suitable for the title of the source status display.
	 *
	 * @param int $checked Timestamp for when the status was checked.
	 * @param int $expires Timestamp for when the status is next to be checked.
	 *
	 * @return string
	 */
	protected static function get_status_title( int $checked, int $expires ): string {
		$checked_string = gmdate( DATE_RFC850, $checked );
		$expires_string = gmdate( DATE_RFC850, $expires );

		return sprintf(
		/* translators: localized date strings */
			__( 'Checked %1$s, next check %2$s.', 'wpe-update-source-selector' ),
			$checked_string,
			$expires_string
		);
	}

	/**
	 * Check the connection status of the given source key.
	 *
	 * @handles wp_ajax_wpe_uss_check_source_status
	 *
	 * @return void
	 */
	public function ajax_check_source_status() {
		check_ajax_referer( self::CHECK_SOURCE_STATUS_NONCE );

		$source_key = ! empty( $_REQUEST['source_key'] ) && is_string( $_REQUEST['source_key'] ) ? sanitize_key( $_REQUEST['source_key'] ) : '';
		$force      = ! empty( $_REQUEST['force'] ) && is_string( $_REQUEST['force'] ) && 'true' === sanitize_key( $_REQUEST['force'] );

		if ( empty( $source_key ) ) {
			wp_send_json_error(
				new WP_Error(
					'wpe-update-source-selector-invalid-source-key',
					__( 'Source key not supplied.', 'wpe-update-source-selector' )
				)
			);
		}

		wp_send_json_success( $this->check_source_status( $source_key, $force ) );
	}

	/**
	 * Check the status of given source keys.
	 *
	 * @handles wpe_uss_check_source_statuses
	 *
	 * @param array<string,array<string,mixed>>|mixed $statuses    Associative array keyed by source key.
	 * @param array<string>                           $source_keys Array of source keys to be checked.
	 * @param bool                                    $force       Skip cached results and do connectivity tests.
	 *
	 * @return array<string,array<string,mixed>>|mixed Associative array keyed by source key.
	 */
	public function filter_check_source_statuses( $statuses, array $source_keys, bool $force = false ) {
		if ( ! is_array( $statuses ) ) {
			return $statuses;
		}

		foreach ( $source_keys as $source_key ) {
			$statuses[ $source_key ] = $this->check_source_status( $source_key, $force );
		}

		return $statuses;
	}

	/**
	 * Check whether we can connect to the given source.
	 *
	 * @param string $source_key Source's key.
	 * @param bool   $force      Skip cache and do check.
	 *
	 * @return array<string,mixed>
	 */
	protected function check_source_status( string $source_key, bool $force = false ): array {
		$checked = time();
		$expires = $checked + MINUTE_IN_SECONDS;

		$error = array(
			'status'  => self::ERROR_STATUS_KEY,
			'checked' => $checked,
			'expires' => $expires,
			'title'   => self::get_status_title( $checked, $expires ),
		);

		// If we don't even know the source key, quickly fail to error.
		if ( ! $this->wpe_uss->valid_source( $source_key ) ) {
			return $error;
		}

		// If the transient has the required status, and status hasn't expired, just use it.
		$statuses = get_site_transient( self::SOURCE_STATUS_TRANSIENT_KEY );

		if ( empty( $statuses ) || ! is_array( $statuses ) ) {
			$statuses = array();
		}

		if (
			! $force &&
			isset( $statuses[ $source_key ] ) &&
			is_array( $statuses[ $source_key ] ) &&
			! empty( $statuses[ $source_key ]['status'] ) &&
			in_array(
				$statuses[ $source_key ]['status'],
				array( self::SUCCESS_STATUS_KEY, self::ERROR_STATUS_KEY ),
				true
			) &&
			! empty( $statuses[ $source_key ]['expires'] ) &&
			time() < (int) $statuses[ $source_key ]['expires']
		) {
			$result          = $statuses[ $source_key ];
			$result['title'] = self::get_status_title( $result['checked'], $result['expires'] );

			return $result;
		}

		// Get source's status check URL, and bail if not set up correctly.
		$status_check_url = $this->wpe_uss->get_source( $source_key )::get_status_check_url();

		if ( empty( $status_check_url ) ) {
			return $error;
		}

		// Get the source's status and update the transient's value.
		$statuses[ $source_key ]           = array( 'checked' => time() );
		$statuses[ $source_key ]['status'] = $this->check_url( $status_check_url )
			? self::SUCCESS_STATUS_KEY
			: self::ERROR_STATUS_KEY;

		/**
		 * Enables adjusting the expiry interval for the source status cache.
		 *
		 * @param int    $seconds Number of seconds, default 900s (15m) or 60s if status is error, min 60s, max 43,200s (12h).
		 * @param string $status  The status being cached, "success" or "error".
		 */
		$expiry = self::SUCCESS_STATUS_KEY === $statuses[ $source_key ]['status'] ? 15 * MINUTE_IN_SECONDS : MINUTE_IN_SECONDS;
		$expiry = apply_filters( 'wpe_uss_source_status_expiry', $expiry, $statuses[ $source_key ]['status'] );
		$expiry = is_int( $expiry )
			? max( min( $expiry, 12 * HOUR_IN_SECONDS ), MINUTE_IN_SECONDS )
			: 15 * MINUTE_IN_SECONDS;

		$statuses[ $source_key ]['expires'] = time() + $expiry;

		set_site_transient( self::SOURCE_STATUS_TRANSIENT_KEY, $statuses, $expiry );

		$result          = $statuses[ $source_key ];
		$result['title'] = self::get_status_title( $result['checked'], $result['expires'] );

		return $result;
	}

	/**
	 * Check whether we can connect to the given URL.
	 *
	 * @param string $url The URL to check connectivity for.
	 *
	 * @return bool
	 */
	private function check_url( string $url ): bool {
		if ( empty( $url ) ) {
			return false;
		}

		/**
		 * Enables adjusting the timeout when testing URL's connectivity.
		 *
		 * @param int $seconds Number of seconds, default 5, min 1, max 10.
		 */
		$timeout = apply_filters( 'wpe_uss_check_url_timeout', 5 );
		$timeout = is_int( $timeout ) ? max( min( $timeout, 10 ), 1 ) : 5;

		/**
		 * Enables adjusting the number of retries allowed when testing a URL's connectivity.
		 *
		 * @param int $retries Default 1, min 0, max 3.
		 */
		$retries = apply_filters( 'wpe_uss_check_url_retries', 1 );
		$retries = is_int( $retries ) ? max( min( $retries, 3 ), 0 ) : 1;

		$args = array(
			'timeout' => $timeout,
		);

		/**
		 * Enables adjusting the user-agent used when testing a URL's connectivity.
		 *
		 * @param string $user_agent Default "plugin-slug/version".
		 * @param string $url        URL being checked.
		 */
		$user_agent = apply_filters(
			'wpe_uss_check_url_user_agent',
			$this->wpe_uss->get_plugin_slug() . '/' . $this->wpe_uss->get_plugin_info( 'Version' ),
			$url
		);

		if ( ! empty( $user_agent ) && is_string( $user_agent ) ) {
			$args['user-agent'] = $user_agent;
		}

		add_filter( 'wpe_uss_pre_http_request', '__return_true' );

		$ok = false;
		foreach ( range( 0, $retries ) as $seconds ) {
			$response = wp_remote_get( $url, $args );

			if ( $this->valid_check_url_response( $response ) ) {
				$ok = true;
				break;
			}

			// Simple backoff.
			if ( $retries < $seconds ) {
				sleep( $seconds + 1 );
			}
		}

		remove_filter( 'wpe_uss_pre_http_request', '__return_true' );

		return $ok;
	}

	/**
	 * Is the response from checking a URL valid?
	 *
	 * Validates that the URL responds with the bare minimum shape of data
	 * expected when performing a WordPress version check.
	 *
	 * @param mixed $response The response from checking a URL.
	 *
	 * @return bool
	 */
	private function valid_check_url_response( $response ): bool {
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			return false;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = trim( wp_remote_retrieve_body( $response ) );
		$body = json_decode( $body, true );

		if ( ! is_array( $body ) || ! isset( $body['offers'] ) ) {
			return false;
		}

		return true;
	}
}
