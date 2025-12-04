<?php

namespace WPFormsGeolocation\Front;

use WPFormsGeolocation\Map;
use WPFormsGeolocation\PlacesProviders\ProvidersFactory;

/**
 * Class Fields.
 *
 * @since 2.0.0
 */
class Fields {

	/**
	 * Provider factory.
	 *
	 * @since 2.0.0
	 *
	 * @var ProvidersFactory
	 */
	private $providers_factory;

	/**
	 * Map.
	 *
	 * @since 2.0.0
	 *
	 * @var Map
	 */
	private $map;

	/**
	 * Enqueue autocomplete scripts.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	private static $are_scripts_included = false;

	/**
	 * Allow field types.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public static $field_types = [ 'text', 'address' ];

	/**
	 * Fields constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param ProvidersFactory $providers_factory Provider factory.
	 * @param Map              $map               Map.
	 */
	public function __construct( ProvidersFactory $providers_factory, Map $map ) {

		$this->providers_factory = $providers_factory;
		$this->map               = $map;
	}

	/**
	 * Init hooks.
	 *
	 * @since 2.0.0
	 */
	public function hooks() {

		add_action( 'wpforms_frontend_output_before', [ $this, 'init_autocomplete' ] );
		add_action( 'wpforms_wp_footer', [ $this, 'settings' ] );

		foreach ( self::$field_types as $field_type ) {
			add_filter( 'wpforms_field_properties_' . $field_type, [ $this, $field_type . '_field_attributes' ], 10, 2 );
			add_action( 'wpforms_display_field_' . $field_type, [ $this, 'map_before_field' ], 9 );
			add_action( 'wpforms_display_field_' . $field_type, [ $this, 'map_after_field' ], 11 );
		}

		add_filter( 'wpforms_field_data', [ $this, 'disable_limit' ], 1000 );
	}

	/**
	 * Init autocomplete.
	 *
	 * @since 2.0.0
	 *
	 * @param array $form_data Form data.
	 */
	public function init_autocomplete( $form_data ) {

		if ( self::$are_scripts_included ) {
			return;
		}

		if ( ! $this->has_autocomplete_field( [ $form_data ] ) ) {
			return;
		}

		$provider = $this->providers_factory->get_current_provider();

		if ( ! $provider ) {
			return;
		}

		$provider->init();

		self::$are_scripts_included = true;
	}

	/**
	 * Show a map before a field.
	 *
	 * @since 2.0.0
	 *
	 * @param array $field Field.
	 */
	public function map_before_field( $field ) {

		if ( empty( $field['enable_address_autocomplete'] ) || empty( $field['display_map'] ) ) {
			return;
		}

		if ( empty( $field['map_position'] ) || $field['map_position'] !== 'above' ) {
			return;
		}

		$provider = $this->providers_factory->get_current_provider();

		if ( ! $provider || ! $provider->is_active() ) {
			return;
		}

		$this->map->print_map( $this->get_map_size( $field ) );
	}

	/**
	 * Show a map after a field.
	 *
	 * @since 2.0.0
	 *
	 * @param array $field Field.
	 */
	public function map_after_field( $field ) {

		if ( empty( $field['enable_address_autocomplete'] ) || empty( $field['display_map'] ) ) {
			return;
		}

		if ( empty( $field['map_position'] ) || $field['map_position'] !== 'below' ) {
			return;
		}

		$provider = $this->providers_factory->get_current_provider();

		if ( ! $provider || ! $provider->is_active() ) {
			return;
		}

		$this->map->print_map( $this->get_map_size( $field ) );
	}

	/**
	 * Get map size.
	 *
	 * @since 2.0.0
	 *
	 * @param array $field Field settings.
	 *
	 * @return string
	 */
	private function get_map_size( $field ) {

		return ! empty( $field['size'] ) ? $field['size'] : '';
	}

	/**
	 * Add properties for text field.
	 *
	 * @since 2.0.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Field.
	 *
	 * @return array
	 */
	public function text_field_attributes( $properties, $field ) {

		if ( ! empty( $field['enable_address_autocomplete'] ) ) {
			$properties['inputs']['primary']['attr']['data-autocomplete'] = true;
		}

		if ( ! empty( $field['display_map'] ) ) {
			$properties['inputs']['primary']['attr']['data-display-map'] = true;
		}

		return $properties;
	}

	/**
	 * Disable Limit Length when Address Autocomplete option enabled.
	 *
	 * @since 2.0.0
	 *
	 * @param array $field Current field.
	 *
	 * @return array
	 */
	public function disable_limit( $field ) {

		if ( empty( $field['type'] ) || $field['type'] !== 'text' ) {
			return $field;
		}

		if ( empty( $field['enable_address_autocomplete'] ) ) {
			return $field;
		}

		unset( $field['limit_enabled'], $field['limit_count'], $field['limit_mode'] );

		return $field;
	}

	/**
	 * Add properties for address field.
	 *
	 * @since 2.0.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Field.
	 *
	 * @return array
	 */
	public function address_field_attributes( $properties, $field ) {

		if ( ! empty( $field['enable_address_autocomplete'] ) ) {
			$properties['inputs']['address1']['attr']['data-autocomplete'] = true;
		}

		if ( ! empty( $field['display_map'] ) ) {
			$properties['inputs']['address1']['attr']['data-display-map'] = true;
		}

		return $properties;
	}

	/**
	 * Print a settings for JS.
	 *
	 * @since 2.0.0
	 *
	 * @param array $forms Page forms.
	 */
	public function settings( $forms ) {

		if ( ! self::$are_scripts_included ) {
			return;
		}

		/**
		 * Modify all Geolocation frontend settings.
		 *
		 * @since 2.3.0
		 *
		 * @param array $settings Geolocation addon settings.
		 * @param array $forms    Page forms.
		 *
		 * @return array
		 */
		$settings = (array) apply_filters(
			'wpforms_geolocation_front_fields_settings',
			array_merge_recursive(
				$this->get_fields_settings( $forms ),
				[
					'current_location'     => wpforms_setting( 'geolocation-current-location', false ),
					'states'               => [
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
						'us' => json_decode( file_get_contents( WPFORMS_GEOLOCATION_PATH . 'assets/json/states.json' ), true ),
					],
					'autocompleteSettings' => [
						'common' => $this->get_common_autocomplete_settings( $forms ),
					],
					'mapSettings'          => [
						'common' => $this->get_common_map_settings( $forms ),
					],
					'markerSettings'       => [
						'common' => $this->get_common_marker_settings( $forms ),
					],
				]
			),
			$forms
		);

		$settings = wp_parse_args(
			$settings,
			[
				'current_location'     => false,
				'states'               => [],
				'autocompleteSettings' => [],
				'mapSettings'          => [],
				'markerSettings'       => [],
			]
		);

		/**
		 * Modify current location setting.
		 *
		 * @since 2.11.0
		 *
		 * @param bool $current_location Current location setting.
		 *
		 * @return bool
		 */
		$settings['current_location'] = (bool) apply_filters( 'wpforms_geolocation_front_fields_settings_current_location', $settings['current_location'] );

		$settings['autocompleteSettings'] = array_filter( $settings['autocompleteSettings'] );
		$settings['mapSettings']          = array_filter( $settings['mapSettings'] );
		$settings['markerSettings']       = array_filter( $settings['markerSettings'] );

		/*
		 * Below we do our own implementation of wp_localize_script in an effort
		 * to be better compatible with caching plugins which were causing
		 * conflicts.
		 */
		echo "<script type='text/javascript'>\n";
		echo "/* <![CDATA[ */\n";
		echo 'var wpforms_geolocation_settings = ' . wp_json_encode( $settings ) . "\n";
		echo "/* ]]> */\n";
		echo "</script>\n";
	}

	/**
	 * Forms has a autocomplete field.
	 *
	 * @since 2.0.0
	 *
	 * @param array $forms Forms.
	 *
	 * @return bool
	 */
	private function has_autocomplete_field( $forms ) {

		foreach ( $forms as $form ) {
			if ( empty( $form['fields'] ) ) {
				continue;
			}

			foreach ( $form['fields'] as $field ) {
				if ( empty( $field['enable_address_autocomplete'] ) || ! in_array( $field['type'], self::$field_types, true ) ) {
					continue;
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Get fields settings.
	 *
	 * @since 2.3.0
	 *
	 * @param array $forms Forms.
	 *
	 * @return array
	 */
	private function get_fields_settings( $forms ) {

		$fields_settings = [];

		foreach ( $forms as $form_data ) {
			if ( empty( $form_data['fields'] ) ) {
				continue;
			}

			foreach ( $form_data['fields'] as $field ) {
				if ( empty( $field['enable_address_autocomplete'] ) || ! in_array( $field['type'], self::$field_types, true ) ) {
					continue;
				}

				$fields_settings = array_merge_recursive( $fields_settings, $this->get_field_settings( $field, $form_data ) );
			}
		}

		return $fields_settings;
	}

	/**
	 * Get field settings.
	 *
	 * @since 2.3.0
	 *
	 * @param array $field     Field settings.
	 * @param array $form_data Form data and settings.
	 *
	 * @return array
	 */
	private function get_field_settings( $field, $form_data ) {

		$settings_name         = sprintf( 'wpforms_%d_field_%d', $form_data['id'], $field['id'] );
		$autocomplete_settings = [];

		if ( ! empty( $field['scheme'] ) && $field['scheme'] === 'us' ) {
			$autocomplete_settings['strict'] = [ 'us' ];
		}

		return [
			'autocompleteSettings' => [
				/**
				 * Modify autocomplete settings for a field.
				 *
				 * @since 2.3.0
				 *
				 * @param array $field_settings Autocomplete field settings.
				 * @param array $field          Field settings.
				 * @param array $form_data      Form data and settings.
				 *
				 * @return array
				 */
				$settings_name => (array) apply_filters( 'wpforms_geolocation_front_fields_get_field_settings_autocomplete_settings', $autocomplete_settings, $field, $form_data ),
			],
			'mapSettings'          => [
				/**
				 * Modify map settings for a field.
				 *
				 * @since 2.3.0
				 *
				 * @param array $field_settings Map field settings.
				 * @param array $field          Field settings.
				 * @param array $form_data      Form data and settings.
				 *
				 * @return array
				 */
				$settings_name => (array) apply_filters( 'wpforms_geolocation_front_fields_get_field_settings_map_settings', [], $field, $form_data ),
			],
			'markerSettings'       => [
				/**
				 * Modify marker settings for a field.
				 *
				 * @since 2.3.0
				 *
				 * @param array $field_settings Marker field settings.
				 * @param array $field          Field settings.
				 * @param array $form_data      Form data and settings.
				 *
				 * @return array
				 */
				$settings_name => (array) apply_filters( 'wpforms_geolocation_front_fields_get_field_settings_marker_settings', [], $field, $form_data ),
			],
		];
	}

	/**
	 * Get map settings.
	 *
	 * @since 2.3.0
	 *
	 * @param array $forms Forms.
	 *
	 * @return array
	 */
	private function get_common_map_settings( $forms ) {

		/**
		 * Modify map settings.
		 *
		 * @since 2.3.0
		 *
		 * @param array $map_settings Map settings.
		 * @param array $forms        Forms.
		 *
		 * @return array
		 */
		return (array) apply_filters( 'wpforms_geolocation_front_fields_get_map_settings', $this->map->get_settings(), $forms );
	}

	/**
	 * Get autocomplete settings.
	 *
	 * @since 2.3.0
	 *
	 * @param array $forms Forms.
	 *
	 * @return array
	 */
	private function get_common_autocomplete_settings( $forms ) {

		/**
		 * Modify autocomplete settings.
		 *
		 * @since 2.3.0
		 *
		 * @param array $autocomplete_settings Autocomplete settings.
		 * @param array $forms                 Forms.
		 *
		 * @return array
		 */
		return (array) apply_filters( 'wpforms_geolocation_front_fields_get_autocomplete_settings', [], $forms );
	}

	/**
	 * Get marker settings.
	 *
	 * @since 2.3.0
	 *
	 * @param array $forms Forms.
	 *
	 * @return array
	 */
	private function get_common_marker_settings( $forms ) {

		/**
		 * Modify marker settings.
		 *
		 * @since 2.3.0
		 *
		 * @param array $marker_settings Marker settings.
		 * @param array $forms           Forms.
		 *
		 * @return array
		 */
		return (array) apply_filters( 'wpforms_geolocation_front_fields_get_marker_settings', [], $forms );
	}
}
