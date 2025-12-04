<?php
/**
 * WP Engine Feature Flags
 *
 * @package wpengine/common-mu-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sets feature flags.
 *
 * @param bool $force Whether to force the feature flags to be initialized again.
 *
 * @return void
 */
function wpe_set_feature_flags( $force = false ) {
	static $feature_flags_initialized = false;

	if ( $feature_flags_initialized && ! $force ) {
		return;
	}
	$feature_flags_initialized = true;

	$option_name     = 'wpe_feature_flags';
	$expiration_name = 'wpe_feature_flags_expiration';
	$expiration      = get_option( $expiration_name );

	if ( $expiration && time() < (int) $expiration ) {
		return;
	}

	// phpcs:ignore -- input is from a trusted source.
	$wpengine_account = isset( $_SERVER['WPENGINE_ACCOUNT'] ) ? $_SERVER['WPENGINE_ACCOUNT'] : '';
	$params           = array(
		'account'  => defined( 'PWP_NAME' ) ? PWP_NAME : $wpengine_account,
		'cluster'  => defined( 'WPE_CLUSTER_ID' ) ? WPE_CLUSTER_ID : 0,
		'platform' => 'wpe',
	);

	$response = wp_remote_get( 'https://wpe-api.wpengine.com/wpe/feature-flags?' . http_build_query( $params ) );
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		// Fallback to an empty array (defaults) if the server is unavailable.
		$data = array();
	} else {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
	}

	$validated_flags = wpe_validate_feature_flags( $data );

	if ( ! wp_installing() ) {
		update_option( $option_name, $validated_flags );
		update_option( $expiration_name, time() + 15 * MINUTE_IN_SECONDS );
	}
}

/** Sets notices. */
function wpe_set_notices() {
	$option_name     = 'wpe_notices';
	$expiration_name = 'wpe_notices_expiration';
	$expiration      = get_option( $expiration_name );

	if ( $expiration && time() < (int) $expiration ) {
		return;
	}

	$response = wp_remote_get( 'https://plugin-updates.wpengine.com/notices.json' );
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		// Fallback to no notices.
		$data = array();
	} else {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
	}

	if ( ! wp_installing() ) {
		update_option( $option_name, wpe_validate_notices( $data ) );
		update_option( $expiration_name, time() + 15 * MINUTE_IN_SECONDS );
	}
}
add_action( 'admin_init', 'wpe_set_notices' );

/**
 * Get value of WPE Notices
 *
 * @return array
 */
function wpe_get_notices() {
	return get_option( 'wpe_notices', array() );
}

/** Handle jetpack features. */
function wpe_jetpack_feature_handler() {
	$feature_flags          = get_option( 'wpe_feature_flags' );
	$active_jetpack_modules = get_option( 'jetpack_active_modules', array() );

	if ( empty( $active_jetpack_modules ) || ! is_array( $active_jetpack_modules ) ) {
		return;
	}

	$modules_to_disable = array( 'photon', 'photon-cdn' );

	$a_module_in_question_is_enabled = false;

	foreach ( $active_jetpack_modules as $index => $jetpack_module ) {
		if ( in_array( $jetpack_module, $modules_to_disable, true ) ) {
			$a_module_in_question_is_enabled = true;
			break;
		}
	}

	if (
		// If a module in question is enabled.
		$a_module_in_question_is_enabled
		&&
		// And it should be turned off.
		isset( $feature_flags['forcePhotonOff'] ) && (bool) $feature_flags['forcePhotonOff']
	) {
		// Save with photon disabled.
		$modules_to_disable = array( 'photon', 'photon-cdn' );

		$rebuilt_jetpack_modules = array();

		foreach ( $active_jetpack_modules as $index => $jetpack_module ) {
			if ( in_array( $jetpack_module, $modules_to_disable, true ) ) {
				// Don't add modules we are disabling.
				continue;
			}

			array_push( $rebuilt_jetpack_modules, $jetpack_module );
		}

		if ( ! wp_installing() ) {
			// Save that it was enabled at one point, as a backup state.
			update_option( 'wpe_photon_was_enabled', true );
			update_option( 'jetpack_active_modules', $rebuilt_jetpack_modules );
		}
	}
}
// Need to use init if no admin page load required.
add_action( 'init', 'wpe_jetpack_feature_handler', PHP_INT_MAX );

/**
 * Gets the feature flag names and their default.
 *
 * This list MUST be mirrored in the wp-updater API's defaults.
 */
function wpe_get_available_feature_flags() {
	return array(
		'wpeApi'                        => true,
		'wpeWordPressComApi'            => false,
		'wpeWooCommerceApi'             => false,
		'showAddPluginsFallbackPanel'   => false,
		'showAddPluginsPopularTags'     => false,
		'showAddThemesFallbackPanel'    => false,
		'showAddPluginsFavoritesTab'    => false,
		'forcePhotonOff'                => false,
		'showUpdateProviderHealthPanel' => false,
		'showCurrentUpdateSource'       => false,
		'showUpdateSourceSelection'     => false,
		'allowSourceOverride'           => false,
	);
}

/** Default format for WPE notices */
function wpe_notices_format() {
	return array(
		'isGlobal'              => 0,
		'screenIds'             => array(),
		'heading'               => '',
		'message'               => '',
		'isDismissible'         => 0,
		'type'                  => 'info',
		'showWpeLogo'           => 0,
		'primaryCallToAction'   => array(),
		'secondaryCallToAction' => array(),
	);
}

/**
 * Validates the feature flag response.
 *
 * @param array $body The response body.
 * @return array An array of feature flags and their status which are supported.
 */
function wpe_validate_feature_flags( $body ) {
	$validated  = array();
	$flag_names = array_keys( wpe_get_available_feature_flags() );
	foreach ( $flag_names as $k ) {
		if ( isset( $body[ $k ] ) && is_bool( $body[ $k ] ) ) {
			$validated[ $k ] = $body[ $k ];
		}
	}

	return $validated;
}

/**
 * Validates the notices.
 *
 * @param array $data The response data.
 * @return array The validated notices.
 */
function wpe_validate_notices( $data ) {
	$validated_notices = array();
	$default_format    = wpe_notices_format();

	if ( ! is_array( $data ) ) {
		return $validated_notices;
	}

	foreach ( $data as $notice ) {
		if ( is_array( $notice ) ) {
			$validated_notice = array(
				'isGlobal'              => isset( $notice['isGlobal'] ) ? ( $notice['isGlobal'] ? 1 : 0 ) : ( $default_format['isGlobal'] ? 1 : 0 ),
				'screenIds'             => isset( $notice['screenIds'] ) && is_array( $notice['screenIds'] ) ? array_map( 'wp_strip_all_tags', $notice['screenIds'] ) : $default_format['screenIds'],
				'isDismissible'         => isset( $notice['isDismissible'] ) ? ( $notice['isDismissible'] ? 1 : 0 ) : ( $default_format['isDismissible'] ? 1 : 0 ),
				'heading'               => isset( $notice['heading'] ) && is_string( $notice['heading'] ) ? wp_strip_all_tags( $notice['heading'] ) : $default_format['heading'],
				'message'               => isset( $notice['message'] ) && is_string( $notice['message'] ) ? wp_kses_post( $notice['message'] ) : $default_format['message'],
				'type'                  => isset( $notice['type'] ) && in_array( $notice['type'], array( 'info', 'warning', 'error', 'success' ), true ) ? $notice['type'] : $default_format['type'],
				'showWpeLogo'           => isset( $notice['showWpeLogo'] ) ? ( $notice['showWpeLogo'] ? 1 : 0 ) : ( $default_format['showWpeLogo'] ? 1 : 0 ),
				'primaryCallToAction'   => wpe_validate_call_to_action( isset( $notice['primaryCallToAction'] ) ? $notice['primaryCallToAction'] : array() ),
				'secondaryCallToAction' => wpe_validate_call_to_action( isset( $notice['secondaryCallToAction'] ) ? $notice['secondaryCallToAction'] : array() ),
			);

			$validated_notices[] = $validated_notice;
		}
	}

	return $validated_notices;
}

/**
 * Validates the call-to-action fields.
 *
 * @param array $cta The call-to-action array.
 * @return array The validated call-to-action array.
 */
function wpe_validate_call_to_action( $cta ) {
	if ( empty( $cta ) ) {
		return array();
	}
	return array(
		'text'        => isset( $cta['text'] ) && is_string( $cta['text'] ) ? wp_strip_all_tags( $cta['text'] ) : '',
		'url'         => isset( $cta['url'] ) && is_string( $cta['url'] ) ? esc_url( $cta['url'] ) : '',
		'opensNewTab' => isset( $cta['opensNewTab'] ) ? ( $cta['opensNewTab'] ? 1 : 0 ) : 0,
	);
}

/**
 * Gets whether a feature flag is active.
 *
 * @param string $flag The flag name.
 * @return bool|null The feature flag override if set, the API provided result if not, or null if the feature flag is requested is unknown.
 */
function wpe_is_feature_flag_active( $flag ) {
	wpe_set_feature_flags();
	$flags = get_option( 'wpe_feature_flags', array() );
	if ( isset( $flags[ $flag ] ) ) {
		return (bool) $flags[ $flag ];
	} else {
		$defaults = wpe_get_available_feature_flags();
		if ( isset( $defaults[ $flag ] ) ) {
			return $defaults[ $flag ];
		}
	}
	return null;
}

/**
 * Whether to use the WPE updater API.
 *
 * This is an alias of wpe_use_wpe_updater_api() that exists in case some
 * external code is calling this function.
 *
 * Historically it made sense that this function referred generically to our
 * wp_updater_api, but now we are referencing multiple updater APIs the old name
 * is confusing.
 *
 * @deprecated
 *
 * @return bool
 */
function wpe_use_wp_updater_api() {
	return wpe_use_wpe_updater_api();
}

/**
 * Whether to use the WPE updater API.
 *
 * @return bool
 */
function wpe_use_wpe_updater_api() {
	return wpe_is_feature_flag_active( 'wpeApi' );
}

/**
 * Whether to use the alternate API for WordPress.com.
 *
 * @return bool
 */
function wpe_use_alternate_wordpress_com_api() {
	return wpe_is_feature_flag_active( 'wpeWordPressComApi' );
}

/**
 * Whether to use the alternate API for WooCommerce.
 *
 * @return bool
 */
function wpe_use_alternate_woo_commerce_api() {
	return wpe_is_feature_flag_active( 'wpeWooCommerceApi' );
}

/**
 * Whether to show the update provider health panel.
 *
 * @return bool
 */
function wpe_show_update_provider_health_panel() {
	return wpe_is_feature_flag_active( 'showUpdateProviderHealthPanel' );
}

/**
 * Whether to show the current update provider source.
 *
 * @return bool
 */
function wpe_show_update_provider_current_source() {
	return wpe_is_feature_flag_active( 'showCurrentUpdateSource' );
}

/**
 * Whether to show the update provider settings page.
 *
 * @return bool
 */
function wpe_show_update_source_selection() {
	return wpe_is_feature_flag_active( 'showUpdateSourceSelection' );
}

/**
 * Whether site admins may change the update source.
 *
 * @return bool
 */
function wpe_allow_source_override() {
	return wpe_is_feature_flag_active( 'allowSourceOverride' );
}

/**
 * Filters the HTTP request arguments before the request is made.
 *
 * This function checks if the request URL is for WordPress updates or plugin/theme information.
 * Also, if it's for a WooCommerce endpoint.
 * If so, it replaces the default API URL with WPE URLs.
 *
 * @param bool|array $c     Whether to preempt the request return. Default false.
 * @param array      $args  HTTP request arguments.
 * @param string     $url   The request URL.
 *
 * @return bool|array The response or false if not preempting the request.
 */
function wpe_use_wpe_api_services( $c, $args, $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host ) {
		return $c;
	}

	/*
	 * If the request is for a known .org API URL, AND the WPE Update Source Selector
	 * isn't enabled AND the feature flags suggest that we should use the WPE updater API,
	 * let's rewrite the URL to use our API.
	 */
	if (
		! defined( 'WPE_USS_FILE' ) &&
		in_array( $host, array( 'api.wordpress.org', 'downloads.wordpress.org', 'plugins.svn.wordpress.org' ), true ) &&
		wpe_use_wpe_updater_api()
	) {
		return wp_remote_request(
			str_replace(
				array( '://api.wordpress.org', '://downloads.wordpress.org', '://plugins.svn.wordpress.org' ),
				array( '://wpe-api.wpengine.com', '://wpe-downloads.wpengine.com', '://wpe-plugins-svn.wpengine.com' ),
				$url
			),
			$args
		);
	}

	if ( 'public-api.wordpress.com' === $host && wpe_use_alternate_wordpress_com_api() ) {
		$url = str_replace( '://public-api.wordpress.com', '://wpe-wcom-api.wpengine.com', $url );
		return wp_remote_request( $url, $args );
	}
	if ( 'api.woocommerce.com' === $host && wpe_use_alternate_woo_commerce_api() ) {
		$url = str_replace( '://api.woocommerce.com', '://wpe-woo-api.wpengine.com', $url );
		return wp_remote_request( $url, $args );
	}

	return $c;
}
add_filter( 'pre_http_request', 'wpe_use_wpe_api_services', 10, 3 );
