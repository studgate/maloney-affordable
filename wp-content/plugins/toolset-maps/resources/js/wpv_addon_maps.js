/* eslint-disable */

var WPViews = WPViews || {};

WPViews.ViewAddonMaps = function( $ ) {
	const API_GOOGLE = 'google';
	const API_AZURE  = 'azure';
	const API_OSM    = 'osm';

	const self = this;

	/** @var {Object} views_addon_maps_i10n */

    // Determine which API is in use and set self.api as needed
	if ( API_GOOGLE === views_addon_maps_i10n.api_used ) {
		if ( typeof google === 'undefined' || typeof google.maps === 'undefined' ) {
			self.api = null;
		} else {
			/** @var {Object} google */
			self.api = google.maps;
		}
	} else {
		/** @var {Object} Azure or OSM: We don’t set self.api immediately, we lazy-load or initialize late */
		self.api = null;
	}

	self.maps_data = [];
	self.maps = {};
	self.markers = {};
	self.markerGroups = {};
	self.infowindows = {};
	self.bounds = {};
	self.view_map_blocks_relation = {};

	self.default_cluster_options = {
		imagePath:			views_addon_maps_i10n.cluster_default_imagePath,
		gridSize:			60,
		maxZoom:			null,
		zoomOnClick:		true,
		minimumClusterSize:	2
	};

	self.cluster_options = {};
	self.has_cluster_options = {};

	self.resize_queue = [];

	/**
	 * collect_maps_data
	 *
	 * Before init_maps
	 *
	 * @since 1.0
	 */

	self.collect_maps_data = function() {
		var mapElements = $( '.js-wpv-addon-maps-render' );
		if ( mapElements.length === 0 ) {
			// No map elements found, data cannot be collected
			return;
		}

		$( '.js-wpv-addon-maps-render' ).each( function() {
			self.collect_map_data( $( this ) );
		});

		$( document ).trigger('js_event_wpv_addon_maps_map_data_collected');
	};

	/**
	 * Initializes street view mode on given map.
	 * @param Object map
	 * @param latlon
	 * @since 1.5
	 */
	self.initStreetView = function( map, latlon ) {
		var streetViewService = new google.maps.StreetViewService();
		var panorama = self.maps[ map.map_id ].getStreetView();

		streetViewService.getPanoramaByLocation( latlon, 100, function (streetViewPanoramaData, status) {

			if ( status === google.maps.StreetViewStatus.OK ) {
				var streetLatlon = streetViewPanoramaData.location.latLng;
				var heading = ( map.map_options.heading !== '' )
						? map.map_options.heading
						: google.maps.geometry.spherical.computeHeading( streetLatlon, latlon);
				var pitch = ( map.map_options.pitch !== '' ) ? map.map_options.pitch : 0;

				panorama.setPosition( streetLatlon );
				panorama.setPov({
					heading: heading,
					pitch: pitch
				});
				panorama.setVisible( true );
			}
		});
	};

	/**
	 * Sets a trigger to init street view after map is ready. Namespaces trigger so we do this only once per map.
	 * @param String mapId
	 * @param jQuery $marker
	 * @listens js_event_wpv_addon_maps_init_map_completed
	 * @since 1.5
	 */
	self.waitForMapInitThenInitStreetView = function( mapId, $marker ) {
		$( document ).on( 'js_event_wpv_addon_maps_init_map_completed', function( event, map ) {
			if ( mapId === map.map_id ){
				var latlon = new google.maps.LatLng( $marker.data('markerlat'), $marker.data('markerlon') );
				self.initStreetView( map, latlon );
			}
		} );
	};

	/**
	 * collect_map_data
	 *
	 * @since 1.0
	 * @since 1.5 handle Street Views
	 */
	self.collect_map_data = function( thiz_map, attributes ) {
		var thiz_map_id,
			thiz_map_points = [],
			thiz_map_options = {};
		var streetViewMarkerFound = false;

		if ( attributes ) {
			// Use attributes to build map data
			thiz_map_id = attributes.mapId;
			thiz_map_options['general_zoom'] = parseInt( attributes.mapZoomLevel ) || 5;
			thiz_map_options['general_center_lat'] = parseFloat( attributes.mapCenterLat ) || 0;
			thiz_map_options['general_center_lon'] = parseFloat( attributes.mapCenterLon ) || 0;
			thiz_map_options['fitbounds'] = attributes.mapZoomAutomatic ? 'on' : 'off';
			thiz_map_options['single_zoom'] = parseInt( attributes.mapZoomLevelForSingleMarker ) || thiz_map_options['general_zoom'];
			thiz_map_options['single_center'] = attributes.mapForceCenterSettingForSingleMarker ? 'on' : 'off';
			thiz_map_options['osm_layer'] = attributes.osmTileLayer || 'standard';
			thiz_map_options['map_type'] = attributes.mapType || 'roadmap';
			thiz_map_options['show_layer_interests'] = attributes.showLayerInterests || '';
			thiz_map_options['marker_icon'] = attributes.mapMarkerIcon || '';
			thiz_map_options['marker_icon_hover'] = attributes.mapMarkerIconHover || '';
			thiz_map_options['draggable'] = attributes.mapDraggable ? 'on' : 'off';
			thiz_map_options['scrollwheel'] = attributes.mapScrollable ? 'on' : 'off';
			thiz_map_options['double_click_zoom'] = attributes.mapDoubleClickZoom ? 'on' : 'off';
			thiz_map_options['map_type_control'] = attributes.mapTypeControl ? 'on' : 'off';
			thiz_map_options['full_screen_control'] = attributes.mapFullScreenControl ? 'on' : 'off';
			thiz_map_options['zoom_control'] = attributes.mapZoomControls ? 'on' : 'off';
			thiz_map_options['street_view_control'] = attributes.mapStreetViewControl ? 'on' : 'off';
			thiz_map_options['background_color'] = attributes.mapBackgroundColor || '';
			thiz_map_options['cluster'] = attributes.mapMarkerClustering ? 'on' : 'off';
			thiz_map_options['style_json'] = attributes.mapStyle || '';
			thiz_map_options['spiderfy'] = attributes.mapMarkerSpiderfying ? 'on' : 'off';
			thiz_map_options['lat'] = attributes.mapCenterLat ? parseFloat( attributes.mapCenterLat ) : null;
			thiz_map_options['long'] = attributes.mapCenterLon ? parseFloat( attributes.mapCenterLon ) : null;
			thiz_map_options['heading'] = parseFloat( attributes.heading ) || 0;
			thiz_map_options['pitch'] = parseFloat( attributes.pitch ) || 0;
			thiz_map_options['multiple_zoom'] = parseInt( attributes.mapZoomLevelForMultipleMarkers ) || thiz_map_options['general_zoom'];
			thiz_map_options['clusterclickzoom'] = attributes.mapMarkerClusteringClickZoom ? 'on' : 'off';
			thiz_map_options['clustermaxzoom'] = parseInt( attributes.mapMarkerClusteringMaximalZoomLevel ) || null;
			thiz_map_options['clustergridsize'] = parseInt( attributes.mapMarkerClusteringMinimalDistance ) || 60;
			thiz_map_options['clusterminsize'] = parseInt( attributes.mapMarkerClusteringMinimalNumber ) || 2;
			thiz_map_options['marker_icon_use_hover'] = attributes.mapMarkerIconUseDifferentForHover ? 'on' : 'off';
			thiz_map_options['street_view'] = attributes.mapStreetView ? 'on' : 'off';
			thiz_map_options['loading_text'] = attributes.mapLoadingText || '';
			thiz_map_options['width'] = attributes.mapWidth || '100%';
			thiz_map_options['height'] = attributes.mapHeight || '500px';

		} else {
			// Original code using DOM elements
			thiz_map_id = thiz_map.data( 'map' );
			thiz_map_options['general_zoom'] = thiz_map.data( 'generalzoom' );
			thiz_map_options['general_center_lat'] = thiz_map.data( 'generalcenterlat' );
			thiz_map_options['general_center_lon'] = thiz_map.data( 'generalcenterlon' );
			thiz_map_options['fitbounds'] = thiz_map.data( 'fitbounds' );
			thiz_map_options['single_zoom'] = thiz_map.data( 'singlezoom' );
			thiz_map_options['single_center'] = thiz_map.data( 'singlecenter' );
			thiz_map_options['osm_layer'] = thiz_map.data( 'osmlayer' );
			thiz_map_options['map_type'] = thiz_map.data( 'maptype' );
			thiz_map_options['show_layer_interests'] = thiz_map.data( 'showlayerinterests' );
			thiz_map_options['marker_icon'] = thiz_map.data( 'markericon' );
			thiz_map_options['marker_icon_hover'] = thiz_map.data( 'markericonhover' );
			thiz_map_options['marker_icon_use_hover'] = thiz_map.data( 'markericonusehover' ) || 'off';
			thiz_map_options['draggable'] = thiz_map.data( 'draggable' );
			thiz_map_options['scrollwheel'] = thiz_map.data( 'scrollwheel' );
			thiz_map_options['double_click_zoom'] = thiz_map.data( 'doubleclickzoom' );
			thiz_map_options['map_type_control'] = thiz_map.data( 'maptypecontrol' );
			thiz_map_options['full_screen_control'] = thiz_map.data( 'fullscreencontrol' );
			thiz_map_options['zoom_control'] = thiz_map.data( 'zoomcontrol' );
			thiz_map_options['street_view_control'] = thiz_map.data( 'streetviewcontrol' );
			thiz_map_options['background_color'] = thiz_map.data( 'backgroundcolor' );
			thiz_map_options['cluster'] = thiz_map.data( 'cluster' );
			thiz_map_options['clustergridsize'] = thiz_map.data( 'clustergridsize' );
			thiz_map_options['clustermaxzoom'] = thiz_map.data( 'clustermaxzoom' );
			thiz_map_options['clusterclickzoom'] = thiz_map.data( 'clusterclickzoom' );
			thiz_map_options['clusterminsize'] = thiz_map.data( 'clusterminsize' );
			thiz_map_options['style_json'] = thiz_map.data( 'stylejson' );
			thiz_map_options['spiderfy'] = thiz_map.data( 'spiderfy' );
			thiz_map_options['lat'] = thiz_map.data( 'lat' );
			thiz_map_options['long'] = thiz_map.data( 'long' );
			thiz_map_options['heading'] = thiz_map.data( 'heading' );
			thiz_map_options['pitch'] = thiz_map.data( 'pitch' );
		}

		$( '.js-wpv-addon-maps-markerfor-' + thiz_map_id ).each( function() {
			var thiz_marker = $( this );
			// Handle special case when we don't have coordinates, but instead need to ask browser for current user's
			// position (only for Google API)
			if (
					thiz_marker.data( 'markerlat' ) === 'geo'
					&& API_GOOGLE === views_addon_maps_i10n.api_used
			) {
				// In case map render wait is requested by a marker, add that data to map
				if ( thiz_marker.data( 'markerlon' ) === 'wait' ) {
					thiz_map_options['render'] = 'wait';
					self.add_current_visitor_location_after_geolocation( thiz_map_id, thiz_marker );
				} else {
					self.add_current_visitor_location_after_init( thiz_map_id, thiz_marker );
				}
				return true;
			}

			// Handle Street View marker as special case (only when using Google API)
			if (
					thiz_map.data( 'streetview' ) === 'on'
					&& API_GOOGLE === views_addon_maps_i10n.api_used
			) {
				if ( thiz_map.data( 'markerid' ) === thiz_marker.data( 'marker' ) ) {
					self.waitForMapInitThenInitStreetView( thiz_map_id, thiz_marker );
					streetViewMarkerFound = true;
					return true;
				} else if ( thiz_map.data( 'location' ) === 'first' && !streetViewMarkerFound ) {
					self.waitForMapInitThenInitStreetView( thiz_map_id, thiz_marker );
					streetViewMarkerFound = true;
					return true;
				}
			}

			thiz_map_points.push( {
				'marker': thiz_marker.data( 'marker' ),
				'title': thiz_marker.data( 'markertitle' ),
				'markerlat': thiz_marker.data( 'markerlat' ),
				'markerlon': thiz_marker.data( 'markerlon' ),
				'markerinfowindow': thiz_marker.html(),
				'markericon': thiz_marker.data( 'markericon' ),
				'markericonhover': thiz_marker.data( 'markericonhover' )
			} );
		} );

		// Some error catching when street view from marker requested and marker not found
		if (
				(
						thiz_map.data( 'location' ) === 'first'
						|| thiz_map.data( 'markerid' )
				)
				&& !streetViewMarkerFound
		) {
			console.warn( views_addon_maps_i10n.marker_not_found_warning, thiz_map_id );
		}

		var thiz_cluster_options = {
			'cluster': thiz_map_options['cluster'],
			'gridSize': parseInt( thiz_map_options['clustergridsize'] ),
			'maxZoom': ( parseInt( thiz_map_options['clustermaxzoom'] ) > 0 ) ? parseInt( thiz_map_options['clustermaxzoom'] ) : null,
			'zoomOnClick': ( thiz_map_options['clusterclickzoom'] == 'off' ) ? false : true,
			'minimumClusterSize': parseInt( thiz_map_options['clusterminsize'] )
		};

		// As we might have cleared those options if we are on a reload event, we need to set them again with the data we saved in self.has_cluster_options
		if ( _.has( self.has_cluster_options, thiz_map_id ) ) {
			if ( _.has( self.has_cluster_options[ thiz_map_id ], "styles" ) ) {
				thiz_cluster_options['styles'] = self.has_cluster_options[ thiz_map_id ]['styles'];
			}
			if ( _.has( self.has_cluster_options[ thiz_map_id ], "calculator" ) ) {
				thiz_cluster_options['calculator'] = self.has_cluster_options[ thiz_map_id ]['calculator'];
			}
		}

		var thiz_map_collected = {
			'map': thiz_map_id,
			'markers': thiz_map_points,
			'options': thiz_map_options,
			'cluster_options': thiz_cluster_options
		};

		self.maps_data.push( thiz_map_collected );
		self.cluster_options[ thiz_map_id ] = thiz_cluster_options;

		return thiz_map_collected;
	};

	/**
	 * Gets current visitor location from browser, then adds the marker to given map.
	 *
	 * If location fetching failed, render the map without it...
	 *
	 * @since 1.4
	 * @param {String} thiz_map_id
	 * @param {jQuery} thiz_marker
	 */
	self.add_current_visitor_location = function(thiz_map_id, thiz_marker) {
		var map_key = self.get_map_key_by_id(thiz_map_id);
		if (navigator.geolocation) {
			navigator.geolocation.getCurrentPosition(
					function (position) {
						// Add data to collection and ask map to redraw
						self.maps_data[map_key].markers.push({
							'marker': thiz_marker.data('marker'),
							'title': thiz_marker.data('markertitle'),
							'markerlat': position.coords.latitude,
							'markerlon': position.coords.longitude,
							'markerinfowindow': thiz_marker.html(),
							'markericon': thiz_marker.data('markericon'),
							'markericonhover': thiz_marker.data('markericonhover')
						});

						self.init_map_after_loading_styles(self.maps_data[map_key]);
					},
					function (position_error) {
						self.init_map_after_loading_styles(self.maps_data[map_key]);
					}
			);
		} else {
			self.init_map_after_loading_styles(self.maps_data[map_key]);
		}
	};

	/**
	 * Wraps waiting for map to render first time before adding browser location marker
	 * @since 1.4
	 * @param {String} thiz_map_id
	 * @param {jQuery} thiz_marker
	 */
	self.add_current_visitor_location_after_init = function(thiz_map_id, thiz_marker) {
		$( document ).on( 'js_event_wpv_addon_maps_init_map_completed.'+thiz_map_id, function( event, data ) {
			if (thiz_map_id === data.map_id) {
				// Stop listening to this event for this map (because the event can fire multiple times
				// and we really need to add marker only once)
				$( document ).off( 'js_event_wpv_addon_maps_init_map_completed.'+thiz_map_id );

				self.add_current_visitor_location(thiz_map_id, thiz_marker);
			}
		});
	};

	/**
	 * Wraps waiting for geolocation before rendering map.
	 *
	 * Waits for all map data to be ready before trying to render it. (Problems could otherwise happen when geolocation
	 * is already approved and map data is not yet collected.)
	 *
	 * @since 1.4
	 * @param {String} thiz_map_id
	 * @param {jQuery} thiz_marker
	 */
	self.add_current_visitor_location_after_geolocation = function(thiz_map_id, thiz_marker) {
		$( document ).one('js_event_wpv_addon_maps_map_data_collected.'+thiz_map_id, function() {
			self.add_current_visitor_location(thiz_map_id, thiz_marker);
		});
	};

	/**
	 * Given map id string returns array key for a map in maps_data
	 * @since 1.4
	 * @param {String} map_id
	 * @returns {Number}
	 */
	self.get_map_key_by_id = function(map_id) {
		return _.findLastIndex(self.maps_data, { map: map_id });
	};

	/**
	 * Checks if json style needed, loads JSON file with map styles and then inits the map
	 * @since 1.4
	 * @param {Object} map
	 */
	self.init_map_after_loading_styles = function( map ) {
		if ( map.options.style_json ) {
			$.getJSON( map.options.style_json )
					.done( function( styles ) {
						map.options.styles = styles;
					} )
					.always( function () {
						// Even if styles loading failed, map can be rendered with standard style, so do it always.
						self.initMapOrWaitIfInsideHiddenBootstrapAccordionOrTab( map );
					} );
		} else {
			self.initMapOrWaitIfInsideHiddenBootstrapAccordionOrTab( map );
		}
	};

	/**
	 * Checks if this map is inside a hidden (collapsed) Bootstrap accordion.
	 *
	 * @param {String} map_selector
	 *
	 * @return {String} Returns accordion id if found, empty string otherwise
	 *
	 * @since 1.4.2
	 */
	self.check_being_inside_collapsed_bootstrap_accordion = function( map_selector ) {
		var accordion_id = '';
		var toggle_href = '';

		// Find all accordion toggles which are currently collapsed, then find which of the accordions contains our map
		// and return accordion id. If none, we'll return empty string.
		$('a[data-toggle="collapse"].collapsed').each( function( index, toggle ) {
			toggle_href = $( toggle ).attr('href');

			if ( $( toggle_href ).has( map_selector ).length ) {
				accordion_id = toggle_href;
				return false;
			}
		} );

		return accordion_id;
	};

	/**
	 * Checks if this map is inside a hidden Bootstrap tab.
	 *
	 * @param {String} map_selector
	 *
	 * @return {string}
	 *
	 * @since 1.4.2
	 */
	self.check_being_inside_hidden_bootstrap_tab = function( map_selector ) {
		var tab_toggle = '';
		var toggle_href = '';
		var $tab_body;

		$('a[data-toggle="tab"]').each( function( index, toggle ) {
			toggle_href = $( toggle ).attr('href');
			$tab_body = $( toggle_href );

			if ( !$tab_body.hasClass('active') && $tab_body.has( map_selector ).length ) {
				tab_toggle = toggle;
				return false;
			}
		} );

		return tab_toggle;
	};

	/**
	 * If not inside hidden Bootstrap accordion or tab, init map, if inside, hook to shown event to init then.
	 *
	 * @param {Object} map
	 *
	 * @since 1.4.2
	 */
	self.initMapOrWaitIfInsideHiddenBootstrapAccordionOrTab = function(map ) {
		var map_selector = '#js-wpv-addon-maps-render-' + map.map;
		var accordion_id = self.check_being_inside_collapsed_bootstrap_accordion( map_selector );
		var tab_toggle = self.check_being_inside_hidden_bootstrap_tab( map_selector );

		if ( accordion_id ) {
			$( accordion_id ).one('shown.bs.collapse', function() {
				self.initMapByAPI( map );
			} );
		} else if( tab_toggle ) {
			$( tab_toggle ).one('shown.bs.tab', function() {
				self.initMapByAPI( map );
			} );
		} else {
			self.initMapByAPI( map );
		}
	};

	/**
	 * Dispatches the correct map-initializer based on the current API setting (Google/Azure/OSM).
	 */
	self.initMapByAPI = function( map ) {
		if ( API_GOOGLE === views_addon_maps_i10n.api_used ) {
			self.init_map( map );
		} else if ( API_AZURE === views_addon_maps_i10n.api_used ) {
			self.initMapAzure( map );
		} else if ( API_OSM === views_addon_maps_i10n.api_used ) {
			self.initMapOsm( map );
		}
	};

	/**
	 * init_maps
	 *
	 * @since 1.0
	 */

	self.init_maps = function() {
		if ( !self.maps_data.length ) {
			// No maps to initialize
			return;
		}
		self.maps_data.map( function( map ) {
			// If there is a marker on the map with option for map rendering to wait until it's ready, do nothing.
			// (Marker itself will trigger map rendering when ready.)
			if (
					map.options.render
					&& map.options.render === 'wait'
			) {
				return true;
			}

			// Handle other maps
			self.init_map_after_loading_styles( map );
		});
	};

	/**
	 * init_map
	 *
	 * @since 1.0
	 */

	self.init_map = function( map ) {
		var map_icon = '',
				map_icon_hover = '',
				map_settings = {
					zoom: map.options['general_zoom']
				},
				event_settings = {
					map_id:			map.map,
					map_options:	map.options
				};
		var spiderfy = false;
		var clickEvent = 'click';

		$( document ).trigger( 'js_event_wpv_addon_maps_init_map_started', [ event_settings ] );

		if (
				map.options['general_center_lat'] != ''
				&& map.options['general_center_lon'] != ''
		) {
			map_settings['center'] = {
				lat: map.options['general_center_lat'],
				lng: map.options['general_center_lon']
			};
		} else {
			map_settings['center'] = {
				lat: 0,
				lng: 0
			};
		}

		if ( map.options['draggable'] == 'off' ) {
			map_settings['draggable'] = false;
		}

		if ( map.options['scrollwheel'] == 'off' ) {
			map_settings['scrollwheel'] = false;
		}

		if ( map.options['double_click_zoom'] == 'off' ) {
			map_settings['disableDoubleClickZoom'] = true;
		}

		if ( map.options['map_type_control'] == 'off' ) {
			map_settings['mapTypeControl'] = false;
		}

		if ( map.options['full_screen_control'] == 'off' ) {
			map_settings['fullscreenControl'] = false;
		}

		if ( map.options['zoom_control'] == 'off' ) {
			map_settings['zoomControl'] = false;
		}

		if ( map.options['street_view_control'] == 'off' ) {
			map_settings['streetViewControl'] = false;
		}

		if ( map.options['background_color'] != '' ) {
			map_settings['backgroundColor'] = map.options['background_color'];
		}

		if ( map.options['styles'] ) {
			map_settings['styles'] = map.options['styles'];
		}

		if ( map.options['map_type'] ) {
			map_settings['mapTypeId'] = map.options['map_type'];
		}

		const mapElement = document.getElementById( 'js-wpv-addon-maps-render-' + map.map );

		// In Gutenberg editor, it might happen that the map gets refreshed a couple of times and the div is missing at
		// this moment. Just give up right now, it will get rendered later on.
		if ( ! mapElement ) return;

		self.maps[ map.map ] = new self.api.Map( mapElement, map_settings );

		$( document ).trigger( 'js_event_wpv_addon_maps_init_map_inited', [ event_settings ] );

		self.bounds[ map.map ] = new self.api.LatLngBounds();

		if ( map.options['marker_icon'] != '' ) {
			map_icon = map.options['marker_icon'];
		}

		if ( map.options['marker_icon_hover'] != '' ) {
			map_icon_hover = map.options['marker_icon_hover'];
		}

		if ( 'on' === map.options['spiderfy'] ) {
			var oms = new OverlappingMarkerSpiderfier( self.maps[ map.map ], {
				markersWontMove: true,
				markersWontHide: true,
				basicFormatEvents: true
			});
			spiderfy = true;
		}

		map.markers.map( function( marker ) {
			var marker_lat_long = new self.api.LatLng( marker.markerlat, marker.markerlon ),
					marker_map_icon = ( marker.markericon == '' ) ? map_icon : marker.markericon,
					marker_map_icon_hover = ( marker.markericonhover == '' ) ? map_icon_hover : marker.markericonhover,
					marker_settings = {
						position: marker_lat_long,
						optimized: false
					};

			// 'default' is just used so that default marker icon doesn't get replaced with global marker icon. Now set
			// it empty, so the rest of code knows how to handle it.
			if ( marker_map_icon === 'default' ) {
				marker_map_icon = '';
			}

			if ( spiderfy ) {
				clickEvent = 'spider_click';
			} else {
				marker_settings.map = self.maps[ map.map ]
				clickEvent = 'click';
			}

			// Helps SVG marker icons to have a reasonable size and render properly in Internet Explorer
			if ( marker_map_icon.slice( -4 ).toLowerCase() === '.svg' ) {
				var scaledSize = new google.maps.Size(32, 32);
			} else {
				var scaledSize = null;
			}

			if ( marker_map_icon != '' ) {
				marker_settings['icon'] = {
					url: marker_map_icon,
					scaledSize: scaledSize,
				}
			}
			if ( marker.title != '' ) {
				marker_settings['title'] = marker.title;
			}

			self.markers[ map.map ] = self.markers[ map.map ] || {};

			self.markers[ map.map ][ marker.marker ] = new self.api.Marker(marker_settings);

			self.bounds[ map.map ].extend( self.markers[ map.map ][ marker.marker ].position );

			if (
					marker_map_icon != ''
					|| marker_map_icon_hover != ''
			) {
				marker_map_icon = ( marker_map_icon == '' ) ? views_addon_maps_i10n.marker_default_url : marker_map_icon;
				marker_map_icon_hover = ( marker_map_icon_hover == '' ) ? marker_map_icon : marker_map_icon_hover;

				if ( map.options['marker_icon_use_hover'] === 'on' &&
				     marker_map_icon != marker_map_icon_hover ) {
					var marker_icon_scaled = {
						url: marker_map_icon,
						scaledSize: scaledSize,
					};
					var marker_hover_icon_scaled = {
						url: marker_map_icon_hover,
						scaledSize: scaledSize,
					};

					self.api.event.addListener( self.markers[ map.map ][ marker.marker ], 'mouseover', function() {
						self.markers[ map.map ][ marker.marker ].setIcon( marker_hover_icon_scaled );
					});
					self.api.event.addListener( self.markers[ map.map ][ marker.marker ], 'mouseout', function() {
						self.markers[ map.map ][ marker.marker ].setIcon( marker_icon_scaled );
					});
					// Add custom classnames to reproduce this hover effect from an HTML element
					$( document ).on( 'mouseover', '.js-toolset-maps-hover-map-' + map.map + '-marker-' + marker.marker, function() {
						self.markers[ map.map ][ marker.marker ].setIcon( marker_hover_icon_scaled );
					});
					$( document ).on( 'mouseout', '.js-toolset-maps-hover-map-' + map.map + '-marker-' + marker.marker, function() {
						self.markers[ map.map ][ marker.marker ].setIcon( marker_icon_scaled );
					});
				}
			}

			if ( marker.markerinfowindow !== '' ) {
				// Create a single self.api.InfoWindow object for each map, if needed, and populate its content based on
				// the marker
				self.infowindows[ map.map ] = self.infowindows[ map.map ] || new self.api.InfoWindow({ content: '' });
				self.api.event.addListener( self.markers[ map.map ][ marker.marker ], clickEvent, function() {
					self.infowindows[ map.map ].setContent( marker.markerinfowindow );
					self.infowindows[ map.map ].open( self.maps[ map.map ], self.markers[ map.map ][ marker.marker ] );
				});
				$( document ).on(
						'click',
						'.js-toolset-maps-open-infowindow-map-' + map.map + '-marker-' + marker.marker,
						function() {
							self.infowindows[ map.map ].setContent( marker.markerinfowindow );
							self.openInfowindowWhenMarkerVisible( map.map, marker.marker );
						}
				);
			}

			if ( spiderfy ) {
				oms.addMarker( self.markers[ map.map ][ marker.marker ] )
			}
		});

		if ( _.size( map.markers ) == 1 ) {
			if ( map.options['single_zoom'] != '' ) {
				self.maps[ map.map ].setZoom( map.options['single_zoom'] );
				if ( map.options['fitbounds'] == 'on' ) {
					self.api.event.addListenerOnce( self.maps[ map.map ], 'bounds_changed', function( event ) {
						self.maps[ map.map ].setZoom( map.options['single_zoom'] );
					});
				}
			}
			if ( map.options['single_center'] == 'on' ) {
				for ( var mark in self.markers[ map.map ] ) {
					self.maps[ map.map ].setCenter( self.markers[ map.map ][ mark ].getPosition() );
					break;
				}
			}
		} else if ( _.size( map.markers ) > 1 ) {
			if ( map.options['fitbounds'] == 'on' ) {
				self.maps[ map.map ].fitBounds( self.bounds[ map.map ] );
			}
		}

		if ( _.contains( self.resize_queue, map.map ) ) {
			self.keep_map_center_and_resize( self.maps[ map.map ] );
			_.reject( self.resize_queue, function( item ) {
				return item == map.map;
			});
		}

		// Init Street View overlay if lat and long are provided (coming from address)
		if ( map.options.lat && map.options.long ) {
			self.initStreetView( event_settings, new google.maps.LatLng( map.options.lat, map.options.long ) );
		}

		$( document ).trigger( 'js_event_wpv_addon_maps_init_map_completed', [ event_settings ] );
	};
	/**
	 * Make sure that marker is visible (not in cluster) before opening info window. If it isn't, wait until it is.
	 *
	 * Because, if marker isn't visible, info window will get wrong location to open (usually another, visible marker).
	 *
	 * @param {String} mapId
	 * @param {String} markerId
	 * @since 1.5
	 */
	self.openInfowindowWhenMarkerVisible = function( mapId, markerId ) {
		if ( self.markers[mapId][markerId].map ) {
			self.infowindows[mapId].open( self.maps[mapId], self.markers[mapId][markerId] );
		} else {
			_.delay(function () {
				self.openInfowindowWhenMarkerVisible( mapId, markerId );
			}, 150);
		}
	};
	/**
	 * clean_map_data
	 *
	 * @param map_id
	 *
	 * @since 1.0
	 */

	self.clean_map_data = function( map_id ) {
		// Remove Leaflet map instance if it exists
		if ( API_OSM === views_addon_maps_i10n.api_used && self.maps[map_id] instanceof L.Map) {
			// Remove marker group if it exists
			if (self.markerGroups[map_id]) {
				self.maps[map_id].removeLayer(self.markerGroups[map_id]);
			}
			self.maps[map_id].remove();
			// Remove the reference from the DOM element
			const mapElement = document.getElementById('js-wpv-addon-maps-render-' + map_id);
			if (mapElement) {
				delete mapElement._leaflet_map;
			}
		}

		self.maps_data = _.filter( self.maps_data, function( map_data_unique ) {
			return map_data_unique.map != map_id;
		});

		self.maps				= _.omit( self.maps, map_id );
		self.markers			= _.omit( self.markers, map_id );
		self.markerGroups		= _.omit( self.markerGroups, map_id );
		self.infowindows		= _.omit( self.infowindows, map_id );
		self.bounds				= _.omit( self.bounds, map_id );
		self.cluster_options	= _.omit( self.cluster_options, map_id );

		var settings = {
			map_id: map_id
		};

		$( document ).trigger( 'js_event_wpv_addon_maps_clean_map_completed', [ settings ] );

	};

	/**
	 * keep_map_center_and_resize
	 *
	 * @param map
	 *
	 * @since 1.1
	 */

	self.keep_map_center_and_resize = function( map ) {
		var map_iter_center = map.getCenter();
		self.api.event.trigger( map, "resize" );
		map.setCenter( map_iter_center );
	};

	/**
	 * Init all maps - Azure API version
	 * @since 1.5
	 */
	self.initMapsAzure = function() {
		let maps = _.map( self.maps_data, self.resolveGeolocatedMarkerThenInitMapAzure );
		let mapIds = _.pluck( self.maps_data, 'map' );

		self.maps = _.object( mapIds, maps );
	};

	/**
	 * Resolve geolocated marker (if any)
	 *
	 * Among markers for a map, there may be a special one which should trigger current visitor geolocation. (It makes
	 * no sense to have more than one, because the visitor is not a quark). This method checks if there is one such
	 * marker, if geolocation is available, and if the map rendering should wait for geolocation or if that marker
	 * should be added after map and other markers render.
	 *
	 * @since 1.5
	 *
	 * @param {Object} map
	 */
	self.resolveGeolocatedMarkerThenInitMapAzure = function( map ) {
		let geoMarker = _.findWhere( map.markers, {markerlat: 'geo'} );
		let renderedMap = null;

		if ( geoMarker ) {
			let geoMarkerIndex = _.indexOf( map.markers, geoMarker );

			if ( "immediate" === geoMarker.markerlon ) {
				// Render map immediately, marker will be added when and if available, center and bounds adjusted if
				// needed.
				renderedMap = self.initMapAzure( map );

				if (navigator.geolocation) {
					navigator.geolocation.getCurrentPosition(function (position) {
						map.markers[geoMarkerIndex].markerlat = position.coords.latitude;
						map.markers[geoMarkerIndex].markerlon = position.coords.longitude;

						if (
								map.markers.length === 1
								&& map.options.single_center === "on"
						) {
							let mapCenter = [position.coords.longitude, position.coords.latitude];
							renderedMap.setCamera( {center: mapCenter} );
						}

						self.maybeFitboundsAzure( map, renderedMap );

						let pin = new atlas.data.Feature(
								new atlas.data.Point( [position.coords.longitude, position.coords.latitude] ),
								{title: geoMarker.title, popup: geoMarker.markerinfowindow},
								geoMarker.marker
						);
						renderedMap.addEventListener('load', function() {
							renderedMap.addPins( [pin], {
								fontColor: "#000",
								fontSize: 14,
								icon: "pin-red",
								iconSize: 1,
								name: "default-pin-layer",
								cluster: ( map.options.cluster === 'on' ),
								textOffset: [0, 20],
							});
						} );
					});
				}
			} else {
				if (navigator.geolocation) {
					navigator.geolocation.getCurrentPosition(function (position) {
						map.markers[geoMarkerIndex].markerlat = position.coords.latitude;
						map.markers[geoMarkerIndex].markerlon = position.coords.longitude;

						// We now have position for geo marker, render as usual.
						renderedMap = self.initMapAzure( map );
					});
				} else {
					// No geolocation? Render what we have, without this marker.
					renderedMap = self.initMapAzure( map );
				}
			}
		} else {
			// No geomarker, just render as usual.
			renderedMap = self.initMapAzure( map );
		}

		return renderedMap;
	};

	/**
	 * If there is more than 1 marker and fitbounds is requested for this map, find bound points and set camera bounds.
	 *
	 * @since 1.5
	 *
	 * @param {Object} map
	 * @param {atlas.Map} renderedMap
	 */
	self.maybeFitboundsAzure = function( map, renderedMap ) {
		if ( map.markers.length <= 1 || map.options.fitbounds !== "on" ) {
			// Not fitting bounds. Either markers <=1 or fitbounds is off
			return;
		}

		// Collect positions from markers
		let positions = map.markers.map( function( m ) {
			let lon = parseFloat( m.markerlon );
			let lat = parseFloat( m.markerlat );
			return [ lon, lat ];
		});

		// Filter out invalid positions
		positions = positions.filter( function( pos ) {
			return !isNaN( pos[0] ) && !isNaN( pos[1] );
		});

		if ( positions.length === 0 ) {
			// No valid marker positions found
			return;
		}

		// Create a bounding box from positions
		let lons = positions.map( pos => pos[0] );
		let lats = positions.map( pos => pos[1] );
		let westmost = Math.min( ...lons );
		let eastmost = Math.max( ...lons );
		let southernmost = Math.min( ...lats );
		let northernmost = Math.max( ...lats );

		// Adjust the map's camera to fit the bounds
		renderedMap.setCameraBounds({
			bounds: [ westmost, southernmost, eastmost, northernmost ],
			padding: 50
		});
	};

	/**
	 * Map type names are sometimes, but not always the same between Google and Azure.
	 *
	 * @since 1.5.3
	 * @param {string} mapType
	 * @return {string}
	 */
	self.translateMapTypesToAzure = function( mapType ) {
		const translationTable = {
			'roadmap': 'road',
			'hybrid': 'satellite_road_labels',
			'terrain': 'road' // 'terrain' type is not supported by Azure, so render the closest - 'road'
		};

		if ( mapType in translationTable ) return translationTable[mapType];
		// 'satellite' type has the same name, so just falls through.
		else return mapType;
	};

	/**
	 * Returns value for marker icon set from marker, map, or default
	 *
	 * @since 1.5.3
	 * @param {Object} marker
	 * @param {Object} mapOptions
	 * @return {string}
	 */
	self.getMarkerIconValue = function( marker, mapOptions ) {
		if ( marker.markericon ) {
			return marker.markericon;
		} else if ( mapOptions.marker_icon ) {
			return mapOptions.marker_icon;
		} else {
			return 'pin-red';
		}
	};

	/**
	 * Adjusts the map to ensure the popup is fully visible.
	 *
	 * @param {Object} renderedMap The map object that is currently rendered
	 * @param {Array} coordinates  The coordinates of the marker
	 */
	self.adjustMapToFitPopup = function(renderedMap, coordinates) {
		const camera = renderedMap.getCamera();
		const bounds = camera.bounds;

		const west = bounds[0];
		const south = bounds[1];
		const east = bounds[2];
		const north = bounds[3];

		const markerLon = coordinates[0];
		const markerLat = coordinates[1];

		// Add padding to ensure the popup is fully visible
		const markerPadding = 0.025;

		let isAdjusted = false;

		// Check if the marker is outside the current bounds
		if (markerLon < west + markerPadding || markerLon > east - markerPadding || markerLat < south + markerPadding || markerLat > north - markerPadding) {
			isAdjusted = true;
		}

		// Center the map on the marker
		if ( isAdjusted ) {
			renderedMap.setCamera({ center: [markerLon, markerLat] });
		}
	};

	/**
	 * Init a map with Azure API
	 *
	 * @since 1.5
	 *
	 * @param {Object} map
	 *
	 * @return {atlas.Map}
	 */
	self.initMapAzure = function( map ) {
		let event_settings = {
			map_id:			map.map,
			map_options:	map.options
		};
		$( document ).trigger( 'js_event_wpv_addon_maps_init_map_started', [ event_settings ] );

		// Initialize defaults from map options
		let defaultCenterLon = parseFloat( map.options.general_center_lon );
		let defaultCenterLat = parseFloat( map.options.general_center_lat );
		let mapCenter = [
			!isNaN(defaultCenterLon) ? defaultCenterLon : 0,
			!isNaN(defaultCenterLat) ? defaultCenterLat : 0
		];
		let mapZoom = parseInt( map.options.general_zoom ) || 10;
		let cluster = ( map.options.cluster === 'on' );

		// Handle single marker logic
		if ( map.markers.length === 1 ) {
			if (
					_.isNumber( parseFloat( map.markers[0].markerlon ) )
					&& map.options.single_center === "on"
			) {
				mapCenter = [
					parseFloat( map.markers[0].markerlon ),
					parseFloat( map.markers[0].markerlat )
				];
			}

			mapZoom = parseInt( map.options.single_zoom ) || mapZoom;
		}

		// Remove possible map loading content. Google Maps does it by itself, Azure doesn't
		$( '#js-wpv-addon-maps-render-' + map.map ).empty();

		// Render map
		let renderedMap = new atlas.Map( "js-wpv-addon-maps-render-" + map.map, {
			"subscription-key": views_addon_maps_i10n.azure_api_key,
			center: mapCenter,
			zoom: mapZoom,
			scrollZoomInteraction: ( map.options.scrollwheel === 'on' ),
			dblClickZoomInteraction: ( map.options.double_click_zoom === 'on' ),
			dragPanInteraction: ( map.options.draggable === 'on' ),
			style: self.translateMapTypesToAzure( map.options.map_type ) // Ignored with API 1.1, but works with 1.2
		} );

		$( document ).trigger( 'js_event_wpv_addon_maps_init_map_inited', [ event_settings ] );

		// Handle multiple markers
		if ( map.markers.length > 1 ) {
			if ( map.options.fitbounds === 'on' ) {
				// Sets fit bounds
				self.maybeFitboundsAzure( map, renderedMap );
			} else if ( map.options.multiple_zoom ) {
				// Filter out markers with invalid coordinates
				let validMarkers = map.markers.filter( function( m ) {
					return !isNaN( parseFloat( m.markerlat ) ) && !isNaN( parseFloat( m.markerlon ) );
				});
				if ( validMarkers.length > 0 ) {
					// Initialize sums
					let sumLat = 0;
					let sumLon = 0;

					// Sum up all latitudes and longitudes
					validMarkers.forEach( function( m ) {
						sumLat += parseFloat( m.markerlat );
						sumLon += parseFloat( m.markerlon );
					});

					// Calculate averages
					let avgLat = sumLat / validMarkers.length;
					let avgLon = sumLon / validMarkers.length;

					// Set the map's camera to the average position
					renderedMap.setCamera( {
						center: [ avgLon, avgLat ],
						zoom: parseInt( map.options.multiple_zoom )
					} );
				}
			} else {
				// Render default map center
				renderedMap.setCamera( {
					center: [ 0, 20 ],
					zoom: 1.5
				} );
			}
		}

		// Create pins (markers)
		let pins = _.map( map.markers, function( marker ) {
			return new atlas.data.Feature(
					new atlas.data.Point( [marker.markerlon, marker.markerlat] ),
					{
						title: marker.title,
						popup: marker.markerinfowindow,
						icon: self.getMarkerIconValue( marker, map.options ),
						iconhover: marker.markericonhover ? marker.markericonhover : map.options.marker_icon_hover,
						id: marker.marker
					},
					marker.marker
			);
		} );

		// Create custom icons for pins (if any)
		let icons = _.filter(
				_.union(
						_.pluck( map.markers, 'markericon' ),
						_.pluck( map.markers, 'markericonhover' ),
						[map.options.marker_icon, map.options.marker_icon_hover]
				)
		);

		// Add markers and their events to map (waiting for load event first, otherwise the library throws a style
		// rendering warning)
		renderedMap.addEventListener('load', function() {
			_.each( icons, function( icon ) {
				var iconImg = document.getElementById( icon );

				if ( iconImg ) {
					renderedMap.addIcon( icon, iconImg );
				}
			} );

			self.addPins( renderedMap, pins, cluster );

			// FUTURE: this needs Azure Maps API 1.2, which is buggy at the moment and kills some other things. Revisit.
			// Add controls
			/*if ( map.options.zoom_control === 'on' ) {
				renderedMap.addControl(new atlas.control.ZoomControl, {});
			}
			if ( map.options.map_type_control === 'on' ) {
				renderedMap.addControl(new atlas.control.StyleControl, {});
			}*/

			// Store the last opened popup
			let currentPopup = null;

			// Add an event listener to close the popup when clicking anywhere on the map
			renderedMap.addEventListener( 'click', function(event) {
				if ( currentPopup ) {
					currentPopup.close();
				}
			});

			// Add an event listener for click (popup)
			renderedMap.addEventListener( 'click', "default-pin-layer", function( event ) {
				// Does pin have some popup text?
				if ( ! event.features[0].properties.popup ) return;

				// Close the currently open popup
				if ( currentPopup ) {
					currentPopup.close();
				}

				// Create content for popup
				let popupContentElement = document.createElement("div");
				popupContentElement.style.padding = "8px";
				let popupNameElement = document.createElement("div");
				popupNameElement.innerHTML = event.features[0].properties.popup;
				popupContentElement.appendChild(popupNameElement);

				// Create a popup
				let popup = new atlas.Popup( {
					content: popupContentElement,
					position: event.features[0].geometry.coordinates,
					pixelOffset: [0, 0]
				} );

				popup.open( renderedMap );
				currentPopup = popup;

				// Ensure the map pans to the popup
				self.adjustMapToFitPopup( renderedMap, event.features[0].geometry.coordinates );
			} );

			// Add event listeners for hover (possible marker icon change)
			let originalIcon = '';
			let hoveredPinIndex = 0;
			renderedMap.addEventListener( 'mouseover', "default-pin-layer", function( event ) {
                if (
                    map.options.marker_icon_use_hover !== 'on' ||  // Check if hover is enabled
                    !event.features[0].properties.iconhover        // Check if hover icon is set
                ) return;

				let id = event.features[0].properties.id;
				let pin = _.findWhere( pins, {id: id} );

				originalIcon = event.features[0].properties.icon;
				hoveredPinIndex = _.indexOf( pins, pin );

				pin.properties.icon = pin.properties.iconhover;
				pins[hoveredPinIndex] = pin;

				self.addPins( renderedMap, pins, cluster );
			} );
			renderedMap.addEventListener( 'mouseout', "default-pin-layer", function( event ) {
				if ( map.options.marker_icon_use_hover !== 'on' || !originalIcon ) return;

				pins[hoveredPinIndex].properties.icon = originalIcon;

				self.addPins( renderedMap, pins, cluster );

				originalIcon = '';
			} );

			$( document ).trigger( 'js_event_wpv_addon_maps_init_map_completed', [ event_settings ] );
		} );

		return renderedMap;
	};

	/**
	 * Adds (or changes) all the pins for the given map on default pin layer.
	 *
	 * (Because you cannot simply change one pin with Azure, you have to change the layer).
	 *
	 * @since 1.5.3
	 * @param atlas.Map map
	 * @param {array} pins
	 * @param {bool} cluster
	 */
	self.addPins = function( map, pins, cluster ) {
		map.addPins( pins, {
			fontColor: "#000",
			fontSize: 14,
			name: "default-pin-layer",
			cluster: cluster,
			textOffset: [0, 20],
			overwrite: true
		} );
	};

	// ------------------------------------
	// API
	// ------------------------------------

	/**
	 * WPViews.view_addon_maps.reload_map
	 *
	 * @param map_id
	 *
	 * @since 1.0
	 */

	self.reload_map = function( map_id ) {
		var settings = {
			map_id: map_id
		};
		$( document ).trigger( 'js_event_wpv_addon_maps_reload_map_started', [ settings ] );
		$( document ).trigger( 'js_event_wpv_addon_maps_reload_map_triggered', [ settings ] );
		$( document ).trigger( 'js_event_wpv_addon_maps_reload_map_completed', [ settings ] );
	};

	/**
	 * document.js_event_wpv_addon_maps_reload_map_triggered
	 *
	 * @param event
	 * @param data
	 * 	data.map_id
	 *
	 * @since 1.0
	 * @since 1.5 supports Google, Open Street Maps and Azure API
	 */
	$( document ).on( 'js_event_wpv_addon_maps_reload_map_triggered', function( event, data ) {
		var defaults = {
				map_id: false
			},
			settings = $.extend( {}, defaults, data );
		if (
			settings.map_id == false ||
			$( '#js-wpv-addon-maps-render-' + settings.map_id ).length != 1
		) {
			return;
		}

		self.clean_map_data( settings.map_id );
		var mpdata = self.collect_map_data( $( '#js-wpv-addon-maps-render-' + settings.map_id ) );

		// Now decide which init to call based on API
		if ( API_GOOGLE === views_addon_maps_i10n.api_used ) {
			self.init_map_after_loading_styles( mpdata );
		} else if ( API_AZURE === views_addon_maps_i10n.api_used ) {
			$( '#js-wpv-addon-maps-render-' + settings.map_id ).empty();
			self.maps[settings.map_id] = self.resolveGeolocatedMarkerThenInitMapAzure( mpdata );
		} else if ( API_OSM === views_addon_maps_i10n.api_used ) {
			// Re-initialize that single map
			$( '#js-wpv-addon-maps-render-' + settings.map_id ).empty();
			self.initMapOsm( mpdata );
		}
	});

	/**
	 * WPViews.view_addon_maps.get_map
	 *
	 * @param map_id
	 *
	 * @return google.maps.Map object | false
	 *
	 * @since 1.0
	 */

	self.get_map = function( map_id ) {
		if ( map_id in self.maps ) {
			return self.maps[ map_id ];
		} else {
			return false;
		}
	};

	/**
	 * WPViews.view_addon_maps.get_map_marker
	 *
	 * @param marker_id
	 * @param map_id
	 *
	 * @return google.maps.Marker object | false
	 *
	 * @since 1.0
	 */

	self.get_map_marker = function( marker_id, map_id ) {
		if (
				map_id in self.markers
				&& marker_id in self.markers[ map_id ]
		) {
			return self.markers[ map_id ][ marker_id ];
		} else {
			return false;
		}
	};

	// ------------------------------------
	// Interaction
	// ------------------------------------

	/**
	 * Reload on js-wpv-addon-maps-reload-map.click
	 *
	 * @since 1.0
	 */

	$( document ).on( 'click', '.js-wpv-addon-maps-reload-map', function( e ) {
		e.preventDefault();
		var thiz = $( this );
		if ( thiz.attr( 'data-map' ) ) {
			self.reload_map( thiz.data( 'map' ) );
		}
	});

	/**
	 * Center on a marker on js-wpv-addon-maps-center-map.click
	 *
	 * @since 1.0
	 * @since 1.5 Azure API supported
	 */
	$( document ).on( 'click', '.js-wpv-addon-maps-focus-map', function( e ) {
		e.preventDefault();
		var thiz = $( this ),
			thiz_map,
			thiz_marker,
			thiz_zoom;
		if (
			thiz.attr( 'data-map' ) &&
			thiz.attr( 'data-marker' )
		) {
			thiz_map = thiz.data( 'map' );
			thiz_marker = thiz.data( 'marker' );

			if ( API_GOOGLE === views_addon_maps_i10n.api_used ) {
				if (
					thiz_map in self.maps &&
					thiz_map in self.markers &&
					thiz_marker in self.markers[thiz_map]
				) {
					thiz_zoom = ($('#js-wpv-addon-maps-render-' + thiz_map).data('singlezoom') != '')
						? $('#js-wpv-addon-maps-render-' + thiz_map).data('singlezoom')
						: 14;
					self.maps[thiz_map].setCenter(self.markers[thiz_map][thiz_marker].getPosition());
					self.maps[thiz_map].setZoom(thiz_zoom);
				}
			} else if ( API_AZURE === views_addon_maps_i10n.api_used ) {
				if ( thiz_map in self.maps ) {
					thiz_zoom = ($('#js-wpv-addon-maps-render-' + thiz_map).data('singlezoom') != '')
						? $('#js-wpv-addon-maps-render-' + thiz_map).data('singlezoom')
						: 14;
					var $thisMarker = $('.js-wpv-addon-maps-marker-' + thiz_marker);
					self.maps[thiz_map].setCamera({
						center: [$thisMarker.eq(0).data('markerlon'), $thisMarker.eq(0).data('markerlat')],
						zoom: thiz_zoom
					});
				}
			} else if ( API_OSM === views_addon_maps_i10n.api_used ) {
				if ( thiz_map in self.maps ) {
					thiz_zoom = parseInt( $('#js-wpv-addon-maps-render-' + thiz_map).data('singlezoom' ), 10 ) || 14;
					var lat = parseFloat( $('.js-wpv-addon-maps-marker-' + thiz_marker).data('markerlat') ) || 0;
					var lon = parseFloat( $('.js-wpv-addon-maps-marker-' + thiz_marker).data('markerlon') ) || 0;
					self.maps[thiz_map].setView([lat, lon], thiz_zoom);
				}
			}
		}
	});

	/**
	 * Center map on fitbounds on js-wpv-addon-maps-center-map-fitbounds.click
	 *
	 * @since 1.0
	 */
	$( document ).on( 'click', '.js-wpv-addon-maps-restore-map', function( e ) {
		e.preventDefault();
		var thiz = $( this ),
				thiz_map,
				current_map_data_array,
				current_map_data;
		if ( thiz.attr( 'data-map' ) ) {
			thiz_map = thiz.data( 'map' );

			if ( API_GOOGLE === views_addon_maps_i10n.api_used ) {
				if (
						thiz_map in self.maps
						&& thiz_map in self.bounds
				) {
					self.maps[ thiz_map ].fitBounds( self.bounds[ thiz_map ] );
				}

				if ( _.size( self.markers[ thiz_map ] ) == 1 ) {
					current_map_data_array = _.filter( self.maps_data, function( map_data_unique ) {
						return map_data_unique.map == thiz_map;
					});
					current_map_data = _.first( current_map_data_array );
					if ( current_map_data.options['single_zoom'] != '' ) {
						self.maps[ thiz_map ].setZoom( current_map_data.options['single_zoom'] );
						if ( current_map_data.options['fitbounds'] == 'on' ) {
							self.api.event.addListenerOnce( self.maps[ thiz_map ], 'bounds_changed', function( event ) {
								self.maps[ thiz_map ].setZoom( current_map_data.options['single_zoom'] );
							});
						}
					}
					if ( current_map_data.options['single_center'] == 'on' ) {
						for ( var mark in self.markers[ thiz_map ] ) {
							self.maps[ thiz_map ].setCenter( self.markers[ thiz_map ][ mark ].getPosition() );
							break;
						}
					}
				}
			}

			if ( API_AZURE === views_addon_maps_i10n.api_used ) {
				current_map_data_array = _.filter( self.maps_data, function( map_data_unique ) {
					return map_data_unique.map == thiz_map;
				});
				current_map_data = _.first( current_map_data_array );

				// Get center latitude and longitude
				var centerLat = parseFloat( current_map_data.options.general_center_lat );
				var centerLon = parseFloat( current_map_data.options.general_center_lon );

				// Check if centerLat or centerLon is exactly 0 or invalid
				if ( centerLat === 0 || centerLon === 0 || isNaN( centerLat ) || isNaN( centerLon ) ) {

					// Proceed to adjust the map based on markers - calculating center from markers
					if ( _.size( current_map_data.markers ) === 1 ) {
						var markerLat = parseFloat( current_map_data.markers[0].markerlat );
						var markerLon = parseFloat( current_map_data.markers[0].markerlon );
						var zoom_level = current_map_data.options.single_zoom ? parseInt( current_map_data.options.single_zoom ) : 15;
						if ( !isNaN( markerLat ) && !isNaN( markerLon ) ) {
							// Setting map camera to center on single marker
							self.maps[ thiz_map ].setCamera( {
								center: [ markerLon, markerLat ],
								zoom: zoom_level
							} );
						}
					} else if ( _.size( current_map_data.markers ) > 1 ) {

						// Multiple markers detected - filter valid markers
						var validMarkers = current_map_data.markers.filter( function( m ) {
							return !isNaN( parseFloat( m.markerlat ) ) && !isNaN( parseFloat( m.markerlon ) );
						} );

						if ( validMarkers.length > 0 ) {
							// Compute average center
							var avgLon = _.meanBy( validMarkers, function( m ) { return parseFloat( m.markerlon ); } );
							var avgLat = _.meanBy( validMarkers, function( m ) { return parseFloat( m.markerlat ); } );
							var multipleZoom = parseInt( current_map_data.options.multiple_zoom ) || 10;
							if ( !isNaN( avgLat ) && !isNaN( avgLon ) ) {
								// Setting center to average position and zoom to multiple_zoom
								self.maps[ thiz_map ].setCamera( {
									center: [ avgLon, avgLat ],
									zoom: multipleZoom
								} );
							}
						}
					}
				} else {
					// Using provided center latitude and longitude
					if ( _.size( current_map_data.markers ) === 1 ) {
						var zoom_level = current_map_data.options.single_zoom ? parseInt( current_map_data.options.single_zoom ) : 15;
						if ( !isNaN( centerLat ) && !isNaN( centerLon ) ) {
							self.maps[ thiz_map ].setCamera( {
								center: [ centerLon, centerLat ],
								zoom: zoom_level
							} );
						} else {
							console.warn( "Invalid center coordinates provided." );
						}
					} else if ( _.size( current_map_data.markers ) > 1 ) {
						// Use fitbounds if available
						if ( current_map_data.options.fitbounds == 'on' ) {
							self.maybeFitboundsAzure( current_map_data, self.maps[ thiz_map ] );
						} else {
							// Fitbounds not on, center map using provided center
							var multipleZoom = parseInt( current_map_data.options.multiple_zoom ) || 10;
							if ( !isNaN( centerLat ) && !isNaN( centerLon ) ) {
								self.maps[ thiz_map ].setCamera( {
									center: [ centerLon, centerLat ],
									zoom: multipleZoom
								} );
							} else {
								console.warn( "Invalid center coordinates provided." );
							}
						}
					}
				}
			}
		}
	});

	// ------------------------------------
	// Views compatibility
	// ------------------------------------

	/**
	 * Reload the maps contained on the parametric search results loaded via AJAX
	 *
	 * @since 1.0
	 */
	$( document ).on( 'js_event_wpv_parametric_search_results_updated', function( event, data ) {
		// Check if markers are present in the new layout
		if ( !data.layout.find( '.js-wpv-addon-maps-marker' ).length ) {
			// Check if data.response and data.response.full exist
			if ( data.response && data.response.full ) {
				// If not present, find markers in the original response data and append to current layout
				var markerDivs = $( data.response.full ).find( '.js-wpv-addon-maps-marker' );
				if ( markerDivs.length ) {
					data.layout.append( markerDivs );
				}
			}
		}
		self.cleanupMarkersFromMapBlock( data.view_unique_id );
		_.each( self.get_affected_maps( data.layout ), self.reload_map );
	});

	/**
	 * Reload the maps contained on the pagination results loaded via AJAX
	 *
	 * @since 1.0
	 */
	$( document ).on( 'js_event_wpv_pagination_completed', function( event, data ) {
		self.cleanupMarkersFromMapBlock( data.view_unique_id );
		_.each( self.get_affected_maps( data.layout ), self.reload_map );
	} );

	/**
	 * Check if a Map block contains markers coming from a View, and it it does, remove those, because new ones are
	 * provided by View.
	 *
	 * @since 2.0
	 * @param {int|string} viewUniqueId
	 */
	self.cleanupMarkersFromMapBlock = function( viewUniqueId ) {
		const viewId = _.isNumber( viewUniqueId ) ? viewUniqueId : viewUniqueId.split( '-' )[ 0 ];

		$( '.js-wpv-addon-maps-marker' ).each( function( key, marker ) {
			// If this is a marker coming from given View...
			if ( Number( $( marker ).data( 'fromview' ) ) === Number( viewId ) ) {
				// Record View - Map blocks relation, as it's useful in some situations
				self.view_map_blocks_relation[ viewUniqueId ] = $( marker ).data( 'markerfor' );

				// Remove markers next to Map div (instead of in View div)
				$( marker ).prev( '.js-wpv-addon-maps-render' ).next().remove();
			}
		} );
	};

	/**
	 * get_affected_maps
	 *
	 * Get all the maps that have related data used in the given containr, no matter if a map render or a marker
	 *
	 * @param container	object
	 *
	 * @return array
	 *
	 * @since 1.1
	 */

	self.get_affected_maps = function( container ) {
		var affected_maps = [];
		container.find( '.js-wpv-addon-maps-render' ).each( function() {
			affected_maps.push( $( this ).data( 'map' ) );
		});
		container.find( '.js-wpv-addon-maps-marker' ).each( function() {
			affected_maps.push( $( this ).data( 'markerfor' ) );
		});
		if ( self.view_map_blocks_relation[ container.data( 'viewnumber' ) ] ) {
			affected_maps.push( self.view_map_blocks_relation[ container.data( 'viewnumber' ) ] );
		}
		affected_maps = _.uniq( affected_maps );
		return affected_maps;
	};

	// ------------------------------------
	// Clusters definitions and interactions
	// ------------------------------------

	/**
	 * WPViews.view_addon_maps.set_cluster_options
	 *
	 * Sets options for clusters, either global or for a specific map
	 *
	 * @param options	object
	 *		@param options.styles = [
	 * 			{
	 * 				url:		string		URL of the cluster image for this style,
	 * 				height:		integer		Width of the cluster image for this style,
	 * 				width:		integer		Height of the cluster image for this style,
	 * 				textColor:	string		(optional) Color of the counter text in this cluster, as a color name or hex value (with #),
	 * 				textSize	integer		(optional) Text size for the counter in this cluster, in px (without unit)
	 * 			},
	 * 			{ ... }
	 * 		]
	 * 		@param options.calculator = function( markers, numStyles ) {
	 * 			@param markers		array	Markers in this cluster
	 * 			@param numStyles	integer	Number of styles defined
	 * 			@return {
	 * 				text:	string		Text to be displayed inside the marker,
	 * 				index:	integer		Index of the options.styles array that will be applied to this cluster - please make it less than numStyles
	 * 			};
	 * 		}
	 * @param map_id		(optional) string The map ID this options will be binded to, global otherwise
	 *
	 * @note Most of the cluster options for a map are set in the map shortcode
	 * @note Maps without specific styling options will get the current global options
	 * @note We stoe the options in self.has_cluster_options for later usage, like on reload events
	 *
	 * @since 1.0
	 */

	self.set_cluster_options = function( options, map_id ) {
		if ( typeof map_id === 'undefined' ) {
			// If map_id is undefined, set global options
			if ( _.has( options , "styles" ) ) {
				self.default_cluster_options['styles'] = options['styles'];
			}
			if ( _.has( options , "calculator" ) ) {
				self.default_cluster_options['calculator'] = options['calculator'];
			}
		} else {
			// Otherwise, bind to a specific map ID
			// Note that defaults are also used
			self.cluster_options[ map_id ] = self.get_cluster_options( map_id );
			if ( _.has( options , "styles" ) ) {
				self.cluster_options[ map_id ]['styles'] = options['styles'];
			}
			if ( _.has( options , "calculator" ) ) {
				self.cluster_options[ map_id ]['calculator'] = options['calculator'];
			}
			self.has_cluster_options[ map_id ] = options;
		}
	};

	/**
	 * WPViews.view_addon_maps.get_cluster_options
	 *
	 * Gets options for clusters, either global of for a specific map
	 *
	 * @param map_id		(optional) string	The map ID to get options from
	 *
	 * @return options	object				Set of options, either global or dedicated if the passed map_id has specific options
	 *
	 * @since 1.0
	 */

	self.get_cluster_options = function( map_id ) {
		var options = self.default_cluster_options;
		if (
				typeof map_id !== 'undefined'
				&& _.has( self.cluster_options, map_id )
		) {
			options = self.cluster_options[ map_id ];
		}
		return options;
	};

	// ------------------------------------
	// Init
	// ------------------------------------

	/**
	 * Inits Google Maps API specific code paths
	 * @since 1.5
	 */
	self.initGoogle = function() {
		if ( self.api === null ) {
			if ( self.maps_data.length ) {
				self.maybeLazyLoadGoogle();
			}
			return;
		} else if ( self.api === false ) { // Lazy loading failed, don't get into an infinite loop.
			return;
		}

		window.addEventListener( "resize", function() {
			_.each(self.maps, function( map_iter, map_id ) {
				self.keep_map_center_and_resize( map_iter );
			});
		});

		$( document ).on( 'js_event_wpv_layout_responsive_resize_completed', function( event ) {
			$( '.js-wpv-layout-responsive .js-wpv-addon-maps-render' ).each( function() {
				var thiz = $( this ),
						thiz_map = thiz.data( 'map' );
				if ( thiz_map in self.maps ) {
					self.keep_map_center_and_resize( self.maps[ thiz_map ] );
				} else {
					self.resize_queue.push( thiz_map );
					_.uniq( self.resize_queue );
				}
			});
		});

		self.maybeInitElementorEditorPreviewFix( self.init_maps );

		self.init_maps();
	};

	/**
	 * Lazily loads the Azure Maps SDK if it's not already loaded and execute a callback upon completion
	 *
	 * This function checks if the Azure Maps SDK is currently loading or already loaded
	 * - If the SDK is loading, it attaches the provided callback to the `azureMapsLoaded` event
	 * - If the SDK is already loaded, it immediately invokes the callback
	 * - If the SDK is not loaded, it dynamically appends the necessary CSS and JS files to the document,
	 *   sets the loading state, and triggers the callback once loading is successful
	 *
	 * @param {Function} callback - The function to execute after the Azure Maps SDK has been loaded
	 *
	 */
	self.maybeLazyLoadAzure = function( callback ) {
		if ( self.azureLoading ) {
			// SDK is already loading, wait for it to load
			$( document ).on( 'azureMapsLoaded', callback );
			return;
		}

		if ( typeof atlas !== 'undefined' && typeof atlas.DataSource !== 'undefined' ) {
			// SDK is already loaded
			callback();
			return;
		}

		self.azureLoading = true;

		// Load Azure Maps CSS
		var link = document.createElement( 'link' );
		link.rel = 'stylesheet';
		link.href = 'https://atlas.microsoft.com/sdk/javascript/mapcontrol/2/atlas.min.css';
		document.head.appendChild(link);

		// Load Azure Maps JS
		$.getScript('https://atlas.microsoft.com/sdk/javascript/mapcontrol/2/atlas.min.js')
				.done(function() {
					self.azureLoading = false;
					$(document).trigger('azureMapsLoaded');
					callback();
				})
				.fail(function() {
					self.azureLoading = false;
					console.warn('Failed to load Azure Maps SDK');
				});
	};

	/**
	 * Inits Microsoft Azure Maps specific code paths
	 * @since 1.5
	 */
	self.initAzure = function() {
		if (typeof atlas === 'undefined') {
			self.maybeLazyLoadAzure(self.initMapsAzure);
		} else {
			self.initMapsAzure();
		}
		self.maybeInitElementorEditorPreviewFix( self.initMapsAzure );
	};

    /**
     * Initializes all OSM/Leaflet maps based on self.maps_data
     */
    self.initOsm = function() {
        // Check for dependencies
        if (typeof L === 'undefined') {
            console.warn('Leaflet (L) not found. Make sure Leaflet JS/CSS is enqueued.');
            return;
        }
        if (typeof _ === 'undefined') {
            console.error('Underscore.js is not loaded. Please include it before this script.');
            return;
        }

        // Loop over all maps
        _.each(self.maps_data, function(map) {
            self.initMapOsm(map);
        });
    };

	self.createOsmMarkerGroup = function (clusterOptions, cluster) {
		const clusteringEnabled = clusterOptions &&
			cluster === 'on' &&
			typeof L.markerClusterGroup === 'function';

		if (!clusteringEnabled) return L.featureGroup();

		const minClusterSize = parseInt(clusterOptions.minimumClusterSize, 10) || 2;

		const clusterGroup = L.markerClusterGroup({
			maxClusterRadius: parseInt(clusterOptions.gridSize, 10) || 60,
			disableClusteringAtZoom: clusterOptions.maxZoom ? parseInt(clusterOptions.maxZoom, 10) : undefined,
			zoomToBoundsOnClick: clusterOptions.zoomOnClick !== false,
			spiderfyOnMaxZoom: false,
			showCoverageOnHover: false,
			removeOutsideVisibleBounds: true,
			iconCreateFunction: function (cl) {
				const n = cl.getChildCount();
				// Hide icon for below-min clusters
				if (n < minClusterSize) {
					return new L.DivIcon({ html: '', className: 'leaflet-marker-cluster-hidden', iconSize: L.point(0, 0) });
				}

				// Normal cluster icon
				let iconNumber, iconSize;
				if (n < 10)        { iconNumber = 1; iconSize = 53; }
				else if (n < 100)  { iconNumber = 2; iconSize = 56; }
				else if (n < 1000) { iconNumber = 3; iconSize = 66; }
				else if (n < 10000){ iconNumber = 4; iconSize = 78; }
				else               { iconNumber = 5; iconSize = 90; }

				const url = views_addon_maps_i10n.cluster_default_imagePath + iconNumber + '.png';
				return new L.DivIcon({
					html: '<div style="position:relative;width:' + iconSize + 'px;height:' + iconSize + 'px;"><img src="' + url + '" class="leaflet-cluster-image"/><span class="leaflet-cluster-span">' + n + '</span></div>',
					className: 'marker-cluster-custom',
					iconSize: L.point(iconSize, iconSize),
					iconAnchor: L.point(iconSize / 2, iconSize / 2)
				});
			}
		});

		// Singles rendered as plain markers (FeatureGroup so it has getBounds)
		const singlesGroup = L.featureGroup();

		// Composite container (so your existing .addLayer/.fitBounds keep working)
		const group = L.featureGroup([clusterGroup, singlesGroup]);

		// Helpers
		const isCluster = (layer) =>
			layer && typeof layer.getChildCount === 'function' && typeof layer.getAllChildMarkers === 'function';

		function promoteSmallClusters() {
			if (!clusterGroup._featureGroup) return;
			const moveOut = [];
			clusterGroup._featureGroup.eachLayer((layer) => {
				if (isCluster(layer) && layer.getChildCount() < minClusterSize) {
					layer.getAllChildMarkers().forEach(m => moveOut.push(m));
				}
			});
			moveOut.forEach(m => {
				if (clusterGroup.hasLayer(m)) clusterGroup.removeLayer(m);
				if (!singlesGroup.hasLayer(m)) singlesGroup.addLayer(m);
			});
		}

		function reintegrateSingles() {
			const back = [];
			singlesGroup.eachLayer(m => back.push(m));
			back.forEach(m => {
				singlesGroup.removeLayer(m);
				clusterGroup.addLayer(m);
			});
		}

		group.addLayer = function (layer) {
			if (layer instanceof L.Marker || typeof layer.getLatLng === 'function') {
				clusterGroup.addLayer(layer);
			} else {
				singlesGroup.addLayer(layer);
			}
			return this;
		};
		group.removeLayer = function (layer) {
			if (singlesGroup.hasLayer(layer)) singlesGroup.removeLayer(layer);
			if (clusterGroup.hasLayer(layer)) clusterGroup.removeLayer(layer);
			return this;
		};
		group.clearLayers = function () { singlesGroup.clearLayers(); clusterGroup.clearLayers(); return this; };
		group.getBounds = function () {
			const b = L.latLngBounds();
			if (clusterGroup.getBounds) b.extend(clusterGroup.getBounds());
			if (singlesGroup.getBounds) b.extend(singlesGroup.getBounds());
			return b;
		};

		// Run after each recluster
		clusterGroup.on('clusteringend', () => {
			// Defer to ensure clusters are fully built
			requestAnimationFrame(() => promoteSmallClusters());
		});

		// Bind to the actual map automatically (no need for self.map)
		let boundMap = null;
		function bindMap(map) {
			if (boundMap === map) return;
			if (boundMap) {
				boundMap.off('zoomstart', reintegrateSingles);
				boundMap.off('movestart', reintegrateSingles);
				boundMap.off('zoomend', promoteSmallClusters);
				boundMap.off('moveend', promoteSmallClusters);
			}
			boundMap = map;
			if (map) {
				map.on('zoomstart', reintegrateSingles);
				map.on('movestart', reintegrateSingles);
				map.on('zoomend', promoteSmallClusters);
				map.on('moveend', promoteSmallClusters);
				// first pass once attached
				setTimeout(promoteSmallClusters, 0);
			}
		}

		group.on('add', () => bindMap(group._map || clusterGroup._map || singlesGroup._map || null));
		group.on('remove', () => bindMap(null));

		// no coverage polygons
		clusterGroup._originalShouldShowCoverageOnHover = clusterGroup._shouldShowCoverageOnHover;
		clusterGroup._shouldShowCoverageOnHover = function () { return false; };

		return group;
	};

    /**
     * Renders a single map with Leaflet using OSM tiles
     * @param {Object} map  - The map object from self.maps_data (map.options, map.markers, etc.)
     */
    self.initMapOsm = function(map) {
        if (self.maps[map.map]) {
            console.warn(`Map with ID ${map.map} is already initialized. Skipping.`);
            return;
        }

        // Check if 'map' and 'map.map' are defined
        if (!map || !map.map) {
            console.error('initOsm: Invalid map data received:', map);
            return;
        }

        // If there's no such element - skip
        const mapElement = document.getElementById('js-wpv-addon-maps-render-' + map.map);
        if (!mapElement) {
            console.warn(`Map element with ID js-wpv-addon-maps-render-${map.map} not found.`);
            return;
        }

        // Define default center
        let lat = parseFloat(map.options.general_center_lat) || 0;
        let lon = parseFloat(map.options.general_center_lon) || 0;
        let zoom = parseInt(map.options.general_zoom, 10) || 5;

        // Create the Leaflet map
        const osmMap = L.map(mapElement).setView([lat, lon], zoom);

        // Decide which tile layer to use
        let tileUrl = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
        let tileOpts = {
            attribution: "&copy; OpenStreetMap contributors",
            maxZoom: 19
        };
        switch ( map.options.osm_layer ) {
            case 'cyclosm':
                tileUrl = "https://{s}.tile-cyclosm.openstreetmap.fr/cyclosm/{z}/{x}/{y}.png";
                tileOpts = {
                    attribution: "&copy; CyclOSM, OpenStreetMap contributors",
                    maxZoom: 20
                };
                break;
            case 'hot':
                tileUrl = "https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png";
                tileOpts = {
                    attribution: "&copy; Humanitarian OpenStreetMap Team",
                    maxZoom: 19
                };
                break;
            case 'standard':
            default:
                // Use fallback
                break;
        }

        L.tileLayer(tileUrl, tileOpts).addTo(osmMap);

        // Optionally disable scroll/drag/zoom if "off" in the data
        if (map.options.scrollwheel === 'off') {
            osmMap.scrollWheelZoom.disable();
        }
        if (map.options.draggable === 'off') {
            osmMap.dragging.disable();
        }
        if (map.options.double_click_zoom === 'off') {
            osmMap.doubleClickZoom.disable();
        }

        // Save reference for later usage, e.g. self.get_map()
        self.maps[map.map] = osmMap;
        self.markers[map.map] = self.markers[map.map] || {};
        mapElement.classList.add('map-full-view');

        // Create marker group based on clustering settings
		let markerGroup = self.createOsmMarkerGroup(map.cluster_options, map.cluster_options ? map.cluster_options.cluster : 'off');

        // Add markers from map.markers
        _.each(map.markers, function(markerData) {
            let mLat = parseFloat(markerData.markerlat);
            let mLon = parseFloat(markerData.markerlon);

            if (!isNaN(mLat) && !isNaN(mLon)) {
                // Choose default icon
                let iconUrl = markerData.markericon || map.options.marker_icon || '';
                if (iconUrl === 'default') iconUrl = '';
                let markerOptions = {};

                if (iconUrl) {
                    markerOptions.icon = L.icon({
                        iconUrl: iconUrl,
                        iconSize:    [35, 45],
                        iconAnchor:  [20, 40],
                        popupAnchor: [0, -40]
                    });
                }

                // Create marker with normal icon or Leaflet's default
                let leafletMarker = L.marker([mLat, mLon], markerOptions);
                self.markers[map.map][ markerData.marker ] = leafletMarker;

                // If there's a popup
                if (markerData.markerinfowindow) {
                    leafletMarker.bindPopup(markerData.markerinfowindow);
                }

                // Enable hover icon if markericonhover is set
                if (map.options.marker_icon_use_hover === 'on') {
                    let hoverUrl = markerData.markericonhover || map.options.marker_icon_hover || '';
                    if (hoverUrl && hoverUrl !== 'default') {
                        let hoverIcon = L.icon({
                            iconUrl: hoverUrl,
                            iconSize:    [35, 45],
                            iconAnchor:  [20, 40],
                            popupAnchor: [0, -40]
                        });
                        // Keep the current icon as normalIcon
                        let normalIcon = leafletMarker.options.icon;

                        leafletMarker.on('mouseover', function() {
                            leafletMarker.setIcon(hoverIcon);
                        });
                        leafletMarker.on('mouseout', function() {
                            leafletMarker.setIcon(normalIcon);
                        });
                    }
                }

                // If there's marker popup content
                if (markerData.markerinfowindow) {
                    leafletMarker.bindPopup(markerData.markerinfowindow);
                }
                // Add marker to group
                leafletMarker.addTo(markerGroup);
            }
        });

        // Store the marker group reference for later updates
        self.markerGroups[map.map] = markerGroup;
        markerGroup.addTo(osmMap);

        const markerCount = map.markers.length;

        if (markerCount > 1) {
            // If fitbounds === 'on', just fit to all markers
            if (map.options.fitbounds === 'on') {
                osmMap.fitBounds(markerGroup.getBounds(), { padding: [50, 50] });
            } else {
                // Otherwise calculate average center
                let sumLat = 0;
                let sumLon = 0;
                let validCount = 0;

                _.each(map.markers, function(m) {
                    let lat = parseFloat(m.markerlat);
                    let lon = parseFloat(m.markerlon);
                    if (!isNaN(lat) && !isNaN(lon)) {
                        sumLat += lat;
                        sumLon += lon;
                        validCount++;
                    }
                });

                if (validCount > 0) {
                    let avgLat = sumLat / validCount;
                    let avgLon = sumLon / validCount;
                    // And apply multiple_zoom
                    let multipleZoom = parseInt(map.options.multiple_zoom, 10) || 5;
                    osmMap.setView([avgLat, avgLon], multipleZoom);
                }
            }
        } else if (markerCount === 1) {
            // Single marker => single_zoom
            let singleMarker = map.markers[0];
            let singleLat = parseFloat(singleMarker.markerlat);
            let singleLon = parseFloat(singleMarker.markerlon);
            let singleZoom = parseInt(map.options.single_zoom, 10) || 6;
            osmMap.setView([singleLat, singleLon], singleZoom);
        } else {
            // Zero markers
            osmMap.setView([0, 0], 2);
        }

        // Trigger an event similar to the other APIs
        $(document).trigger('js_event_wpv_addon_maps_init_map_completed', [{
            map_id: map.map,
            map_options: map.options
        }]);

		// Fixes the issues with missing tiles in the map - known OSM issue
		setTimeout(function() {
			osmMap.invalidateSize();
		}, 500);
    };

	/**
	 * Initialize maps on Elementor widget ready so it works when previewing an Elementor design.
	 *
	 * @since 1.5.3
	 *
	 * @param {Function} mapInitCallback
	 */
	self.maybeInitElementorEditorPreviewFix = function( mapInitCallback ) {
		if (typeof elementor === 'object' && typeof elementorFrontend === 'object') {
			elementorFrontend.hooks.addAction('frontend/element_ready/widget', function ($scope) {
				var mapInScope = $scope.find('div.js-wpv-addon-maps-render');
				if (mapInScope.length) {
					self.collect_maps_data();
					mapInitCallback();
				}
			});
		}
	};

	/**
	 * Initializes a single map by given id, and also cleans up if map with same id was already initialized.
	 *
	 * API agnostic.
	 *
	 * @since 1.7
	 * @param {string} id
	 */
	self.initMapById = function( id, attributes ) {
		self.clean_map_data( id );

		const mapData = self.collect_map_data( $( 'div#js-wpv-addon-maps-render-' + id ), attributes );

		// It might happen that this method was called, but the actual map wasn't rendered yet. In that case, it will
		// delay itself until the map HTML is ready.
		if ( ! mapData.map ) {
			_.delay( self.initMapById, 200, id, attributes );
			return;
		}

		if ( API_GOOGLE === views_addon_maps_i10n.api_used ) {
			self.maybeLazyLoadGoogle( self.init_map_after_loading_styles, mapData );
		} else if ( API_AZURE === views_addon_maps_i10n.api_used ) {
			self.maps[id] = self.resolveGeolocatedMarkerThenInitMapAzure( mapData );
		} else if ( API_OSM === views_addon_maps_i10n.api_used ) {
			self.initMapOsm( mapData );
		}
	};

	/**
	 * Updates a map instance based on the given ID and attributes
	 *
	 * @param {string} id - The unique identifier for the map instance to update
	 * @param {Object} attributes - An object containing attributes to update the map with
	 *
	 */
	self.updateMapById = function( id, attributes ) {
        var mapInstance = self.maps[ id ];
        if ( ! mapInstance ) {
            // Map doesn't exist, initialize it
            self.initMapById( id, attributes );
            return;
        }

        // On-the-fly update of the width and height of the map element
        const $mapElement = $( 'div#js-wpv-addon-maps-render-' + id );
        if ( attributes.mapWidth ) {
            $mapElement.css( 'width', attributes.mapWidth );
        }
        if ( attributes.mapHeight ) {
            $mapElement.css( 'height', attributes.mapHeight );
        }

        var mapData = self.collect_map_data( $( 'div#js-wpv-addon-maps-render-' + id ), attributes );
        if (attributes && mapData.cluster_options) {
            mapData.cluster_options.cluster = attributes.mapMarkerClustering ? 'on' : 'off';
            mapData.cluster_options.gridSize = parseInt(attributes.mapMarkerClusteringMinimalDistance) || 60;
            mapData.cluster_options.maxZoom = parseInt(attributes.mapMarkerClusteringMaximalZoomLevel) || null;
            mapData.cluster_options.zoomOnClick = attributes.mapMarkerClusteringClickZoom !== false;
            mapData.cluster_options.minimumClusterSize = parseInt(attributes.mapMarkerClusteringMinimalNumber) || 2;
        }

        if ( API_GOOGLE === views_addon_maps_i10n.api_used ) {
            self.updateGoogleMapInstance( mapInstance, mapData );
        } else if ( API_AZURE === views_addon_maps_i10n.api_used ) {
            // Ensure atlas is defined before updating
            if (typeof atlas === 'undefined' || typeof atlas.DataSource === 'undefined') {
                self.maybeLazyLoadAzure(function() {
                    self.updateAzureMapInstance(mapInstance, mapData);
                });
            } else {
                self.updateAzureMapInstance(mapInstance, mapData);
            }
        } else if ( API_OSM === views_addon_maps_i10n.api_used ) {
            self.updateMapOsmInstance( mapInstance, mapData );
        }
    };

	/**
	 * Updates Google map instance with new data
	 *
	 * @param {Object} mapInstance Google map instance
	 * @param {Object} mapData     Data for updating the map
	 *
	 */
	self.updateGoogleMapInstance = function(mapInstance, mapData) {

		// Update map options
		var mapOptions = {
			center: {
				lat: parseFloat( mapData.options.general_center_lat ) || mapInstance.getCenter().lat(),
				lng: parseFloat( mapData.options.general_center_lon ) || mapInstance.getCenter().lng()
			},
			mapTypeId: mapData.options.map_type || mapInstance.getMapTypeId(),
			draggable: mapData.options.draggable !== 'off',
			scrollwheel: mapData.options.scrollwheel !== 'off',
			disableDoubleClickZoom: mapData.options.double_click_zoom === 'off',
			mapTypeControl: mapData.options.map_type_control !== 'off',
			fullscreenControl: mapData.options.full_screen_control !== 'off',
			zoomControl: mapData.options.zoom_control !== 'off',
			streetViewControl: mapData.options.street_view_control !== 'off',
			styles: mapData.options.style_json || mapInstance.get('styles'),
			backgroundColor: mapData.options.background_color || mapInstance.get( 'backgroundColor' )
		};

		// Function to set map options after styles are loaded
		var setMapOptions = function() {
			mapInstance.setOptions( mapOptions );
		};

		// Handle styles
		if ( mapData.options.styles ) {
			mapOptions.styles = mapData.options.styles;
			setMapOptions();
		} else if ( mapData.options.style_json ) {
			// Load styles from the JSON file
			$.getJSON( mapData.options.style_json )
					.done( function( styles ) {
						mapData.options.styles = styles;
						mapOptions.styles = styles;
					} )
					.fail( function() {
						console.warn('Failed to load map styles from', mapData.options.style_json);
					} )
					.always( function() {
						setMapOptions();
					} );
		} else {
			mapOptions.styles = mapInstance.get( 'styles' );
			setMapOptions();
		}

		// Only update zoom if it has changed and multiple_zoom logic
		const currentZoom = mapInstance.getZoom();
		if ( mapData.options.general_zoom !== currentZoom ) {
			mapInstance.setZoom( parseInt( mapData.options.general_zoom ) || currentZoom );
		}

		// Handle fitBounds for multiple markers
		if ( mapData.options.fitbounds === 'on' && Object.keys( self.markers[ mapData.map ] ).length > 1 ) {
			const bounds = new google.maps.LatLngBounds();
			Object.values( self.markers[mapData.map] ).forEach( marker => {
				bounds.extend( marker.getPosition() );
			});
			mapInstance.fitBounds( bounds );
		} else if ( Object.keys( self.markers[ mapData.map ] ).length > 1 && mapData.options.multiple_zoom ) {
			// Apply multiple_zoom if fitbounds is not enabled
			mapInstance.setZoom( parseInt( mapData.options.multiple_zoom ) || mapInstance.getZoom() );
		}

		// Handle single marker settings (single_center and single_zoom)
		if ( Object.keys( self.markers[mapData.map] ).length === 1 ) {
			const markerPosition = Object.values( self.markers[mapData.map] )[0].getPosition();
			if ( mapData.options.single_center === 'on' ) {
				mapInstance.setCenter( markerPosition );
			}
			mapInstance.setZoom( parseInt( mapData.options.single_zoom ) || mapInstance.getZoom() );
		}

		// Handle marker clustering if enabled
		if ( mapData.options.cluster === 'on' ) {
			// Check if MarkerClusterer is available
			if ( typeof MarkerClusterer === 'undefined' ) {
				console.debug( 'MarkerClusterer library is not loaded. Clustering will not be available.' );
				return;
			}

			const clusterOptions = {
				gridSize: parseInt( mapData.options.clustergridsize ),
				maxZoom: parseInt( mapData.options.clustermaxzoom ),
				zoomOnClick: mapData.options.clusterclickzoom !== 'off',
				minimumClusterSize: parseInt( mapData.options.clusterminsize )
			};

			if ( self.markerClusterer ) {
				self.markerClusterer.clearMarkers(); // Clear existing clusters
			}

			self.markerClusterer = new MarkerClusterer( mapInstance, Object.values( self.markers[ mapData.map ] ), clusterOptions );
		} else if ( self.markerClusterer ) {
			// Remove clustering and restore markers to the map
			self.markerClusterer.clearMarkers();
			self.markerClusterer.setMap( null );
			self.markerClusterer = null;

			// Re-add all markers directly to the map
			Object.values( self.markers[ mapData.map ] ).forEach( function( marker ) {
				marker.setMap( mapInstance );
			});
		}

		// Handle markers
		if ( mapData.markers && self.markers[ mapData.map ] ) {
			mapData.markers.forEach( function( markerData ) {
				var markerId = markerData.marker;
				var marker = self.markers[ mapData.map ][ markerId ];
				if ( marker ) {
					// Determine the marker icon and hover icon
					var markerIcon = markerData.markericon || mapData.options.marker_icon || '';
					var markerIconHover = markerData.markericonhover || mapData.options.marker_icon_hover || markerIcon;

					// Set the marker's title
					marker.setTitle( markerData.title || '' );

					// Update the marker's icon
					if ( markerIcon ) {
						marker.setIcon( {
							url: markerIcon,
						} );
					} else {
						// Reverts to default marker if markerIcon is empty
						marker.setIcon( null );
					}

					// Remove existing event listeners to avoid duplicates
					google.maps.event.clearListeners( marker, 'mouseover' );
					google.maps.event.clearListeners( marker, 'mouseout' );

					// Add hover events if necessary
                    if (
                        mapData.options.marker_icon_use_hover === 'on' &&
                        markerIcon !== markerIconHover
                    ) {
						marker.addListener( 'mouseover', function() {
							marker.setIcon( {
								url: markerIconHover
							} );
						});
						marker.addListener( 'mouseout', function() {
							marker.setIcon( {
								url: markerIcon
							} );
						});
					}
				}
			});
		}

		// Handle enabling or disabling Street View mode
		if ( mapData.options.street_view === 'on' ) {
			const streetViewService = new google.maps.StreetViewService();
			const center = mapInstance.getCenter();
			streetViewService.getPanorama( { location: center, radius: 50 }, function( panoramaData, status ) {
				if ( status === google.maps.StreetViewStatus.OK ) {
					const streetView = mapInstance.getStreetView();
					streetView.setPosition( panoramaData.location.latLng );
					streetView.setPov( {
						heading: parseFloat( mapData.options.heading ) || 0,
						pitch: parseFloat( mapData.options.pitch ) || 0
					} );
					streetView.setVisible( true );
				} else {
					console.warn( 'Street View data not found for this location.' );
					const streetView = mapInstance.getStreetView();
					streetView.setVisible( false );
				}
			});
		} else {
			const streetView = mapInstance.getStreetView();
			streetView.setVisible( false );
		}
	};

	/**
	 * Updates Azure map instance with new data and markers, keeping the previous zoom level.
	 *
	 * @param {Object} mapInstance Azure map instance
	 * @param {Object} mapData     Data for updating the map
	 *
	 */
	self.updateAzureMapInstance = function( mapInstance, mapData ) {

		// Capture the current zoom level before reinitializing the map
		let previousZoom = mapInstance.getCamera().zoom;

		// Re-initialize the map and capture the new instance
		let newMapInstance = self.initMapAzure( mapData );

		// Restore the previous zoom level if necessary
		newMapInstance.setCamera( {
			zoom: previousZoom
		} );

		// Use DOM elements to gather markers
		let thiz_map_id = mapData.map;
		let thiz_map_points = [];

		// Iterate over each marker DOM element associated with this map ID
		$( '.js-wpv-addon-maps-markerfor-' + thiz_map_id ).each( function()  {
			let thiz_marker = $( this );

			// Collect marker data from the DOM element
			let markerLat = parseFloat( thiz_marker.data('markerlat') );
			let markerLon = parseFloat( thiz_marker.data('markerlon') );

			// Skip markers without valid coordinates
			if ( !markerLat || !markerLon || markerLat === 'geo' ) {
				// Skipping marker due to invalid lat/lon or geo position
				return true;
			}

			thiz_map_points.push( {
				'marker': thiz_marker.data( 'marker' ),
				'title': thiz_marker.data( 'markertitle' ),
				'markerlat': markerLat,
				'markerlon': markerLon,
				'markerinfowindow': thiz_marker.html(),
				'markericon': thiz_marker.data( 'markericon' ),
				'markericonhover': thiz_marker.data( 'markericonhover' )
			} );
		});

		// Update mapData with collected markers
		mapData.markers = thiz_map_points;

		// Prepare the data needed for reinitialization
		let newMapData = {
			map: mapData.map,
			markers: mapData.markers,  // Use the newly collected markers
			options: {
				general_center_lon: mapData.options.general_center_lon,
				general_center_lat: mapData.options.general_center_lat,
				general_zoom: mapData.options.general_zoom,
				single_center: mapData.options.single_center,
				single_zoom: mapData.options.single_zoom,
				scrollwheel: mapData.options.scrollwheel,
				double_click_zoom: mapData.options.double_click_zoom,
				draggable: mapData.options.draggable,
				map_type: mapData.options.map_type,
				styles: mapData.options.styles,
				zoom_control: mapData.options.zoom_control
			}
		};

		// Call the existing initMapAzure() to reinitialize the map
		self.initMapAzure( newMapData );

		// Update markers
		if ( newMapData.markers.length > 0 ) {
			let bounds = new atlas.data.BoundingBox.fromPositions(
					newMapData.markers.map( marker => [ parseFloat( marker.markerlon ), parseFloat( marker.markerlat ) ] )
			);

			newMapData.markers.forEach( ( marker, index) => {
				if ( marker.markerlat && marker.markerlon ) {
					// Define the marker icon with hover and normal icons
					let icon = marker.markericon || '';
					let iconHover = marker.markericonhover || icon;

					let mapMarker = new atlas.HtmlMarker({
						position: [ marker.markerlon, marker.markerlat ],
						text: marker.title || 'Marker',
						htmlContent: `<img src="${icon}" class="marker-icon" />`
					});

					// Add marker to the map
					mapInstance.markers.add( mapMarker );

					// Add hover effect
					mapMarker.getElement().addEventListener( 'mouseover', function() {
						mapMarker.setOptions( {
							htmlContent: `<img src="${iconHover}" class="marker-icon-hover" />`
						} );
					});

					mapMarker.getElement().addEventListener( 'mouseout', function() {
						mapMarker.setOptions( {
							htmlContent: `<img src="${icon}" class="marker-icon" />`
						} );
					});
				}
			});

			// Handle zoom for one marker or multiple markers
			if ( newMapData.markers.length === 1 ) {
				// Single marker - use a predefined zoom level
				let singleMarker = newMapData.markers[0];
				mapInstance.setCamera( {
					center: [ singleMarker.markerlon, singleMarker.markerlat ],
					zoom: newMapData.options.single_zoom || 15
				} );
			} else if ( bounds && bounds.southWest && bounds.northEast ) {
				// Multiple markers - fit bounds
				mapInstance.setCamera( {
					bounds: bounds,
					padding: 20
				} );
			} else {
				// Invalid bounds - setting default center and zoom
				mapInstance.setCamera( {
					center: [ parseFloat( mapData.options.general_center_lon ), parseFloat( mapData.options.general_center_lat ) ],
					zoom: mapData.options.general_zoom || 10
				});
			}
		}
		self.resolveGeolocatedMarkerThenInitMapAzure( mapData );
	};

    /**
     * Updates Leaflet (OSM) map instance with new data
     *
     * @param {Object} mapInstance Leaflet map instance
     * @param {Object} mapData     Data for updating the map
     */
    self.updateMapOsmInstance = function( mapInstance, mapData ) {

        // Remove previous tile layers
        mapInstance.eachLayer( function( layer ) {
            if ( layer instanceof L.TileLayer ) {
                mapInstance.removeLayer( layer );
            }
        } );

        // Choose which tile layer to use
        let tileUrl = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"; // fallback
        let tileOpts = {
            attribution: "&copy; OpenStreetMap contributors",
            maxZoom: 19
        };

        switch ( mapData.options.osm_layer ) {
            case 'cyclosm':
                tileUrl = "https://{s}.tile-cyclosm.openstreetmap.fr/cyclosm/{z}/{x}/{y}.png";
                tileOpts = {
                    attribution: "&copy; CyclOSM, OpenStreetMap contributors",
                    maxZoom: 20
                };
                break;
            case 'hot':
                tileUrl = "https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png";
                tileOpts = {
                    attribution: "&copy; Humanitarian OpenStreetMap Team",
                    maxZoom: 19
                };
                break;
            case 'standard':
            default:
                // use fallback
                break;
        }

        // Set the new tile layer
        L.tileLayer( tileUrl, tileOpts ).addTo( mapInstance );

        // Update map center and zoom
        if ( mapData.options.general_center_lat && mapData.options.general_center_lon ) {
            var newCenter = [ parseFloat( mapData.options.general_center_lat ), parseFloat( mapData.options.general_center_lon ) ];
            mapInstance.setView( newCenter, parseInt( mapData.options.general_zoom, 10 ) || mapInstance.getZoom() );
        }

        // Optionally disable scroll/drag/zoom based on new options
        if ( mapData.options.scrollwheel === 'off' ) {
            mapInstance.scrollWheelZoom.disable();
        } else {
            mapInstance.scrollWheelZoom.enable();
        }

        if ( mapData.options.draggable === 'off' ) {
            mapInstance.dragging.disable();
        } else {
            mapInstance.dragging.enable();
        }

        if ( mapData.options.double_click_zoom === 'off' ) {
            mapInstance.doubleClickZoom.disable();
        } else {
            mapInstance.doubleClickZoom.enable();
        }

        // Update markers
        if ( self.markers[mapData.map] ) {
            Object.keys( self.markers[mapData.map] ).forEach( function( markerId ) {
                var oldMarker = self.markers[ mapData.map ][ markerId ];
                mapInstance.removeLayer( oldMarker );
            });
        }
                
        // Remove the existing marker group (if any)
        if ( self.markerGroups[mapData.map] ) {
            mapInstance.removeLayer( self.markerGroups[mapData.map] );
            delete self.markerGroups[mapData.map];
        }
        
        // Reset self.markers[mapData.map] to an empty object
        self.markers[ mapData.map ] = {};

        // Add new markers based on clustering settings
		let markerGroup = self.createOsmMarkerGroup(mapData.cluster_options, mapData.cluster_options ? mapData.cluster_options.cluster : 'off');

        _.each( mapData.markers, function( markerData ) {
            var mLat = parseFloat( markerData.markerlat );
            var mLon = parseFloat( markerData.markerlon );

            if ( !isNaN( mLat ) && !isNaN( mLon ) ) {
                // Handle marker icon
                let iconUrl = markerData.markericon || mapData.options.marker_icon || '';
                if ( iconUrl === 'default' ) iconUrl = '';

                let markerOptions = {};
                if ( iconUrl ) {
                    markerOptions.icon = L.icon( {
                        iconUrl:     iconUrl,
                        iconSize:    [35, 45],
                        iconAnchor:  [20, 40],
                        popupAnchor: [0, -40]
                    } );
                }

                let leafletMarker = L.marker( [ mLat, mLon ], markerOptions );

                // Store by unique ID
                self.markers[ mapData.map ][ markerData.marker ] = leafletMarker;

                // If there's popup content
                if ( markerData.markerinfowindow ) {
                    leafletMarker.bindPopup( markerData.markerinfowindow );
                }

                // Handle marker hover icons
                if ( mapData.options.marker_icon_use_hover === 'on' ) {
                    let hoverUrl = markerData.markericonhover || mapData.options.marker_icon_hover || '';
                    if ( hoverUrl && hoverUrl !== 'default' ) {
                        let hoverIcon = L.icon( {
                            iconUrl:     hoverUrl,
                            iconSize:    [35, 45],
                            iconAnchor:  [20, 40],
                            popupAnchor: [0, -40]
                        } );
                        let normalIcon = leafletMarker.options.icon; // might be undefined if no icon

                        leafletMarker.on( 'mouseover', function() {
                            leafletMarker.setIcon( hoverIcon );
                        } );
                        leafletMarker.on( 'mouseout', function() {
                            leafletMarker.setIcon( normalIcon || L.marker([0,0]).options.icon );
                        } );
                    }
                }

                // Finally add the marker to markerGroup
                leafletMarker.addTo( markerGroup );
            } else {
                console.warn( `Invalid marker coordinates: lat=${markerData.markerlat}, lon=${markerData.markerlon}` );
            }
        });

        // Store the marker group reference for later updates
        self.markerGroups[mapData.map] = markerGroup;
        markerGroup.addTo( mapInstance );

        // Fit bounds if necessary
        const markerCount = mapData.markers.length;

        if ( markerCount > 1 ) {
            // If fitbounds is on - do fitBounds
            if ( mapData.options.fitbounds === 'on' ) {
                mapInstance.fitBounds( markerGroup.getBounds(), { padding: [50, 50] } );
            } else {
                // Calculate average center and use multiple_zoom
                let sumLat = 0;
                let sumLon = 0;
                let validCount = 0;

                _.each(mapData.markers, function( m ) {
                    let lat = parseFloat( m.markerlat );
                    let lon = parseFloat( m.markerlon );
                    if ( !isNaN( lat ) && !isNaN( lon) ) {
                        sumLat += lat;
                        sumLon += lon;
                        validCount++;
                    }
                });

                if ( validCount > 0 ) {
                    let avgLat = sumLat / validCount;
                    let avgLon = sumLon / validCount;
                    let multipleZoom = parseInt( mapData.options.multiple_zoom, 10 ) || mapInstance.getZoom();
                    mapInstance.setView( [avgLat, avgLon], multipleZoom );
                }
            }
        } else if (markerCount === 1) {
            // Single marker => single_zoom
            let singleMarker = mapData.markers[ 0 ];
            let singleLat = parseFloat( singleMarker.markerlat );
            let singleLon = parseFloat( singleMarker.markerlon );
            let singleZoom = parseInt( mapData.options.single_zoom, 10 ) || mapInstance.getZoom();
            mapInstance.setView( [singleLat, singleLon], singleZoom );
        } else {
            // Zero markers => fallback
            mapInstance.setView( [0,0], 2 );
        }

        // Trigger event
        $(document).trigger( 'js_event_wpv_addon_maps_init_map_completed', [ {
            map_id: mapData.map,
            map_options: mapData.options
        } ] );

		// Fixes the issues with missing tiles in the map - known OSM issue
		setTimeout(function() {
			mapInstance.invalidateSize();
		}, 500);

    };

	/**
	 * Lazy load Google libraries and other libraries depending on it, if needed.
	 * @param {Function} initMap
	 * @param {object} mapData Callback parameter.
	 */
	self.maybeLazyLoadGoogle = function( initMap = null, mapData = null ) {
		if ( self.api === null ) {
			$.getScript(
					`https://maps.googleapis.com/maps/api/js?libraries=places&v=3&key=${ views_addon_maps_i10n.google_api_key }&callback=Function.prototype`
			)
					.done( function() {
						self.api = window.google.maps;
						self.initGoogle(); // Do full init, because only now we have the API ready.
					} )
					.fail( function() {
						self.api = false;
						console.warn( 'Toolset Maps: failed to lazy load Google Maps library.' );
					} );
		} else {
			initMap( mapData );
			// This is emitted only by collect_maps_data, not by the single map data collector that's used here, and is
			// needed by add_current_visitor_location_after_geolocation in order to run at the right time. (Google API
			// only).
			$( document ).trigger('js_event_wpv_addon_maps_map_data_collected');
		}
	}

	/**
	 * Observes the DOM for changes and initializes the appropriate map API
	 * (Google Maps or Azure Maps) once map data becomes available.
	 *
	 * This function sets up a MutationObserver on the document body to monitor
	 * for any additions or modifications. When map data is detected, it
	 * collects the data, stops observing further changes, and initializes
	 * the selected map API based on the configuration.
	 *
	 */
    self.waitForMapData = function() {
        const targetNode = document.body;
        const config = { childList: true, subtree: true };

        const callback = function( mutationsList, observer ) {
            self.collect_maps_data();
            if ( self.maps_data.length > 0 ) {
                observer.disconnect();
                if (API_GOOGLE === views_addon_maps_i10n.api_used) {
                    self.initGoogle();
                } else if (API_AZURE === views_addon_maps_i10n.api_used) {
                    self.initAzure();
                } else if (API_OSM === views_addon_maps_i10n.api_used) {
                    self.initOsm();
                }
            }
        };

        const observer = new MutationObserver( callback );
        observer.observe( targetNode, config );
    };


	self.init = function() {
		self.collect_maps_data();
		// If no map data collected yet, set up observer
		if ( self.maps_data.length === 0 ) {
			self.waitForMapData();
			return;
		}

        if (API_GOOGLE === views_addon_maps_i10n.api_used) {
            self.initGoogle();
        } else if (API_AZURE === views_addon_maps_i10n.api_used) {
            self.initAzure();
        } else if (API_OSM === views_addon_maps_i10n.api_used) {
            self.initOsm();
        }
	};

	self.init();
};

jQuery( function( $ ) {
	WPViews.view_addon_maps = new WPViews.ViewAddonMaps( $ );
});
