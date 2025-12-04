<?php
/**
 * Source class file.
 *
 * @package WPE_Update_Source_Selector
 */

namespace WPE_Update_Source_Selector\Sources;

/**
 * Class: Source
 *
 * Base class for data objects defining URLs and strings for the source.
 */
abstract class Source {
	const API_DOMAIN_KEY       = 'api';
	const DOWNLOADS_DOMAIN_KEY = 'downloads';
	const PLUGINS_DOMAIN_KEY   = 'plugins';

	/**
	 * Unique key to identify the source in settings, filters etc.
	 *
	 * @var string
	 */
	protected static $key = '';

	/**
	 * Name for the source.
	 *
	 * @var string
	 */
	protected static $name = '';

	/**
	 * Short name for the source.
	 *
	 * Optional, defaults to name.
	 *
	 * @var string
	 */
	protected static $short_name = '';

	/**
	 * Long name for the source.
	 *
	 * Optional, defaults to name.
	 *
	 * @var string
	 */
	protected static $long_name = '';

	/**
	 * Domains for API services.
	 *
	 * @var array<string,string>
	 */
	protected static $domains = array();

	/**
	 * A representative API domain that can be displayed.
	 *
	 * The domain is not validated.
	 *
	 * Optional, defaults to domain with type "api", and failing that, name.
	 *
	 * @var string
	 */
	protected static $display_domain = '';

	/**
	 * A URL that can be used to check the status of the source.
	 *
	 * Should be the same as used for checking for WordPress Core updates.
	 *
	 * Optional, defaults to domain with type "api", using https scheme,
	 * and with path "/core/version-check/1.7/".
	 *
	 * @var string
	 */
	protected static $status_check_url = '';

	/**
	 * Get the source's unique identifier.
	 *
	 * @return string
	 */
	public static function get_key(): string {
		return static::$key;
	}

	/**
	 * Get the source's full name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return static::$name;
	}

	/**
	 * Get the source's short name.
	 *
	 * @return string
	 */
	public static function get_short_name(): string {
		return empty( static::$short_name ) ? static::get_name() : static::$short_name;
	}

	/**
	 * Get the source's long name.
	 *
	 * @return string
	 */
	public static function get_long_name(): string {
		return empty( static::$long_name ) ? static::get_name() : static::$long_name;
	}

	/**
	 * Get the source's API service domains.
	 *
	 * @return array<string,string>
	 */
	public static function get_domains(): array {
		return static::$domains;
	}

	/**
	 * Get a representative domain that can be displayed.
	 *
	 * @return string
	 */
	public static function get_display_domain(): string {
		if ( ! empty( static::$display_domain ) ) {
			return static::$display_domain;
		}

		if ( ! empty( static::get_domains()[ self::API_DOMAIN_KEY ] ) ) {
			return static::get_domains()[ self::API_DOMAIN_KEY ];
		}

		return static::get_name();
	}

	/**
	 * Get a URL that can be used to check the status of the source.
	 *
	 * @return string
	 */
	public static function get_status_check_url(): string {
		if (
			! empty( static::$status_check_url ) &&
			wp_http_validate_url( static::$status_check_url ) === static::$status_check_url
		) {
			return static::$status_check_url;
		}

		if ( ! empty( static::get_domains()[ self::API_DOMAIN_KEY ] ) ) {
			return 'https://' . trailingslashit( static::get_domains()[ self::API_DOMAIN_KEY ] ) . 'core/version-check/1.7/';
		}

		return '';
	}
}
