<?php
namespace OTGS\Toolset\Maps\Controller;

use Toolset_Ajax;

/**
 * Toolset Maps Ajax controller
 *
 * @since 2.0
 */
class Ajax extends Toolset_Ajax {
	const HANDLER_CLASS_PREFIX = 'Maps_Ajax_Handler_';

	// Toolset Settings page
	const CALLBACK_UPDATE_ADDRESS_CACHE = 'update_address_cache';

	const CALLBACK_ADD_TO_CACHE = 'add_to_cache';

	/**
	 * @var Ajax
	 */
	private static $maps_instance;

	/**
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$maps_instance ) {
			self::$maps_instance = new self();
		}
		return self::$maps_instance;
	}

	/**
	 * @return array
	 */
	protected function get_callback_names() {
		return [
			self::CALLBACK_UPDATE_ADDRESS_CACHE,
			self::CALLBACK_ADD_TO_CACHE,
		];
	}

	/**
	 * @param bool $capitalized
	 *
	 * @return string
	 */
	protected function get_plugin_slug( $capitalized = false ) {
		return ( $capitalized ? 'Toolset_Maps' : 'toolset_maps' );
	}
}
