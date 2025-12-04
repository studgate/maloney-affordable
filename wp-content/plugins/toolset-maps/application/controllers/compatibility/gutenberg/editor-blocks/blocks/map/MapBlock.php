<?php
namespace OTGS\Toolset\Maps\Controller\Compatibility\Gutenberg\EditorBlocks\Blocks\Map;

use Toolset\DynamicSources\PostProviders\CustomPost;
use Toolset_Addon_Maps_Common;
use Toolset_Addon_Maps_Views;
use Toolset_Assets_Manager;
use WP_Post;
use OTGS\Toolset\Maps\Controller\Compatibility\Gutenberg\EditorBlocks\MapsEditorBlocks;

class MapBlock extends \Toolset_Gutenberg_Block {

	const BLOCK_NAME = 'toolset/map';

	protected $enqueue_marker_clusterer_script;

	/**
	 * Block initialization.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// These need to happen one after another, and all after initializing DS API
		add_action( 'init', array( $this, 'register_block_editor_assets' ), 20 );
		add_action( 'init', array( $this, 'register_block_type' ), 30 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );

		add_filter( 'wpv_filter_view_loop_item_output', array( $this, 'render_marker_from_view' ), 10, 4 );
	}

	/**
	 * Block editor asset registration.
	 *
	 * @return void
	 */
	public function register_block_editor_assets() {
		$editor_script_dependencies = array(
			'wp-editor',
			'lodash',
			'jquery',
			'views-addon-maps-script',
			'wp-blocks',
			'wp-i18n',
			'wp-element',
			'wp-components',
			'wp-compose',
			'wp-data',
			// Using literal instead of Toolset\DynamicSources\DynamicSources::TOOLSET_DYNAMIC_SOURCES_SCRIPT_HANDLE
			// because that would be the only dependency on that class, and in some circumstances WP will needlessly
			// call this without calling our block factory where the autoloader for DS is registered.
			'toolset_dynamic_sources_script',
			'toolset-common-es',
			Toolset_Assets_Manager::SCRIPT_CODEMIRROR,
		);
		$api_used = apply_filters( 'toolset_maps_get_api_used', '' );

		$this->toolset_assets_manager->register_script(
			'toolset-map-block-js',
			TOOLSET_ADDON_MAPS_URL . MapsEditorBlocks::TOOLSET_MAPS_BLOCKS_ASSETS_RELATIVE_PATH . '/js/map.block.editor.js',
			$editor_script_dependencies,
			TOOLSET_ADDON_MAPS_VERSION
		);

		wp_localize_script(
			'toolset-map-block-js',
			'toolset_map_block_strings',
			array(
				'blockName' => self::BLOCK_NAME,
				'blockCategory' => \Toolset_Blocks::TOOLSET_GUTENBERG_BLOCKS_CATEGORY_SLUG,
				'mapCounter' => $this->get_map_counter(),
				'markerCounter' => $this->get_marker_counter(),
				'api' => $api_used,
				'apiKey' => $this->is_the_right_api_key_entered(),
				'settingsLink' => Toolset_Addon_Maps_Common::get_settings_link(),
				'themeColors' => get_theme_support( 'editor-color-palette' ),
				'mapDefaultSettings' => Toolset_Addon_Maps_Common::$map_defaults,
				'mapStyleOptions' => Toolset_Addon_Maps_Common::get_style_options(),
				'markerOptions' => apply_filters( 'toolset_maps_views_get_marker_options', array() ),
				'isFrontendServerOverHttps' => $this->is_frontend_served_over_https(),
				'addressFields' => $this->get_address_fields(),
				'assetsURL' => TOOLSET_ADDON_MAPS_URL,
			)
		);

		$this->toolset_assets_manager->register_style(
			'toolset-map-block-editor-css',
			TOOLSET_ADDON_MAPS_URL . MapsEditorBlocks::TOOLSET_MAPS_BLOCKS_ASSETS_RELATIVE_PATH . '/css/map.block.editor.css',
			array( 'toolset-common-es' ),
			TOOLSET_ADDON_MAPS_VERSION
		);
	}

	/**
	 * We need to do this separately and later than register_block_type would do in order to be compatible with DS API.
	 *
	 * If not done like this, DS API, if loaded from Views and not Maps, would load too late for Maps block.
	 */
	public function enqueue_block_editor_assets() {
		wp_enqueue_script( 'toolset-map-block-js' );
		wp_enqueue_style( 'toolset-map-block-editor-css' );
	}

	/**
	 * Server side block registration.
	 *
	 * @return void
	 */
	public function register_block_type() {
		register_block_type(
			self::BLOCK_NAME,
			array(
				'attributes' => array(
					'mapId' => array(
						'type' => 'string',
						'default' => '',
					),
					'mapWidth' => array(
						'type' => 'string',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['map_width'],
					),
					'mapHeight' => array(
						'type' => 'string',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['map_height'],
					),
					'mapZoomAutomatic' => array(
						'type' => 'boolean',
						'default' => true,
					),
					'mapZoomLevelForMultipleMarkers' => array(
						'type' => 'integer',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['general_zoom'],
					),
					'mapZoomLevelForSingleMarker' => array(
						'type' => 'integer',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['single_zoom'],
					),
					'mapCenterLat' => array(
						'type' => 'number',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['general_center_lat'],
					),
					'mapCenterLon' => array(
						'type' => 'number',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['general_center_lon'],
					),
					'mapForceCenterSettingForSingleMarker' => array(
						'type' => 'boolean',
						'default' => false,
					),
					'mapMarkerClustering' => array(
						'type' => 'boolean',
						'default' => false,
					),
					'mapMarkerClusteringMinimalNumber' => array(
						'type' => 'integer',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['cluster_min_size'],
					),
					'mapMarkerClusteringMinimalDistance' => array(
						'type' => 'integer',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['cluster_grid_size'],
					),
					'mapMarkerClusteringMaximalZoomLevel' => array(
						'type' => 'integer',
						'default' => 14,
					),
					'mapMarkerClusteringClickZoom' => array(
						'type' => 'boolean',
						'default' => true,
					),
					'mapMarkerSpiderfying' => array(
						'type' => 'boolean',
						'default' => false,
					),
					'mapDraggable' => array(
						'type' => 'boolean',
						'default' => true,
					),
					'mapScrollable' => array(
						'type' => 'boolean',
						'default' => true,
					),
					'mapDoubleClickZoom' => array(
						'type' => 'boolean',
						'default' => true,
					),
                    'osmTileLayer' => array(
                        'type' => 'string',
                        'default' => Toolset_Addon_Maps_Common::$map_defaults['osm_layer'],
                    ),
					'mapType' => array(
						'type' => 'string',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['map_type'],
					),
					'mapTypeControl' => array(
						'type' => 'boolean',
						'default' => true,
					),
					'mapZoomControls' => array(
						'type' => 'boolean',
						'default' => true,
					),
					'mapStreetViewControl' => array(
						'type' => 'boolean',
						'default' => true,
					),
					'mapFullScreenControl' => array(
						'type' => 'boolean',
						'default' => true,
					),
					'mapBackgroundColor' => array(
						'type' => 'string',
						'default' => '',
					),
					'mapStyle' => array(
						'type' => 'string',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['style_json'],
					),
					'mapLoadingText' => array(
						'type' => 'string',
						'default' => '',
					),
					'mapMarkerIcon' => array(
						'type' => 'string',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['marker_icon'],
					),
					'mapMarkerIconUseDifferentForHover' => array(
						'type' => 'boolean',
						'default' => false,
					),
					'mapMarkerIconHover' => array(
						'type' => 'string',
						'default' => Toolset_Addon_Maps_Common::$map_defaults['marker_icon_hover'],
					),
					'mapStreetView' => array(
						'type' => 'boolean',
						'default' => false,
					),
					// There is array type, but then Gutenberg goes crazy validating. Instead, we have to serialize
					// arrays ourselves, and everything needs to be of type string...
					'markerId' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( '' ) ),
					),
					'markerAddress' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( '' ) ),
					),
					'markerSource' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( 'address' ) ),
					),
					'currentVisitorLocationRenderTime' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( 'immediate' ) ),
					),
					'markerLat' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( '' ) ),
					),
					'markerLon' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( '' ) ),
					),
					'markerTitle' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( '' ) ),
					),
					'popupContent' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( '' ) ),
					),
					'markerUseMapIcon' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( true ) ),
					),
					'markerIcon' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( '' ) ),
					),
					'markerIconUseDifferentForHover' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( false ) ),
					),
					'markerIconHover' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( '' ) ),
					),
					'markerDynamicAddress' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( new \stdClass() ) ),
					),
					'markerView' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( '' ) ),
					),
					'markerPreviewView' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( '' ) ),
					),
					'markerViewField' => array(
						'type' => 'string',
						'default' => wp_json_encode( array( '' ) ),
					),
					'align' => array(
						'type' => 'string',
					),
				),
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * @param boolean $cluster
	 * @param boolean $spiderfy
	 */
	private function maybe_enqueue_map_rendering_scripts( $cluster, $spiderfy ) {
		if ( ! wp_script_is( 'views-addon-maps-script' ) ) {
			wp_enqueue_script( 'views-addon-maps-script' );
			Toolset_Addon_Maps_Common::maybe_enqueue_azure_css();
		}

		if ( $cluster ) {
			$this->enqueue_marker_clusterer_script = true;
			if ( ! wp_script_is( 'marker-clusterer-script' ) ) {
				wp_enqueue_script( 'marker-clusterer-script' );
			}
		}

		if ( $spiderfy ) {
			if ( ! wp_script_is( 'overlapping-marker-spiderfier' ) ) {
				wp_enqueue_script( 'overlapping-marker-spiderfier' );
			}
		}
	}

	/**
	 * In a View with content template containing a map, the same map id repeats multiple times. This makes ids unique.
	 *
	 * @since 1.7.3
	 *
	 * @param string $map_id
	 *
	 * @return string Unique map id, if the same one repeats on this page.
	 */
	private function get_unique_map_id( $map_id ) {
		$used_map_ids = Toolset_Addon_Maps_Common::$used_map_ids;
		$map_id_corrected = $map_id;
		$loop_counter = 0;
		while ( in_array( $map_id_corrected, $used_map_ids, true ) ) {
			$loop_counter++;
			$map_id_corrected = $map_id . '-' . $loop_counter;
		}

		if ( $map_id_corrected !== $map_id ) {
			$this->keep_corrected_map_id( $map_id, $map_id_corrected );
		}

		return $map_id_corrected;
	}

	/**
	 * When a map id is corrected (unique), keep association with old id, so it can be picked up by markers.
	 *
	 * This is kept for compatibility with maps & markers inserted through shortcodes.
	 *
	 * @since 1.7.3
	 *
	 * @param string $map_id
	 * @param string $map_id_corrected
	 */
	private function keep_corrected_map_id( $map_id, $map_id_corrected ) {
		Toolset_Addon_Maps_Views::$corrected_map_ids[ $map_id ] = $map_id_corrected;
	}

	/**
	 * @since 2.0.1
	 *
	 * @param array $attributes
	 * @param string $attributes_key
	 * @param string $defaults_key
	 *
	 * @return string
	 */
	private function convert_bool_attribute_to_on_off( array $attributes, $attributes_key, $defaults_key ) {
		if ( array_key_exists( $attributes_key, $attributes ) ) {
			return $attributes['mapFullScreenControl'] ? 'on' : 'off';
		}
		return Toolset_Addon_Maps_Common::$map_defaults[ $defaults_key ];
	}

	/**
	 * Renders map & marker attributes to HTML, loads maps rendering JS if needed.
	 *
	 * @param array $attributes Contains attributes + added shortcodes which are the only thing used.
	 * @param string $content Previous version of rendered HTML. Unused.
	 *
	 * @return string
	 */
	public function render( array $attributes, $content ) {
		$this->maybe_enqueue_map_rendering_scripts(
			$attributes['mapMarkerClustering'],
			$attributes['mapMarkerSpiderfying']
		);
		$map_id = $this->get_unique_map_id( $attributes['mapId'] );
		$output = Toolset_Addon_Maps_Common::render_map(
			$map_id,
			array(
				'map_width'            => $attributes['mapWidth'],
				'map_height'           => $attributes['mapHeight'],
				'general_zoom'         => $attributes['mapZoomLevelForMultipleMarkers'],
				'general_center_lat'   => $attributes['mapCenterLat'],
				'general_center_lon'   => $attributes['mapCenterLon'],
				'fitbounds'            => $attributes['mapZoomAutomatic'] ? 'on' : 'off',
				'single_zoom'          => $attributes['mapZoomLevelForSingleMarker'],
				'single_center'        => $attributes['mapForceCenterSettingForSingleMarker'] ? 'off' : 'on',
                'osm_layer'            => $attributes['osmTileLayer'],
				'map_type'             => $attributes['mapType'],
				'show_layer_interests' => Toolset_Addon_Maps_Common::$map_defaults['show_layer_interests'],
				'marker_icon'          => $attributes['mapMarkerIcon'],
				'marker_icon_hover'    => $attributes['mapMarkerIconHover'],
				'draggable'            => $attributes['mapDraggable'] ? 'on' : 'off',
				'scrollwheel'          => $attributes['mapScrollable'] ? 'on' : 'off',
				'double_click_zoom'    => $attributes['mapDoubleClickZoom'] ? 'on' : 'off',
				'map_type_control'     => $attributes['mapTypeControl'] ? 'on' : 'off',
				'full_screen_control'  => $this->convert_bool_attribute_to_on_off(
					$attributes,
					'mapFullScreenControl',
					'full_screen_control'
				),
				'zoom_control'         => $attributes['mapZoomControls'] ? 'on' : 'off',
				'street_view_control'  => $attributes['mapStreetViewControl'] ? 'on' : 'off',
				'background_color'     => $attributes['mapBackgroundColor'],
				'cluster'              => $attributes['mapMarkerClustering'] ? 'on' : 'off',
				'cluster_grid_size'    => $attributes['mapMarkerClusteringMinimalDistance'],
				'cluster_max_zoom'     => $attributes['mapMarkerClusteringMaximalZoomLevel'],
				'cluster_click_zoom'   => $attributes['mapMarkerClusteringClickZoom'],
				'cluster_min_size'     => $attributes['mapMarkerClusteringMinimalNumber'],
				'style_json'           => $attributes['mapStyle'],
				'spiderfy'             => $attributes['mapMarkerSpiderfying'] ? 'on' : 'off',
				'street_view'          => $attributes['mapStreetView'] ? 'on' : 'off',
				'marker_id'            => Toolset_Addon_Maps_Common::$map_defaults['marker_id'],
				'location'             => $attributes['mapStreetView'] ?
					'first' :
					Toolset_Addon_Maps_Common::$map_defaults['location'],
				'address'              => Toolset_Addon_Maps_Common::$map_defaults['address'],
				'heading'              => Toolset_Addon_Maps_Common::$map_defaults['heading'],
				'pitch'                => Toolset_Addon_Maps_Common::$map_defaults['pitch'],
				'align'                => isset( $attributes['align'] ) ? $attributes['align'] : null,
			),
			$attributes['mapLoadingText']
		);

		foreach ( $this->get_marker_attribute_array( $attributes ) as $marker ) {
			if (
				'address' === $marker['markerSource'] &&
				$marker['markerAddress']
			) {
				$output .= $this->render_marker_from_address( $map_id, $marker, $marker['markerAddress'] );
			} elseif ( 'browser_geolocation' === $marker['markerSource'] ) {
				// Special case when we need to get coordinates from browser - collect_map_data method will recognize
				// and process it.
				$output .= $this->render_marker(
					$map_id,
					$marker,
					'geo',
					$marker['currentVisitorLocationRenderTime']
				);
			} elseif ( 'dynamic' === $marker['markerSource'] ) {
				// Don't try to render marker if actual dynamic address provider is not selected yet.
				if ( ! (
					property_exists( $marker['markerDynamicAddress'], 'postProvider' )
					|| property_exists( $marker['markerDynamicAddress'], 'provider' )
				) ) {
					continue;
				}

				// Register custom post providers, if needed
				add_filter(
					'toolset/dynamic_sources/filters/register_post_providers',
					function( $providers ) use ( $marker ) {
						if (
							isset( $marker['markerDynamicAddress']->customPost ) &&
							! array_key_exists( $marker['markerDynamicAddress']->customPost->value, $providers )
						) {
							$custom_post_data = explode( '|', $marker['markerDynamicAddress']->customPost->value );
							array_push( $providers, new CustomPost( $custom_post_data[1], $custom_post_data[2] ) );
						}
						return $providers;
					},
					10000 // This should be late enough that other filters that might have added it already ran.
				);
				// Register sources, if needed
				if ( ! did_action( 'toolset/dynamic_sources/actions/register_sources' ) ) {
					do_action( 'toolset/dynamic_sources/actions/register_sources' );
				}
				$address = apply_filters(
					'toolset/dynamic_sources/filters/get_source_content',
					'',
					property_exists( $marker['markerDynamicAddress'], 'postProvider' )
						? $marker['markerDynamicAddress']->postProvider
						: $marker['markerDynamicAddress']->provider, // Compatibility with old saves
					get_the_ID(),
					$marker['markerDynamicAddress']->source,
					$marker['markerDynamicAddress']->field
				);

				// Multiple field instances
				if ( is_array( $address ) ) {
					$output .= $this->render_markers_from_multiple_addresses( $map_id, $marker, $address );
				} else { // Single address
					$output .= $this->render_marker_from_address( $map_id, $marker, $address );
				}
			} elseif ( 'view' === $marker['markerSource'] ) {
				// If there's a preview View (new View made in a new View block), and we are in Gutenberg (checked by
				// REST_REQUEST), use preview View instead of the regular one (as it's more current, and the regular one
				// might not be published yet anyway). Also note that this preview View is in 'draft' status.
				if (
					defined( 'REST_REQUEST' )
					&& REST_REQUEST
					&& ! empty( $marker['markerPreviewView'] )
				) {
					$view_query_results = get_view_query_results(
						$marker['markerPreviewView'],
						null,
						null,
						[],
						'draft',
						true
					);
				} else {
					$view_query_results = get_view_query_results(
						$marker['markerView'],
						null,
						null,
						[]
					);
				}

				// Save current $post, $authordata, $id.
				global $post, $authordata, $id;
				$tmp_post = ( isset( $post ) && $post instanceof WP_Post ) ? clone $post : null;
				$tmp_authordata = ( isset( $authordata ) && is_object( $authordata ) ) ? clone $authordata : null;
				$tmp_id = $id;

				foreach ( $view_query_results as $key => $post_in_loop ) {
					// Switch $post, $authordata, $id to the one in loop, so do_shortcode() operates on the right ones
					$post = $post_in_loop;
					$authordata = new \WP_User( $post->post_author );
					$id = $post->ID;

					$output .= $this->render_markers_from_multiple_addresses(
						$map_id,
						$this->add_key_to_multiple_marker_id( $key, $marker ),
						get_post_meta( $post->ID, $marker['markerViewField'] )
					);
				}
				// Restore current $post, $authordata, $id
				$post = ( isset( $tmp_post ) && ( $tmp_post instanceof WP_Post ) ) ? clone $tmp_post : null;
				$authordata = ( isset( $tmp_authordata ) && is_object( $tmp_authordata ) ) ? clone $tmp_authordata : null;
				$id = $tmp_id;
			} elseif ( 'latlon' === $marker['markerSource'] ) { // When lat/lng given as numbers
				$output .= $this->render_marker( $map_id, $marker );
			}
		}

		return $output;
	}

	/**
	 * Make unique IDs for markers with multiple addresses (multiple field instances and/or markers coming from views).
	 *
	 * @since 2.0
	 *
	 * @param int $key
	 * @param array $marker
	 *
	 * @return array
	 */
	private function add_key_to_multiple_marker_id( $key, array $marker ) {
		if ( $key ) {
			$marker['markerId'] = $marker['markerId'] . '-' . $key;
		}
		return $marker;
	}

	/**
	 * @since 2.0
	 *
	 * @param string $map_id
	 * @param array $marker
	 * @param array $addresses
	 *
	 * @return string
	 */
	private function render_markers_from_multiple_addresses( $map_id, $marker, $addresses ) {
		$output = '';

		foreach ( $addresses as $key => $address ) {
			$output .= $this->render_marker_from_address(
				$map_id,
				$this->add_key_to_multiple_marker_id( $key, $marker ),
				$address
			);
		}

		return $output;
	}

	/**
	 * @param string $map_id
	 * @param array $marker
	 * @param null|string|float $lat
	 * @param null|string|float $lon
	 *
	 * @return string
	 */
	private function render_marker( $map_id, array $marker, $lat = null, $lon = null ) {
		return Toolset_Addon_Maps_Common::render_marker(
			$map_id,
			array(
				'id'			=> $marker['markerId'],
				'title'			=> array_key_exists( 'markerTitle', $marker )
					? $this->expand_shortcode( $marker['markerTitle'] )
					: '',
				'lat'			=> $lat ?: $marker['markerLat'],
				'lon'			=> $lon ?: $marker['markerLon'],
				'icon'			=> array_key_exists( 'markerIcon', $marker ) ? $marker['markerIcon'] : '',
				'icon_hover'	=> array_key_exists( 'markerIconHover', $marker ) ? $marker['markerIconHover'] : '',
				'from_view'     => array_key_exists( 'markerView', $marker ) ? $marker['markerView'] : '',
			),
			array_key_exists( 'popupContent', $marker ) ?
				$this->expand_shortcode( $marker['popupContent'] ) :
				''
		);
	}

	/**
	 * We don't want shortcodes expanded too early (outside of View loop), so we do this on demand using this method.
	 *
	 * @since 2.0
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	private function expand_shortcode( $content ) {
		return do_shortcode( str_replace( '{{', '[', str_replace( '}}', ']', $content ) ) );
	}

	/**
	 * @param string $map_id
	 * @param array $marker
	 * @param string $address
	 *
	 * @return string
	 */
	private function render_marker_from_address( $map_id, array $marker, $address ) {
		$address_data = Toolset_Addon_Maps_Common::get_coordinates( $address );
		if ( is_array( $address_data ) ) {
			return $this->render_marker( $map_id, $marker, $address_data['lat'], $address_data['lon'] );
		}
		return '';
	}

	/**
	 * This gets called from a filter in Views.
	 *
	 * If there is ajax paging or filtering on a View, we'll render the markers together with View output, and Maps JS
	 * will take care of cleaning of old markers that were rendered with map and refreshing map.
	 *
	 * @param string $out
	 * @param int $i Loop counter.
	 * @param object $item
	 * @param array $view_settings
	 *
	 * @return string
	 *
	 * @since 2.0
	 */
	public function render_marker_from_view( $out, $i, $item, array $view_settings ) {
		if ( ! $item instanceof WP_Post ) {
			return $out;
		}

		if ( array_key_exists( 'extra', $_POST ) ) {
			$extra = toolset_getpost( 'extra' );
		} else if ( array_key_exists( 'environment', $_POST ) ) {
			$extra = toolset_getpost( 'environment' );
		} else {
			return $out;
		}

		$view_id = $view_settings['view_id'];
		$page_id = $this->get_page_id( $extra );
		$attributes = $this->get_block_attributes_for_markers_coming_from_view( $view_id, $page_id );

		// If we couldn't find a Map block connected to this View, nothing to do.
		if ( empty( $attributes ) ) {
			return $out;
		}

		$map_id = $attributes['mapId'];

		foreach ( $this->get_marker_attribute_array( $attributes ) as $marker ) {
			if (
				'view' === $marker['markerSource']
				&& $marker['markerView'] == $view_id
			) {
				foreach ( get_post_meta( $item->ID, $marker['markerViewField'] ) as $single_address ) {
					$out .= $this->render_marker_from_address(
						$map_id,
						$this->add_key_to_multiple_marker_id( $i, $marker ),
						$single_address
					);
				}
			}
		}

		return $out;
	}

	/**
	 * That page id can be hidden under a number of different keys in this given array, so search for any of them.
	 *
	 * @since 2.0
	 *
	 * @param array $extra
	 *
	 * @return int|null
	 */
	private function get_page_id( array $extra ) {
		foreach ( [ 'page_id', 'p', 'wpv_aux_current_post_id', 'preview_id' ] as $possible_key ) {
			if ( array_key_exists( $possible_key, $extra ) ) {
				return $extra[ $possible_key ];
			}
		}
		return null;
	}

	/**
	 * Gets Map block's attributes if it has markers coming from given view. Empty array otherwise.
	 *
	 * @since 2.0
	 *
	 * @param int $view_id
	 * @param int $page_id
	 *
	 * @return array
	 */
	private function get_block_attributes_for_markers_coming_from_view( $view_id, $page_id ) {
		static $cache = [];

		if ( array_key_exists( $view_id, $cache ) ) {
			return $cache[ $view_id ];
		}

		$page = get_post( $page_id );

		// Are there any blocks on page?
		if ( has_blocks( $page->post_content ) ) {
			$blocks = parse_blocks( $page->post_content );
			$flat_list_of_blocks = $this->get_flat_list_of_blocks( $blocks );

			// Is there a Maps block?
			foreach ( $flat_list_of_blocks as $block ) {
				if (
					'toolset/map' === $block['blockName']
				) {
					$attributes = $block['attrs'];

					// Does this block have a marker source coming from given View?
					foreach( $this->get_marker_attribute_array( $attributes ) as $marker ) {
						if (
							'view' === $marker['markerSource']
							&& $marker['markerView'] == $view_id
						) {
							$cache[ $view_id ] = $attributes;
							return $attributes;
						}
					}
				}
			}
		}
		$cache[ $view_id ] = [];
		return [];
	}

	/**
	 * Flattens the given list of blocks. Useful when searching for a specific type of block, no matter how deeply
	 * nested it is.
	 *
	 * @since 2.0.1
	 *
	 * @param array $blocks
	 *
	 * @return array
	 */
	private function get_flat_list_of_blocks( array $blocks ) {
		static $flat_blocks = [];

		foreach ( $blocks as $block ) {
			$flat_blocks[] = $block;
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->get_flat_list_of_blocks( $block['innerBlocks'] );
			}
		}
		return $flat_blocks;
	}

	/**
	 * JSON decodes and flattens marker attributes to a nice array
	 *
	 * @param array $attributes
	 *
	 * @return array
	 */
	private function get_marker_attribute_array( array $attributes ) {
		$marker_attributes = array(
			'markerId',
			'markerAddress',
			'markerSource',
			'currentVisitorLocationRenderTime',
			'markerLat',
			'markerLon',
			'markerTitle',
			'popupContent',
			'markerUseMapIcon',
			'markerIcon',
			'markerIconUseDifferentForHover',
			'markerIconHover',
			'markerDynamicAddress',
			'markerView',
			'markerPreviewView',
			'markerViewField',
		);

		$markers_decoded = array();
		foreach ( $marker_attributes as $attribute ) {
			if ( isset( $attributes[ $attribute ] ) ) {
				// For some unknown reason (probably some content filter somewhere), JSON data sometimes has some
				// characters turned into bad unicode representation, which then can't be properly decoded back, but
				// this simple replace helps mitigate it.
				$fixed_broken_encoding = str_replace(
					[ 'u0022', 'u003c', 'u003e' ],
					[ '"', '<', '>' ],
					$attributes[ $attribute ]
				);
				$markers_decoded[ $attribute ] = json_decode( $fixed_broken_encoding );

				// Mitigate if something went wrong decoding JSON.
				if ( null === $markers_decoded[ $attribute ] ) {
					$markers_decoded[ $attribute ] = [];
				}
			}
		}

		$markers = array();
		foreach ( $markers_decoded as $attribute => $values ) {
			foreach ( $values as $key => $value ) {
				$markers[ $key ][ $attribute ] = $value;
			}
		}

		return $markers;
	}

	/**
	 * @param string $option
	 *
	 * @return mixed
	 */
	private function get_saved_option( $option ) {
		$saved_options = apply_filters( 'toolset_filter_toolset_maps_get_options', array() );

		return $saved_options[$option];
	}

	/**
	 * @return int
	 */
	private function get_map_counter() {
		return $this->get_saved_option( 'map_counter' );
	}

	/**
	 * @return int
	 */
	private function get_marker_counter() {
		return $this->get_saved_option( 'marker_counter' );
	}

	/**
	 * Multi-API aware check for API keys.
     *
	 * @return bool
	 */
	private function is_the_right_api_key_entered() {
		$api_used = apply_filters( 'toolset_maps_get_api_used', '' );

		if ( Toolset_Addon_Maps_Common::API_GOOGLE === $api_used ) {
            // Google Maps
			$key = apply_filters( 'toolset_filter_toolset_maps_get_api_key', '' );
            return ! empty( $key );

        } elseif ( Toolset_Addon_Maps_Common::API_AZURE === $api_used ) {
            // Azure
            $key = apply_filters( 'toolset_filter_toolset_maps_get_azure_api_key', '' );
            return ! empty( $key );

		} else {
            // Open Street Maps - no key needed
            return true;
		}
	}

	/**
	 * Under assumption that site settings are not wrong, answers if frontend is served over https
	 *
	 * @return bool
	 */
	private function is_frontend_served_over_https(){
		return ( wp_parse_url( get_home_url(), PHP_URL_SCHEME ) === 'https' );
	}

	/**
	 * Gives all postmeta fields of type address as meta_key => name
	 *
	 * @since 2.0
	 * @return array
	 */
	private function get_address_fields() {
		$fields = [];
		foreach ( apply_filters( 'toolset_filter_toolset_maps_get_types_postmeta_fields', [] ) as $field ) {
			$fields[ $field['meta_key'] ] = $field['name'];
		}

		return $fields;
	}
}
