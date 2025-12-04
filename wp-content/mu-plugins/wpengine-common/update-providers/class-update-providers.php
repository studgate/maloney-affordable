<?php
/**
 * Update Providers class.
 *
 * This is implemented as a singleton so that its instantiation can be delayed
 * until it is actually needed.
 *
 * @package wpengine/common-mu-plugin
 */

namespace wpe\plugin\update_providers;

/**
 * A list of update providers that can be used to check for updates.
 *
 * This class loads all the update provider classes and associated functionality.
 *
 * New providers should be added to the PROVIDER_CLASSES array.
 */
class Update_Providers {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * The directory where the update provider classes are stored.
	 *
	 * @var string
	 */
	const PROVIDERS_DIR = __DIR__ . '/providers';

	/**
	 * The class name for the WordPress.org update provider.
	 *
	 * This is only really needed while the current provider is determined
	 * by the simple use of the wpeApi feature flag.
	 *
	 * @var string
	 */
	const WORDPRESSORG_PROVIDER_CLASSNAME = 'WordPressOrg';

	/**
	 * The class name for the WP Engine update provider.
	 *
	 * This is only really needed while the current provider is determined
	 * by the simple use of the wpeApi feature flag.
	 *
	 * @var string
	 */
	const WPENGINE_PROVIDER_CLASSNAME = 'WPEngine';

	/**
	 * This is the list of provider files to load, indexed by their classname (without the namespace).
	 *
	 * This is needed because we don't have an autoloader.
	 *
	 * @var array<string,string>
	 */
	const PROVIDER_CLASSES = array(
		self::WORDPRESSORG_PROVIDER_CLASSNAME => 'class-wordpressorg.php',
		self::WPENGINE_PROVIDER_CLASSNAME     => 'class-wpengine.php',
	);

	/**
	 * List of update provider objects.
	 *
	 * @var Update_Provider[]
	 */
	public $providers = array();

	/**
	 * Constructor.
	 *
	 * This is a singleton and the class should not be instantiated directly.
	 */
	public function __construct() {
		require_once __DIR__ . '/class-update-provider.php';

		// Must load the provider classes before we do setup as we need filters to added before we
		// do the apply_filters.
		$this->load_providers();
	}

	/**
	 * Get the singleton instance of this class.
	 *
	 * Will load and initialize all the update provider classes if not already done.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Loads each of the update provider classes in the provider directory.
	 *
	 * @return void
	 */
	protected function load_providers() {
		$provider_dir = trailingslashit( self::PROVIDERS_DIR );

		foreach ( self::PROVIDER_CLASSES as $class_name => $file_name ) {
			$this->load_provider( $class_name, $provider_dir . $file_name );
		}
	}

	/**
	 * Load a provider class file and instantiate the provider object.
	 *
	 * @param string $class_name The class name to load.
	 * @param string $file_path The full file path to load.
	 * @return bool True if the provider was loaded, false otherwise.
	 */
	protected function load_provider( $class_name, $file_path ) {
		// Doing a file_exists check here is slow on our platform. So we just
		// try to include the file, and return early if the class didn't load.
		include_once $file_path;

		$full_class_name = __NAMESPACE__ . '\\providers\\' . $class_name;

		if ( ! class_exists( $full_class_name ) ) {
			return false;
		}

		// This will register itself into the list of providers.
		$provider                       = new $full_class_name();
		$this->providers[ $class_name ] = $provider;

		return true;
	}

	/**
	 * Returns the update provider child classname for the currently active provider.
	 *
	 * @return string
	 */
	public function get_current_provider_classname() {
		// If WPE Update Source Selector is active, use the provider class it has selected.
		$provider_key = apply_filters( 'wpe_uss_get_current_source', '' );
		if ( ! empty( $provider_key ) ) {
			switch ( $provider_key ) {
				case 'wpengine':
					return self::WPENGINE_PROVIDER_CLASSNAME;
				// phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
				case 'wordpress':
					return self::WORDPRESSORG_PROVIDER_CLASSNAME;
			}
		}

		// Otherwise, use the WPE API feature flag to determine the provider.
		return wpe_use_wpe_updater_api() ? self::WPENGINE_PROVIDER_CLASSNAME : self::WORDPRESSORG_PROVIDER_CLASSNAME;
	}

	/**
	 * Returns the update provider object for the currently active provider.
	 *
	 * @return Update_Provider
	 */
	public function get_current_provider() {
		$provider_classname = $this->get_current_provider_classname();
		return $this->providers[ $provider_classname ];
	}

	/**
	 * Returns the update provider object for a provider name.
	 *
	 * @param string $provider_name The (internal) name/key of the provider to get.
	 *
	 * @return Update_Provider|null
	 */
	public function get_provider( $provider_name ) {
		foreach ( $this->providers as $provider ) {
			if ( $provider->name === $provider_name ) {
				return $provider;
			}
		}

		return null;
	}
}
