<?php
/**
 * WPE_Update_Source_Selector class file.
 *
 * @package WPE_Update_Source_Selector
 */

namespace WPE_Update_Source_Selector;

use WPE_Update_Source_Selector\Sources\WordPress;
use WPE_Update_Source_Selector\Sources\WPEngine;

/**
 * Class: WPE_Update_Source_Selector
 *
 * The main entry class for the plugin.
 */
class WPE_Update_Source_Selector {
	use Plugin_Info_Trait;
	use Settings_Trait;

	/**
	 * The core source.
	 *
	 * @var string
	 */
	protected $core_source;

	/**
	 * An array of alternative sources.
	 *
	 * @var array<string,string>
	 */
	protected $alt_sources = array();

	/**
	 * Source currently required to be used by host.
	 *
	 * @var string
	 */
	protected $host_override;

	/**
	 * Source preferred by host.
	 *
	 * @var string
	 */
	protected $host_preference;

	/**
	 * Source preferred by hosting account.
	 *
	 * @var string
	 */
	protected $hosting_account_preference;

	/**
	 * Core API request manager.
	 *
	 * @var API_Request_Manager
	 */
	protected $api_request_manager;

	/**
	 * Status check manager.
	 *
	 * @var Status_Check_Manager
	 */
	protected $status_check_manager;

	/**
	 * Admin UI.
	 *
	 * @var Admin_UI
	 */
	protected $admin_ui;

	/**
	 * WPE_Update_Source_Selector constructor.
	 *
	 * @param string $plugin_slug Plugin's slug.
	 * @param string $plugin_file Plugin's entry file.
	 * @param string $base_path   Base path for source files.
	 *
	 * @return void
	 */
	public function __construct( string $plugin_slug, string $plugin_file, string $base_path ) {
		$this->plugin_slug = $plugin_slug;
		$this->plugin_file = $plugin_file;
		$this->base_path   = empty( $base_path ) ? trailingslashit( dirname( __DIR__ ) ) : trailingslashit( $base_path );

		$this->init();
	}

	/**
	 * Initialize the various components of the plugin.
	 *
	 * Each component will register any hooks as appropriate.
	 *
	 * @return void
	 */
	protected function init() {
		// Initialize default core sources.
		$this->core_source = apply_filters(
			'wpe_uss_core_source',
			WordPress::class
		);

		// Initialize default alternative sources.
		$this->alt_sources = apply_filters(
			'wpe_uss_alt_sources',
			array(
				WPEngine::get_key() => WPEngine::class,
			)
		);

		// Initialize Core API handling.
		$this->api_request_manager = new API_Request_Manager( $this );

		// Initialize status check manager.
		$this->status_check_manager = new Status_Check_Manager( $this );

		// Initialize Admin UI.
		$this->admin_ui = new Admin_UI( $this );

		// Let other plugins find out what the current source's key is.
		add_filter( 'wpe_uss_get_current_source', array( $this, 'filter_get_current_source' ) );
	}

	/**
	 * Returns the currently enabled core source.
	 *
	 * @return string
	 */
	public function get_core_source(): string {
		// Make sure we have a valid core source by falling back to WordPress source.
		if ( empty( $this->core_source ) || ! class_exists( $this->core_source ) ) {
			$this->core_source = WordPress::class;
		}

		return $this->core_source;
	}

	/**
	 * Returns all the currently enabled alt sources.
	 *
	 * @return array<string,string>
	 */
	public function get_alt_sources(): array {
		return $this->alt_sources;
	}

	/**
	 * Returns all the currently enabled sources.
	 *
	 * They're returned sorted by their key.
	 *
	 * @return array<string,string>
	 */
	public function get_sources(): array {
		$core_source = $this->get_core_source();
		$sources     = $this->get_alt_sources();

		// Add the core source to our array.
		$sources[ $core_source::get_key() ] = $core_source;

		return $sources;
	}

	/**
	 * Returns the currently active alt source.
	 *
	 * This function checks the existence of various levels of source preference
	 * in a particular order so that it can keep tabs on those levels for retrieval
	 * later, even if overridden by a higher priority level.
	 *
	 * @return string
	 */
	public function get_alt_source(): string {
		static $source;

		if ( ! empty( $source ) ) {
			return $source;
		}

		/**
		 * Filter enables a host to override any source preferences in order
		 * overcome widespread connectivity or security issues.
		 *
		 * @param string   $source_key  Source key, default none (empty string).
		 * @param string[] $source_keys An array of source keys that may be selected from.
		 */
		$host_override = apply_filters( 'wpe_uss_get_host_override', '', $this->valid_source_keys() );

		if ( $this->valid_source( $host_override ) ) {
			$this->host_override = $host_override;
		} else {
			$this->host_override = '';
		}

		/**
		 * Filter enables a host to set a preferred source.
		 *
		 * @param string   $source_key  Source key, default none (empty string).
		 * @param string[] $source_keys An array of source keys that may be selected from.
		 */
		$host_preference = apply_filters(
			'wpe_uss_get_host_preference',
			'',
			$this->valid_source_keys()
		);

		if ( $this->valid_source( $host_preference ) ) {
			$this->host_preference = $host_preference;
		} else {
			$this->host_preference = '';
		}

		/**
		 * Filter enables a hosting account to set a preferred source.
		 *
		 * @param string   $source_key  Source key, default none (empty string).
		 * @param string[] $source_keys An array of source keys that may be selected from.
		 */
		$hosting_account_preference = apply_filters(
			'wpe_uss_get_hosting_account_preference',
			'',
			$this->valid_source_keys()
		);

		if ( $this->valid_source( $hosting_account_preference ) ) {
			$this->hosting_account_preference = $hosting_account_preference;
		} else {
			$this->hosting_account_preference = '';
		}

		// We've gathered all the validated preferences, let's decide which to use.
		if ( ! empty( $this->host_override ) ) {
			$source = $this->get_source( $this->host_override );
		} elseif ( ! empty( $this->get_site_preference() ) ) {
			$source = $this->get_source( $this->get_site_preference() );
		} elseif ( ! empty( $this->hosting_account_preference ) ) {
			$source = $this->get_source( $this->hosting_account_preference );
		} elseif ( ! empty( $this->host_preference ) ) {
			$source = $this->get_source( $this->host_preference );
		}

		return empty( $source ) ? $this->get_core_source() : $source;
	}

	/**
	 * Returns the source's class name for the given source key.
	 *
	 * @param string $source_key Source key to get class for.
	 *
	 * @return string
	 */
	public function get_source( string $source_key ): string {
		if ( ! $this->valid_source( $source_key ) ) {
			return '';
		}

		return $this->alt_sources[ $source_key ] ?? $this->get_core_source();
	}

	/**
	 * Get a description of what the default source is and potentially
	 * where it came from.
	 *
	 * @return string
	 */
	public function get_default_source_desc(): string {
		// Make sure properties set.
		$this->get_alt_source();

		if ( ! empty( $this->hosting_account_preference ) ) {
			$desc = sprintf(
			/* translators: Name of source set as default. */
				__(
					'%s is the current default source configured in your hosting account.',
					'wpe-update-source-selector'
				),
				$this->get_source( $this->hosting_account_preference )::get_name()
			);
		} elseif ( ! empty( $this->host_preference ) ) {
			$desc = sprintf(
			/* translators: Name of source set as default. */
				__( '%s is the current default source.', 'wpe-update-source-selector' ),
				$this->get_source( $this->host_preference )::get_name()
			);
		} else {
			$desc = sprintf(
			/* translators: Name of source set as default. */
				__( '%s is the current default source.', 'wpe-update-source-selector' ),
				$this->get_core_source()::get_name()
			);
		}

		return $desc;
	}

	/**
	 * Returns host override source.
	 *
	 * @return string
	 */
	public function get_host_override(): string {
		// Make sure properties set.
		$this->get_alt_source();

		return $this->host_override;
	}

	/**
	 * Has the host override been set?
	 *
	 * @return bool
	 */
	public function host_override_set(): bool {
		// Make sure properties set.
		$this->get_alt_source();

		return ! empty( $this->get_host_override() );
	}

	/**
	 * Filter the current source's key.
	 *
	 * @handles wpe_uss_get_current_source
	 *
	 * @param string $source_key Current source key, default empty string.
	 *
	 * @return string
	 */
	public function filter_get_current_source( $source_key = '' ): string {
		if ( $this->valid_source( $source_key ) ) {
			return $source_key;
		}

		return $this->get_alt_source()::get_key();
	}
}
