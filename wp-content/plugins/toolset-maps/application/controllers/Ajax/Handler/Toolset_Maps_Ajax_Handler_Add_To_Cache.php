<?php

use OTGS\Toolset\Maps\Controller\Ajax;

/**
 * Ajax call that allows sending address lat/lng directly to cache (e.g. from finished address autocomplete).
 */
class Toolset_Maps_Ajax_Handler_Add_To_Cache extends Toolset_Ajax_Handler_Abstract {
	/**
	 * @param array $arguments
	 */
	public function process_call( $arguments ) {
		$ajax_manager = $this->get_ajax_manager();
		$ajax_manager->ajax_begin( [
			'nonce' => Ajax::CALLBACK_ADD_TO_CACHE,
			'public' => false,
		] );

		// This data is coming directly from API, and needs to be saved to cache table as is - especially the address,
		// which is later on a SQL join point between postmeta and address cache table, so even a single character
		// change would break that. That's why any sanitization and/or escaping needs to happen after the data is read
		// from database. We are protected from SQL injection, of course, in update_cached_coordinates(). And, actually
		// lat and lon are sanitized by db itself when they are inserted using ST_PointFromText() SQL function.
		$address_passed = toolset_getpost( 'address' );
		$address = toolset_getpost( 'formattedAddress' );
		$lat = toolset_getpost( 'lat' );
		$lon = toolset_getpost( 'lon' );

		if ( ! array_key_exists( $address_passed, Toolset_Addon_Maps_Common::get_cached_coordinates() ) ) {
			Toolset_Addon_Maps_Common::update_cached_coordinates( $address_passed, $address, $lat, $lon );
		}

		$ajax_manager->ajax_finish( null, true );
	}
}
