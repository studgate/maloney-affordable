<?php
/**
 * Plugin_Info_Trait trait file.
 *
 * @package WPE_Update_Source_Selector;
 */

namespace WPE_Update_Source_Selector;

/**
 * Trait: Plugin_Info_Trait
 *
 * A utility trait for handling information regarding the plugin.
 */
trait Plugin_Info_Trait {
	/**
	 * The plugin's slug.
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * The plugin's entry file.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * The base path where source files are nested under.
	 *
	 * @var string
	 */
	private $base_path;

	/**
	 * Get a value from the plugin's header comment.
	 *
	 * @param string $key Camel cased plugin header value to retrieve.
	 *
	 * @return string
	 */
	public function get_plugin_info( string $key ): string {
		static $plugin_info = array();

		if ( empty( $plugin_info ) && file_exists( $this->get_plugin_file() ) ) {
			$plugin_info = $this->get_plugin_data( $this->get_plugin_file(), false );
		}

		return $plugin_info[ $key ] ?? '';
	}

	/**
	 * Wrapper for Core's get_plugin_data function to make sure it is loaded.
	 *
	 * @param string $plugin_file Plugin's entry file.
	 * @param bool   $markup      Optional, should the returned data have HTML markup applied, default true.
	 * @param bool   $translate   Optional, should the returned data be translated, default true.
	 *
	 * @return array<string>
	 */
	private function get_plugin_data( string $plugin_file, bool $markup = true, bool $translate = true ): array {
		static $plugin_data = array();

		if ( empty( $plugin_data ) && file_exists( $plugin_file ) ) {
			if ( ! function_exists( 'get_plugin_data' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin_data = get_plugin_data( $plugin_file, $markup, $translate );
		}

		return $plugin_data;
	}

	/**
	 * Returns the plugin's slug.
	 *
	 * @return string
	 */
	public function get_plugin_slug(): string {
		return $this->plugin_slug;
	}

	/**
	 * Returns the plugin's entry file.
	 *
	 * @return string
	 */
	public function get_plugin_file(): string {
		return $this->plugin_file;
	}

	/**
	 * Return's the plugin's base path.
	 *
	 * @return string
	 */
	public function get_base_path(): string {
		return $this->base_path;
	}

	/**
	 * Return's the plugin's readme.txt path.
	 *
	 * @return string
	 */
	public function get_readme_path(): string {
		return trailingslashit( $this->get_base_path() ) . 'readme.txt';
	}
}
