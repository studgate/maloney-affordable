<?php

namespace WPFormsGeolocation\Admin\Settings;

use WPForms\Admin\Notice;

/**
 * Class MapboxSearch.
 *
 * @since 2.3.0
 */
class MapboxSearch {

	/**
	 * The provider slug.
	 *
	 * @since 2.3.0
	 *
	 * @var string
	 */
	const SLUG = 'mapbox-search';

	/**
	 * Init hooks.
	 *
	 * @since 2.3.0
	 */
	public function hooks() {

		$slug = self::SLUG;

		add_filter( 'wpforms_geolocation_admin_settings_settings_get_providers', [ $this, 'register_provider' ] );
		add_filter( "wpforms_geolocation_admin_settings_settings_get_provider_options_{$slug}", [ $this, 'register_settings' ] );
		add_filter( 'wpforms_update_settings', [ $this, 'validate_access_token' ] );
	}

	/**
	 * Register a provider.
	 *
	 * @since 2.3.0
	 *
	 * @param array $providers List of providers.
	 *
	 * @return array
	 */
	public function register_provider( $providers ) {

		$providers[ self::SLUG ] = esc_html__( 'Mapbox Search', 'wpforms-geolocation' );

		return $providers;
	}

	/**
	 * Register settings.
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	public function register_settings() {

		return [
			'access-token' => [
				'type' => 'text',
				'id'   => 'access-token',
				'name' => esc_html__( 'Access Token', 'wpforms-geolocation' ),
				'desc' => esc_html__( 'Paste your Access Token to connect to Mapbox Search.', 'wpforms-geolocation' ),
			],
		];
	}

	/**
	 * Validate the access token field.
	 *
	 * @since 2.3.0
	 *
	 * @param array $settings An array of plugin settings to modify.
	 *
	 * @return mixed
	 */
	public function validate_access_token( $settings ) {

		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		$access_token = sprintf(
			'%s-%s-%s',
			Settings::SLUG,
			self::SLUG,
			'access-token'
		);

		if ( empty( $settings[ $access_token ] ) || strpos( $settings[ $access_token ], 'pk.' ) === 0 ) {
			return $settings;
		}

		unset( $settings[ $access_token ] );

		Notice::error(
			sprintf(
				wp_kses( /* translators: %s - link to documentation. */
					__( 'Your Access Token for Mapbox Search is invalid. The Access Token must begin with \'pk.\'. Please read more in <a href="%s" target="_blank" rel="noopener noreferrer">the addon documentation</a>.', 'wpforms-geolocation' ),
					[
						'a' => [
							'rel'    => [],
							'href'   => [],
							'target' => [],
						],
					]
				),
				'https://wpforms.com/docs/how-to-install-and-use-the-geolocation-addon-with-wpforms/'
			)
		);

		return $settings;
	}
}
