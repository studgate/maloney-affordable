<?php

use const OTGS\Toolset\Views\UserCapabilities\EDIT_VIEWS;

/**
 * Toolset Maps - Views Distance Filter
 *
 * @package ToolsetMaps
 *
 * @since 1.4
 */
class Toolset_Addon_Maps_Views_Distance_Filter extends Toolset_Maps_Views_Distance {

	const DISTANCE_CENTER_DEFAULT_URL_PARAM = 'toolset_maps_distance_center';
	const DISTANCE_RADIUS_DEFAULT_URL_PARAM = 'toolset_maps_distance_radius';
	const DISTANCE_UNIT_DEFAULT_URL_PARAM = 'toolset_maps_distance_unit';

	const DISTANCE_FILTER_INPUTS_PLACEHOLDER = 'Show results within %%DISTANCE%% of %%CENTER%%';
	const DISTANCE_FILTER_VISITOR_LOCATION_BUTTON_TEXT = 'Use my location';

	protected $api_used = Toolset_Addon_Maps_Common::API_GOOGLE;

	public $frontend_js = array(
		'use_user_location' => false
	);
	public $use_frontend_script = false;

	static $distance_filter_options = array(
		'map_distance'                            => 5,
		'map_distance_center'                     => '',
		'map_distance_center_required'            => 'no',
		'map_distance_unit'                       => 'km',
		'map_center_lat'                          => '',
		'map_center_lng'                          => '',
		'map_center_source'                       => 'address',
		'map_distance_center_url_param_name'      => 'mapcenter',
		'map_distance_radius_url_param_name'      => '',
		'map_distance_unit_url_param_name'        => '',
		'map_distance_center_shortcode_attr_name' => 'mapcenter',
		'map_distance_compare_field'              => null,
		'map_distance_what_to_show'               => 'inside',
	);

	/** @var array Keeps current filter settings, so they can be picked up by the next filter called. */
	private $filter_settings = [];

	private $force_distance_center_url_param = '';
	private $force_distance_radius_url_param = '';
	private $force_distance_unit_url_param = '';

	function __construct() {
		if ( ! $this->is_types_active() ) return;

		parent::__construct();

		$this->register_child_setting_string( 'map_distance_filter' );

		$this->api_used = apply_filters( 'toolset_maps_get_api_used', '' );

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );

		if ( is_admin() ) {
			//Add Views query filter and frontend filter
			add_filter( 'wpv_filters_add_filter', array( $this, 'add_distance_views_filter' ), 1, 1 );
			//action for listing distance query filter
			add_action( 'wpv_add_filter_list_item', array( $this, 'list_items' ), 1, 1 );
			//updating deleting distance query filter
			add_action( 'wp_ajax_toolset_maps_distance_views_filter_update', array(	$this, 'update_callback' ) );
			add_action( 'wp_ajax_wpv_filter_maps_distance_delete', array( $this, 'delete_callback' ) );
			add_filter( 'wpv_filter_wpv_shortcodes_gui_data', array( $this,	'register_gui_data'	) );
		}

		// Add action to filter the loop items with distance before they're rendered
		if ( Toolset_Addon_Maps_Common::are_coordinates_migrated() ) {
			add_filter( 'wpv_filter_query', array( $this, 'filter_posts_in_sql' ), 10, 3 );
		} else {
			// (Priority 100 because it needs to run after Relevanssi, which has priority 99.)
			add_filter( 'wpv_filter_query_post_process', array( $this, 'distance_views_filter_apply_rules' ), 100, 3 );
		}

		// Register distance search filter
		add_shortcode( 'wpv-control-distance', array( $this, 'shortcode_wpv_control_post_distance' ) );
		add_filter( 'wpv_filter_wpv_register_form_filters_shortcodes', array(
			$this,
			'add_distance_custom_search_filter'
		), 5 );

		add_action( 'toolset_maps_distance_use_frontend_script', array( $this, 'add_distance_filter_settings' ) );

		// Register string for translation
		add_action( 'init', array( $this, 'register_for_translation' ) );

		add_filter( 'wpv_filter_register_shortcode_attributes_for_posts', array(
			$this,
			'shortcode_attributes'
		), 10, 2 );
		add_filter( 'wpv_filter_register_url_parameters_for_posts', array( $this, 'register_url_parameters' ), 10, 2 );

		// Force disable a View cache when it contains a distance filter.
		add_filter( 'wpv_filter_disable_caching', array( $this, 'disable_views_caching' ), 10, 3 );
	}

	/**
	 * Force disable the View caching mechanism when it contains a distance filter.
	 *
	 * This ensures that the frontend distance filter script is enqueued.
	 *
	 * @since 2.0.7
	 * @param bool $state
	 * @param int $view_id
	 * @param array $view_settings
	 * @return bool
	 */
	public function disable_views_caching( $state, $view_id, array $view_settings ) {
		return array_key_exists( 'map_distance_filter', $view_settings ) ? true : $state;
	}

	/**
	 * Register the filter to get URL parameters
	 *
	 * @param array $attributes
	 * @param array $view_settings
	 *
	 * @return array
	 */
	public function register_url_parameters( array $attributes, array $view_settings ) {
		if (
			isset( $view_settings['map_distance_filter'] )
			&& isset( $view_settings['map_distance_filter']['map_center_source'] )
			&& $view_settings['map_distance_filter']['map_center_source'] == 'url_param'
		) {
			$fields = array(
				self::DISTANCE_CENTER_DEFAULT_URL_PARAM => $view_settings['map_distance_filter']['map_distance_center'],
				self::DISTANCE_RADIUS_DEFAULT_URL_PARAM => $view_settings['map_distance_filter']['map_distance'],
				self::DISTANCE_UNIT_DEFAULT_URL_PARAM => $view_settings['map_distance_filter']['map_distance_unit']
			);
			foreach ( $fields as $attribute => $value ) {
				$attributes[] = array(
					'query_type'   => $view_settings['query_type'][0],
					'filter_type'  => $attribute, // Filter type must be unique, because it's used as array key later on
					'filter_label' => '',
					'value'        => $value,
					'attribute'    => $attribute,
					'expected'     => 'string',
					'placeholder'  => '',
					'description'  => ''
				);
			}
		}
		return $attributes;
	}

	/**
	 * Registers for translation using Views filter
	 * @since 1.4.2
	 */
	public function register_for_translation() {
		add_filter(
			'wpv_filter_get_fake_shortcodes_for_attributes_translation',
			array( $this, 'add_shortcode_atts_for_translation' )
		);
	}

	/**
	 * @param array $fake_shortcodes
	 *
	 * @return array
	 *
	 * @since 1.4.2
	 */
	function add_shortcode_atts_for_translation( array $fake_shortcodes ) {
		$fake_shortcodes['wpv-control-distance'] = array( $this, 'register_shortcode_atts' );

		return $fake_shortcodes;
	}

	/**
	 * Register wpv-control-distance shortcode attributes for translation.
	 *
	 * @param array $atts The shortcode attributes
	 *
	 * @since 1.4.2
	 */
	public function register_shortcode_atts( array $atts ) {
		$attributes_to_translate = array( 'inputs_placeholder', 'visitor_location_button_text' );

		foreach ( $attributes_to_translate as $att_to_translate ) {
			if ( isset( $atts[ $att_to_translate ] ) ) {
				do_action(
					'wpml_register_single_string',
					'toolset-maps',
					$att_to_translate,
					$atts[ $att_to_translate ]
				);
			}
		}
	}

	static function shortcode_attributes( $attributes, $view_settings ) {
		if (
			isset( $view_settings['map_distance_filter'] )
			&& isset( $view_settings['map_distance_filter']['map_center_source'] )
			&& $view_settings['map_distance_filter']['map_center_source'] == 'shortcode_attr'
		) {
			$distance_filter_settings = $view_settings['map_distance_filter'];
			$attributes[]             = array(
				'query_type'   => $view_settings['query_type'][0],
				'filter_type'  => 'maps_distance',
				'filter_label' => __( 'Map Distance Center', 'toolset-maps' ),
				'value'        => '',
				'attribute'    => $distance_filter_settings['map_distance_center_shortcode_attr_name'],
				'expected'     => 'string',
				'placeholder'  => '30.013056, 31.208853',
				'description'  => __( 'Please type a comma separated latitude, longitude', 'toolset-maps' )
			);
		}

		return $attributes;
	}

	function admin_init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_view_editor_assets' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_backend_assets' ), 20 );
	}

	function init() {
		$this->frontend_js['geolocation_error'] = __(
			'Cannot do this search without user location. Error: ',
			'toolset-maps'
		);

		$this->register_assets();
	}

	/**
	 * @since 2.0.7
	 */
	public function rest_api_init() {
		// Needed for View block compatibility
		add_action( 'wpv_add_filter_list_item', array( $this, 'list_items' ), 1, 1 );
	}

	/**
	 * Registers distance filters JS and CSS.
	 */
	private function register_assets() {
		$backend_js_dependencies = ( Toolset_Addon_Maps_Common::API_GOOGLE === $this->api_used )
			? array( 'toolset-google-map-editor-script' )
			: array( 'toolset-maps-address-autocomplete' );

		wp_register_script(
			'toolset-maps-views-filter-distance-backend-js',
			TOOLSET_ADDON_MAPS_URL . '/resources/js/views_filter_distance.js',
			$backend_js_dependencies,
			TOOLSET_ADDON_MAPS_VERSION,
			false
		);

		wp_register_style(
			'toolset-maps-views-filter-distance-backend-css',
			TOOLSET_ADDON_MAPS_URL . '/resources/css/views_filter_distance_backend.css',
			array(),
			TOOLSET_ADDON_MAPS_VERSION
		);

		$frontend_js_dependencies = ( Toolset_Addon_Maps_Common::API_GOOGLE === $this->api_used )
			? array( 'google-maps', 'jquery-geocomplete' )
			: array( 'toolset-maps-address-autocomplete' );

		wp_register_script(
			'toolset-maps-views-filter-distance-frontend-js',
			TOOLSET_ADDON_MAPS_URL . '/resources/js/views_filter_distance_frontend.js',
			$frontend_js_dependencies,
			TOOLSET_ADDON_MAPS_VERSION,
			true
		);

		wp_register_style(
			'toolset-maps-views-filter-distance-frontend-css',
			TOOLSET_ADDON_MAPS_URL . '/resources/css/views_filter_distance_frontend.css',
			array(),
			TOOLSET_ADDON_MAPS_VERSION
		);
	}

	public function enqueue_backend_assets() {
		wp_enqueue_style( 'toolset-maps-views-filter-distance-backend-css' );
		wp_enqueue_script( 'toolset-maps-views-filter-distance-backend-js' );
		Toolset_Addon_Maps_Common::maybe_enqueue_azure_css();
	}

	/**
	 * @action admin_enqueue_scripts
	 */
	public function enqueue_view_editor_assets() {
		if ( isset( $_GET['page'] ) && 'views-editor' === $_GET['page'] ) {
			$this->enqueue_backend_assets();
		}
	}

	/**
	 * Adds localization late (on action call), so special settings for frontend JS can be filled in when needed
	 * @action toolset_maps_distance_use_frontend_script
	 */
	public function add_distance_filter_settings() {
		wp_localize_script(
			'toolset-maps-views-filter-distance-frontend-js',
			'toolset_maps_distance_filter_settings',
			$this->frontend_js
		);
	}

	static function add_distance_views_filter( $filters ) {
		if ( self::get_saved_option( 'api_key' ) ) {
			$filters['maps_distance'] = array(
				'name'     => __( 'Distance', 'toolset-maps' ),
				'present'  => 'map_distance_filter',
				'callback' => array(
					'Toolset_Addon_Maps_Views_Distance_Filter',
					'add_new_distance_views_filter_list_items'
				),
				'group'    => 'Toolset Maps'
			);
		}

		return $filters;
	}

	static function add_new_distance_views_filter_list_items() {
		$args = array(
			'view-query-mode'     => 'normal',
			'map_distance_filter' => Toolset_Addon_Maps_Views_Distance_Filter::$distance_filter_options
		);

		Toolset_Addon_Maps_Views_Distance_Filter::list_items( $args );
	}

	static function list_items( $view_settings ) {
		if ( isset( $view_settings['map_distance_filter'] ) ) {
			if ( class_exists( 'WPV_Filter_Item' ) ) {
				$li = Toolset_Addon_Maps_Views_Distance_Filter::get_list_item_ui_distance_filter( $view_settings );
				WPV_Filter_Item::simple_filter_list_item( 'maps_distance', 'posts', 'maps-distance', __( 'Distance', 'toolset-maps' ), $li );
			}
		}
	}

	static function get_list_item_ui_distance_filter( $view_settings = array() ) {
		$analytics_strings = array(
			'utm_source'	=> 'toolsetmapsplugin',
			'utm_campaign'	=> 'toolsetmaps',
			'utm_medium'	=> 'views-integration-distance-filter',
			'utm_term'		=> 'our documentation'
		);
		$href = Toolset_Addon_Maps_Common::get_documentation_promotional_link(
				array( 'query' => $analytics_strings, 'anchor' => 'filtering-markers-by-distance' ),
				TOOLSET_ADDON_MAPS_DOC_LINK . 'displaying-markers-on-google-maps/'
		);
		$link = '<a class="wpv-help-link" href="' . $href . '" target="_blank">';

		ob_start();
		?>
		<p class='wpv-filter-maps-distance-edit-summary js-wpv-filter-summary js-wpv-filter-maps-distance-summary'>
			<?php echo Toolset_Addon_Maps_Views_Distance_Filter::get_summary_txt( $view_settings ); ?>
		</p>
		<?php
		WPV_Filter_Item::simple_filter_list_item_buttons( 'maps-distance', 'toolset_maps_distance_views_filter_update', wp_create_nonce( 'toolset_maps_distance_views_filter_update_nonce' ), 'toolset_maps_distance_views_filter_delete', wp_create_nonce( 'toolset_maps_distance_views_filter_delete_nonce' ) );
		?>
		<div id="wpv-filter-maps-distance-edit" class="wpv-filter-edit js-wpv-filter-edit">
			<div id="wpv-filter-maps-distance" class="js-wpv-filter-options js-wpv-filter-maps-distance-options">
				<?php Toolset_Addon_Maps_Views_Distance_Filter::render_distance_views_options( $view_settings ); ?>
			</div>
			<div class="js-wpv-filter-toolset-messages"></div>
			<span class="filter-doc-help">
				<?php echo sprintf( __( '%sLearn about filtering by Distance%s', 'toolset-maps' ),
					$link,
					' &raquo;</a>'
				); ?>
			</span>
		</div>
		<?php
		$res = ob_get_clean();

		return $res;
	}

	static function render_distance_views_options( array $view_settings = array() ) {
		$defaults = array( 'view-query-mode' => 'normal' );
		$defaults = array_merge( $defaults, array( 'map_distance_filter' => Toolset_Addon_Maps_Views_Distance_Filter::$distance_filter_options ) );

		$view_settings     = wp_parse_args( $view_settings, $defaults );
		$distance_settings = $view_settings['map_distance_filter'];
		$args = array(
			'field_type' => array( 'google_address' ),
			'filter'     => 'types'
		);
		$address_fields = apply_filters( 'types_filter_query_field_definitions', array(), $args );
		$map_distance = esc_attr(
			!empty( $distance_settings['map_distance'] )
				? $distance_settings['map_distance']
				: self::$distance_filter_options['map_distance']
		);
		$distance_center_settings = $distance_settings;
		$distance_center_settings['frontend_served_over_https'] = self::is_frontend_served_over_https_static();

		// Compatibility with old views, which don't have this saved
		if ( array_key_exists( 'map_distance_what_to_show', $distance_settings ) ) {
			$what_to_show = $distance_settings['map_distance_what_to_show'];
		} else {
			$what_to_show = self::$distance_filter_options['map_distance_what_to_show'];
		}

		?>
		<h4><?php _e( 'How to filter', 'toolset-maps' ); ?></h4>
		<ul class="wpv-filter-options-set">
			<?php
			echo self::render_view_static(
				'map_distance_compare_field',
				array( $address_fields, $distance_settings['map_distance_compare_field'] )
			);
			echo self::render_view_static(
				'map_distance',
				array( $map_distance, $distance_settings['map_distance_unit'] )
			);
			echo self::render_view_static(
				'map_distance_what_to_show',
				array( $what_to_show )
			);
			echo self::render_view_static( 'map_distance_center', $distance_center_settings );
			?>
		</ul>
		<div class="filter-helper js-wpv-author-helper"></div>
		<?php
	}

	static function update_callback() {
		if ( ! current_user_can( EDIT_VIEWS ) ) {
			wp_send_json_error( array(
				'type'    => 'capability',
				'message' => __( 'You do not have permissions for that.', 'toolset-maps' )
			) );
		}
		if (
			! isset( $_POST["wpnonce"] )
			|| ! wp_verify_nonce( $_POST["wpnonce"], 'toolset_maps_distance_views_filter_update_nonce' )
		) {
			wp_send_json_error( array(
				'type'    => 'nonce',
				'message' => __(
					'Your security credentials have expired. Please reload the page to get new ones.',
					'toolset-maps'
				),
			) );
		}
		if (
			! isset( $_POST["id"] )
			|| ! is_numeric( $_POST["id"] )
			|| intval( $_POST['id'] ) < 1
		) {
			wp_send_json_error( array(
				'type'    => 'id',
				'message' => __( 'Wrong or missing ID.', 'toolset-maps' )
			) );
		}
		if ( empty( $_POST['filter_options'] ) ) {
			wp_send_json_error( array(
				'type'    => 'data_missing',
				'message' => __( 'Wrong or missing data.', 'toolset-maps' )
			) );
		}
		$view_id = intval( $_POST['id'] );
		parse_str( $_POST['filter_options'], $distance_filter );
		$change     = false;
		$view_array = get_post_meta( $view_id, '_wpv_settings', true );

		$settings_to_check = array_keys( Toolset_Addon_Maps_Views_Distance_Filter::$distance_filter_options );

		foreach ( $settings_to_check as $set ) {
			if (
				isset( $distance_filter[ $set ] )
				&& (
					! isset( $view_array[ $set ] )
					|| $distance_filter[ $set ] != $view_array[ $set ]
				)
			) {
				if ( is_array( $distance_filter[ $set ] ) ) {
					$distance_filter[ $set ] = array_map( 'sanitize_text_field', $distance_filter[ $set ] );
				} else {
					$distance_filter[ $set ] = sanitize_text_field( $distance_filter[ $set ] );
				}
				$change = true;
			}
		}
		if ( $change ) {
			$view_array['map_distance_filter'] = $distance_filter;
			update_post_meta( $view_id, '_wpv_settings', $view_array );
			do_action( 'wpv_action_wpv_save_item', $view_id );
		}
		wp_send_json_success( array(
			'id'      => $view_id,
			'message' => __( 'Distance filter saved', 'toolset-maps' ),
			'summary' => Toolset_Addon_Maps_Views_Distance_Filter::get_summary_txt(
				array( 'map_distance_filter' => $distance_filter )
			),
		) );
	}

	/**
	 * Multi-API aware check for API keys.
	 * @return bool
	 */
	protected static function is_the_right_api_key_entered() {
		$api_used = apply_filters( 'toolset_maps_get_api_used', '' );

		if ( Toolset_Addon_Maps_Common::API_GOOGLE === $api_used ) {
            // Check Google Maps key
			$key = apply_filters( 'toolset_filter_toolset_maps_get_api_key', '' );
            return ! empty( $key );

        } elseif ( Toolset_Addon_Maps_Common::API_AZURE === $api_used ) {
            // Check Azure key
            $key = apply_filters( 'toolset_filter_toolset_maps_get_azure_api_key', '' );
            return ! empty( $key );

		} else {
            // Open Street Maps - no key needed
            return true;
		}
	}

	static function get_summary_txt( array $views_settings ) {
		if ( !self::is_the_right_api_key_entered() ) {
			return __( 'You need to set a valid API key', 'toolset-maps' );
		}

		$distance_filter = $views_settings['map_distance_filter'];
		if (
			! isset( $distance_filter['map_distance'] )
			|| ! isset( $distance_filter['map_distance_center'] )
			|| ! isset( $distance_filter['map_distance_unit'] )
			|| ! isset( $distance_filter['map_center_source'] )
		) {
			return '';
		}

		if ( !$distance_filter['map_distance_unit'] ) {
			$distance_filter['map_distance_unit'] = self::$distance_filter_options['map_distance_unit'];
		}
		if ( !$distance_filter['map_distance'] ) {
			$distance_filter['map_distance'] = self::$distance_filter_options['map_distance'];
		}

		if ( array_key_exists( 'map_distance_what_to_show', $distance_filter ) ) {
			$within_or_outside = $distance_filter['map_distance_what_to_show'] === 'inside'
				? __( 'within', 'toolset-maps' )
				: __( 'outside of', 'toolset-maps' );
		} else {
			$within_or_outside = __( 'within', 'toolset-maps' );
		}

		switch ( $distance_filter['map_center_source'] ) {
			case 'address':
				$distance_center_summary = $distance_filter['map_distance_center'];
				break;
			case 'url_param':
				$distance_center_summary = __(' address/coordinates provided using <i>', 'toolset-maps' )
										   . $distance_filter['map_distance_center_url_param_name']
										   . __( '</i> URL parameter', 'toolset-maps' );
				break;
			case 'shortcode_attr':
				$distance_center_summary = __(' address/coordinates provided using <i>', 'toolset-maps' )
										   . $distance_filter['map_distance_center_shortcode_attr_name']
										   . __( '</i> shortcode attribute', 'toolset-maps' );
				break;
			case 'user_location':
				$distance_center_summary = __(' the viewing user\'s location', 'toolset-maps' );
				break;
			default:
				$distance_center_summary = '';
		}

		return sprintf(
			/* translators: %1$s is either 'within' or 'outside of', %2$s is distance number, %3$s is distance unit and
			%4$s is the distance center */
			__( 'Show posts %1$s <strong>%2$s%3$s</strong> radius of <strong>%4$s</strong>.', 'toolset-maps' ),
			$within_or_outside,
			$distance_filter['map_distance'],
			$distance_filter['map_distance_unit'],
			$distance_center_summary
		);
	}

	static function delete_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$data = array(
				'type'    => 'capability',
				'message' => __( 'You do not have permissions for that.', 'toolset-maps' )
			);
			wp_send_json_error( $data );
		}
		if (
			! isset( $_POST['wpnonce'] ) || ! wp_verify_nonce( $_POST['wpnonce'], 'toolset_maps_distance_views_filter_delete_nonce' )
		) {
			$data = array(
				'type'    => 'nonce',
				'message' => __( 'Your security credentials have expired. Please reload the page to get new ones.', 'toolset-maps' )
			);
			wp_send_json_error( $data );
		}
		if (
			! isset( $_POST['id'] ) || ! is_numeric( $_POST['id'] ) || intval( $_POST['id'] ) < 1
		) {
			$data = array(
				'type'    => 'id',
				'message' => __( 'Wrong or missing ID.', 'toolset-maps' )
			);
			wp_send_json_error( $data );
		}

		$view_array = get_post_meta( $_POST['id'], '_wpv_settings', true );



				unset( $view_array['map_distance_filter'] );

		update_post_meta( $_POST['id'], '_wpv_settings', $view_array );
		do_action( 'wpv_action_wpv_save_item', $_POST['id'] );
		$data = array(
			'id'      => $_POST['id'],
			'message' => __( 'Distance filter deleted', 'toolset-maps' )
		);
		wp_send_json_success( $data );
	}

	/**
	 * @param array $center_coords
	 * @param array $address_coords
	 * @param string $unit
	 *
	 * @return float|int
	 */
	static function calculate_distance_diff( $center_coords, $address_coords, $unit = 'km' ) {
		$earth_radius = ( $unit == 'mi' ? 3963.0 : 6371 );

		$lat_diff = deg2rad( $address_coords['lat'] - $center_coords['lat'] );
		$lon_diff = deg2rad( $address_coords['lon'] - $center_coords['lon'] );

		$lat_lon_delta = sin( $lat_diff / 2 ) * sin( $lat_diff / 2 ) + cos( deg2rad( $center_coords['lat'] ) ) * cos( deg2rad( $address_coords['lat'] ) ) * sin( $lon_diff / 2 ) * sin( $lon_diff / 2 );
		$lat_lon_angle = 2 * asin( sqrt( $lat_lon_delta ) );
		$distance      = $earth_radius * $lat_lon_angle;

		return $distance;
	}

	static function coords_or_address( $input ) {
		if ( empty ( $input ) ) {
			return false;
		}

		if ( self::validate_coords( $input ) ) {
			return 'coords';
		}

		// It is practically impossible to validate if a string is an address or not, so if the $input is not empty and
		// not coordinates, we have to assume address and ask API (hopefully cached) if it knows of this address.
		return 'address';
	}

	static function validate_coords( $coords ) {
		return preg_match( '/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?);[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $coords );
	}

	static function get_coords_array_from_input( $input ) {
		$provided_center_type = self::coords_or_address( $input );
		$coords_array    = array();

		if ( $provided_center_type !== false ) {
			if ( $provided_center_type == 'address' ) {
				$address_coords = Toolset_Addon_Maps_Common::get_coordinates( $input );

				return $address_coords;
			}

			$exploded_coords = explode( ',', $input );

			if ( count( $exploded_coords ) > 1 ) {
				$coords_array['lat'] = $exploded_coords[0];
				$coords_array['lon'] = $exploded_coords[1];
			}
		}

		return $coords_array;
	}

	/**
	 * @param mixed $coords_array
	 * @return bool
	 */
	static function is_valid_coords_array( $coords_array ) {
		return (
			is_array( $coords_array )
			&& array_key_exists( 'lat', $coords_array )
			&& array_key_exists( 'lon', $coords_array )
		);
	}

	/**
	 * Fake a frontend distance filter shortcode to extract values form some of its attrbutes.
	 * Use this to globally store the URL parameters to listen to,
	 * to get center, radius and units in a frontend filter by distance.
	 *
	 * @param array $view_settings
	 * @since 1.8.2
	 */
	private function fake_distance_shortcode_to_get_url_parameters( $view_settings ) {
		$view_filter_editor = toolset_getarr( $view_settings, 'filter_meta_html' );

		if ( strpos( $view_filter_editor, '[wpv-control-distance' ) === false ) {
			$this->force_distance_center_url_param = '';
			$this->force_distance_radius_url_param = '';
			$this->force_distance_unit_url_param = '';
			return;
		}

		global $shortcode_tags;
		// Back up current registered shortcodes and clear them all out
		$orig_shortcode_tags = $shortcode_tags;

		remove_all_shortcodes();

		add_shortcode( 'wpv-control-distance', array( $this, 'get_filter_url_parameters' ) );

		$view_filter_editor = stripslashes( $view_filter_editor );

		do_shortcode( $view_filter_editor );

		$shortcode_tags = $orig_shortcode_tags;

		return;
	}

	/**
	 * Fake callback to the wpv-control-distance shortcode, to extract values from its attributes.
	 *
	 * @param array $atts
	 * @param string|null $content
	 * @since 1.8.2
	 */
	public function get_filter_url_parameters( $atts, $content = null ) {
		// Note that a shortcode without attributes will pass a string in $atts.
		// Usually shortcode_atts takes care of this but we call it too late.
		$atts = (array) $atts;

		$required_atts = array(
			'distance_center_url_param',
			'distance_radius_url_param',
			'distance_unit_url_param'
		);
		foreach ( $required_atts as $required_att ) {
			if ( !array_key_exists( $required_att, $atts ) ) {
				$this->force_distance_center_url_param = '';
				$this->force_distance_radius_url_param = '';
				$this->force_distance_unit_url_param = '';
				return;
			}
		}

		$atts = shortcode_atts( array(
			'distance_center_url_param' => self::DISTANCE_CENTER_DEFAULT_URL_PARAM,
			'distance_radius_url_param' => self::DISTANCE_RADIUS_DEFAULT_URL_PARAM,
			'distance_unit_url_param' => self::DISTANCE_UNIT_DEFAULT_URL_PARAM,
		), $atts);

		$distance_radius_url_param = esc_attr( $atts['distance_radius_url_param'] );
		$distance_center_url_param = esc_attr( $atts['distance_center_url_param'] );
		$distance_unit_url_param   = esc_attr( $atts['distance_unit_url_param'] );

		$this->force_distance_center_url_param = $distance_center_url_param;
		$this->force_distance_radius_url_param = $distance_radius_url_param;
		$this->force_distance_unit_url_param = $distance_unit_url_param;

		return;
	}

	/**
	 * Before performing the filtering, make sure that center, radius and unit values coming from
	 * URL parameters, set by query args from a frontend filter, are available.
	 *
	 * To do so:
	 * - Check that the View has a query filter by distance using an URL parameter for the center.
	 * - Check that the View has a frontend search filter by distance.
	 * - Store the relevant frontend search URL parameters in some properties of this object.
	 * - Make sure we do listen to those URL parameers.
	 *
	 * @param array $view_settings
	 * @return array
	 * @since 1.8.2
	 */
	private function maybe_apply_missing_filter_settings_from_url( $view_settings ) {
		if ( 'url_param' !== toolset_getnest( $view_settings, array( 'map_distance_filter', 'map_center_source' ) ) ) {
			return $view_settings;
		}

		$this->fake_distance_shortcode_to_get_url_parameters( $view_settings );

		$filter_settings = toolset_getarr( $view_settings, 'map_distance_filter', array() );

		if ( '' !== $this->force_distance_center_url_param ) {
			$filter_settings['map_distance_center_url_param_name'] = $this->force_distance_center_url_param;
		}
		if ( '' !== $this->force_distance_radius_url_param ) {
			$filter_settings['map_distance_radius_url_param_name'] = $this->force_distance_radius_url_param;
		}
		if ( '' !== $this->force_distance_unit_url_param ) {
			$filter_settings['map_distance_unit_url_param_name'] = $this->force_distance_unit_url_param;
		}

		$view_settings['map_distance_filter'] = $filter_settings;
		return $view_settings;
	}

	public function set_coords_from_center_source( $view_settings, $shortcode_attrs = null ) {
		$distance_filter_options = $view_settings['map_distance_filter'];

		if ( array_key_exists( 'map_center_source', $distance_filter_options ) ) {
			switch ( $distance_filter_options['map_center_source'] ) {

				case 'address':
					$address_coords = self::get_coords_array_from_input(
						$distance_filter_options['map_distance_center']
					);

					if (
						! empty( $address_coords )
						&& is_array( $address_coords )
						&& array_key_exists( 'lat', $address_coords )
						&& array_key_exists( 'lon', $address_coords )
					) {
						$distance_filter_options['map_center_lat'] = $address_coords['lat'];
						$distance_filter_options['map_center_lng'] = $address_coords['lon'];
					}
					break;

				case 'user_location':
					$user_location = Toolset_Maps_Location_Factory::create_from_cookie();

					if ( $user_location ) {
						$distance_filter_options['map_center_lat'] = $user_location->get_lat();
						$distance_filter_options['map_center_lng'] = $user_location->get_lng();
					}
					break;

				case 'url_param':
					$distance_filter_options['map_center_lat'] = null;
					$distance_filter_options['map_center_lng'] = null;
					$url_param_value                           = null;

					if ( array_key_exists( 'map_distance_center_url_param_name', $distance_filter_options ) && isset( $_GET[ $distance_filter_options['map_distance_center_url_param_name'] ] ) ) {
						$url_param_value = $_GET[ $distance_filter_options['map_distance_center_url_param_name'] ];

					} elseif ( isset( $_GET[ self::DISTANCE_CENTER_DEFAULT_URL_PARAM ] ) ) {
						$url_param_value = $_GET[ $distance_filter_options['map_distance_center_url_param_name'] ];
					}

					if ( $url_param_value !== null ) {
						$coords_array = self::get_coords_array_from_input( $url_param_value );

						if ( self::is_valid_coords_array( $coords_array ) ) {
							$distance_filter_options['map_center_lat'] = $coords_array['lat'];
							$distance_filter_options['map_center_lng'] = $coords_array['lon'];
						}
					}
					break;

				case 'shortcode_attr':
					if ( is_array( $shortcode_attrs ) && array_key_exists( $distance_filter_options['map_distance_center_shortcode_attr_name'], $shortcode_attrs ) ) {
						$coords_array = self::get_coords_array_from_input( $shortcode_attrs[ $distance_filter_options['map_distance_center_shortcode_attr_name'] ] );

						if ( self::is_valid_coords_array( $coords_array ) ) {
							$distance_filter_options['map_center_lat'] = $coords_array['lat'];
							$distance_filter_options['map_center_lng'] = $coords_array['lon'];
						}
					}
					break;
			}
		}

		return $distance_filter_options;
	}

	public function distance_views_filter_apply_rules( $post_query, $view_settings, $view_id ) {
		if ( ! $this->is_distance_filter_requested( $view_settings, $view_id ) ) {
			return $post_query;
		}

		$view_settings = $this->maybe_apply_missing_filter_settings_from_url( $view_settings );

		//Replace view settings value with shortcode ones
		$view_shortcode_attrs     = apply_filters( 'wpv_filter_wpv_get_view_shortcodes_attributes', array() );
		$distance_filter_settings = $this->set_coords_from_center_source( $view_settings, $view_shortcode_attrs );

		// If there are no coordinates to filter by, most likely the filter hasn't been used yet, so nothing to do.
		if (
			empty( $distance_filter_settings['map_center_lat'] )
			|| empty( $distance_filter_settings['map_center_lng'] )
		) {
			// If there is also ordering, wait with paging, order class will take care of that.
			if ( ! $this->is_distance_order_requested( $view_settings ) ) {
				$post_query = $this->bring_paging_back( $post_query );
			}
			return $post_query;
		}

		if ( $distance_filter_settings['map_center_source'] == 'url_param' ) {
			if (
				isset( $distance_filter_settings['map_distance_radius_url_param_name'] )
				&& $distance_filter_settings['map_distance_radius_url_param_name'] !== ''
				&& isset( $_GET[ $distance_filter_settings['map_distance_radius_url_param_name'] ] )
			) {
				$distance_filter_settings['map_distance'] = (double) $_GET[ $distance_filter_settings['map_distance_radius_url_param_name'] ];
			}

			if (
				isset ( $distance_filter_settings['map_distance_unit_url_param_name'] )
				&& $distance_filter_settings['map_distance_unit_url_param_name'] !== ''
				&& isset( $_GET[ $distance_filter_settings['map_distance_unit_url_param_name'] ] )
			) {
				$distance_filter_settings['map_distance_unit'] = (string) sanitize_text_field( $_GET[ $distance_filter_settings['map_distance_unit_url_param_name'] ] );
			}

			if (
				isset ( $distance_filter_settings['map_distance_center_url_param_name'] )
				&& $distance_filter_settings['map_distance_center_url_param_name'] !== ''
				&& isset( $_GET[ $distance_filter_settings['map_distance_center_url_param_name'] ] )
			) {
				$distance_filter_settings['map_distance_center'] = (string) sanitize_text_field( $_GET[ $distance_filter_settings['map_distance_center_url_param_name'] ] );
			}
		}

		$posts_to_remove = array();

		$args = array(
			'domain'     => 'posts',
			'field_type' => array( 'google_address' ),
			'filter'     => 'types'
		);

		if (
			isset ( $distance_filter_settings['map_distance_compare_field'] )
			&& $distance_filter_settings['map_distance_compare_field'] != - 1
		) {
			$args['search'] = $distance_filter_settings['map_distance_compare_field'];
		}

		$address_fields = apply_filters( 'types_filter_query_field_definitions', array(), $args );

		foreach ( $post_query->posts as $post_index => $the_post ) {
			//I'm leaving the logic for multiple address rule in case we use it in the future
			if ( count( $address_fields ) > 0 ) {
				$is_outside_radius = true;
				$no_latlon = false;

				foreach ( $address_fields as $field ) {
					$post_addresses = get_post_meta( $the_post->ID, $field['meta_key'] );
					$distance_diffs = array();

					// Repeating fields: calculate all distances, then find the smallest one.
					foreach ( $post_addresses as $post_address ) {
						$address_coords = self::get_coords_array_from_input( $post_address );

						// We may get no lat & lon if address is broken. In that case, simply skip this in filtering.
						if ( ! self::is_valid_coords_array( $address_coords ) ) {
							$no_latlon = true;
							continue;
						}

						$center_coords = array(
							'lat' => (double) $distance_filter_settings['map_center_lat'],
							'lon' => (double) $distance_filter_settings['map_center_lng']
						);

						$distance_diff = Toolset_Addon_Maps_Views_Distance_Filter::calculate_distance_diff(
							$center_coords,
							$address_coords,
							$distance_filter_settings['map_distance_unit']
						);

						$distance_diffs[] = $distance_diff;
					}

					if (
						! empty( $distance_diffs )
						&& min( $distance_diffs ) < (double) $distance_filter_settings['map_distance']
					) {
						$is_outside_radius = false;
						break;
					}
				}

				// Filter out stuff outside or inside the radius, depending on setting
				$show_inside = (
					// Compatibility with old views, which haven't got this setting - same as 'inside'
					!array_key_exists( 'map_distance_what_to_show', $distance_filter_settings )
					// And if the setting is present, check what it is
					|| $distance_filter_settings['map_distance_what_to_show'] === 'inside'
				);
				if (
					$no_latlon
					|| ( $is_outside_radius && $show_inside )
					|| ( !$is_outside_radius && !$show_inside )
				) {
					$post_query->post_count  -= 1;
					$post_query->found_posts -= 1;
					$posts_to_remove[]       = $post_index;
				}
			}
		}

		$post_query->posts = array_values( array_diff_key( $post_query->posts, array_flip( $posts_to_remove ) ) );

		// If asked for, bring paging back now that we have the filtered result set. (But not if distance order is also
		// requested, because we need to leave the full result set for it to order on. Distance order will bring paging
		// back when it's finished ordering.)
		if ( ! $this->is_distance_order_requested( $view_settings ) ) {
			$post_query = $this->bring_paging_back( $post_query );
		}

		return $post_query;
	}

	/**
	 * Adds filters to do distance filtering in SQL, if requested.
	 *
	 * @since 1.8
	 *
	 * @param array $query
	 * @param array $view_settings
	 * @param int $view_id
	 *
	 * @return array
	 */
	public function filter_posts_in_sql( array $query, array $view_settings, $view_id ) {
		if ( ! $this->is_distance_filter_requested( $view_settings, $view_id ) ) {
			return $query;
		}

		$view_settings = $this->maybe_apply_missing_filter_settings_from_url( $view_settings );

		// Save filter settings, so they can be picked up by where filter
		$this->filter_settings = $view_settings['map_distance_filter'];

		// Add the filters
		add_filter( 'posts_join', [ $this, 'join_address_cache_to_post_query' ] );
		add_filter( 'posts_where', [ $this, 'filter_by_address_cache' ] );
		add_filter( 'posts_groupby', [ $this, 'groupby_post_id' ] );

		return $query;
	}

	/**
	 * If the address field is repeatable, join will select multiples of a post (for each repeated address), so we need
	 * to group posts back together by post ID.
	 *
	 * @since 1.8.3
	 *
	 * @param string $groupby
	 *
	 * @return string
	 */
	public function groupby_post_id( $groupby ) {
		remove_filter( 'posts_groupby', [ $this, 'groupby_post_id' ] );

		global $wpdb;

		$groupby = "{$wpdb->posts}.ID";

		return $groupby;
	}

	/**
	 * Adds the distance filtering SQL to WHERE clause.
	 *
	 * @since 1.8
	 *
	 * @param string $where
	 *
	 * @return string
	 */
	public function filter_by_address_cache( $where ) {
		global $wpdb;

		remove_filter( 'posts_where', [ $this, 'filter_by_address_cache' ] );

		$address_cache_table_name = $this->get_address_cache_table_name();
		$comparison_sign = $this->get_comparison_sign_for_filtering();

		if ( $this->can_use_sphere_distance_sql() ) {
			$lng_lat = $wpdb->_real_escape( $this->get_distance_center() );

			if ( $lng_lat ) {
				$distance = $wpdb->_real_escape( $this->get_distance_in_meters() );

				$where .= "\nAND ST_Distance_Sphere(ST_PointFromText('POINT($lng_lat)'), $address_cache_table_name.point) "
					. "$comparison_sign $distance\n";
			}
			return $where;
		}

		$lng_lat_coordinates = $this->get_distance_center_coordinates();

		if ( $lng_lat_coordinates ) {
			$modifier = 6371;
			if ( 'mi' === $this->filter_settings['map_distance_unit'] ) {
				$modifier = 3963;
			}

			$lng = $wpdb->_real_escape( $lng_lat_coordinates['lng'] );
			$lat = $wpdb->_real_escape( $lng_lat_coordinates['lat'] );

			$escaped_distance = $wpdb->_real_escape( $this->filter_settings['map_distance'] );

			$where .= "\nAND ( {$modifier} * 2 * ASIN("
				. "SQRT("
					. "POW( SIN( ( radians( ST_Y({$address_cache_table_name}.point) - {$lat} ) ) / 2 ), 2 ) "
					. "+ COS( radians( {$lat} ) ) * COS( radians( ST_Y({$address_cache_table_name}.point) ) ) "
					. "* POW( SIN( ( radians( ST_X({$address_cache_table_name}.point) - {$lng} ) ) / 2 ), 2 ) "
				. ")"
			. ") )"
			. "$comparison_sign $escaped_distance\n";
		}

		return $where;
	}

	/**
	 * Calculates distance in meters, as needed for comparison with ST_Distance_Sphere result.
	 *
	 * @since 1.8
	 *
	 * @return float|int
	 */
	private function get_distance_in_meters() {
		switch ( $this->filter_settings['map_distance_unit'] ) {
			case 'mi':
				return $this->filter_settings['map_distance'] * 1609.344;
			default:
				return $this->filter_settings['map_distance'] * 1000;
		}
	}

	/**
	 * Turns filter setting into the appropriate comparison sign
	 *
	 * @since 1.8
	 *
	 * @return string
	 */
	private function get_comparison_sign_for_filtering() {
		// Older Views don't have the setting, and they imply it's 'inside'
		if ( ! array_key_exists( 'map_distance_what_to_show', $this->filter_settings ) ) {
			return '<';
		}

		switch ( $this->filter_settings['map_distance_what_to_show'] ) {
			case 'outside':
				return '>';
			default:
				return '<';
		}
	}

	/**
	 * Gets a distance center in 'lon lat' string from whatever available source
	 *
	 * @since 1.8
	 *
	 * @return string
	 */
	private function get_distance_center() {
		switch ( $this->filter_settings['map_center_source'] ) {
			case 'user_location':
				$this->get_filter_settings_from_browser_geolocation();
				break;

			case 'url_param':
				$this->get_filter_settings_from_url();
				break;

			case 'shortcode_attr':
				$this->get_filter_settings_from_shortcode();
				break;
		}

		if (
			! empty( $this->filter_settings['map_center_lng'] )
			&& ! empty( $this->filter_settings['map_center_lat'] )
		) {
			return $this->filter_settings['map_center_lng'] . ' ' . $this->filter_settings['map_center_lat'];
		}

		if ( $this->filter_settings['map_distance_center'] ) {
			$coords = self::get_coords_array_from_input( $this->filter_settings['map_distance_center'] );
			if ( is_array( $coords ) ) {
				return $coords['lon'] . ' ' . $coords['lat'];
			}
		}

		return '';
	}
	/**
	 * Gets a distance center in 'lon lat' string from whatever available source
	 *
	 * @since 1.8
	 *
	 * @return string
	 */
	private function get_distance_center_coordinates() {
		switch ( $this->filter_settings['map_center_source'] ) {
			case 'user_location':
				$this->get_filter_settings_from_browser_geolocation();
				break;

			case 'url_param':
				$this->get_filter_settings_from_url();
				break;

			case 'shortcode_attr':
				$this->get_filter_settings_from_shortcode();
				break;
		}

		if (
			! empty( $this->filter_settings['map_center_lng'] )
			&& ! empty( $this->filter_settings['map_center_lat'] )
		) {
			return array(
				'lng' => $this->filter_settings['map_center_lng'],
				'lat' => $this->filter_settings['map_center_lat'],
			);
		}

		if ( $this->filter_settings['map_distance_center'] ) {
			$coords = self::get_coords_array_from_input( $this->filter_settings['map_distance_center'] );
			if ( is_array( $coords ) ) {
				return array(
					'lng' => $coords['lon'],
					'lat' => $coords['lat'],
				);
			}
		}

		return false;
	}

	/**
	 * Get distance center settings from view shortcode
	 *
	 * @since 1.8
	 */
	private function get_filter_settings_from_shortcode() {
		$shortcode_attrs = apply_filters( 'wpv_filter_wpv_get_view_shortcodes_attributes', array() );

		if (
			is_array( $shortcode_attrs )
			&& array_key_exists( $this->filter_settings['map_distance_center_shortcode_attr_name'], $shortcode_attrs )
		) {
			$coords_array = self::get_coords_array_from_input(
				$shortcode_attrs[ $this->filter_settings['map_distance_center_shortcode_attr_name'] ]
			);

			if ( self::is_valid_coords_array( $coords_array ) ) {
				$this->filter_settings['map_center_lat'] = $coords_array['lat'];
				$this->filter_settings['map_center_lng'] = $coords_array['lon'];
			}
		}
	}

	/**
	 * Gets distance center data from current visitor location provided by browser
	 *
	 * @since 1.8
	 */
	private function get_filter_settings_from_browser_geolocation() {
		$user_location = Toolset_Maps_Location_Factory::create_from_cookie();

		if ( $user_location ) {
			$this->filter_settings['map_center_lat'] = $user_location->get_lat();
			$this->filter_settings['map_center_lng'] = $user_location->get_lng();
		}
	}

	/**
	 * When a custom distance search is used, gets data from URL into filter settings.
	 *
	 * @since 1.8
	 */
	private function get_filter_settings_from_url() {
		if ( ! empty( $this->filter_settings['map_distance_radius_url_param_name'] ) ) {
			$this->filter_settings['map_distance'] = (float) toolset_getget(
				$this->filter_settings['map_distance_radius_url_param_name'],
				// If the distance setting is not available in URL, use the previously set distance, which comes from
				// default_distance shortcode parameter.
				$this->filter_settings['map_distance']
			);
		}

		if ( ! empty( $this->filter_settings['map_distance_unit_url_param_name'] ) ) {
			$this->filter_settings['map_distance_unit'] = sanitize_text_field(
				toolset_getget(
					$this->filter_settings['map_distance_unit_url_param_name'],
					self::$distance_filter_options['map_distance_unit']
				)
			);
		}

		if ( ! empty( $this->filter_settings['map_distance_center_url_param_name'] ) ) {
			$this->filter_settings['map_distance_center'] = sanitize_text_field(
				toolset_getget(
					$this->filter_settings['map_distance_center_url_param_name'],
					self::$distance_filter_options['map_distance_center']
				)
			);
		}
	}

	/**
	 * @param array $form_filters_shortcodes
	 *
	 * @return array
	 */
	static function add_distance_custom_search_filter( array $form_filters_shortcodes ) {
		if (
			self::get_saved_option( 'api_key' )
			&& ! self::is_archive_view()
		) {
			$form_filters_shortcodes['wpv-control-distance'] = array(
				'query_type_target'            => 'posts',
				'query_filter_define_callback' => array(
					'Toolset_Addon_Maps_Views_Distance_Filter',
					'query_filter_define_callback'
				),
				'custom_search_filter_group'   => 'Toolset Maps',
				'custom_search_filter_items'   => array(
					'maps_distance' => array(
						'name'    => __( 'Distance', 'toolset-maps' ),
						'present' => 'map_distance_filter',
						'params'  => array(
							'attributes' => array(
								'compare_field' => 'compare_field',
								'distance_center_url_param' => 'toolset_maps_distance_center',
								'distance_radius_url_param' => 'toolset_maps_distance_radius',
								'distance_unit_url_param' => 'toolset_maps_distance_unit',
							)
						)
					)
				)
			);
		}

		return $form_filters_shortcodes;
	}

	/**
	 * Checks if the view is an archive one (needed because distance filter doesn't work on archives)
	 * @return bool
	 */
	protected static function is_archive_view() {
		$view_id = toolset_getget( 'id' );
		$view_settings = apply_filters( 'wpv_filter_wpv_get_view_settings', array(), $view_id );

		return ( isset( $view_settings['view-query-mode'] ) && $view_settings['view-query-mode'] === 'archive' );
	}

	/**
	 * Callback to display the custom search filter by distance.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Filter form HTML
	 */
	public function shortcode_wpv_control_post_distance( $atts ) {
		if ( ! self::get_saved_option( 'api_key' ) ) {
			return '';
		}

		// Note that a shortcode without attributes will pass a string in $atts.
		// Usually shortcode_atts takes care of this but we call it too late.
		$atts = (array) $atts;

		// If a required att is missing, return early without content.
		$required_atts = array(
			'compare_field',
			'distance_center_url_param',
			'distance_radius_url_param',
			'distance_unit_url_param',
		);

		foreach ( $required_atts as $required_att ) {
			if ( ! array_key_exists( $required_att, $atts ) ) {
				return '';
			}
		}

		// Load resources JIT
		wp_enqueue_script( 'toolset-maps-views-filter-distance-frontend-js' );
		wp_enqueue_style( 'toolset-maps-views-filter-distance-frontend-css' );

		// Use defaults if atts are missing or empty
		$compare_fields = $this->get_comparison_address_fields();
		$default_compare_field = reset( $compare_fields );
		$atts = shortcode_atts( array(
			'default_distance' => self::$distance_filter_options['map_distance'],
			'default_unit' => self::$distance_filter_options['map_distance_unit'],
			'compare_field' => $default_compare_field,
			'distance_center_url_param' => self::DISTANCE_CENTER_DEFAULT_URL_PARAM,
			'distance_radius_url_param' => self::DISTANCE_RADIUS_DEFAULT_URL_PARAM,
			'distance_unit_url_param' => self::DISTANCE_UNIT_DEFAULT_URL_PARAM,
			'inputs_placeholder' => self::DISTANCE_FILTER_INPUTS_PLACEHOLDER,
			'visitor_location_button_text' => self::DISTANCE_FILTER_VISITOR_LOCATION_BUTTON_TEXT,
			'what_to_show' => self::$distance_filter_options['map_distance_what_to_show'],
			'distance_center_required' => self::$distance_filter_options['map_distance_center_required'],
		), $atts);

		$distance_radius_url_param = esc_attr( $atts['distance_radius_url_param'] );
		$distance_center_url_param = esc_attr( $atts['distance_center_url_param'] );
		$distance_unit_url_param = esc_attr( $atts['distance_unit_url_param'] );

		$defaults = array(
			$distance_radius_url_param => isset( $atts['default_distance'] )
				? (float) $atts['default_distance']
				: self::$distance_filter_options['map_distance'],
			$distance_center_url_param => '',
			$distance_unit_url_param => isset( $atts['default_unit'] ) ? $atts['default_unit'] : '',
		);

		if ( isset( $_GET[ $distance_radius_url_param ] ) ) {
			$defaults[ $distance_radius_url_param ] = (float) $_GET[ $distance_radius_url_param ];
		}

		if ( isset( $_GET[ $distance_center_url_param ] ) ) {
			$defaults[ $distance_center_url_param ] = sanitize_text_field( $_GET[ $distance_center_url_param ] );
		}

		if ( isset( $_GET[ $distance_unit_url_param ] ) ) {
			$defaults[ $distance_unit_url_param ] = sanitize_text_field( $_GET[ $distance_unit_url_param ] );
		}

		$defaults = wp_parse_args( $_GET, $defaults );

		$km_selected = selected( $defaults[ $distance_unit_url_param ], 'km', false );
		$mi_selected = selected( $defaults[ $distance_unit_url_param ], 'mi', false );

		$wpml_is_active_and_configured = new Toolset_Condition_Plugin_Wpml_Is_Active_And_Configured();

		// Use edited or translated string. And enable edited string to also be translated, if WPML is active.
		$inputs_placeholder_translated = empty( $atts['inputs_placeholder'] )
			? __( 'Show results within %%DISTANCE%% of %%CENTER%%', 'toolset-maps' )
			: (
				$wpml_is_active_and_configured->is_met()
				? wpv_translate(
					'Inputs placeholder: ' . $atts['inputs_placeholder'],
					$atts['inputs_placeholder'],
					true,
					'toolset-maps'
				)
				: $atts['inputs_placeholder']
			);
		$use_my_location_translated = empty( $atts['visitor_location_button_text'] )
			? __( 'Use my location', 'toolset-maps' )
			: (
				$wpml_is_active_and_configured->is_met()
				? wpv_translate(
					'Visitor location button text: ' . $atts['visitor_location_button_text'],
					$atts['visitor_location_button_text'],
					true,
					'toolset-maps'
				)
				: $atts['visitor_location_button_text']
			);

		// Is distance center input required?
		$distance_center_required = ( 'yes' === $atts['distance_center_required'] )
			? ' required'
			: '';

		// Text for aria-labels
		// translators: this is a location distance radius input field aria label
		$distance_radius_label = esc_attr( __( 'Distance radius', 'toolset-maps' ) );
		// translators: this is a location distance units select field aria label
		$distance_units_label = esc_attr( __( 'Kilometres or miles', 'toolset-maps' ) );
		// translators: this is a location input field placeholder text
		$enter_a_location_translated = esc_attr( __( 'Enter a location', 'toolset-maps' ) );

		$distance_input = <<<HTML
			<input type="number" min="0" id="toolset-maps-distance-value"
				name="$distance_radius_url_param"
				class="form-control js-toolset-maps-distance-value js-wpv-filter-trigger"
				value="{$defaults[ $distance_radius_url_param ]}"
				required
				aria-label="$distance_radius_label">
			<select class="js-toolset-maps-distance-unit form-control js-wpv-filter-trigger"
					name="$distance_unit_url_param"
					id="toolset-maps-distance"
					aria-label="$distance_units_label">
				<option value="km" $km_selected>km</option>
				<option value="mi" $mi_selected>mi</option>
			</select>
HTML;
		$center_input = <<<HTML
			<input type="text" id="toolset-maps-distance-center"
				name="$distance_center_url_param"
				class="form-control js-toolset-maps-distance-center js-wpv-filter-trigger-delayed js-toolset-maps-address-autocomplete"
				value="{$defaults[ $distance_center_url_param ]}"
				$distance_center_required
				placeholder="$enter_a_location_translated"
				aria-label="$enter_a_location_translated"
			>
HTML;

		if ( $this->is_frontend_served_over_https() ) {
			$center_input .= "<input type='button' class='btn js-toolset-maps-distance-current-location' "
				. "value='$use_my_location_translated' />";
		}

		$inputs_interpolated = str_replace(
			'%%DISTANCE%%', $distance_input, str_replace(
				'%%CENTER%%', $center_input, $inputs_placeholder_translated
			)
		);

		return '<div class="form-group">' . $inputs_interpolated . '</div>';
	}

	public function register_gui_data( $views_shortcodes ) {
		$views_shortcodes['wpv-control-distance'] = array(
			'callback' => array( $this, 'get_gui_data' )
		);

		return $views_shortcodes;
	}

	/**
	 * Usually we need just one option from the saved options array
	 * @param string $key
	 * @return mixed
	 */
	protected static function get_saved_option( $key ) {
		$saved_options = apply_filters( 'toolset_filter_toolset_maps_get_options', array() );

		if ( isset( $saved_options[$key] ) ) {
			return $saved_options[ $key ];
		} else {
			return null;
		}
	}

	private function get_comparison_address_fields() {
		$args           = array(
			'field_type' => array( 'google_address' ),
			'filter'     => 'types'
		);
		$address_fields = apply_filters( 'types_filter_query_field_definitions', array(), $args );

		$fields_array = array();

		foreach ( $address_fields['posts'] as $field ) {
			$fields_array[ $field['slug'] ] = stripslashes( $field['name'] );
		}

		return $fields_array;
	}

	static function query_filter_define_callback( $view_id, $shortcode, $attributes = array(), $attributes_raw = array() ) {
		if ( ! isset( $attributes['distance_center_url_param'] ) || ! isset( $attributes['distance_radius_url_param'] ) || ! isset( $attributes['distance_unit_url_param'] ) ) {
			return;
		}

		$view_array = get_post_meta( $view_id, '_wpv_settings', true );

		$distance_options = array(
			'map_distance'                            => ( isset( $attributes['default_distance'] ) ? $attributes['default_distance'] : 0 ),
			'map_distance_center'                     => '',
			'map_distance_unit'                       => ( isset( $attributes['default_unit'] ) ? $attributes['default_unit'] : 0 ),
			'map_center_lat'                          => '',
			'map_center_lng'                          => '',
			'map_center_source'                       => 'url_param',
			'map_distance_center_url_param_name'      => $attributes['distance_center_url_param'],
			'map_distance_radius_url_param_name'      => $attributes['distance_radius_url_param'],
			'map_distance_unit_url_param_name'        => $attributes['distance_unit_url_param'],
			'map_distance_center_shortcode_attr_name' => 'mapcenter',
			'map_distance_compare_field'              => $attributes['compare_field'],
			'map_distance_what_to_show'               => ( isset( $attributes['what_to_show'] ) ? $attributes['what_to_show'] : self::$distance_filter_options['map_distance_what_to_show'] ),
		);

		$view_array['map_distance_filter'] = $distance_options;

		update_post_meta( $view_id, '_wpv_settings', $view_array );

		do_action( 'wpv_action_wpv_save_item', $view_id );
	}

	public function get_gui_data( $parameters = array(), $overrides = array() ) {
		$address_fields = $this->get_comparison_address_fields();
		$address_fields_slugs = array_keys( $address_fields );
		$data = array(
			'attributes' => array(
				'display-options' => array(
					'label'  => __( 'Display options', 'toolset-maps' ),
					'header' => 'Distance filter will allow users to search posts using map distance',
					'fields' => array(
						'default_distance'          => array(
							'label'   => __( 'Default filter radius', 'toolset-maps' ),
							'type'    => 'text',
							'default' => self::$distance_filter_options['map_distance'],
						),
						'default_unit'              => array(
							'label'   => __( 'Distance radius unit', 'toolset-maps' ),
							'type'    => 'radio',
							'options' => array(
								'km' => 'km',
								'mi' => 'mi'
							),
							'default' => self::$distance_filter_options['map_distance_unit'],
						),
						'distance_center_required'  => array(
							'label'     => __( 'Should distance center input be required', 'toolset-maps' ),
							'type'      => 'radio',
							'options'   => array(
								'no'    => __( 'No (use when combining this filter with others)', 'toolset-maps' ),
								'yes'   => __( 'Yes (UI optimization when using only this filter)', 'toolset-maps' ),
							),
							'default'   => self::$distance_filter_options['map_distance_center_required'],
						),
						'what_to_show' => array(
							'label'     => __( 'What to show', 'toolset-maps' ),
							'type'      => 'radio',
							'options'   => array(
								'outside' => __( 'Outside of radius', 'toolset-maps' ),
								'inside'  => __( 'Inside of radius', 'toolset-maps' )
							),
							'default'   => self::$distance_filter_options['map_distance_what_to_show']
						),
						'compare_field'             => array(
							'label'         => __( 'Comparison Field', 'toolset-maps' ),
							'type'          => 'select',
							'options'       => $address_fields,
							'default_force' => count( $address_fields_slugs ) > 0 ? $address_fields_slugs[0] : '',
							'required'      => true
						),
						'distance_center_url_param' => array(
							'label'         => __( 'Distance Center URL parameter to use', 'toolset-maps' ),
							'type'          => 'text',
							'default_force' => self::DISTANCE_CENTER_DEFAULT_URL_PARAM,
							'required'      => true
						),
						'distance_radius_url_param' => array(
							'label'         => __( 'Distance radius URL parameter to use', 'toolset-maps' ),
							'type'          => 'text',
							'default_force' => self::DISTANCE_RADIUS_DEFAULT_URL_PARAM,
							'required'      => true
						),
						'distance_unit_url_param'   => array(
							'label'         => __( 'Distance unit URL parameter to use', 'toolset-maps' ),
							'type'          => 'text',
							'default_force' => self::DISTANCE_UNIT_DEFAULT_URL_PARAM,
							'required'      => true
						),
						'inputs_placeholder'  => array (
							'label'     => __( 'Text and placeholders for input fields', 'toolset-maps' ),
							'type'      => 'text',
							'default'   => self::DISTANCE_FILTER_INPUTS_PLACEHOLDER
						)
					)
				),
			),
		);

		if ($this->is_frontend_served_over_https() ) {
			$data['attributes']['display-options']['fields']['visitor_location_button_text'] = array (
				'label'     => __( 'Text for visitor location button', 'toolset-maps' ),
				'type'      => 'text',
				'default'   => self::DISTANCE_FILTER_VISITOR_LOCATION_BUTTON_TEXT
			);
		}

		$dialog_label = __( 'Distance filter', 'toolset-maps' );

		$data['name']  = $dialog_label;
		$data['label'] = $dialog_label;

		return $data;
	}

	/**
	 * Under assumption that site settings are not wrong, answers if frontend is served over https
	 * @todo: move to toolset-common
	 * @return bool
	 */
	public function is_frontend_served_over_https(){
		return self::is_frontend_served_over_https_static();
	}

	/**
	 * Static version of the same function, just so it can be called from other static functions...
	 *
	 * @since 1.7.2
	 * @return bool
	 */
	public static function is_frontend_served_over_https_static() {
		return ( parse_url( get_home_url(), PHP_URL_SCHEME ) === 'https' );
	}
}

$Toolset_Addon_Maps_Views_Distance_Filter = new Toolset_Addon_Maps_Views_Distance_Filter();
