<?php
/**
 * Update Provider Health Check.
 *
 * We use AJAX for this rather than the REST API as the REST API requires HTTPS
 * and is not guaranteed to be available on all sites, or in local development.
 *
 * @package wpengine/common-mu-plugin
 */

namespace wpe\plugin\update_providers;

use wpe\plugin\update_providers\Update_Providers;
use \WpeCommon;

/**
 * Functionality for update provider health checks.
 */
class Update_Provider_Health {
	const NONCE_NAME = 'wpe_update_provider_health_check';

	/**
	 * Constructor.
	 */
	public function __construct() {
		require_once __DIR__ . '/class-update-provider-healthcheck-response.php';

		$this->register_endpoints();
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
	}

	/**
	 * Returns true if we are on a page that should show the provider health check.
	 *
	 * @return boolean
	 */
	private function should_show_provider_health_check() {
		return WpeCommon::is_plugin_page();
	}

	/**
	 * Adds the AJAX endpoints for the provider healthcheck data.
	 *
	 * @return void
	 */
	public function register_endpoints() {
		add_action( 'wp_ajax_wpe_update_provider_health_check', array( $this, 'health_check' ) );
	}


	/**
	 * Registers JavaScript for use by the provider health check widget if required.
	 *
	 * @return void
	 */
	public function register_scripts() {
		if (
			! $this->should_show_provider_health_check() ||
			! current_user_can( WpeCommon::get_required_admin_capability() )
		) {
			return;
		}

		wp_enqueue_script( 'wpe-update-provider-health', trailingslashit( WPE_PLUGIN_URL ) . 'js/update-provider-health.js', array(), WPE_PLUGIN_VERSION, true );
		wp_localize_script(
			'wpe-update-provider-health',
			'wpe_update_provider_health',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_NAME ),
			)
		);
	}

	/**
	 * Handle the AJAX request for the provider health check data.
	 *
	 * @return void
	 */
	public function health_check() {
		// The nonce that we check should only be given to admins, but this
		// check makes the security explicit for this endpoint.
		if ( ! current_user_can( WpeCommon::get_required_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
			wp_die();
		}

		$update_providers = Update_Providers::instance();

		$auth_check = check_ajax_referer( self::NONCE_NAME );

		if ( false === $auth_check ) {
			wp_send_json_error( 'Invalid nonce', 403 );
			wp_die();
		}

		$provider_health_responses = array();
		foreach ( $update_providers->providers as $provider ) {
			$provider->update_api_reachable();
			$provider_health_responses[ $provider->name ] = Update_Provider_Healthcheck_Response::from_update_provider( $provider );
		}

		wp_send_json_success( $provider_health_responses );
		wp_die();
	}
}
