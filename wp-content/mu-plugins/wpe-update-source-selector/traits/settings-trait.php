<?php
/**
 * Settings_Trait trait file.
 *
 * @package WPE_Update_Source_Selector;
 */

namespace WPE_Update_Source_Selector;

/**
 * Trait: Settings_Trait
 *
 * A utility trait for managing the plugin's settings.
 */
trait Settings_Trait {
	/**
	 * The string used to save the currently preferred source.
	 *
	 * @var string
	 */
	protected static $site_preference_settings_key = 'wpe_uss_site_preference';

	/**
	 * Get the preferred source for the site.
	 *
	 * Returns an empty string if a preferred source has not been set.
	 *
	 * @return string
	 */
	public function get_site_preference(): string {
		$site_preference = get_site_option( static::$site_preference_settings_key );

		if ( is_string( $site_preference ) && $this->valid_source( $site_preference ) ) {
			return $site_preference;
		}

		return '';
	}

	/**
	 * Set the preferred source for the site.
	 *
	 * @param string $source_key Source's key.
	 *
	 * @return bool
	 */
	public function set_site_preference( string $source_key ): bool {
		return update_site_option( static::$site_preference_settings_key, $source_key );
	}

	/**
	 * Delete the preferred source for the site.
	 *
	 * @return bool
	 */
	public function delete_site_preference(): bool {
		if ( ! get_site_option( static::$site_preference_settings_key ) ) {
			return true;
		}

		return delete_site_option( static::$site_preference_settings_key );
	}

	/**
	 * Returns a list of valid source keys.
	 *
	 * @return string[]
	 */
	protected function valid_source_keys(): array {
		$source_keys = array( $this->get_core_source()::get_key() );

		return array_merge( $source_keys, array_keys( $this->get_alt_sources() ) );
	}

	/**
	 * Is the given key for a source that is available to use?
	 *
	 * @param string $source_key Source's key to be checked as available for use.
	 *
	 * @return bool
	 */
	public function valid_source( string $source_key ): bool {
		if (
			! empty( $source_key ) &&
			in_array( $source_key, $this->valid_source_keys(), true )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the currently enabled core source.
	 *
	 * @return string
	 */
	abstract public function get_core_source(): string;

	/**
	 * Returns all the currently enabled alt sources.
	 *
	 * @return array<string,string>
	 */
	abstract public function get_alt_sources(): array;

	/**
	 * Returns the currently active alt source.
	 *
	 * @return string
	 */
	abstract public function get_alt_source(): string;
}
