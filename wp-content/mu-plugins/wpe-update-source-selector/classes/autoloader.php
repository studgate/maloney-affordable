<?php
/**
 * Autoloader class file.
 *
 * @package WPE_Update_Source_Selector
 */

namespace WPE_Update_Source_Selector;

if ( ! class_exists( __NAMESPACE__ . '\Autoloader' ) ) {
	/**
	 * Class Autoloader
	 *
	 * When instantiated, registers an autoloader callback for plugin.
	 */
	class Autoloader {
		/**
		 * The base prefix for the plugin's namespace.
		 *
		 * @var string
		 */
		protected $base_prefix;

		/**
		 * The base path where source files are nested under.
		 *
		 * @var string
		 */
		protected $base_path;

		/**
		 * Autoloader constructor.
		 *
		 * @param string $base_prefix Base prefix for namespace.
		 * @param string $base_path   Base path for source files.
		 *
		 * @return void
		 */
		public function __construct( string $base_prefix, string $base_path ) {
			$this->base_prefix = empty( $base_prefix ) ? 'WPE_Update_Source_Selector' : $base_prefix;
			$this->base_path   = empty( $base_path ) ? trailingslashit( dirname( __DIR__ ) ) : trailingslashit( $base_path );

			spl_autoload_register( array( $this, 'autoloader' ) );
		}

		/**
		 * Autoloader callback function.
		 *
		 * @param string $class_name Fully qualified class name.
		 *
		 * @return void
		 */
		public function autoloader( string $class_name ) {
			if ( ! $this->source_belongs_to_plugin( $class_name ) ) {
				return;
			}

			$bare_source_path = $this->get_bare_source_path( $class_name );

			// We have both class and interface files that may need to be loaded.
			foreach ( array( 'classes', 'traits' ) as $type ) {
				$path = $this->get_source_directory( $type ) . $bare_source_path;

				if ( file_exists( $path ) ) {
					require_once $path;

					return;
				}
			}
		}

		/**
		 * Does source path belong to plugin?
		 *
		 * @param string $class_name Fully qualified class name.
		 *
		 * @return bool
		 */
		protected function source_belongs_to_plugin( string $class_name ): bool {
			if ( 0 !== strpos( $class_name, $this->base_prefix . '\\' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Get un-prefixed source path.
		 *
		 * @param string $class_name Fully qualified class name.
		 *
		 * @return string
		 */
		protected function get_bare_source_path( string $class_name ): string {
			$parts = explode( '\\', strtolower( $class_name ) );

			// Get parts without prefix element.
			$parts = array_slice( $parts, 1 );

			$filename = implode( DIRECTORY_SEPARATOR, $parts ) . '.php';

			return str_replace( '_', '-', strtolower( $filename ) );
		}

		/**
		 * Get base source directory for type.
		 *
		 * @param string $type Either classes or interfaces.
		 *
		 * @return string
		 */
		protected function get_source_directory( string $type ): string {
			return trailingslashit( $this->base_path . $type );
		}
	}
}
