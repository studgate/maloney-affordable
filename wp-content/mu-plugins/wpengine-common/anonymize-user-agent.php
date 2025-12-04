<?php
/**
 * Anonymize the user-agent site URL string for requests to known updater endpoints.
 *
 * @package wpengine/common-mu-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Removes domain information from the HTTP request arguments before a request
 * to a known updater endpoint is made.
 *
 * @param array  $args HTTP request arguments.
 * @param string $url  The request URL.
 *
 * @return array The cleaned HTTP request arguments.
 */
function wpe_clean_api_services_headers( $args, $url ) {
	if ( ! is_array( $args ) ) {
		return $args;
	}

	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host ) {
		return $args;
	}

	$org_hosts = array( 'api.wordpress.org', 'downloads.wordpress.org', 'plugins.svn.wordpress.org' );
	$wpe_hosts = array( 'wpe-api.wpengine.com', 'wpe-downloads.wpengine.com', 'wpe-plugins-svn.wpengine.com' );
	$hosts     = array_merge( $org_hosts, $wpe_hosts );

	if ( ! in_array( $host, $hosts, true ) ) {
		return $args;
	}

	if ( isset( $args['user-agent'] ) ) {
		$args['user-agent'] = 'WordPress/' . get_bloginfo( 'version' ) . '; http://localhost/';
	}

	if ( is_array( $args['headers'] ) ) {
		unset( $args['headers']['wp_install'] );
		unset( $args['headers']['wp_blog'] );
	}

	return $args;
}
add_filter( 'http_request_args', 'wpe_clean_api_services_headers', 10, 2 );
