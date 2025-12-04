<?php
/**
 * WPEngine update provider.
 *
 * @package wpengine/common-mu-plugin
 */

namespace wpe\plugin\update_providers\providers;

use wpe\plugin\update_providers\Update_Provider;

/**
 * Implement the abstract Update_Provider class for the WPEngine update provider.
 */
class WPEngine extends Update_Provider {
	/**
	 * The internal name of the update provider.
	 *
	 * @var string
	 */
	public $name = 'wpengine';

	/**
	 * The human-readable name of the update provider.
	 *
	 * @var string
	 */
	public $display_name = 'WP Engine’s Mirror of the WordPress.org update service';

	/**
	 * The URL of the API to check for this provider.
	 *
	 * @var string
	 */
	public $api_url = 'https://wpe-api.wpengine.com';

	/**
	 * The domain name of the API provider to use in display.
	 *
	 * @var string
	 */
	public $display_domain = 'wpe-api.wpengine.com';

	/**
	 * The user agent for HTTP requests.
	 *
	 * This is a variable/property so that it can be overridden in child classes.
	 *
	 * @var string
	 */
	protected $api_check_http_user_agent = 'WPE-Update-Provider-Health-Check';
}
