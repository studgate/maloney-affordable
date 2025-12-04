<?php
namespace OTGS\Toolset\Maps;

use OTGS\Toolset\Maps\Controller\Ajax;
use OTGS\Toolset\Maps\Controller\Troubleshooting\CacheUpdate;
use OTGS\Toolset\Common\Utils\RequestMode;

/**
 * Class Bootstrap
 * @package OTGS\Toolset\Maps
 * @since 1.5.3
 */
class Bootstrap {
	protected $soft_dependencies = array();

	public function __construct( array $do_available ) {
		$this->soft_dependencies = $do_available;
	}

	public function init() {
		if ( in_array( 'views', $this->soft_dependencies, true ) ) {
			// These are currently only needed for Map block, which doesn't work without Views/Blocks
			add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 30 );
		}
		add_action( 'toolset_common_loaded', array( $this, 'register_autoloaded_classes' ), 10 );
		add_action( 'toolset_common_loaded', array( $this, 'initialize_classes' ), 20 );
	}

	/**
	 * Register autoload classmap to Toolset Common autoloader
	 */
	public function register_autoloaded_classes() {
		$classmap = include( TOOLSET_ADDON_MAPS_PATH . '/application/autoload_classmap.php' );

		do_action( 'toolset_register_classmap', $classmap );
	}

	/**
	 * Initialize autoloaded classes, including those based on soft_dependencies.
	 */
	public function initialize_classes() {
		if ( in_array( 'views', $this->soft_dependencies ) ) {

		}

		// Init possible cache storage update
		$cache_update = new CacheUpdate();
		$cache_update->init();

		// Initialize the AJAX handler if DOING_AJAX.
		/** @var RequestMode $request_mode */
		$request_mode = toolset_dic_make( '\OTGS\Toolset\Common\Utils\RequestMode' );
		if ( RequestMode::AJAX === $request_mode->get() ) {
			$maps_ajax = Ajax::get_instance();
			$maps_ajax::initialize();
		}
	}

	/**
	 * Do some early initializations, like DS API.
	 */
	public function plugins_loaded() {
		// Init DS API
		require_once TOOLSET_ADDON_MAPS_PATH . '/vendor/toolset/dynamic-sources/server/ds-instance.php';

		/* Bootstrap Toolset Common ES */
		require_once TOOLSET_ADDON_MAPS_PATH . '/vendor/toolset/common-es/loader.php';
	}
}
