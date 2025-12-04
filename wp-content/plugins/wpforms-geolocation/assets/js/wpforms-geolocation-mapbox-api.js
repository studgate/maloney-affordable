/* global wpforms_geolocation_settings, mapboxsearch, mapboxgl */

/**
 * WPForms Geolocation Mapbox API.
 *
 * @since 2.3.0
 */
const WPFormsGeolocationMapboxAPI = window.WPFormsGeolocationMapboxAPI || ( function( document, window ) {
	/**
	 * List of fields with autocomplete feature.
	 *
	 * @since 2.3.0
	 *
	 * @type {Array}
	 */
	const fieldsPlaces = [],

		/**
		 * Functions for working with Search API feature object.
		 *
		 * @since 2.3.0
		 */
		featureHelper = {

			/**
			 * Get the first feature.
			 *
			 * @since 2.3.0
			 *
			 * @param {Object} featureCollection Feature collection.
			 *
			 * @return {object|null} First feature or null.
			 */
			getFirstFeature( featureCollection ) {
				return featureCollection && Object.prototype.hasOwnProperty.call( featureCollection, 'features' ) && featureCollection.features.length
					? featureCollection.features.shift() : null;
			},

			/**
			 * Get feature properties.
			 *
			 * @since 2.3.0
			 *
			 * @param {Object} feature A feature.
			 *
			 * @return {object|null} Feature properties or null.
			 */
			getFeatureProperties( feature ) {
				return Object.prototype.hasOwnProperty.call( feature, 'properties' ) ? feature.properties : null;
			},

			/**
			 * Get a feature property value.
			 *
			 * @since 2.3.0
			 *
			 * @param {Object} feature      Current feature.
			 * @param {string} propertyName Property name.
			 *
			 * @return {string} Property value.
			 */
			getFeatureProperty( feature, propertyName ) {
				const featureProperties = featureHelper.getFeatureProperties( feature );

				if ( ! Object.prototype.hasOwnProperty.call( featureProperties, propertyName ) ) {
					return '';
				}

				return [ 'region_code', 'country_code' ].includes( propertyName )
					? featureProperties[ propertyName ].toUpperCase()
					: featureProperties[ propertyName ];
			},

			/**
			 * Get feature coordinates.
			 *
			 * @since 2.3.0
			 *
			 * @param {Object} feature A feature.
			 *
			 * @return {Object} Longitude and latitude.
			 */
			getFeatureCoordinates( feature ) {
				return Object.prototype.hasOwnProperty.call( feature, 'geometry' ) && Object.prototype.hasOwnProperty.call( feature.geometry, 'coordinates' )
					? { lng: feature.geometry.coordinates[ 0 ], lat: feature.geometry.coordinates[ 1 ] }
					: wpforms_geolocation_settings.default_location;
			},

			/**
			 * Prepare Search API features properties from a Geocoder API feature.
			 *
			 * @since 2.3.0
			 *
			 * @param {Object} feature Feature from the Geocoder API.
			 *
			 * @return {Object} Converted feature properties to Search API
			 */
			prepareProperties( feature ) {
				const properties = {};

				feature.context.forEach( function( property ) {
					const id = property.id.split( '.' )[ 0 ];

					properties[ id ] = property.text;

					if ( Object.prototype.hasOwnProperty.call( property, 'short_code' ) ) {
						properties[ id + '_code' ] = property.short_code.replace( /^US-/, '' );
					}
				} );

				if ( Object.prototype.hasOwnProperty.call( feature, 'text' ) ) {
					// eslint-disable-next-line camelcase
					properties.address_line1 = feature.text;
				}

				if ( Object.prototype.hasOwnProperty.call( feature, 'address' ) ) {
					// eslint-disable-next-line camelcase
					properties.address_line1 += ' ' + feature.address;
				}

				if ( Object.prototype.hasOwnProperty.call( feature, 'place_name' ) ) {
					// eslint-disable-next-line camelcase
					properties.place_name = feature.place_name;
				}

				return properties;
			},
		},

		/**
		 * Geocoder API that helps to detect current place by coordinates.
		 *
		 * @since 2.3.0
		 *
		 * @type {Object}
		 */
		geocoder = {

			/**
			 * Fetch place data.
			 *
			 * @since 2.11.0
			 *
			 * @param {URL}      url      URL.
			 * @param {Function} callback Success callback.
			 */
			fetchPlaceData( url, callback ) {
				const xhr = new XMLHttpRequest;

				url.searchParams.set( 'access_token', wpforms_geolocation_settings.autocompleteSettings.common.access_token );
				url.searchParams.set( 'limit', '1' );
				url.searchParams.set( 'type', 'address' );

				xhr.onreadystatechange = function() {
					if ( xhr.readyState === 4 && xhr.status === 200 ) {
						const data = JSON.parse( xhr.responseText ),
							feature = featureHelper.getFirstFeature( data );

						if ( feature ) {
							feature.properties = featureHelper.prepareProperties( feature );
							callback( feature );
						}
					}
				};

				xhr.open( 'GET', url.toString() );
				xhr.send();
			},

			/**
			 * Receive place by coordinates.
			 *
			 * @since 2.3.0
			 *
			 * @param {Object}   latLng   Latitude and longitude.
			 * @param {Function} callback Success callback.
			 */
			receivePlace( latLng, callback ) {
				const url = new URL( `https://api.mapbox.com/geocoding/v5/mapbox.places/${ latLng.lng },${ latLng.lat }.json` );
				this.fetchPlaceData( url, callback );
			},

			/**
			 * Receive place by query.
			 *
			 * @since 2.11.0
			 *
			 * @param {string}   query    Query.
			 * @param {Function} callback Success callback.
			 */
			receivePlaceByQuery( query, callback ) {
				const url = new URL( `https://api.mapbox.com/geocoding/v5/mapbox.places/${ query }.json` );
				this.fetchPlaceData( url, callback );
			},
		},

		/**
		 * States object.
		 *
		 * @since 2.3.0
		 */
		states = {

			/**
			 * Get state coordinates by the state code.
			 *
			 * @since 2.3.0
			 *
			 * @param {Object} currentFieldPlace Current group field with places API.
			 * @param {string} stateCode         State name.
			 *
			 * @return {object|null} Latitude and longitude coordinates or null.
			 */
			getStateCoordinates( currentFieldPlace, stateCode ) {
				if ( ! currentFieldPlace.settings.autocompleteSettings.strict ) {
					return null;
				}

				const countryCode = currentFieldPlace.settings.autocompleteSettings.strict.toString().toLowerCase();

				return Object.prototype.hasOwnProperty.call( wpforms_geolocation_settings.states, countryCode ) && Object.prototype.hasOwnProperty.call( wpforms_geolocation_settings.states[ countryCode ], stateCode )
					? wpforms_geolocation_settings.states[ countryCode ][ stateCode ]
					: null;
			},
		};

	/**
	 * Plugin engine.
	 *
	 * @since 2.3.0
	 *
	 * @type {Object}
	 */
	const app = {

		/**
		 * Start the engine.
		 *
		 * @since 2.3.0
		 */
		init() {
			if ( document.readyState === 'loading' ) {
				window.addEventListener( 'load', app.ready );
			} else {
				app.ready();
			}
		},

		/**
		 * Document ready.
		 *
		 * @since 2.3.0
		 */
		ready() {
			app.getFields();

			if ( ! fieldsPlaces.length ) {
				return;
			}

			app.events();
			app.initFieldPlaceMaps();
			app.detectGeolocation();
		},

		/**
		 * Event listeners.
		 *
		 * @since 2.10.0
		 */
		events() {
			document.onwpformsProcessConditionalsField = function( e, formID, fieldID ) {
				const el = document.getElementById( 'wpforms-' + formID + '-field_' + fieldID );

				if ( ! el || ! el.hasAttribute( 'data-autocomplete' ) ) {
					return;
				}

				window.dispatchEvent( new Event( 'resize' ) );
			};

			document.onwpformsRepeaterFieldCloneCreated = function() {
				app.getFields();
				app.initFieldPlaceMaps();
				app.detectGeolocation();
			};
		},

		/**
		 * Init maps on field places.
		 *
		 * @since 2.3.0
		 */
		initFieldPlaceMaps() {
			fieldsPlaces.forEach( function( currentFieldPlace ) {
				// Skip already initialized maps.
				if ( currentFieldPlace.map ) {
					return;
				}

				app.initMap( currentFieldPlace );
				app.initAutocomplete( currentFieldPlace );
			} );
		},

		/**
		 * Show a debug message.
		 *
		 * @since 2.3.0
		 *
		 * @param {string|object} message Debug message.
		 */
		showDebugMessage( message ) {
			if ( ! window.location.hash || '#wpformsdebug' !== window.location.hash ) {
				return;
			}

			// eslint-disable-next-line no-console
			console.log( message );
		},

		/**
		 * Get all fields for geolocation.
		 *
		 * @since 2.3.0
		 */
		getFields() {
			const fields = Array.prototype.slice.call(
				document.querySelectorAll( '.wpforms-form .wpforms-field input[type="text"][data-autocomplete="1"]' )
			);

			fields.forEach( function( el ) {
				const fieldWrapper = el.closest( '.wpforms-field' ),
					mapField = el.hasAttribute( 'data-display-map' ) ? fieldWrapper.querySelector( '.wpforms-geolocation-map' ) : null,
					type = fieldWrapper.classList[ 1 ] ? fieldWrapper.classList[ 1 ].replace( 'wpforms-field-', '' ) : 'text';

				let additionalFields = {};

				if ( 'address' === type ) {
					additionalFields = {
						/* eslint-disable camelcase */
						address_line1: fieldWrapper.querySelector( '.wpforms-field-address-address1' ),
						address_line2: fieldWrapper.querySelector( '.wpforms-field-address-address2' ),
						place: fieldWrapper.querySelector( '.wpforms-field-address-city' ),
						postcode: fieldWrapper.querySelector( '.wpforms-field-address-postal' ),
						country_code: fieldWrapper.querySelector( '.wpforms-field-address-country' ),
						/* eslint-enable camelcase */
					};

					const state = fieldWrapper.querySelector( '.wpforms-field-address-state' );

					if ( state.tagName === 'SELECT' ) {
						// eslint-disable-next-line camelcase
						additionalFields.region_code = state;
					} else {
						additionalFields.region = state;
					}
				}

				const fieldID = el.getAttribute( 'id' );

				if ( app.placeAlreadyAdded( fieldID ) ) {
					return;
				}

				fieldsPlaces.push( {
					fieldID,
					searchField: el,
					mapField,
					type,
					additionalFields,
					settings: app.getFieldSettings( el ),
				} );
			} );
		},

		/**
		 * Check if the place has already been added.
		 *
		 * @since 2.11.0
		 *
		 * @param {string} fieldID Field ID.
		 *
		 * @return {boolean} True if the place is already added, false otherwise.
		 */
		placeAlreadyAdded( fieldID ) {
			return fieldsPlaces.some( function( field ) {
				return field.fieldID === fieldID;
			} );
		},

		/**
		 * Get the field settings.
		 *
		 * @since 2.3.0
		 *
		 * @param {Element} el Element.
		 *
		 * @return {Object} The field settings.
		 */
		getFieldSettings( el ) {
			const fieldSettingsName = el.getAttribute( 'id' ).replaceAll( '-', '_' );

			return {
				autocompleteSettings: app.getFieldAutocompleteSettings( fieldSettingsName ),
				mapSettings: app.getFieldMapSettings( fieldSettingsName ),
				markerSettings: app.getFieldMarkerSettings( fieldSettingsName ),
			};
		},

		/**
		 * Get the autocomplete field settings.
		 *
		 * @since 2.3.0
		 *
		 * @param {string} fieldSettingsName The field setting name.
		 *
		 * @return {Object} The field autocomplete settings.
		 */
		getFieldAutocompleteSettings( fieldSettingsName ) {
			return Object.assign(
				{},
				wpforms_geolocation_settings.autocompleteSettings.common ? wpforms_geolocation_settings.autocompleteSettings.common : {},
				wpforms_geolocation_settings.autocompleteSettings[ fieldSettingsName ] ? wpforms_geolocation_settings.autocompleteSettings[ fieldSettingsName ] : {},
			);
		},

		/**
		 * Get the map field settings.
		 *
		 * @since 2.3.0
		 *
		 * @param {string} fieldSettingsName The field setting name.
		 *
		 * @return {Object} The field map settings.
		 */
		getFieldMapSettings( fieldSettingsName ) {
			return Object.assign(
				{
					trackResize: true,
				},
				wpforms_geolocation_settings.mapSettings.common ? wpforms_geolocation_settings.mapSettings.common : {},
				wpforms_geolocation_settings.mapSettings[ fieldSettingsName ] ? wpforms_geolocation_settings.mapSettings[ fieldSettingsName ] : {},
			);
		},

		/**
		 * Get the marker field settings.
		 *
		 * @since 2.3.0
		 *
		 * @param {string} fieldSettingsName The field setting name.
		 *
		 * @return {Object} The field marker settings.
		 */
		getFieldMarkerSettings( fieldSettingsName ) {
			return Object.assign(
				{
					draggable: true,
				},
				wpforms_geolocation_settings.markerSettings.common ? wpforms_geolocation_settings.markerSettings.common : {},
				wpforms_geolocation_settings.markerSettings[ fieldSettingsName ] ? wpforms_geolocation_settings.markerSettings[ fieldSettingsName ] : {},
			);
		},

		/**
		 * Init Mapbox Map.
		 *
		 * @since 2.3.0
		 *
		 * @param {Object} currentFieldPlace Current group field with places API.
		 */
		initMap( currentFieldPlace ) {
			if ( ! currentFieldPlace.mapField ) {
				return;
			}

			// Prevent reinitialization if the map is already initialized.
			if ( currentFieldPlace.mapField.classList.contains( 'mapboxgl-map' ) ) {
				return;
			}

			//Prevent initialization if the Elementor popup is hidden.
			const closestPopup = currentFieldPlace.mapField.closest( '.elementor-location-popup' );

			if ( closestPopup && ! closestPopup.offsetParent ) {
				return;
			}

			mapboxgl.accessToken = wpforms_geolocation_settings.autocompleteSettings.common.access_token;

			currentFieldPlace.map = new mapboxgl.Map(
				Object.assign(
					{
						container: currentFieldPlace.mapField,
					},
					currentFieldPlace.settings.mapSettings
				)
			);

			currentFieldPlace.map.addControl( new mapboxgl.NavigationControl() );

			currentFieldPlace.marker = new mapboxgl.Marker( currentFieldPlace.settings.markerSettings )
				.setLngLat( [ currentFieldPlace.settings.mapSettings.center.lng, currentFieldPlace.settings.mapSettings.center.lat ] )
				.addTo( currentFieldPlace.map );

			currentFieldPlace.marker.on( 'dragend', app.markerChanged );

			// Resize the map after the field visibility was changed.
			const observer = new MutationObserver( function( mutations ) {
				mutations.forEach( function() {
					currentFieldPlace.map.resize();
				} );
			} );

			const mapField = currentFieldPlace.mapField;
			const fieldWrapper = mapField.closest( '.wpforms-page' );

			observer.observe( mapField.parentElement, { attributes: true, attributeFilter: [ 'style' ] } );

			if ( fieldWrapper ) {
				observer.observe( fieldWrapper, { attributes: true, attributeFilter: [ 'style' ] } );
			}
		},

		/**
		 * Update map.
		 *
		 * @since 2.3.0
		 *
		 * @param {Object} currentFieldPlace Current group field with places API.
		 * @param {Object} feature           Current feature.
		 */
		updateMap( currentFieldPlace, feature ) {
			if ( ! currentFieldPlace.map || ! currentFieldPlace.marker ) {
				return;
			}

			const latLng = featureHelper.getFeatureCoordinates( feature );

			currentFieldPlace.marker.setLngLat( [ latLng.lng, latLng.lat ] );
			currentFieldPlace.map.setCenter( [ latLng.lng, latLng.lat ] );
		},

		/**
		 * Marker changed event.
		 *
		 * @since 2.3.0
		 */
		markerChanged() {
			const currentFieldPlace = app.findFieldPlaceBy( 'map', this._map );

			if ( ! currentFieldPlace ) {
				return;
			}

			geocoder.receivePlace( this.getLngLat(), function( feature ) {
				app.updateMap( currentFieldPlace, feature );
				app.updateFields( currentFieldPlace, feature );
			} );
		},

		/**
		 * Find current group field by a field name and value.
		 *
		 * @since 2.3.0
		 *
		 * @param {string} name  Field name.
		 * @param {*}      value Value.
		 *
		 * @return {object|null} currentFieldPlace Current group field with places API.
		 */
		findFieldPlaceBy( name, value ) {
			let currentFieldPlace = null;

			fieldsPlaces.some( function( el ) {
				if (
					( Object.prototype.hasOwnProperty.call( el, name ) && el[ name ] === value ) ||
					( el.additionalFields && Object.prototype.hasOwnProperty.call( el.additionalFields, name ) && el.additionalFields[ name ] === value )
				) {
					currentFieldPlace = el;

					return true;
				}

				return false;
			} );

			return currentFieldPlace;
		},

		/**
		 * Init Mapbox Autocomplete.
		 *
		 * @since 2.3.0
		 *
		 * @param {Object} currentFieldPlace Current group field with places API.
		 */
		initAutocomplete( currentFieldPlace ) {
			mapboxsearch.config.accessToken = wpforms_geolocation_settings.autocompleteSettings.common.access_token;

			const autofill = document.createElement( 'mapbox-address-autofill' );

			autofill.append( currentFieldPlace.searchField.cloneNode( true ) );
			currentFieldPlace.searchField.replaceWith( autofill );

			autofill.accessToken = wpforms_geolocation_settings.autocompleteSettings.common.access_token;
			autofill.options = currentFieldPlace.settings.autocompleteSettings;

			// Add custom styles for the autocomplete dropdown.
			autofill.theme = {
				cssText: `
					.Results {
						z-index: 10000;
					}
				`,
			};

			currentFieldPlace.autocomplete = autofill;
			currentFieldPlace.searchField = autofill.querySelector( 'input' );

			currentFieldPlace.autocomplete.addEventListener( 'retrieve', app.updateFieldPlace );
			currentFieldPlace.autocomplete.addEventListener( 'keydown', app.preventSubmitOnPressEnter );

			// Retrieve the place by the search field value.
			// If form is loaded from a Save and Resume link.
			if ( currentFieldPlace.searchField.value ) {
				geocoder.receivePlaceByQuery( currentFieldPlace.searchField.value, function( feature ) {
					app.updateMap( currentFieldPlace, feature );
				} );
			}

			if ( 'address' === currentFieldPlace.type ) {
				// eslint-disable-next-line camelcase
				currentFieldPlace.additionalFields.address_line1 = currentFieldPlace.searchField;

				app.bindAddressFieldEvents( currentFieldPlace );
			}
		},

		/**
		 * Bind events for the address field.
		 *
		 * @since 2.3.0
		 *
		 * @param {Object} currentFieldPlace Current group field with places API.
		 */
		bindAddressFieldEvents( currentFieldPlace ) {
			if ( currentFieldPlace.additionalFields.country_code ) {
				currentFieldPlace.additionalFields.country_code.addEventListener( 'change', app.updateCountry );
			}

			if ( currentFieldPlace.settings.autocompleteSettings.strict ) {
				// Multi-country restriction doesn't work like for Google Places Provider.
				const country = Array.isArray( currentFieldPlace.settings.autocompleteSettings.strict )
					? currentFieldPlace.settings.autocompleteSettings.strict[ 0 ]
					: currentFieldPlace.settings.autocompleteSettings.strict;

				currentFieldPlace.settings.autocompleteSettings.strict = country;
				currentFieldPlace.autocomplete.options.country = country ? country.toString().toUpperCase() : '';

				currentFieldPlace.additionalFields.region_code.addEventListener( 'change', app.updateArea );
			}
		},

		/**
		 * Update fields on update autocomplete field.
		 *
		 * @since 2.3.0
		 *
		 * @param {Object} e Element.
		 */
		updateFieldPlace( e ) {
			const currentFieldPlace = app.findFieldPlaceBy( 'autocomplete', e.target );

			if ( ! currentFieldPlace ) {
				return;
			}

			const feature = featureHelper.getFirstFeature( e.detail );

			app.updateFields( currentFieldPlace, feature );
			app.updateMap( currentFieldPlace, feature );
		},

		/**
		 * Prevent triggering form submit event when selecting the address in the autocomplete dropdown using the keyboard.
		 *
		 * @since 2.4.0
		 *
		 * @param {Object} e Event object.
		 */
		preventSubmitOnPressEnter( e ) {
			if ( e.keyCode === 13 && e.target.ariaExpanded ) {
				e.stopPropagation();
			}
		},

		/**
		 * Update fields using some place.
		 *
		 * @since 2.3.0
		 *
		 * @param {Object} currentFieldPlace Current group field with places API.
		 * @param {Object} feature           Current feature.
		 */
		updateFields( currentFieldPlace, feature ) {
			if ( 'text' === currentFieldPlace.type ) {
				app.updateTextField( currentFieldPlace, feature );
			} else if ( 'address' === currentFieldPlace.type ) {
				app.updateAddressField( currentFieldPlace, feature );
			}

			app.showDebugMessage( 'Fields was updated' );
			app.showDebugMessage( currentFieldPlace );
			app.showDebugMessage( feature );
		},

		/**
		 * Update text field using some feature.
		 *
		 * @since 2.3.0
		 *
		 * @param {Object} currentFieldPlace Current group field with places API.
		 * @param {Object} feature           Current feature.
		 */
		updateTextField( currentFieldPlace, feature ) {
			currentFieldPlace.searchField.value = featureHelper.getFeatureProperty( feature, 'place_name' );

			app.triggerEvent( currentFieldPlace.searchField, 'change' );
		},

		/**
		 * Update address fields at specified place.
		 *
		 * @since 2.3.0
		 *
		 * @param {Object} currentFieldPlace Current group field with places API.
		 * @param {Object} feature           Current feature.
		 */
		updateAddressField( currentFieldPlace, feature ) {
			app.clearAdditionalFields( currentFieldPlace );

			for ( const [ fieldName, fieldElement ] of Object.entries( currentFieldPlace.additionalFields ) ) {
				const value = featureHelper.getFeatureProperty( feature, fieldName );

				if ( ! value ) {
					continue;
				}

				if ( ! fieldElement && fieldName === 'country_code' ) {
					// Handle absent element value change (used for hidden country fields)
					document.dispatchEvent(
						new CustomEvent( 'wpforms-geolocation-absent-country-changed', {
							detail: {
								stateInputElement: currentFieldPlace.additionalFields.region,
								shortName: value,
							},
						} )
					);
					continue;
				}

				if ( ! fieldElement ) {
					continue;
				}

				if ( app.isConversationalSelect( fieldElement ) ) {
					app.updateConversationalSelect( fieldElement, value );

					continue;
				}

				fieldElement.value = value;

				app.triggerEvent( fieldElement, 'change' );
			}
		},

		/**
		 * Is conversational forms select.
		 *
		 * @since 2.3.0
		 *
		 * @param {Element} field Field.
		 *
		 * @return {boolean} Is the field is conversational select format?
		 */
		isConversationalSelect( field ) {
			if ( field.tagName !== 'SELECT' ) {
				return false;
			}

			return Boolean( field.closest( '.wpforms-conversational-select' ) );
		},

		/**
		 * Update conversational forms select.
		 *
		 * @since 2.3.0
		 *
		 * @param {Element} field Field.
		 * @param {string}  value Value.
		 */
		updateConversationalSelect( field, value ) {
			const select = field.closest( '.wpforms-conversational-select' ),
				selectedOption = field.querySelector( 'option[value="' + value + '"]' ),
				input = select.querySelector( '.wpforms-conversational-form-dropdown-input input' );

			field.value = value;
			app.triggerEvent( field, 'change' );

			if ( selectedOption && input ) {
				input.value = selectedOption.innerText;
			}
		},

		/**
		 * Trigger JS event.
		 *
		 * @since 2.3.0
		 *
		 * @param {Element} el        Element.
		 * @param {string}  eventName Event name.
		 */
		triggerEvent( el, eventName ) {
			const e = new Event( eventName, { bubbles: true, cancelable: true } );

			el.dispatchEvent( e );
		},

		/**
		 * Clear additional fields.
		 *
		 * @since 2.3.0
		 *
		 * @param {Object} currentFieldPlace Current group field with places API.
		 */
		clearAdditionalFields( currentFieldPlace ) {
			if ( ! currentFieldPlace.additionalFields ) {
				return;
			}

			Object.values( currentFieldPlace.additionalFields ).forEach( function( field ) {
				if ( ! field ) {
					return;
				}

				field.value = '';
			} );
		},

		/**
		 * Restrict search results after changing the address country field. The condition is strict.
		 *
		 * @since 2.3.0
		 */
		updateCountry() {
			const currentFieldPlace = app.findFieldPlaceBy( 'country_code', this );

			if ( ! currentFieldPlace || ! currentFieldPlace.autocomplete ) {
				return;
			}

			const countryCode = this.value.toString().toUpperCase();

			currentFieldPlace.autocomplete.options.country = countryCode;

			app.showDebugMessage( 'Autocomplete field restrict to country: ' + countryCode );
		},

		/**
		 * Restrict search results after changing the address state field. The condition isn't strict.
		 *
		 * @since 2.3.0
		 */
		updateArea() {
			const currentFieldPlace = app.findFieldPlaceBy( 'region_code', this ),
				stateCode = this.value.toString().toUpperCase(),
				stateLngLat = states.getStateCoordinates( currentFieldPlace, stateCode );

			app.showDebugMessage( 'Autocomplete field try to find the ' + stateCode + ' state' );

			if ( ! currentFieldPlace || ! stateCode || ! stateLngLat ) {
				app.showDebugMessage( 'Autocomplete field doesn\'t restrict to the ' + stateCode + ' state' );
			}

			currentFieldPlace.autocomplete.options.proximity = stateLngLat;

			app.showDebugMessage( 'Autocomplete field restrict to the ' + stateCode + ' state' );
		},

		/**
		 * Detect customer geolocation.
		 *
		 * @since 2.3.0
		 */
		detectGeolocation() {
			if ( ! wpforms_geolocation_settings.current_location || ! navigator.geolocation || ! fieldsPlaces ) {
				return;
			}

			navigator.geolocation.getCurrentPosition( function( position ) {
				const geolocation = {
					lat: position.coords.latitude.toFixed( 6 ),
					lng: position.coords.longitude.toFixed( 6 ),
				};

				geocoder.receivePlace( geolocation, function( feature ) {
					fieldsPlaces.forEach( function( currentFieldPlace, i ) {
						// Skip when the field map location has already been initialized.
						if ( currentFieldPlace.currentGeolocationInited ) {
							return;
						}

						const container = currentFieldPlace.searchField.closest( '.wpforms-field' );

						if ( container && container.classList.contains( 'wpforms-conditional-hide' ) ) {
							return;
						}

						app.updateMap( currentFieldPlace, feature );
						app.updateFields( currentFieldPlace, feature );

						fieldsPlaces[ i ].currentGeolocationInited = true;
					} );
				} );
			} );
		},
	};

	// Provide access to public functions/properties.
	return app;
}( document, window ) );

// Initialize.
WPFormsGeolocationMapboxAPI.init();

// Initialize after the Elementor popup is opened.
window.addEventListener( 'elementor/popup/show', WPFormsGeolocationMapboxAPI.init );
