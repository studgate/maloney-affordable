<?php

namespace WPFormsGeolocation\Admin;

use WPFormsGeolocation\SmartTags;
use WPFormsGeolocation\RetrieveGeoData;
use WPFormsGeolocation\Tasks\EntryGeolocationUpdateTask;

/**
 * Class Entry.
 *
 * @since 2.0.0
 */
class Entry {

	/**
	 * Retrieve Geolocation Data.
	 *
	 * @since 2.0.0
	 *
	 * @var RetrieveGeoData
	 */
	private $retrieve_geo_data;

	/**
	 * Smart tags.
	 *
	 * @since 2.3.0
	 *
	 * @var SmartTags
	 */
	private $smart_tags;

	/**
	 * Entry constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param RetrieveGeoData $retrieve_geo_data Retrieve Geolocation Data.
	 * @param SmartTags       $smart_tags        Smart tags.
	 */
	public function __construct( RetrieveGeoData $retrieve_geo_data, SmartTags $smart_tags ) {

		$this->retrieve_geo_data = $retrieve_geo_data;
		$this->smart_tags        = $smart_tags;
	}

	/**
	 * Hooks.
	 *
	 * @since 2.0.0
	 */
	public function hooks() {

		add_action( 'wpforms_entry_details_init', [ $this, 'entry_details_init' ] );
		add_action( 'wpforms_entry_details_content', [ $this, 'entry_details_location' ], 20 );
		add_action( 'wpforms_process_entry_save', [ $this, 'entry_save_location' ], 20, 4 );
		add_filter( 'wpforms_helpers_templates_include_html_located', [ $this, 'templates' ], 10, 2 );
		add_filter( 'wpforms_pro_admin_entries_table_facades_columns_get_meta_columns_columns_data', [ $this, 'add_geolocation_column_entries_table' ] );

		if ( wpforms_is_admin_page( 'entries', 'print' ) ) {
			add_action( 'wpforms_pro_admin_entries_print_preview_entry', [ $this, 'entry_print_preview_init' ], 10, 2 );
			add_filter( 'wpforms_pro_admin_entries_print_preview_display_options', [ $this, 'register_option' ], 10, 3 );
			add_filter( 'wpforms_pro_admin_entries_printpreview_print_html_head', [ $this, 'add_print_page_styles' ], 10, 2 );
			add_action( 'wpforms_pro_admin_entries_printpreview_print_html_fields_after', [ $this, 'add_print_page_item' ], 10, 2 );
		}

		if ( wpforms_is_admin_page( 'entries', 'list' ) ) {
			add_filter( 'wpforms_pro_admin_entries_list_table_column_form_field_meta_field_value', [ $this, 'entries_table_column_value' ], 10, 3 );
		}
	}

	/**
	 * Maybe fetch geolocation data.
	 *
	 * If a form is using the location smart tag in an email notification, then
	 * we need to process the geolocation data before emails are sent. Otherwise
	 * geolocation data is processed on-demand when viewing an individual entry.
	 *
	 * @since 2.0.0
	 *
	 * @param array $fields    List of form fields.
	 * @param array $entry     User submitted data.
	 * @param int   $form_id   Form ID.
	 * @param array $form_data Form data and settings.
	 */
	public function entry_save_location( $fields, $entry, $form_id, $form_data ) {

		if ( empty( wpforms()->obj( 'process' )->entry_id ) ) {
			return;
		}

		if ( ! wpforms_is_collecting_ip_allowed( $form_data ) ) {
			return;
		}

		wpforms()
			->obj( 'tasks' )
			->create( EntryGeolocationUpdateTask::ACTION )
			->async()
			->params(
				absint( wpforms()->obj( 'process' )->entry_id ),
				absint( $form_id ),
				wpforms_get_ip()
			)
			->register();
	}

	/**
	 * Maybe process geolocation data when an individual entry is viewed.
	 *
	 * @since 2.0.0
	 *
	 * @param object $entries WPForms_Entries_Single.
	 */
	public function entry_details_init( $entries ) {

		$this->set_entry_location( $entries->entry );
	}

	/**
	 * Entry initialization on print preview.
	 *
	 * @since 2.5.0
	 *
	 * @param object $entry     Entry.
	 * @param array  $form_data Form data and settings.
	 */
	public function entry_print_preview_init( $entry, $form_data ) {

		$this->set_entry_location( $entry );
	}

	/**
	 * Get formatted location for table view.
	 *
	 * @since 2.8.0
	 *
	 * @param string|mixed $value  Value.
	 * @param object       $entry  Entry.
	 * @param string       $column Column.
	 *
	 * @return string
	 */
	public function entries_table_column_value( $value, $entry, string $column ): string {

		if ( $column !== 'geolocation' ) {
			return (string) $value;
		}

		$location = wpforms()->obj( 'entry_meta' )->get_meta(
			[
				'entry_id' => $entry->entry_id,
				'type'     => 'location',
				'number'   => 1,
			]
		);

		if ( empty( $location ) ) {
			return esc_html__( 'N/A', 'wpforms-geolocation' );
		}

		$location = json_decode( $location[0]->data, true );

		return $this->get_formatted_location( $location );
	}

	/**
	 * Add geolocation column to the entries table.
	 *
	 * @since 2.8.0
	 *
	 * @param array|mixed $columns_data Columns data.
	 *
	 * @return array
	 */
	public function add_geolocation_column_entries_table( $columns_data ): array {

		$columns_data                = (array) $columns_data;
		$columns_data['geolocation'] = [
			'label' => esc_html__( 'Geolocation Details', 'wpforms-geolocation' ),
		];

		return $columns_data;
	}

	/**
	 * Set location properties to an entry.
	 *
	 * @since 2.5.0
	 *
	 * @param object $entry Entry.
	 */
	private function set_entry_location( $entry ) {

		$entry->entry_location     = [];
		$entry->formatted_location = '';

		$entry_meta = wpforms()->obj( 'entry_meta' );
		$location   = $entry_meta->get_meta(
			[
				'entry_id' => $entry->entry_id,
				'type'     => 'location',
				'number'   => 1,
			]
		);

		if ( ! empty( $location ) ) {
			$location = json_decode( $location[0]->data, true );

			$entry->entry_location     = $location;
			$entry->formatted_location = $this->get_formatted_location( $location );

			return;
		}

		if ( empty( $entry->ip_address ) ) {
			return;
		}

		$location = $this->retrieve_geo_data->get_location( $entry->ip_address );

		if ( empty( $location ) ) {
			return;
		}

		$data = [
			'entry_id' => absint( $entry->entry_id ),
			'form_id'  => absint( $entry->form_id ),
			'type'     => 'location',
			'data'     => wp_json_encode( $location ),
		];

		$entry_meta->add( $data, 'entry_meta' );

		$entry->entry_location     = $location;
		$entry->formatted_location = $this->get_formatted_location( $location );
	}

	/**
	 * Formatting location.
	 *
	 * @since 2.5.0
	 *
	 * @param array $location Location.
	 *
	 * @return string
	 */
	private function get_formatted_location( $location ) {

		$location = wp_parse_args(
			$location,
			[
				'city'      => '',
				'region'    => '',
				'country'   => '',
				'postal'    => '',
				'latitude'  => '',
				'longitude' => '',
			]
		);

		$line_placeholder = '%1$s, %2$s';

		$lines = [
			sprintf( $line_placeholder, $location['city'], $location['region'] ),
			sprintf( $line_placeholder, $location['country'], $location['postal'] ),
			sprintf( $line_placeholder, $location['latitude'], $location['longitude'] ),
		];

		$lines = array_filter(
			array_map(
				static function( $line ) {

					return trim( $line, " \t\n\r\0\x0B," );
				},
				$lines
			)
		);

		return implode( PHP_EOL, $lines );
	}

	/**
	 * Register option in the Display Settings section on the Entry Print page.
	 *
	 * @since 2.5.0
	 *
	 * @param array  $display_options List of print page options for the Display Section.
	 * @param object $entry           Entry.
	 * @param array  $form_data       Form data and settings.
	 */
	public function register_option( $display_options, $entry, $form_data ) {

		if ( empty( $entry->formatted_location ) ) {
			return $display_options;
		}

		return wpforms_array_insert(
			$display_options,
			[
				'location' => __( 'Locations Data', 'wpforms-geolocation' ),
			],
			'compact'
		);
	}

	/**
	 * Add styles for toggling the Location information.
	 *
	 * @since 2.5.0
	 *
	 * @param object $entry     Entry.
	 * @param array  $form_data Form data and settings.
	 */
	public function add_print_page_styles( $entry, $form_data ) {

		if ( empty( $entry->formatted_location ) ) {
			return;
		}

		echo '<style>.print-preview:not(.wpforms-preview-mode-location) .wpforms-field-location {display: none !important;}</style>';
	}

	/**
	 * Add Location print item for the Entry Print page.
	 *
	 * @since 2.5.0
	 *
	 * @param object $entry     Entry.
	 * @param array  $form_data Form data and settings.
	 */
	public function add_print_page_item( $entry, $form_data ) {

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render(
			WPFORMS_GEOLOCATION_PATH . 'templates/entry/print-item',
			[
				'entry'     => $entry,
				'form_data' => $form_data,
			],
			true
		);
	}

	/**
	 * Change a template location.
	 *
	 * @since 1.0.0
	 *
	 * @param string $located  Template location.
	 * @param string $template Template.
	 *
	 * @return string
	 */
	public function templates( $located, $template ) {

		// Checking if `$template` is an absolute path and passed from this plugin.
		if (
			( 0 === strpos( $template, WPFORMS_GEOLOCATION_PATH ) ) &&
			is_readable( $template )
		) {
			return $template;
		}

		return $located;
	}

	/**
	 * Entry details location metabox, display the info and make it look fancy.
	 *
	 * @since 2.0.0
	 *
	 * @param object $entry Entry.
	 */
	public function entry_details_location( $entry ) {

		echo wpforms_render( //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			WPFORMS_GEOLOCATION_PATH . 'templates/metabox/entry-details-location',
			[
				'entry'   => $entry,
				'map_url' => $this->get_map_preview_url( $entry ),
			],
			true
		);
	}

	/**
	 * Get url for map preview.
	 *
	 * @since 2.0.0
	 *
	 * @param object $entry Entry.
	 *
	 * @return string
	 */
	private function get_map_preview_url( $entry ) {

		$entry->entry_location = array_map( 'sanitize_text_field', $entry->entry_location );
		$latitude              = ! empty( $entry->entry_location['latitude'] ) ? $entry->entry_location['latitude'] : '';
		$longitude             = ! empty( $entry->entry_location['longitude'] ) ? $entry->entry_location['longitude'] : '';

		if ( empty( $latitude ) || empty( $longitude ) ) {
			return '';
		}

		$latlong    = "$latitude, $longitude";
		$location   = '';
		$loc_city   = ! empty( $entry->entry_location['city'] ) ? $entry->entry_location['city'] : '';
		$loc_region = ! empty( $entry->entry_location['region'] ) ? $entry->entry_location['region'] : '';

		if ( ! empty( $loc_city ) && ! empty( $loc_region ) ) {
			$location = "$loc_city, $loc_region";
		}

		return add_query_arg(
			[
				'q'      => str_replace( ' ', '', $location ),
				'll'     => str_replace( ' ', '', $latlong ),
				'z'      => absint( apply_filters( 'wpforms_geolocation_map_zoom', 6, 'entry' ) ),
				'output' => 'embed',
			],
			'https://maps.google.com/maps'
		);
	}
}
