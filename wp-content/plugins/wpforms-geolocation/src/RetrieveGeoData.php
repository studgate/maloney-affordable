<?php

namespace WPFormsGeolocation;

/**
 * Class RetrieveGeoData.
 *
 * @since 2.0.0
 */
class RetrieveGeoData {

	/**
	 * Available sources for retrieving geolocation data by API.
	 *
	 * @since 2.3.0
	 *
	 * @var string[]
	 */
	private $available_sources = [
		'wpforms' => 'https://geo.wpforms.com/v3/geolocate/json/%s',
		'ipapi'   => 'https://ipapi.co/%s/json',
		'keycdn'  => 'https://tools.keycdn.com/geo.json?host=%s',
	];

	/**
	 * Hooks.
	 *
	 * @since 2.2.0
	 */
	public function hooks() {

		add_filter( 'wpforms_geolocation_retrieve_geo_data_request_keycdn_args', [ $this, 'modify_keycdn_request_args' ], 10, 2 );
	}

	/**
	 * Get geolocation information from an IP address.
	 *
	 * @since 2.0.0
	 *
	 * @param string $ip User IP.
	 *
	 * @return array
	 */
	public function get_location( $ip = '' ) {

		if ( $this->is_local( $ip ) ) {
			return [];
		}

		foreach ( $this->get_sources() as $source ) {
			if ( ! is_string( $source ) ) {
				continue;
			}

			$data = $this->retrieve_body( $ip, $source );

			if ( ! empty( $data ) ) {
				return array_map( 'sanitize_text_field', $data );
			}
		}

		return [];
	}

	/**
	 * Get the list of sources for retrieving geolocation data.
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	private function get_sources() {

		/**
		 * Modify the list of sources to obtain geolocation data. You can use multiple sources that run one after the other.
		 * The geolocation data from the first successful API request will store in an entry.
		 *
		 * @since 2.3.0
		 *
		 * @param array $sources List of sources for retrieving geolocation data.
		 *
		 * @return array
		 */
		return (array) apply_filters( 'wpforms_geolocation_retrieve_geo_data_get_sources', array_keys( $this->available_sources ) );
	}

	/**
	 * Retrieve geolocation for a specific source by IP.
	 *
	 * @since 2.3.0
	 *
	 * @param string $ip     IP Address.
	 * @param string $source A source name.
	 *
	 * @return array
	 */
	private function retrieve_body( $ip, $source ) {

		if ( ! empty( $this->available_sources[ $source ] ) ) {
			return $this->request( $source, $this->available_sources[ $source ], $ip );
		}

		/**
		 * Allow modifying geolocation data for custom providers.
		 *
		 * @since 2.3.0
		 *
		 * @param array  $data A geolocation data.
		 * @param string $ip   IP Address.
		 *
		 * @return array
		 */
		$data = (array) apply_filters( "wpforms_geolocation_retrieve_geo_data_retrieve_body_{$source}", [], $ip );

		if ( empty( $data['latitude'] ) || empty( $data['longitude'] ) ) {
			return [];
		}

		return wp_parse_args(
			$data,
			[
				'latitude'  => '',
				'longitude' => '',
				'city'      => '',
				'region'    => '',
				'country'   => '',
				'postal'    => '',
			]
		);
	}

	/**
	 * Is local IP Address.
	 *
	 * @since 2.0.0
	 *
	 * @param string $ip IP Address.
	 *
	 * @return bool
	 */
	private function is_local( $ip = '' ) {

		return empty( $ip ) || in_array( $ip, [ '127.0.0.1', '::1' ], true );
	}

	/**
	 * Request for get user geolocation data.
	 *
	 * @since 2.0.0
	 *
	 * @param string $source   Source name.
	 * @param string $endpoint Endpoint.
	 * @param string $ip       IP address.
	 *
	 * @uses  wpforms_response, ipapi_response, keycdn_response
	 *
	 * @return array
	 */
	private function request( $source, $endpoint, $ip ) {

		$endpoint = sprintf( $endpoint, $ip );
		$data     = [];

		/**
		 * Allow modifying request arguments.
		 *
		 * @since 2.2.0
		 *
		 * @param array  $args Request arguments.
		 * @param string $ip   IP address.
		 *
		 * @return array
		 */
		$args = (array) apply_filters( "wpforms_geolocation_retrieve_geo_data_request_{$source}_args", [], $ip );

		$request = wp_remote_get( $endpoint, $args );

		if ( ! is_wp_error( $request ) ) {
			$request = json_decode( wp_remote_retrieve_body( $request ), true );
			$method  = $source . '_response';
			$data    = $this->{$method}( $request );
		}

		return $data;
	}

	/**
	 * Processing request from WPForms.
	 *
	 * @since 2.0.0
	 *
	 * @param array $request_body Request body.
	 *
	 * @return array
	 */
	private function wpforms_response( $request_body ) {

		if ( empty( $request_body['latitude'] ) || empty( $request_body['longitude'] ) ) {
			return [];
		}

		return [
			'latitude'  => $request_body['latitude'],
			'longitude' => $request_body['longitude'],
			'city'      => ! empty( $request_body['city'] ) ? $request_body['city'] : '',
			'region'    => ! empty( $request_body['region_name'] ) ? $request_body['region_name'] : '',
			'country'   => ! empty( $request_body['country_iso'] ) ? $request_body['country_iso'] : '',
			'postal'    => ! empty( $request_body['zip_code'] ) ? $request_body['zip_code'] : '',
		];
	}

	/**
	 * Processing request from IpAPI.
	 *
	 * @since 2.0.0
	 *
	 * @param array $request_body Request body.
	 *
	 * @return array
	 */
	private function ipapi_response( $request_body ) {

		if ( empty( $request_body['latitude'] ) || empty( $request_body['longitude'] ) ) {
			return [];
		}

		return [
			'latitude'  => $request_body['latitude'],
			'longitude' => $request_body['longitude'],
			'city'      => ! empty( $request_body['city'] ) ? $request_body['city'] : '',
			'region'    => ! empty( $request_body['region'] ) ? $request_body['region'] : '',
			'country'   => ! empty( $request_body['country'] ) ? $request_body['country'] : '',
			'postal'    => ! empty( $request_body['postal'] ) ? $request_body['postal'] : '',
		];
	}

	/**
	 * Processing request from KeyCDN.
	 *
	 * @since 2.0.0
	 *
	 * @param array $request_body Request body.
	 *
	 * @return array
	 */
	private function keycdn_response( $request_body ) {

		if ( empty( $request_body['data']['geo']['latitude'] ) || empty( $request_body['data']['geo']['longitude'] ) ) {
			return [];
		}

		return [
			'latitude'  => $request_body['data']['geo']['latitude'],
			'longitude' => $request_body['data']['geo']['longitude'],
			'city'      => ! empty( $request_body['data']['geo']['city'] ) ? $request_body['data']['geo']['city'] : '',
			'region'    => ! empty( $request_body['data']['geo']['region_name'] ) ? $request_body['data']['geo']['region_name'] : '',
			'country'   => ! empty( $request_body['data']['geo']['country_code'] ) ? $request_body['data']['geo']['country_code'] : '',
			'postal'    => ! empty( $request_body['data']['geo']['postal_code'] ) ? $request_body['data']['geo']['postal_code'] : '',
		];
	}

	/**
	 * Modify request arguments for the KeyCDN geolocation provider.
	 *
	 * @since 2.2.0
	 *
	 * @param array  $args Request arguments.
	 * @param string $ip   IP address.
	 *
	 * @return array
	 */
	public function modify_keycdn_request_args( $args, $ip ) {

		$args['user-agent'] = sprintf( 'keycdn-tools:%s', site_url() );

		return $args;
	}
}
