<?php

use OTGS\Toolset\Maps\Controller\Ajax;
use OTGS\Toolset\Maps\Controller\Cache\Convert;
use OTGS\Toolset\Maps\Controller\Cache\CreateDatabaseTable;

/**
 * Creates database table and converts old cache data to new.
 */
class Toolset_Maps_Ajax_Handler_Update_Address_Cache extends Toolset_Ajax_Handler_Abstract {
	/**
	 * @param array $arguments
	 */
	public function process_call( $arguments ) {
		$ajax_manager = $this->get_ajax_manager();
		$ajax_manager->ajax_begin( [
			'nonce' => Ajax::CALLBACK_UPDATE_ADDRESS_CACHE,
			'public' => false,
		] );

		$messages = CreateDatabaseTable::run();

		if ( CreateDatabaseTable::cache_table_exists() ) {
			$messages .= Convert::run();
		} else {
			$messages .= __(
				'WP could not create a database table for address cache. Old cache system will be used.',
				'toolset-maps'
			);
		}

		$ajax_manager->ajax_finish( [
			'message' => $messages,
		], true );
	}
}
