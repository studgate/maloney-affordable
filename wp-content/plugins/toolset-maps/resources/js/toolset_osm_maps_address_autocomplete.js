/* eslint-disable */

var WPViews = WPViews || {};

/**
 * Address autocomplete component. Uses OpenStreetMap Nominatim + Leaflet for map previews.
 *
 * @param {jQuery} $
 * @constructor
 * @since 1.5
 */
WPViews.MapsAddressAutocomplete = function( $ ) {
	"use strict";

	let self = this;

	/** @var {Object} toolset_maps_address_autocomplete_i10n */

	const ADDRESS_AUTOCOMPLETE_SELECTOR = '.js-toolset-maps-address-autocomplete';
	const AUTOCOMPLETE_INITED_SELECTOR = '.ui-autocomplete-input';

	const AUTOCOMPLETE_SETTINGS = {
		source: function (request, response) {
			// Nominatim endpoint for forward geocoding
			// We set `addressdetails=1&format=jsonv2` for more complete data
			$.ajax({
				url: "https://nominatim.openstreetmap.org/search",
				dataType: "json",
				data: {
					q: request.term,
					format: "jsonv2",
					addressdetails: 1,
					limit: 10
				},
				success: function(data) {
					// Each result from Nominatim contains lat, lon, display_name, etc.
					// Build a list of addresses and store lat/lon in self.latLngCache
					let suggestions = [];
					self.latLngCache = {};

					data.forEach(function(item) {
						let label = item.display_name; // Full address string
						suggestions.push({
							label: label,
							value: label
						});
						self.latLngCache[label] = {
							lat: item.lat,
							lon: item.lon
						};
					});

					response(suggestions);
				}
			});
		},
		minLength: 2,
		select: function( event, ui ) {
			let $container = $( event.target ).closest('.js-toolset-google-map-inputs-container');

			// If there is a container, update lat/lon and map.
			if ( $container.length ) {
				let position = self.latLngCache[ui.item.value];

				self.updateLatlonValues(
					$container,
					position.lat,
					position.lon,
					'address'
				);
			}
		},
		open: function() {
			$( this )
				.data( 'uiAutocomplete' )
				.menu.element.addClass( 'toolset-maps-address-autocomplete-ui-menu' );
		},
	};

	// Class selectors to match Toolset’s structure.
	const MAP_EDITOR_CONTAINER = '.js-toolset-google-map-container';
	const MAP_EDITOR_INPUTS_CONTAINER = '.js-toolset-google-map-inputs-container';

	// Latitude and longitude validation regex
	self.validateLat = /^(-?([0-9]|8[0-4]|[1-7][0-9])(\.{1}\d{1,20})?)$/;
	self.validateLon = /^-?([0-9]|[1-9][0-9]|1[0-7][0-9]|180)(\.{1}\d{1,20})?$/;

	// Counters & storages
	self.mapCounter = 0;
	self.maps = {};        // Keep track of Leaflet map instances by ID
	self.markers = {};     // Keep track of marker references
	self.latLngCache = {}; // Store lat/lon from autocomplete

	/**
	 * Helper: Validate latitude
	 * @param {Number} lat
	 * @return {boolean}
	 */
	self.isValidLatitude = function( lat ) {
		return self.validateLat.test( lat );
	};

	/**
	 * Helper: Validate longitude
	 * @param {Number} lon
	 * @return {boolean}
	 */
	self.isValidLongitude = function( lon ) {
		return self.validateLon.test( lon );
	};

	/**
	 * Checks if current page is on a secure connection.
	 *
	 * @return {boolean}
	 *
	 * @since 1.5.3
	 */
	self.isSecurePage = function () {
		return ( location.protocol === 'https:' );
	};

	/**
	 * Inits event bindings
	 */
	self.initEvents = function() {
		// 1) On Toolset’s dynamic loading events
		$( document ).on( 'toolset_ajax_fields_loaded', function( event, form ) {
			self.initFieldsInsideContainer( $( 'form#' + form.form_id ) );
			self.initMapEditorComponentsInsideContainer( $( 'form#' + form.form_id ) );
		});

		// 2) On repeating field group item toggles
		$( document ).on( 'toolset_types_rfg_item_toggle', function( event, item ) {
			if ( item.visible() ) {
				self.initAllFields();
				self.initMapEditorComponents();
			}
		});

		// 3) On repetitive field additions
		$( document ).on( 'toolset_repetitive_field_added', function( event, parent ) {
			self.initFieldsInsideContainer( $( parent ) );
			self.initMapEditorComponentsInsideContainer( $( parent ) );
		});

		// 4) Show/hide lat/lon
		$( document ).on( 'click', '.js-toolset-google-map-toggle-latlon', function( event ) {
			event.preventDefault();
			let $this = $( this );
			let $container = $this.closest( '.js-toolset-google-map-inputs-container' );
			$container.find( '.js-toolset-google-map-toggling-latlon' ).slideToggle( 'fast' );
		});

		// 5) Update lat/lon inputs => updates map
		$( document ).on( 'input cut paste', '.js-toolset-google-map-latlon', function() {
			let $container = $( this ).closest( '.js-toolset-google-map-inputs-container' ),
				latVal = $container.find( '.js-toolset-google-map-lat' ).val(),
				lonVal = $container.find( '.js-toolset-google-map-lon' ).val();

			self.updateLatlonValues( $container, latVal, lonVal, 'address' );
		});

		// 6) If address field is typed as "{lat,lon}", parse it
		$( document ).on( 'input cut paste', ADDRESS_AUTOCOMPLETE_SELECTOR, function() {
			let $this = $( this );
			let thisVal = $this.val();

			if ( thisVal && thisVal.match("^{") && thisVal.match("}$") ) {
				let coords = thisVal.slice( 1, -1 ).split( ',' );
				if ( coords.length === 2 ) {
					let $container = $this.closest( '.js-toolset-google-map-inputs-container' );
					self.updateLatlonValues( $container, coords[0], coords[1], 'latlon' );
				}
			}
		});

		// 7) Use visitor location if geolocation + HTTPS
		$( document ).on( 'click', '.js-toolset-google-map-use-visitor-location', function( event ) {
			event.preventDefault();
			let $container = $( this ).closest('.js-toolset-google-map-inputs-container');

			if (navigator.geolocation) {
				navigator.geolocation.getCurrentPosition(
					function (position) {
						self.updateLatlonValues(
							$container,
							position.coords.latitude,
							position.coords.longitude,
							'both'
						);
					},
					function (err) {
						console.warn( err.message );
					}
				);
			}
		});

		// 8) "Use this address" from reverse-geocoding preview
		$( document ).on( 'click', '.js-toolset-google-map-preview-closest-address-apply', function( event ) {
			event.preventDefault();

			let $this = $( this ),
				$thisContainer = $this.closest( '.js-toolset-google-map-container' ),
				$addressEl = $thisContainer.find( '.js-toolset-google-map-preview-closest-address-value' ),
				$lat = $thisContainer.find( '.js-toolset-google-map-lat' ),
				$lon = $thisContainer.find( '.js-toolset-google-map-lon' ),
				$address = $thisContainer.find( '.js-toolset-google-map' );

			let latVal = $addressEl.data('lat');
			let lonVal = $addressEl.data('lon');
			let displayName = $addressEl.text();

			$lat.val( latVal );
			$lon.val( lonVal );
			$address
				.val( displayName )
				.data( 'coordinates', "{" + latVal + ',' + lonVal + "}" )
				.trigger( 'js_event_toolset_latlon_values_updated' );

			self.glowSelectors( $address, 'toolset-being-updated' );
			self.glowSelectors( $lat, 'toolset-being-updated' );
			self.glowSelectors( $lon, 'toolset-being-updated' );

			$thisContainer
				.find( '.toolset-google-map-preview-closest-address' )
				.slideUp( 'fast' );
		});

		// 9) Re-init fields after CRED Ajax forms
		$( document ).on( 'js_event_cred_ajax_form_response_completed', function() {
			self.initAllFields();
			self.initMapEditorComponents();
		});
	};

	/**
	 * Update lat/lon values consistently in the container, plus any map marker.
	 *
	 * @param {jQuery} $container
	 * @param {string|number} latVal
	 * @param {string|number} lonVal
	 * @param {string} updateMainTarget
	 */
	self.updateLatlonValues = function( $container, latVal, lonVal, updateMainTarget ) {
		let $lat = $container.find('.js-toolset-google-map-lat'),
			$lon = $container.find('.js-toolset-google-map-lon'),
			$address = $container.find('.js-toolset-google-map'),
			$toggling = $container.find('.js-toolset-google-map-toggling-latlon');

		$container
			.find('.js-toolset-latlon-error')
			.removeClass('toolset-latlon-error js-toolset-latlon-error');

		// Validate
		if ( ! self.isValidLatitude( latVal ) ) {
			$lat.addClass('toolset-latlon-error js-toolset-latlon-error');
			$address.trigger('js_event_toolset_latlon_values_error');
			return;
		}
		if ( ! self.isValidLongitude( lonVal ) ) {
			$lon.addClass('toolset-latlon-error js-toolset-latlon-error');
			$address.trigger('js_event_toolset_latlon_values_error');
			return;
		}

		// Set them
		$lat.val( latVal );
		$lon.val( lonVal );

		$address
			.val( "{" + latVal + ',' + lonVal + "}" )
			.data( 'coordinates', "{" + latVal + ',' + lonVal + "}" )
			.trigger( 'js_event_toolset_latlon_values_updated' );

		// Update the map if it exists
		let mapId = $container.siblings('.js-toolset-google-map-preview').first().data('id');
		if ( typeof mapId !== 'undefined' ) {
			self.movePin( self.maps[mapId], [ latVal, lonVal ], mapId );
		}

		// Visual feedback
		if ( updateMainTarget === 'address' ) {
			self.glowSelectors( $address, 'toolset-being-updated' );
		} else if ( updateMainTarget === 'latlon' ) {
			$toggling.slideDown( 'fast', function() {
				self.glowSelectors( $lat, 'toolset-being-updated' );
				self.glowSelectors( $lon, 'toolset-being-updated' );
			});
		} else if ( updateMainTarget === 'both' ) {
			$toggling.slideDown( 'fast', function() {
				self.glowSelectors( $address, 'toolset-being-updated' );
				self.glowSelectors( $lat, 'toolset-being-updated' );
				self.glowSelectors( $lon, 'toolset-being-updated' );
			});
		}
	};

	/**
	 * Glow a given set of elements
	 *
	 * @param {jQuery} selectors
	 * @param {string} reason
	 */
	self.glowSelectors = function( selectors, reason ) {
		$( selectors ).addClass( reason );
		setTimeout( function () {
			$( selectors ).removeClass( reason );
		}, 500 );
	};

	/**
	 * Init a single address-autocomplete field
	 * @param {jQuery} $field
	 */
	self.initField = function( $field ) {
		if ( ! $field.hasClass( AUTOCOMPLETE_INITED_SELECTOR ) ) {
			$field.autocomplete( AUTOCOMPLETE_SETTINGS );
		}
	};

	/**
	 * Init all fields inside a container
	 * @param {jQuery} $container
	 */
	self.initFieldsInsideContainer = function( $container ) {
		$container
			.find( ADDRESS_AUTOCOMPLETE_SELECTOR )
			.not( AUTOCOMPLETE_INITED_SELECTOR )
			.autocomplete( AUTOCOMPLETE_SETTINGS );
	};

	/**
	 * Init all fields in the document
	 */
	self.initAllFields = function() {
		self.initFieldsInsideContainer( $( document ) );
	};

	/**
	 * Inits all the map editor components (lat/lng fields, map preview) inside the document
	 */
	self.initMapEditorComponents = function() {
		self.initMapEditorComponentsInsideContainer( $( document ) );
	};

	/**
	 * Inits map editor components (map preview, lat/lng toggles, etc.) inside a given container
	 * @param {jQuery} $container
	 */
	self.initMapEditorComponentsInsideContainer = function( $container ) {
		// 1) For each map container, add a Leaflet map preview if not already added
		$container
			.find( MAP_EDITOR_CONTAINER )
			.each( function() {
				let $innerContainer = $( this );
				let $previewContainer = $innerContainer.children( '.js-toolset-google-map-preview' );

				if ( !$previewContainer.length ) {
					let $inputsContainer = $innerContainer
						.children(MAP_EDITOR_INPUTS_CONTAINER)
						.first();

					let latLng = self.getLatLngFromAutocomplete( $inputsContainer );
					$innerContainer.append(
						self.getPreviewStructure( self.mapCounter )
					);

					// Load Leaflet if needed, then init map
					if ( typeof L === 'undefined' ) {
						self.delayedLazyLoadLeaflet( latLng );
					} else {
						self.addMapPreviewToMaps( latLng );
					}
				}
			});

		// 2) For each input container, add lat/lon toggles if not already inited
		$container
			.find( MAP_EDITOR_INPUTS_CONTAINER )
			.not( '.js-toolset-google-map-inputs-container-inited' )
			.each( function() {
				let $innerContainer = $( this );
				let latLng = self.getLatLngFromAutocomplete( $innerContainer );

				$innerContainer.append(
					self.getInputsStructure( latLng[0], latLng[1] )
				);

				$innerContainer.addClass(
					'js-toolset-google-map-inputs-container-inited'
				);
			});
	};

	/**
	 * Extract lat-lon from data-coordinates in the .js-toolset-maps-address-autocomplete
	 *
	 * @param {jQuery} $container
	 * @return {[string, string]} Array [lat, lon]
	 */
	self.getLatLngFromAutocomplete = function( $container ) {
		let $autocomplete = $container
			.children( '.js-toolset-maps-address-autocomplete' )
			.first();
		let coordinates = $autocomplete.data( 'coordinates' );

		if ( coordinates ) {
			return coordinates.slice( 1, -1 ).split( ',' );
		}
		return ['', ''];
	};

	/**
	 * Create the lat-lon toggle structure
	 * @param {string} lat
	 * @param {string} lon
	 * @return {string}
	 */
	self.getInputsStructure = function( lat, lon ) {
		let showHideCoords = toolset_maps_address_autocomplete_i10n.showhidecoords || "Show/Hide coordinates";
		let useMyLocation = toolset_maps_address_autocomplete_i10n.usemylocation || "Use my location";
		let latitudeLabel = toolset_maps_address_autocomplete_i10n.latitude || "Latitude";
		let longitudeLabel = toolset_maps_address_autocomplete_i10n.longitude || "Longitude";

		let inputsStructure = '<a class="toolset-google-map-toggle-latlon js-toolset-google-map-toggle-latlon">'
			+ showHideCoords
			+ '</a>';

		if ( navigator.geolocation && self.isSecurePage() ) {
			inputsStructure += ' | <a class="toolset-google-map-use-visitor-location js-toolset-google-map-use-visitor-location">'
				+ useMyLocation
				+ '</a>';
		}

		inputsStructure += '<div class="js-toolset-google-map-toggling-latlon toolset-google-map-toggling-latlon" style="display:none;">'
			+ '<p><label class="toolset-google-map-label">' + latitudeLabel + '</label>'
			+ '<input class="js-toolset-google-map-latlon js-toolset-google-map-lat toolset-google-map-lat" type="text" value="'
			+ lat
			+ '"/></p>'
			+ '<p><label class="toolset-google-map-label">' + longitudeLabel + '</label>'
			+ '<input class="js-toolset-google-map-latlon js-toolset-google-map-lon toolset-google-map-lon" type="text" value="'
			+ lon
			+ '"/></p></div>';
		return inputsStructure;
	};

	/**
	 * Create the map preview container structure
	 * @param {number} counter
	 * @return {string}
	 */
	self.getPreviewStructure = function( counter ) {
		let closestAddressLabel = toolset_maps_address_autocomplete_i10n.closestaddress || "Closest address: ";
		let useThisAddress = toolset_maps_address_autocomplete_i10n.usethisaddress || "Use this address";
		return ''
			+ '<div id="js-toolset-maps-preview-map-' + counter + '" '
			+ 'class="toolset-google-map-preview js-toolset-google-map-preview" '
			+ 'data-id="' + counter + '" '
			+ 'style="width:45%;height:250px;background:#f0f0f0;">'
			+ '</div>'
			+ '<div style="display:none;" class="toolset-google-map-preview-closest-address js-toolset-google-map-preview-closest-address">'
			+ '  <div style="padding:5px 10px 10px;">'
			+       closestAddressLabel
			+ '    <span class="toolset-google-map-preview-closest-address-value js-toolset-google-map-preview-closest-address-value"></span><br />'
			+ '    <button class="button button-secondary button-small js-toolset-google-map-preview-closest-address-apply">'
			+         useThisAddress
			+ '    </button>'
			+ '  </div>'
			+ '</div>';
	};

	/**
	 * Add a map preview for the given lat/lon by creating a Leaflet map instance.
	 * @param {[string, string]} latLng
	 */
	self.addMapPreviewToMaps = function( latLng ) {
		self.maps[self.mapCounter] = self.initMapPreview( self.mapCounter, latLng );
		self.mapCounter++;
	};

	/**
	 * Lazy-load Leaflet if not already loaded
	 * @param {[string, string]} latLng
	 */
	self.lazyLoadLeaflet = function( latLng ) {
		// Add Leaflet CSS
		jQuery('<link/>', {
			rel: 'stylesheet',
			type: 'text/css',
			href: 'https://unpkg.com/leaflet@1.9.3/dist/leaflet.css'
		}).appendTo('head');

		// Load Leaflet script
		jQuery.getScript('https://unpkg.com/leaflet@1.9.3/dist/leaflet.js')
			.done( function() {
				self.addMapPreviewToMaps( latLng );
			});
	};

	/**
	 * If Leaflet is in the middle of loading or not available, wait a bit, then try again
	 * @param {[string, string]} latLng
	 */
	self.delayedLazyLoadLeaflet = function( latLng ) {
		_.delay( function() {
			if ( typeof L === 'undefined' ) {
				self.lazyLoadLeaflet( latLng );
			} else {
				self.addMapPreviewToMaps( latLng );
			}
		}, 1000 );
	};

	/**
	 * Initialize a Leaflet map preview
	 * @param {number} counter
	 * @param {[string, string]} latLng [lat, lon]
	 * @return {L.Map}
	 */
	self.initMapPreview = function( counter, latLng ) {
		let mapId = "js-toolset-maps-preview-map-" + counter;
		let $mapDiv = $('#' + mapId);

		// Convert latLng to numeric or default if empty
		let lat = parseFloat(latLng[0]) || 0;
		let lon = parseFloat(latLng[1]) || 0;
		let zoomLevel = (lat === 0 && lon === 0) ? 2 : 10;

		// Create the map
		let map = L.map(mapId).setView([lat, lon], zoomLevel);

		// Add OSM tile layer
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution:
				'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		}).addTo(map);

		// Add marker if we have nonzero lat/long
		if (!isNaN(lat) && !isNaN(lon) && (lat !== 0 || lon !== 0)) {
			let marker = L.marker([lat, lon]).addTo(map);
			self.markers[counter] = marker;
		}

		// Handle map clicks => reverse geocode => update lat/lon
		map.on('click', function(e) {
			let clickedLat = e.latlng.lat;
			let clickedLon = e.latlng.lng;

			// Move the pin
			self.movePin(map, [clickedLat, clickedLon], counter);
			self.updateLatlonValues($mapDiv.parent(), clickedLat, clickedLon, 'both');

			// Reverse geocoding with Nominatim
			let url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2"
				+ "&lat=" + encodeURIComponent(clickedLat)
				+ "&lon=" + encodeURIComponent(clickedLon);

			$.ajax({
				url: url,
				dataType: "json",
				success: function(data) {
					let displayName = data.display_name || "No address found!";
					$mapDiv
						.siblings('.js-toolset-google-map-preview-closest-address')
						.slideDown('fast')
						.find('.js-toolset-google-map-preview-closest-address-value')
						.text(displayName)
						.data('lat', clickedLat)
						.data('lon', clickedLon);
				}
			});
		});

		// Fixes the issues with missing tiles in the map - known OSM issue
		setTimeout(function() {
			map.invalidateSize();
		}, 500);

		return map;
	};

	/**
	 * Move the pin (marker) on an existing Leaflet map
	 * @param {L.Map} map
	 * @param {[number, number]} coordinates [lat, lon]
	 * @param {number} mapId
	 */
	self.movePin = function( map, coordinates, mapId ) {
		let lat = parseFloat(coordinates[0]) || 0;
		let lon = parseFloat(coordinates[1]) || 0;

		// Reposition existing marker or create if it doesn't exist yet
		if ( self.markers[mapId] ) {
			self.markers[mapId].setLatLng([lat, lon]);
		} else {
			self.markers[mapId] = L.marker([lat, lon]).addTo(map);
		}
		// Optionally recenter the map
		map.setView([lat, lon], map.getZoom() || 10);
	};

	/**
	 * Master init
	 */
	self.init = function() {
		self.initAllFields();
		self.initMapEditorComponents();
		self.initEvents();
	};

	// Kick it off
	self.init();
};

// Initialize when document is ready
jQuery( function( $ ) {
	WPViews.mapsAddressAutocomplete = new WPViews.MapsAddressAutocomplete( $ );
});
