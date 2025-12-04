<?php
// phpcs:ignoreFile
namespace WPFormsGeolocation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

_deprecated_file( __FILE__, '2.3.0 of the WPForms Geolocation addon' );

/**
 * Class UsageTracker.
 *
 * @since 2.0.0
 * @deprecated 2.3.0
 */
class UsageTracker {

	/**
	 * Hooks.
	 *
	 * @since 2.0.0
	 * @deprecated 2.3.0
	 */
	public function hooks() {

		_deprecated_function( __METHOD__, '2.3.0 of the WPForms Geolocation addon' );

		add_filter( 'http_request_args', [ $this, 'remove_credentials' ], 10, 2 );
	}

	/**
	 * Remove credentials from the Usage Tracker.
	 *
	 * @since 2.0.0
	 * @deprecated 2.3.0
	 *
	 * @param array  $parsed_args Request arguments.
	 * @param string $url         Requested URL.
	 *
	 * @return array
	 */
	public function remove_credentials( $parsed_args, $url ) {

		_deprecated_function( __METHOD__, '2.3.0 of the WPForms Geolocation addon', '\WPForms\Integrations\UsageTracking\UsageTracking::get_settings()' );

		if (
			! class_exists( 'WPForms\Integrations\UsageTracking\SendUsageTask' ) ||
			empty( $parsed_args['method'] ) ||
			$parsed_args['method'] !== 'POST' ||
			$url !== \WPForms\Integrations\UsageTracking\SendUsageTask::TRACK_URL ||
			empty( $parsed_args['body']['wpforms_settings'] )
		) {
			return $parsed_args;
		}

		$parsed_args['body']['wpforms_settings'] = array_diff_key(
			$parsed_args['body']['wpforms_settings'],
			array_flip(
				[
					'geolocation-google-places-api-key',
					'geolocation-algolia-places-application-id',
					'geolocation-algolia-places-search-only-api-key',
				]
			)
		);

		return $parsed_args;
	}
}
