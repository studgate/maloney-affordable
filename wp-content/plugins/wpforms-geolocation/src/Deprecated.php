<?php

namespace WPFormsGeolocation;

/**
 * Deprecated class to prevent fatal errors for removed classes.
 *
 * @since 2.3.0
 */
class Deprecated {

	/**
	 * Deprecate all methods call.
	 *
	 * @since 2.3.0
	 *
	 * @param string $name      Method name.
	 * @param array  $arguments List of arguments.
	 */
	public function __call( $name, $arguments ) {

		_deprecated_function( esc_html( get_class( $this ) . '::' . $name ), '2.3.0 of the WPForms Geolocation addon' );
	}
}
