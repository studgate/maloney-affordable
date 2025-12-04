<?php

namespace WPFormsGeolocation;

use WPForms_Updater;
use WPFormsGeolocation\Admin\Entry;
use WPFormsGeolocation\Front\Fields;
use WPFormsGeolocation\Admin\Builder;
use WPFormsGeolocation\Admin\Settings\Settings;
use WPFormsGeolocation\Admin\Settings\Preview;
use WPFormsGeolocation\PlacesProviders\ProvidersFactory;
use WPFormsGeolocation\Tasks\EntryGeolocationUpdateTask;

/**
 * Class Plugin.
 *
 * @since 2.0.0
 */
final class Plugin {

	/**
	 * Plugin constructor.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
	}

	/**
	 * Get a single instance of the addon.
	 *
	 * @since 2.0.0
	 *
	 * @return Plugin
	 */
	public static function get_instance() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();

			$instance->init();
		}

		return $instance;
	}

	/**
	 * Init plugin.
	 *
	 * @since 2.0.0
	 * @since 2.5.0 Returns Plugin instance.
	 *
	 * @return Plugin
	 */
	private function init() {

		$settings          = new Settings();
		$providers_factory = new ProvidersFactory( $settings );
		$map               = new Map();
		$retrieve_geo_data = new RetrieveGeoData();
		$fields            = new Fields( $providers_factory, $map );
		$smart_tags        = new SmartTags();

		$settings->hooks();
		$smart_tags->hooks();
		$retrieve_geo_data->hooks();
		( new SmartTags() )->hooks();
		( new Entry( $retrieve_geo_data, $smart_tags ) )->hooks();
		( new EntryGeolocationUpdateTask( $retrieve_geo_data ) )->hooks();
		( new Integrations() )->hooks();
		( new Builder( $providers_factory, $map ) )->hooks();
		$fields->hooks();
		( new Preview( $providers_factory, $fields, $settings, $map ) )->hooks();

		return $this;
	}

	/**
	 * Load the plugin updater.
	 *
	 * @since 1.0.0
	 * @deprecated 2.11.0
	 *
	 * @todo Remove with core 1.9.2
	 *
	 * @param string $key License key.
	 */
	public function updater( $key ) {

		_deprecated_function( __METHOD__, '2.11.0 of the WPForms Geolocation plugin' );

		new WPForms_Updater(
			[
				'plugin_name' => 'WPForms Geolocation',
				'plugin_slug' => 'wpforms-geolocation',
				'plugin_path' => plugin_basename( WPFORMS_GEOLOCATION_FILE ),
				'plugin_url'  => trailingslashit( WPFORMS_GEOLOCATION_URL ),
				'remote_url'  => WPFORMS_UPDATER_API,
				'version'     => WPFORMS_GEOLOCATION_VERSION,
				'key'         => $key,
			]
		);
	}
}
