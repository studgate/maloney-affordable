<?php
/** Functions relating to site health data storage for use in the go live checklist.
 *
 * @package wpengine/common-mu-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * A filter that stores the site health check results in the database for access by the go live checklist.
 * It is necessary to store it in this fashion as it is set as a transient by default, which is not guaranteed
 * to be saved in the database.
 *
 * @param string $value the value of the transient being saved.
 * @return string the unchanged value of the transient.
 */
function store_site_health_results_in_db( $value ) {
	update_option( 'wpe-health-check-site-status-result', $value );

	return $value;
}

add_filter( 'pre_set_transient_health-check-site-status-result', 'store_site_health_results_in_db' );

/**
 * A filter to add communication checks with WP Engine's WordPress API.
 * Adding both two tests, one for standard WordPress methods of external communication, and one for direct comms
 * via PHP function which will allow support to see if theme or plugin code is affecting the ability to communicate.
 *
 * @return array an array of additional fields which get merged into the WP Core site health fields.
 */
function wpe_get_site_health_api_fields() {
	$return    = array();
	$wp_result = wp_remote_get( 'https://wpe-api.wpengine.com', array( 'timeout' => 5 ) );

	if ( is_wp_error( $wp_result ) ) {
		$result_string = 'Unable to communicate with the WP Engine WordPress API.';
		$debug         = 'WP_Error';
	} elseif ( ! is_array( $wp_result ) || '{}' !== $wp_result['body'] ) {
		$result_string = 'An unexpected result was returned from the WP Engine WordPress API.';
		$debug         = ! empty( $wp_result['body'] ) && is_string( $wp_result['body'] ) ? $wp_result['body'] : 'false';
	} else {
		$result_string = 'The WP Engine WordPress API is reachable.';
		$debug         = true;
	}

	$return['wpengine_api'] = array(
		'label' => 'Communication with the WP Engine WordPress API',
		'value' => $result_string,
		'debug' => $debug,
	);

	$opts    = array(
		'http' => array(
			'timeout' => 60,
		),
	);
	$context = stream_context_create( $opts );

	/**
	 * WPE API Direct Check
	 *
	 * An extra non-filterable connection check to our API, some plugins (especially those modified to remove licensing code)
	 * break our API code, so this check is designed to see if there is a local WP issue causing problems.
	 */
	// phpcs:ignore -- we need to use file_get_contents here to test the direct connection.
	$raw_result = @file_get_contents( 'https://wpe-api.wpengine.com', false, $context );

	if ( is_wp_error( $raw_result ) || false === $raw_result ) {
		$result_string = 'Unable to communicate with the WP Engine WordPress API Directly.';
	} elseif ( '{}' !== $raw_result ) {
		$result_string = 'An unexpected result was returned from the WP Engine WordPress API Directly.';
	} else {
		$result_string = 'The WP Engine WordPress API is reachable directly.';
	}

	$return['wpengine_api_direct'] = array(
		'label' => 'Communication with the WP Engine WordPress API (Direct)',
		'value' => $result_string,
		'debug' => is_string( $wp_result ) ? $wp_result : false,
	);

	return $return;
}

add_filter(
	'debug_information',
	function ( $info ) {
		$info['wp-core']['fields'] = array_merge( $info['wp-core']['fields'], wpe_get_site_health_api_fields() );
		return $info;
	}
);
