/**
 * jQuery Geocoding and Places Autocomplete Plugin - V 1.7.0
 *
 * @author Martin Kleppe <kleppe@ubilabs.net>, 2016
 * @author Ubilabs http://ubilabs.net, 2016
 * @license MIT License <http://www.opensource.org/licenses/mit-license.php>
 */

// # $.geocomplete()
// ## jQuery Geocoding and Places Autocomplete Plugin
//
// * https://github.com/ubilabs/geocomplete/
// * by Martin Kleppe <kleppe@ubilabs.net>

( function( $, window, document, undefined ) {
	// ## Options
	// The default options for this plugin.
	//
	// * `map` - Might be a selector, an jQuery object or a DOM element. Default is `false` which shows no map.
	// * `details` - The container that should be populated with data. Defaults to `false` which ignores the setting.
	// * 'detailsScope' - Allows you to scope the 'details' container and have multiple geocomplete fields on one page. Must be a parent of the input. Default is 'null'
	// * `location` - Location to initialize the map on. Might be an address `string` or an `array` with [latitude, longitude] or a `google.maps.LatLng`object. Default is `false` which shows a blank map.
	// * `bounds` - Whether to snap geocode search to map bounds. Default: `true` if false search globally. Alternatively pass a custom `LatLngBounds object.
	// * `autoselect` - Automatically selects the highlighted item or the first item from the suggestions list on Enter.
	// * `detailsAttribute` - The attribute's name to use as an indicator. Default: `"name"`
	// * `mapOptions` - Options to pass to the `google.maps.Map` constructor. See the full list [here](http://code.google.com/apis/maps/documentation/javascript/reference.html#MapOptions).
	// * `mapOptions.zoom` - The inital zoom level. Default: `14`
	// * `mapOptions.scrollwheel` - Whether to enable the scrollwheel to zoom the map. Default: `false`
	// * `mapOptions.mapTypeId` - The map type. Default: `"roadmap"`
	// * `markerOptions` - The options to pass to the `google.maps.Marker` constructor. See the full list [here](http://code.google.com/apis/maps/documentation/javascript/reference.html#MarkerOptions).
	// * `markerOptions.draggable` - If the marker is draggable. Default: `false`. Set to true to enable dragging.
	// * `markerOptions.disabled` - Do not show marker. Default: `false`. Set to true to disable marker.
	// * `maxZoom` - The maximum zoom level too zoom in after a geocoding response. Default: `16`
	// * `types` - An array containing one or more of the supported types for the places request. Default: `['geocode']` See the full list [here](http://code.google.com/apis/maps/documentation/javascript/places.html#place_search_requests).
	// * `blur` - Trigger geocode when input loses focus.
	// * `geocodeAfterResult` - If blur is set to true, choose whether to geocode if user has explicitly selected a result before blur.
	// * `restoreValueAfterBlur` - Restores the input's value upon blurring. Default is `false` which ignores the setting.

	const defaults = {
		bounds: true,
		strictBounds: false,
		country: null,
		map: false,
		details: false,
		detailsAttribute: 'name',
		detailsScope: null,
		autoselect: true,
		location: false,

		mapOptions: {
			zoom: 14,
			scrollwheel: false,
			mapTypeId: 'roadmap',
		},

		markerOptions: {
			draggable: false,
		},

		maxZoom: 16,
		types: [ 'geocode' ],
		blur: false,
		geocodeAfterResult: false,
		restoreValueAfterBlur: false,
	};

	// See: [Geocoding Types](https://developers.google.com/maps/documentation/geocoding/#Types)
	// on Google Developers.
	const componentTypes = ( 'street_address route intersection political ' +
    'country administrative_area_level_1 administrative_area_level_2 ' +
    'administrative_area_level_3 colloquial_area locality sublocality ' +
    'neighborhood premise subpremise postal_code natural_feature airport ' +
    'park point_of_interest post_box street_number floor room ' +
    'lat lng viewport location ' +
    'formatted_address location_type bounds' ).split( ' ' );

	// See: [Places Details Responses](https://developers.google.com/maps/documentation/javascript/places#place_details_responses)
	// on Google Developers.
	const placesDetails = ( 'id place_id url website vicinity reference name rating ' +
    'international_phone_number icon formatted_phone_number' ).split( ' ' );

	// The actual plugin constructor.
	function GeoComplete( input, options ) {
		this.options = $.extend( true, {}, defaults, options );

		// This is a fix to allow types:[] not to be overridden by defaults
		// so search results includes everything
		if ( options && options.types ) {
			this.options.types = options.types;
		}

		this.input = input;
		this.$input = $( input );

		this._defaults = defaults;
		this._name = 'geocomplete';

		// API version tracking
		this.apiVersion = null;
		this.isAPITested = false;

		this.init();
	}

	// Initialize all parts of the plugin.
	$.extend( GeoComplete.prototype, {
		init: function() {
			this.initMap();
			this.initMarker();
			this.initGeocoder();
			this.initDetails();
			this.initLocation();
		},

		// Initialize the map but only if the option `map` was set.
		// This will create a `map` within the given container
		// using the provided `mapOptions` or link to the existing map instance.
		initMap: function() {
			if ( ! this.options.map ) {
				return;
			}

			if ( typeof this.options.map.setCenter === 'function' ) {
				this.map = this.options.map;
				return;
			}

			this.map = new google.maps.Map(
				$( this.options.map )[ 0 ],
				this.options.mapOptions
			);

			// add click event listener on the map
			google.maps.event.addListener(
				this.map,
				'click',
				$.proxy( this.mapClicked, this )
			);

			// add dragend even listener on the map
			google.maps.event.addListener(
				this.map,
				'dragend',
				$.proxy( this.mapDragged, this )
			);

			// add idle even listener on the map
			google.maps.event.addListener(
				this.map,
				'idle',
				$.proxy( this.mapIdle, this )
			);

			google.maps.event.addListener(
				this.map,
				'zoom_changed',
				$.proxy( this.mapZoomed, this )
			);
		},

		// Add a marker with the provided `markerOptions` but only
		// if the option was set. Additionally it listens for the `dragend` event
		// to notify the plugin about changes.
		initMarker: function() {
			if ( ! this.map ) {
				return;
			}
			const options = $.extend( this.options.markerOptions, { map: this.map } );

			if ( options.disabled ) {
				return;
			}

			this.marker = new google.maps.Marker( options );

			google.maps.event.addListener(
				this.marker,
				'dragend',
				$.proxy( this.markerDragged, this )
			);
		},

		// Test which API version is available
		testAPIVersion: async function() {
			if ( this.isAPITested ) {
				return this.apiVersion !== 'none';
			}

			// First, try the new API
			try {
				if ( google.maps.places && google.maps.places.AutocompleteSuggestion ) {
					console.debug( 'Testing new AutocompleteSuggestion API...' );

					const request = {
						input: 'Phoenix',
						sessionToken: new google.maps.places.AutocompleteSessionToken(),
					};

					const { suggestions } = await google.maps.places.AutocompleteSuggestion.fetchAutocompleteSuggestions( request );

					if ( suggestions && suggestions.length > 0 ) {
						this.apiVersion = 'new';
						this.isAPITested = true;
						console.debug( 'New AutocompleteSuggestion API is working - using new API' );
						return true;
					}
				}
			} catch ( newApiError ) {
				console.warn( 'New API failed:', newApiError.message );
			}

			// New API didn't work, fall back to classic API
			try {
				console.log( 'Toolset Notice: Falling back to the classic AutocompleteService API - you may see deprecation notices.' +
            'For best results, please create a new API key or enable the latest Places API in your Google Cloud Console.' );

				const service = new google.maps.places.AutocompleteService();

				await new Promise( ( resolve, reject ) => {
					service.getPlacePredictions(
						{ input: 'Phoenix' },
						function( predictions, status ) {
							if ( status === google.maps.places.PlacesServiceStatus.OK && predictions && predictions.length > 0 ) {
								console.debug( 'Classic AutocompleteService API is working - using classic API' );
								resolve();
							} else {
								reject( new Error( 'Classic API failed with status: ' + status ) );
							}
						}
					);
				} );

				this.apiVersion = 'classic';
				this.isAPITested = true;
				return true;
			} catch ( classicError ) {
				console.error( 'Both APIs failed. Autocomplete will not be available.' );
				console.error( 'Classic API error:', classicError.message );
				this.apiVersion = 'none';
				this.isAPITested = true;
				return false;
			}
		},

		// Associate the input with the autocompleter and create a geocoder
		// to fall back when the autocompleter does not return a value.
		initGeocoder: async function() {
			// Test which API is available
			await this.testAPIVersion();

			// Only proceed if an API is available
			if ( this.apiVersion === 'none' ) {
				console.warn( 'No autocomplete API available, skipping autocomplete initialization' );
				this.geocoder = new google.maps.Geocoder();
				return;
			}

			// Indicates is user did select a result from the dropdown.
			const selected = false;

			const options = {
				types: this.options.types,
				bounds: this.options.bounds === true ? null : this.options.bounds,
				componentRestrictions: this.options.componentRestrictions,
				strictBounds: this.options.strictBounds,
			};

			if ( this.options.country ) {
				options.componentRestrictions = { country: this.options.country };
			}

			// Only use the deprecated Autocomplete widget if we're on the classic API
			// If we have the new API, we should avoid creating the deprecated widget
			if ( this.apiVersion === 'classic' ) {
				this.autocomplete = new google.maps.places.Autocomplete(
					this.input, options
				);

				this.geocoder = new google.maps.Geocoder();

				// Bind autocomplete to map bounds but only if there is a map
				// and `options.bindToMap` is set to true.
				if ( this.map && this.options.bounds === true ) {
					this.autocomplete.bindTo( 'bounds', this.map );
				}

				// Watch `place_changed` events on the autocomplete input field.
				google.maps.event.addListener(
					this.autocomplete,
					'place_changed',
					$.proxy( this.placeChanged, this )
				);
			} else if ( this.apiVersion === 'new' ) {
				// For new API, we'll implement our own autocomplete to avoid deprecation warnings
				this.geocoder = new google.maps.Geocoder();
				this.initCustomAutocomplete( options );
			}

			// Prevent parent form from being submitted if user hit enter.
			this.$input.on( 'keypress.' + this._name, function( event ) {
				if ( event.keyCode === 13 ) {
					// For new API, check if we have a selected item or should select first result
					if ( self.apiVersion === 'new' && self.options.autoselect && ! self.selected ) {
						if ( self.selectedIndex >= 0 || ( self.currentSuggestions && self.currentSuggestions.length > 0 ) ) {
							event.preventDefault();
							if ( self.selectedIndex >= 0 ) {
								self.selectSuggestion( self.selectedIndex );
							} else {
								self.selectSuggestion( 0 );
							}
							return false;
						}
					}
					return false;
				}
			} );

			// Assume that if user types anything after having selected a result,
			// the selected location is not valid any more.
			if ( this.options.geocodeAfterResult === true ) {
				this.$input.bind( 'keypress.' + this._name, $.proxy( function() {
					if ( event.keyCode != 9 && this.selected === true ) {
						this.selected = false;
					}
				}, this ) );
			}

			// Listen for "geocode" events and trigger find action.
			this.$input.bind( 'geocode.' + this._name, $.proxy( function() {
				this.find();
			}, this ) );

			// Saves the previous input value
			this.$input.bind( 'geocode:result.' + this._name, $.proxy( function() {
				this.lastInputVal = this.$input.val();
			}, this ) );

			// Trigger find action when input element is blurred out and user has
			// not explicitly selected a result.
			// (Useful for typing partial location and tabbing to the next field
			// or clicking somewhere else.)
			if ( this.options.blur === true ) {
				this.$input.on( 'blur.' + this._name, $.proxy( function() {
					if ( this.options.geocodeAfterResult === true && this.selected === true ) {
						return;
					}

					if ( this.options.restoreValueAfterBlur === true && this.selected === true ) {
						setTimeout( $.proxy( this.restoreLastValue, this ), 0 );
					} else {
						this.find();
					}
				}, this ) );
			}
		},

		// Initialize custom autocomplete for new API without using deprecated widgets
		initCustomAutocomplete: function( options ) {
			const self = this;
			let debounceTimer;
			let currentSuggestions = [];
			let selectedIndex = -1;

			// Create a session token
			this.sessionToken = new google.maps.places.AutocompleteSessionToken();

			// Add custom styles for the dropdown if they don't exist
			if ( ! $( '#geocomplete-dropdown-styles' ).length ) {
				const styles = '<style id="geocomplete-dropdown-styles">' +
            '.pac-container { background-color: #fff; position: absolute; z-index: 1000; border-radius: 2px; border-top: 1px solid #d9d9d9; font-family: Arial, sans-serif; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3); -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; overflow: hidden; }' +
            '.pac-item { cursor: default; padding: 0 4px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; line-height: 30px; text-align: left; border-top: 1px solid #e6e6e6; font-size: 11px; color: #999; }' +
            '.pac-item:hover, .pac-item-selected { background-color: #fafafa; }' +
            '.pac-item-query { font-size: 13px; padding-right: 3px; color: #000; }' +
            '.pac-icon { width: 15px; height: 20px; margin-right: 7px; margin-top: 6px; display: inline-block; vertical-align: top; background-image: url(https://maps.gstatic.com/mapfiles/api-3/images/autocomplete-icons.png); background-size: 34px; }' +
            '.pac-icon-marker { background-position: -1px -161px; }' +
            '.hdpi .pac-icon { background-image: url(https://maps.gstatic.com/mapfiles/api-3/images/autocomplete-icons_hdpi.png); }' +
            '</style>';
				$( 'head' ).append( styles );
			}

			// Create dropdown container
			this.$dropdown = $( '<div class="pac-container pac-logo" style="display: none;"></div>' );
			$( 'body' ).append( this.$dropdown );

			// Position dropdown
			function positionDropdown() {
				const offset = self.$input.offset();
				const height = self.$input.outerHeight();
				self.$dropdown.css( {
					top: offset.top + height,
					left: offset.left,
					width: self.$input.outerWidth(),
				} );
			}

			// Handle input changes
			this.$input.on( 'input.' + this._name, function() {
				clearTimeout( debounceTimer );
				const value = $( this ).val();

				// Reset selected flag when user types
				self.selected = false;

				if ( value.length < 2 ) {
					self.$dropdown.hide();
					currentSuggestions = [];
					selectedIndex = -1;
					return;
				}

				debounceTimer = setTimeout( function() {
					self.fetchSuggestions( value, options );
				}, 300 );
			} );

			// Handle keyboard navigation
			this.$input.on( 'keydown.' + this._name, function( e ) {
				if ( ! self.$dropdown.is( ':visible' ) || currentSuggestions.length === 0 ) {
					return;
				}

				switch ( e.keyCode ) {
					case 38: // Up arrow
						e.preventDefault();
						selectedIndex = Math.max( -1, selectedIndex - 1 );
						self.updateSelection();
						break;
					case 40: // Down arrow
						e.preventDefault();
						selectedIndex = Math.min( currentSuggestions.length - 1, selectedIndex + 1 );
						self.updateSelection();
						break;
					case 13: // Enter
						if ( selectedIndex >= 0 ) {
							e.preventDefault();
							self.selectSuggestion( selectedIndex );
						}
						break;
					case 27: // Escape
						self.$dropdown.hide();
						selectedIndex = -1;
						break;
				}
			} );

			// Handle click on suggestion
			this.$dropdown.on( 'click', '.pac-item', function( e ) {
				e.preventDefault();
				const index = $( this ).data( 'index' );
				self.selectSuggestion( index );
			} );

			// Hide dropdown on outside click
			$( document ).on( 'click.' + this._name, function( e ) {
				if ( ! $( e.target ).closest( self.$input ).length && ! $( e.target ).closest( self.$dropdown ).length ) {
					self.$dropdown.hide();
					selectedIndex = -1;
				}
			} );

			// Handle blur
			this.$input.on( 'blur.' + this._name, function() {
				// Delay to allow click on dropdown
				setTimeout( function() {
					if ( ! self.selected && self.$input.val() ) {
						self.find( self.$input.val() );
					}
				}, 200 );
			} );

			// Handle window resize
			$( window ).on( 'resize.' + this._name, positionDropdown );

			// Store references for cleanup
			this.currentSuggestions = currentSuggestions;
			this.selectedIndex = selectedIndex;
			this.positionDropdown = positionDropdown;
		},

		// Update visual selection in dropdown
		updateSelection: function() {
			this.$dropdown.find( '.pac-item' ).removeClass( 'pac-item-selected' );
			if ( this.selectedIndex >= 0 ) {
				this.$dropdown.find( '.pac-item' ).eq( this.selectedIndex ).addClass( 'pac-item-selected' );
			}
		},

		// Select a suggestion
		selectSuggestion: async function( index ) {
			if ( index < 0 || index >= this.currentSuggestions.length ) {
				return;
			}

			const suggestion = this.currentSuggestions[ index ];
			this.$dropdown.hide();
			this.selected = true;

			try {
				// Fetch place details
				const place = await suggestion.placePrediction.toPlace();
				await place.fetchFields( {
					fields: [ 'displayName', 'formattedAddress', 'location', 'viewport', 'addressComponents', 'id', 'types' ],
				} );

				// Update input value
				this.$input.val( place.formattedAddress || place.displayName );

				// Convert to format expected by the rest of the plugin
				const result = {
					formatted_address: place.formattedAddress || place.displayName,
					geometry: {
						location: place.location,
						viewport: place.viewport,
					},
					place_id: place.id,
					address_components: place.addressComponents || [],
					types: place.types || [],
				};

				// Create new session token for next search
				this.sessionToken = new google.maps.places.AutocompleteSessionToken();

				// Trigger update
				this.update( result );
			} catch ( error ) {
				console.error( 'Error fetching place details:', error );
				this.selected = false;
			}
		},

		// Fetch suggestions using the new API
		fetchSuggestions: async function( input, options ) {
			try {
				const request = {
					input: input,
					sessionToken: this.sessionToken,
				};

				// Apply any bounds or country restrictions
				if ( options.bounds ) {
					request.locationBias = options.bounds;
				}

				if ( options.componentRestrictions && options.componentRestrictions.country ) {
					request.region = options.componentRestrictions.country;
				}

				const { suggestions } = await google.maps.places.AutocompleteSuggestion.fetchAutocompleteSuggestions( request );

				if ( suggestions && suggestions.length > 0 ) {
					this.currentSuggestions = suggestions;
					this.selectedIndex = -1;
					this.renderDropdown( suggestions );
				} else {
					this.$dropdown.hide();
					this.currentSuggestions = [];
				}
			} catch ( error ) {
				console.error( 'Error fetching suggestions:', error );
				this.$dropdown.hide();
			}
		},

		// Render the dropdown with suggestions
		renderDropdown: function( suggestions ) {
			let html = '';

			suggestions.forEach( function( suggestion, index ) {
				const prediction = suggestion.placePrediction;
				const mainText = prediction.text ? prediction.text.text : '';
				const secondaryText = prediction.secondaryText ? prediction.secondaryText.text : '';

				html += '<div class="pac-item" data-index="' + index + '">';
				html += '<span class="pac-icon pac-icon-marker"></span>';
				html += '<span class="pac-item-query">';

				// Add matched text highlighting if available
				if ( prediction.structuredFormat && prediction.structuredFormat.mainText ) {
					html += prediction.structuredFormat.mainText.text;
				} else {
					html += mainText;
				}

				html += '</span>';

				if ( secondaryText ) {
					html += '<span> - ' + secondaryText + '</span>';
				}

				html += '</div>';
			} );

			this.$dropdown.html( html );
			this.positionDropdown();
			this.$dropdown.show();
		},

		// Prepare a given DOM structure to be populated when we got some data.
		// This will cycle through the list of component types and map the
		// corresponding elements.
		initDetails: function() {
			if ( ! this.options.details ) {
				return;
			}

			if ( this.options.detailsScope ) {
				var $details = $( this.input ).parents( this.options.detailsScope ).find( this.options.details );
			} else {
				var $details = $( this.options.details );
			}

			const attribute = this.options.detailsAttribute,
				details = {};

			function setDetail( value ) {
				details[ value ] = $details.find( '[' + attribute + '=' + value + ']' );
			}

			$.each( componentTypes, function( index, key ) {
				setDetail( key );
				setDetail( key + '_short' );
			} );

			$.each( placesDetails, function( index, key ) {
				setDetail( key );
			} );

			this.$details = $details;
			this.details = details;
		},

		// Set the initial location of the plugin if the `location` options was set.
		// This method will care about converting the value into the right format.
		initLocation: function() {
			let location = this.options.location,
				latLng;

			if ( ! location ) {
				return;
			}

			if ( typeof location === 'string' ) {
				this.find( location );
				return;
			}

			if ( location instanceof Array ) {
				latLng = new google.maps.LatLng( location[ 0 ], location[ 1 ] );
			}

			if ( location instanceof google.maps.LatLng ) {
				latLng = location;
			}

			if ( latLng ) {
				if ( this.map ) {
					this.map.setCenter( latLng );
				}
				if ( this.marker ) {
					this.marker.setPosition( latLng );
				}
			}
		},

		destroy: function() {
			if ( this.map ) {
				google.maps.event.clearInstanceListeners( this.map );
				google.maps.event.clearInstanceListeners( this.marker );
			}

			if ( this.autocomplete ) {
				this.autocomplete.unbindAll();
				google.maps.event.clearInstanceListeners( this.autocomplete );
			}

			// Clean up custom dropdown if it exists
			if ( this.$dropdown ) {
				this.$dropdown.remove();
			}

			google.maps.event.clearInstanceListeners( this.input );
			this.$input.removeData();
			this.$input.off( this._name );
			this.$input.unbind( '.' + this._name );
			$( document ).off( '.' + this._name );
			$( window ).off( '.' + this._name );
		},

		// Look up a given address. If no `address` was specified it uses
		// the current value of the input.
		find: function( address ) {
			this.geocode( {
				address: address || this.$input.val(),
			} );
		},

		// Requests details about a given location.
		// Additionally it will bias the requests to the provided bounds.
		geocode: function( request ) {
			// Don't geocode if the requested address is empty
			if ( ! request.address ) {
				return;
			}
			if ( this.options.bounds && ! request.bounds ) {
				if ( this.options.bounds === true ) {
					request.bounds = this.map && this.map.getBounds();
				} else {
					request.bounds = this.options.bounds;
				}
			}

			if ( this.options.country ) {
				request.region = this.options.country;
			}

			this.geocoder.geocode( request, $.proxy( this.handleGeocode, this ) );
		},

		// Get the selected result. If no result is selected on the list, then get
		// the first result from the list.
		selectFirstResult: function() {
			// Handle new API case
			if ( this.apiVersion === 'new' && this.currentSuggestions && this.currentSuggestions.length > 0 ) {
				// Select the first suggestion from our stored suggestions
				this.selectSuggestion( 0 );
				return this.$input.val();
			}

			// Original code for classic API
			//$(".pac-container").hide();

			let selected = '';
			// Check if any result is selected.
			if ( $( '.pac-item-selected' )[ 0 ] ) {
				selected = '-selected';
			}

			// Get the first suggestion's text.
			const $span1 = $( '.pac-container:visible .pac-item' + selected + ':first span:nth-child(2)' ).text();
			const $span2 = $( '.pac-container:visible .pac-item' + selected + ':first span:nth-child(3)' ).text();

			// Adds the additional information, if available.
			let firstResult = $span1;
			if ( $span2 ) {
				firstResult += ' - ' + $span2;
			}

			this.$input.val( firstResult );

			return firstResult;
		},

		// Restores the input value using the previous value if it exists
		restoreLastValue: function() {
			if ( this.lastInputVal ) {
				this.$input.val( this.lastInputVal );
			}
		},

		// Handles the geocode response. If more than one results was found
		// it triggers the "geocode:multiple" events. If there was an error
		// the "geocode:error" event is fired.
		handleGeocode: function( results, status ) {
			if ( status === google.maps.GeocoderStatus.OK ) {
				const result = results[ 0 ];
				this.$input.val( result.formatted_address );
				this.update( result );

				if ( results.length > 1 ) {
					this.trigger( 'geocode:multiple', results );
				}
			} else {
				this.trigger( 'geocode:error', status );
			}
		},

		// Triggers a given `event` with optional `arguments` on the input.
		trigger: function( event, argument ) {
			this.$input.trigger( event, [ argument ] );
		},

		// Set the map to a new center by passing a `geometry`.
		// If the geometry has a viewport, the map zooms out to fit the bounds.
		// Additionally it updates the marker position.
		center: function( geometry ) {
			if ( geometry.viewport ) {
				this.map.fitBounds( geometry.viewport );
				if ( this.map.getZoom() > this.options.maxZoom ) {
					this.map.setZoom( this.options.maxZoom );
				}
			} else {
				this.map.setZoom( this.options.maxZoom );
				this.map.setCenter( geometry.location );
			}

			if ( this.marker ) {
				this.marker.setPosition( geometry.location );
				this.marker.setAnimation( this.options.markerOptions.animation );
			}
		},

		// Update the elements based on a single places or geocoding response
		// and trigger the "geocode:result" event on the input.
		update: function( result ) {
			if ( this.map ) {
				this.center( result.geometry );
			}

			if ( this.$details ) {
				this.fillDetails( result );
			}

			this.trigger( 'geocode:result', result );
		},

		// Populate the provided elements with new `result` data.
		// This will lookup all elements that has an attribute with the given
		// component type.
		fillDetails: function( result ) {
			const data = {},
				geometry = result.geometry,
				viewport = geometry.viewport,
				bounds = geometry.bounds;

			// Create a simplified version of the address components.
			$.each( result.address_components, function( index, object ) {
				const name = object.types[ 0 ];

				$.each( object.types, function( index, name ) {
					data[ name ] = object.long_name;
					data[ name + '_short' ] = object.short_name;
				} );
			} );

			// Add properties of the places details.
			$.each( placesDetails, function( index, key ) {
				data[ key ] = result[ key ];
			} );

			// Add infos about the address and geometry.
			$.extend( data, {
				formatted_address: result.formatted_address,
				location_type: geometry.location_type || 'PLACES',
				viewport: viewport,
				bounds: bounds,
				location: geometry.location,
				lat: geometry.location.lat(),
				lng: geometry.location.lng(),
			} );

			// Set the values for all details.
			$.each( this.details, $.proxy( function( key, $detail ) {
				const value = data[ key ];
				this.setDetail( $detail, value );
			}, this ) );

			this.data = data;
		},

		// Assign a given `value` to a single `$element`.
		// If the element is an input, the value is set, otherwise it updates
		// the text content.
		setDetail: function( $element, value ) {
			if ( value === undefined ) {
				value = '';
			} else if ( typeof value.toUrlValue === 'function' ) {
				value = value.toUrlValue();
			}

			if ( $element.is( ':input' ) ) {
				$element.val( value );
			} else {
				$element.text( value );
			}
		},

		// Fire the "geocode:dragged" event and pass the new position.
		markerDragged: function( event ) {
			this.trigger( 'geocode:dragged', event.latLng );
		},

		mapClicked: function( event ) {
			this.trigger( 'geocode:click', event.latLng );
		},

		// Fire the "geocode:mapdragged" event and pass the current position of the map center.
		mapDragged: function( event ) {
			this.trigger( 'geocode:mapdragged', this.map.getCenter() );
		},

		// Fire the "geocode:idle" event and pass the current position of the map center.
		mapIdle: function( event ) {
			this.trigger( 'geocode:idle', this.map.getCenter() );
		},

		mapZoomed: function( event ) {
			this.trigger( 'geocode:zoom', this.map.getZoom() );
		},

		// Restore the old position of the marker to the last knwon location.
		resetMarker: function() {
			this.marker.setPosition( this.data.location );
			this.setDetail( this.details.lat, this.data.location.lat() );
			this.setDetail( this.details.lng, this.data.location.lng() );
		},

		// Update the plugin after the user has selected an autocomplete entry.
		// If the place has no geometry it passes it to the geocoder.
		placeChanged: function() {
			if ( ! this.autocomplete ) {
				// If we don't have an autocomplete instance just return
				return;
			}

			const place = this.autocomplete.getPlace();
			this.selected = true;

			if ( ! place.geometry ) {
				if ( this.options.autoselect ) {
					// Automatically selects the highlighted item or the first item from the
					// suggestions list.
					const autoSelection = this.selectFirstResult();
					this.find( autoSelection );
				}
			} else {
				// Use the input text if it already gives geometry.
				this.update( place );
			}
		},
	} );

	// A plugin wrapper around the constructor.
	// Pass `options` with all settings that are different from the default.
	// The attribute is used to prevent multiple instantiations of the plugin.
	$.fn.geocomplete = function( options ) {
		const attribute = 'plugin_geocomplete';

		// If you call `.geocomplete()` with a string as the first parameter
		// it returns the corresponding property or calls the method with the
		// following arguments.
		if ( typeof options === 'string' ) {
			let instance = $( this ).data( attribute ) || $( this ).geocomplete().data( attribute ),
				prop = instance[ options ];

			if ( typeof prop === 'function' ) {
				prop.apply( instance, Array.prototype.slice.call( arguments, 1 ) );
				return $( this );
			}
			if ( arguments.length == 2 ) {
				prop = arguments[ 1 ];
			}
			return prop;
		}
		return this.each( function() {
			// Prevent against multiple instantiations.
			let instance = $.data( this, attribute );
			if ( ! instance ) {
				instance = new GeoComplete( this, options );
				$.data( this, attribute, instance );
			}
		} );
	};
}( jQuery, window, document ) );
