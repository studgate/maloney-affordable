<?php

namespace WPFormsGeolocation\Admin\Settings;

use WPForms\Admin\Notice;

/**
 * Class Settings.
 *
 * @since 2.0.0
 */
class Settings {

	/**
	 * Settings slug.
	 *
	 * @since 2.0.0
	 */
	const SLUG = 'geolocation';

	/**
	 * Hooks.
	 *
	 * @since 2.0.0
	 */
	public function hooks() {

		add_filter( 'wpforms_settings_tabs', [ $this, 'register_tab' ], 11 );
		add_action( 'admin_init', [ $this, 'shutdown_algolia_notice' ], 10 );

		if ( ! wpforms_is_admin_page( 'settings', self::SLUG ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'wpforms_settings_tabs', [ $this, 'update_settings_tab' ], 12 );
		add_filter( 'wpforms_settings_defaults', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'load_providers' ], 9 );
	}

	/**
	 * Register tab.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tabs List of registered tabs.
	 *
	 * @return array
	 */
	public function register_tab( $tabs ) {

		if ( isset( $tabs['geolocation'] ) ) {
			return $tabs;
		}

		$tab = [
			'geolocation' => [
				'name'   => esc_html__( 'Geolocation', 'wpforms-geolocation' ),
				'form'   => false,
				'submit' => false,
			],
		];

		return wpforms_list_insert_after( $tabs, 'integrations', $tab );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 2.0.0
	 */
	public function enqueue_scripts() {

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-admin-settings-geolocation',
			WPFORMS_GEOLOCATION_URL . "assets/js/admin/wpforms-geolocation-settings{$min}.js",
			[ 'jquery' ],
			WPFORMS_GEOLOCATION_VERSION,
			true
		);

		wp_enqueue_style(
			'wpforms-admin-settings-geolocation',
			WPFORMS_GEOLOCATION_URL . "assets/css/admin/wpforms-geolocation-settings{$min}.css",
			[],
			WPFORMS_GEOLOCATION_VERSION
		);
	}

	/**
	 * Load Settings providers.
	 *
	 * @since 2.0.0
	 */
	public function load_providers() {

		$default_providers = [
			GooglePlaces::class,
			MapboxSearch::class,
		];

		foreach ( $default_providers as $provider ) {
			( new $provider() )->hooks();
		}
	}

	/**
	 * Display notice about removing Algolia Places service.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function shutdown_algolia_notice() {

		// Bail if the Algolia Places is not used.
		if ( $this->get_current_provider() !== 'algolia-places' || ! $this->is_algolia_active() ) {
			return;
		}

		$url = add_query_arg(
			[
				'utm_source'   => 'WordPress',
				'utm_medium'   => 'Admin Notice',
				'utm_campaign' => 'plugin',
				'utm_content'  => 'Algolia Places Notification',
			],
			'https://wpforms.com/algolia-places-sunset-geolocation-addon/'
		);

		Notice::warning(
			sprintf(
				wp_kses( /* translators: %s - link to documentation. */
					__( 'Heads up: If you\'re using the WPForms Geolocation addon, please note that Algolia Places shut down on May 31st, 2022. Algolia users need to <a href="%s" target="_blank" rel="noopener noreferrer">switch to Google Places</a> to continue using form geolocation features.', 'wpforms-geolocation' ),
					[
						'a' => [
							'href'   => [],
							'rel'    => [],
							'target' => [],
						],
					]
				),
				esc_url( $url )
			)
		);
	}

	/**
	 * Update geolocation api tab.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tabs Settings tabs.
	 *
	 * @return array
	 */
	public function update_settings_tab( $tabs ) {

		$tabs[ self::SLUG ]['form']   = true;
		$tabs[ self::SLUG ]['submit'] = esc_html__( 'Save Settings', 'wpforms-geolocation' );

		return $tabs;
	}

	/**
	 * Register settings.
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings Settings.
	 *
	 * @return array
	 */
	public function register_settings( $settings ) {

		$current_provider = $this->get_current_provider();
		$providers        = $this->get_providers();

		$settings[ self::SLUG ][ self::SLUG . '-heading' ] = [
			'id'       => self::SLUG . '-heading',
			'content'  =>
				'<h4>' . esc_html__( 'Geolocation', 'wpforms-geolocation' ) . '</h4><p>' .
				sprintf(
					wp_kses( /* translators: %s - link to documentation. */
						__(
							'Geolocation provides address autocomplete for Address and Single Text fields so users can submit your forms even faster, with less mistakes. You can even display map previews and enable location auto-detection to further enhance your forms. See our <a href="%s" target="_blank" rel="noopener noreferrer">Geolocation documentation</a> to learn more.',
							'wpforms-geolocation'
						),
						[
							'a' => [
								'href'   => [],
								'target' => [],
								'rel'    => [],
							],
						]
					),
					esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-install-and-use-the-geolocation-addon-with-wpforms/', 'Settings - Geolocation', 'Geolocation Documentation' ) )
				)
				. '</p>',
			'type'     => 'content',
			'no_label' => true,
			'class'    => [ 'section-heading' ],
		];

		$settings[ self::SLUG ][ self::SLUG . '-field-provider' ] = [
			'id'      => self::SLUG . '-field-provider',
			'type'    => 'radio',
			'options' => $providers + [ '' => esc_html__( 'None', 'wpforms-geolocation' ) ],
			'desc'    => esc_html__( 'Select which API should be used for the address autocomplete, autodetect, and show a map for fields functionality.', 'wpforms-geolocation' ),
		];

		foreach ( $providers as $provider_slug => $provider_name ) {
			$options = $this->get_provider_options( $provider_slug, $current_provider === $provider_slug );

			if ( ! $options ) {
				continue;
			}
			$settings[ self::SLUG ] = array_merge( $settings[ self::SLUG ], $options );
		}

		$settings[ self::SLUG ][ self::SLUG . '-current-location' ] = [
			'id'     => self::SLUG . '-current-location',
			'name'   => esc_html__( 'Current Location', 'wpforms-geolocation' ),
			'type'   => 'toggle',
			'status' => true,
			'desc'   => esc_html__( 'Detect and pre-fill user\'s current location on form load, works for both map and inputs.', 'wpforms-geolocation' ),
			'class'  => empty( $this->get_current_provider() ) ? [ 'wpforms-hide' ] : [],
		];

		return $settings;
	}

	/**
	 * Get provider options.
	 *
	 * @since 2.0.0
	 *
	 * @param string $provider_name Provider name.
	 * @param bool   $is_active     Is active provider.
	 *
	 * @return array
	 */
	private function get_provider_options( $provider_name, $is_active ) {

		$options = (array) apply_filters( "wpforms_geolocation_admin_settings_settings_get_provider_options_$provider_name", [] );

		if ( ! $options ) {
			return [];
		}

		$new_options = [];

		foreach ( $options as $key => $option ) {
			$new_options[ self::SLUG . '-' . $provider_name . '-' . $key ] = $this->prepare_provider_option( $provider_name, $option, $is_active );
		}

		return $new_options;
	}

	/**
	 * Add a prefixes for provider option.
	 *
	 * @since 2.0.0
	 *
	 * @param string $provider_name Provider name.
	 * @param array  $option        Provider option.
	 * @param bool   $is_active     Is active provider.
	 *
	 * @return array
	 */
	private function prepare_provider_option( $provider_name, $option, $is_active ) {

		$option['id']      = self::SLUG . '-' . $provider_name . '-' . $option['id'];
		$option['class'][] = 'wpforms-geolocation-settings-provider';
		$option['class'][] = 'wpforms-geolocation-settings-provider-' . $provider_name;

		if ( ! $is_active ) {
			$option['class'][] = 'wpforms-hide';
		}

		return $option;
	}

	/**
	 * Get registered settings providers.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_providers() {

		return (array) apply_filters( 'wpforms_geolocation_admin_settings_settings_get_providers', [] );
	}

	/**
	 * Get current provider slug.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_current_provider() {

		return wpforms_setting( self::SLUG . '-field-provider', '' );
	}

	/**
	 * Check if Algolia provider has app ID and api key already saved.
	 *
	 * @since 2.2.0
	 *
	 * @return bool
	 */
	private function is_algolia_active() {

		$app_id  = wpforms_setting( self::SLUG . '-algolia-places-application-id', '' );
		$api_key = wpforms_setting( self::SLUG . '-algolia-places-search-only-api-key', '' );

		return ! empty( $api_key ) && ! empty( $app_id );
	}
}
