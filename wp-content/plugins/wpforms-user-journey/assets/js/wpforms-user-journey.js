/* global wpforms_user_journey */

'use strict';

/**
 * WPForms User Journey function.
 *
 * @since 1.0.0
 */
var WPFormsUserJourney = window.WPFormsUserJourney || ( function( document, window ) {

	/**
	 * Public functions and properties.
	 *
	 * @since 1.0.0
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Start the engine.
		 *
		 * @since 1.0.0
		 */
		init: function() {

			if ( ! String.prototype.startsWith ) {
				Object.defineProperty(
					String.prototype,
					'startsWith',
					{
						value: function( search, rawPos ) {
							var pos = rawPos > 0 ? rawPos | 0 : 0;
							return this.substring( pos, pos + search.length ) === search;
						},
					}
				);
			}

			var timeStamp = Math.round( Date.now() / 1000 ),
				cookie    = app.getCookie( '_wpfuj' ),
				data      = {},
				url       = window.location.href;

			if ( ! cookie && document.referrer !== '' && ! document.referrer.startsWith( window.location.origin ) ) {
				data[ timeStamp - 2 ] = document.referrer + '|#|{ReferrerPageTitle}';
			}

			url += '|#|' + document.title;

			if ( typeof wpforms_user_journey !== 'undefined' && wpforms_user_journey.page_id ) {
				url += '|#|' + Number( wpforms_user_journey.page_id );
			}

			if ( cookie ) {
				data = JSON.parse( cookie );
			}

			const encodedUrl = encodeURIComponent( app.addSlashes( url ) );
			const latestTimeStamp = app.getLatestTimeStamp( data );

			// Do not repeat info about the same page on reload.
			if ( data[latestTimeStamp] !== encodedUrl ) {
				data[timeStamp] = encodedUrl;
			}

			// Max Cookie length is 4096 bytes. We add less than 80 bytes of supplemental info to the journey data object.
			const maxDataLength = 4096 - 80;

			let dataString = JSON.stringify( data );

			// If cookie info exceeds the max length, cookie will not be updated anymore.
			// So, we have to delete the oldest info from the journey object.
			while ( dataString.length > maxDataLength ) {
				delete data[ app.getEarliestTimeStamp( data ) ];
				dataString = JSON.stringify( data );
			}

			app.createCookie( '_wpfuj', JSON.stringify( data ), 365 );
		},

		/**
		 * Get the earliest timestamp from data object.
		 *
		 * @since 1.0.3
		 *
		 * @param {object} data Data object containing journey info.
		 * @returns {string} The earliest timestamp.
		 */
		getEarliestTimeStamp: function( data ) {

			let timeStamps = Object.keys( data ).map( timeStamp => parseInt( timeStamp, 10 ) );

			return Math.min( ...timeStamps ).toString();
		},

		/**
		 * Get the latest timestamp from data object.
		 *
		 * @since 1.0.3
		 *
		 * @param {object} data Data object containing journey info.
		 * @returns {string} The latest timestamp.
		 */
		getLatestTimeStamp: function( data ) {

			let timeStamps = Object.keys( data ).map( timeStamp => parseInt( timeStamp, 10 ) );

			return Math.max( ...timeStamps ).toString();
		},

		/**
		 * Create cookie.
		 * We can't use this method from wpforms because this script must load on each page.
		 *
		 * @since 1.0.0
		 *
		 * @param {string} name  Cookie name.
		 * @param {string} value Cookie value.
		 * @param {string} days  Whether it should expire and when.
		 */
		createCookie: function( name, value, days ) {

			var expires = '';
			var secure = '';

			if ( wpforms_user_journey.is_ssl ) {
				secure = ';secure';
			}

			// If we have a days value, set it in the expiry of the cookie.
			if ( days ) {

				// If -1 is our value, set a session based cookie instead of a persistent cookie.
				if ( '-1' === days ) {
					expires = '';
				} else {
					var date = new Date();
					date.setTime( date.getTime() + ( days * 24 * 60 * 60 * 1000 ) );
					expires = ';expires=' + date.toGMTString();
				}
			} else {
				expires = ';expires=Thu, 01 Jan 1970 00:00:01 GMT';
			}

			// Write the cookie.
			document.cookie = name + '=' + value + expires + ';path=/;samesite=strict' + secure;
		},

		/**
		 * Retrieve cookie.
		 * We can't use this method from wpforms because this script must load on each page.
		 *
		 * @since 1.0.0
		 *
		 * @param {string} name Cookie name.
		 *
		 * @returns {string|null} Cookie value or null when it doesn't exist.
		 */
		getCookie: function( name ) {

			var nameEQ = name + '=',
				ca     = document.cookie.split( ';' );

			for ( var i = 0; i < ca.length; i++ ) {
				var c = ca[i];
				while ( ' ' === c.charAt( 0 ) ) {
					c = c.substring( 1, c.length );
				}
				if ( 0 === c.indexOf( nameEQ ) ) {
					return c.substring( nameEQ.length, c.length );
				}
			}

			return null;
		},

		/**
		 * Add slashes to the string.
		 *
		 * @see https://locutus.io/php/strings/addslashes/
		 *
		 * @since 1.1.0
		 *
		 * @param {string} str Text string.
		 *
		 * @returns {string} String with slashes.
		 */
		addSlashes: function( str ) {

			return ( str + '' )
				.replace( /[\\"]/g, '\\$&' );
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window ) );

// Initialize.
WPFormsUserJourney.init();
