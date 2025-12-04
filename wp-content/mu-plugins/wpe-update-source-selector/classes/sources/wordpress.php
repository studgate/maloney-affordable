<?php
/**
 * WordPress class file.
 *
 * @package WPE_Update_Source_Selector
 */

namespace WPE_Update_Source_Selector\Sources;

/**
 * Class: WordPress
 *
 * Data object defining URLs and strings for the source.
 */
class WordPress extends Source {
	/**
	 * Unique key to identify the source in settings, filters etc.
	 *
	 * @var string
	 */
	protected static $key = 'wordpress';

	/**
	 * Name for the source.
	 *
	 * @var string
	 */
	protected static $name = 'WordPress.org';

	/**
	 * Domains for API services.
	 *
	 * @var array<string,string>
	 */
	protected static $domains = array(
		self::API_DOMAIN_KEY       => 'api.wordpress.org',
		self::DOWNLOADS_DOMAIN_KEY => 'downloads.wordpress.org',
		self::PLUGINS_DOMAIN_KEY   => 'plugins.svn.wordpress.org',
	);
}
