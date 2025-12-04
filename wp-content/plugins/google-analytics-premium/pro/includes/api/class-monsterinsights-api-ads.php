<?php
/**
 * Ads API Client class for MonsterInsights.
 *
 * @since 8.0.0
 *
 * @package MonsterInsights
 */

class MonsterInsights_API_Ads extends MonsterInsights_API_Client {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->base_url = apply_filters( 'monsterinsights_api_url_ads', 'https://app.monsterinsights.com/api/v3/google-ads' );
		parent::__construct();
	}

	/**
	 * Get the access token for the Google Ads API.
	 *
	 * @return string|WP_Error The access token or a WP_Error if there was an error.
	 */
	public function get_access_token() {
		return $this->request( 'token', array(), 'GET' );
	}
} 