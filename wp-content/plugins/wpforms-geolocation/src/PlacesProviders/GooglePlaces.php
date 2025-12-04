<?php

namespace WPFormsGeolocation\PlacesProviders;

use WPFormsGeolocation\Admin\Settings\Settings;
use WPFormsGeolocation\Admin\Settings\GooglePlaces as SettingsGooglePlaces;

/**
 * Class GooglePlaces.
 *
 * @since 2.0.0
 */
class GooglePlaces implements IPlacesProvider {

	/**
	 * Init Google Places provider.
	 *
	 * @since 2.0.0
	 */
	public function init() {

		if ( ! $this->is_active() ) {
			return;
		}

		add_action( 'wpforms_frontend_css', [ $this, 'enqueue_styles' ] );
		add_action( 'wpforms_frontend_js', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue styles.
	 *
	 * @since 2.0.0
	 *
	 * @param array $forms List of forms.
	 */
	public function enqueue_styles( $forms ) {

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-geolocation-google-places',
			WPFORMS_GEOLOCATION_URL . "assets/css/wpforms-geolocation-google{$min}.css",
			[],
			WPFORMS_GEOLOCATION_VERSION
		);
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 2.0.0
	 *
	 * @param array $forms List of forms.
	 */
	public function enqueue_scripts( $forms ) {

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-geolocation-google-places',
			WPFORMS_GEOLOCATION_URL . "assets/js/wpforms-geolocation-google-api{$min}.js",
			[],
			WPFORMS_GEOLOCATION_VERSION,
			false
		);

		/**
		 * Allow developers to filter query args when enqueuing Google Geolocation script.
		 *
		 * @since 2.2.0
		 *
		 * @param array $query_args Query arguments.
		 */
		$query_args = apply_filters(
			'wpforms_geolocation_places_providers_google_places_query_args',
			[
				'key'       => wpforms_setting( Settings::SLUG . '-' . SettingsGooglePlaces::SLUG . '-api-key' ),
				'libraries' => 'places',
				'callback'  => 'WPFormsGeolocationInitGooglePlacesAPI',
			]
		);

		// phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script(
			'google-geolocation-api',
			add_query_arg( $query_args, 'https://maps.googleapis.com/maps/api/js' ),
			[ 'wpforms-geolocation-google-places' ],
			null,
			true
		);
		// phpcs:enable WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}

	/**
	 * Determine whether the Google Places provider is active.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_active() {

		return (bool) wpforms_setting( Settings::SLUG . '-' . SettingsGooglePlaces::SLUG . '-api-key' );
	}
}
