<?php
/**
 * WPEngine class file.
 *
 * @package WPE_Update_Source_Selector
 */

namespace WPE_Update_Source_Selector\Sources;

/**
 * Class: WPEngine
 *
 * Data object defining URLs and strings for the source.
 */
class WPEngine extends Source {
	/**
	 * Unique key to identify the source in settings, filters etc.
	 *
	 * @var string
	 */
	protected static $key = 'wpengine';

	/**
	 * Name for the source.
	 *
	 * @var string
	 */
	protected static $name = 'WP Engine';

	/**
	 * Domains for API services.
	 *
	 * @var array<string,string>
	 */
	protected static $domains = array(
		self::API_DOMAIN_KEY       => 'wpe-api.wpengine.com',
		self::DOWNLOADS_DOMAIN_KEY => 'wpe-downloads.wpengine.com',
		self::PLUGINS_DOMAIN_KEY   => 'wpe-plugins-svn.wpengine.com',
	);

	/**
	 * Get the source's full name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return __( 'WP Engine\'s Mirror of the WordPress.org update service', 'wpe-update-source-selector' );
	}
}
