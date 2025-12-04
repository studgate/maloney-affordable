<?php
/**
 * WordPress.org Update Provider.
 *
 * @package wpengine/common-mu-plugin
 */

namespace wpe\plugin\update_providers\providers;

use wpe\plugin\update_providers\Update_Provider;

/**
 * Implement the abstract Update_Provider class for the wordpress.org update provider.
 */
class WordPressOrg extends Update_Provider {
	/**
	 * The internal name of the update provider.
	 *
	 * @var string
	 */
	public $name = 'wordpress-org';

	/**
	 * The human-readable name of the update provider.
	 *
	 * @var string
	 */
	public $display_name = 'WordPress.org';

	/**
	 * The URL of the API to check for this provider.
	 *
	 * @var string
	 */
	public $api_url = 'https://api.wordpress.org/core/version-check/1.7/';

	/**
	 * The domain name of the API provider to use in display.
	 *
	 * @var string
	 */
	public $display_domain = 'api.wordpress.org';
}
