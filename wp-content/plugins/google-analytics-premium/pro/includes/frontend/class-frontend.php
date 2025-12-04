<?php

//  TODO Go through this file and remove UA and dual tracking references/usages

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function monsterinsights_add_analytics_options( $options ) {
	if ( monsterinsights_get_option( 'userid', false ) && is_user_logged_in() ) {
		$options['userid'] = "'set', 'userId', '" . get_current_user_id() . "'";
	}

	return $options;
}

add_filter( 'monsterinsights_frontend_tracking_options_analytics_before_scripts', 'monsterinsights_add_analytics_options' );

// For gtag
function monsterinsights_add_analytics_options_gtag( $options ) {
	if ( monsterinsights_get_option( 'userid', false ) && is_user_logged_in() ) {
		$options['user_id'] = get_current_user_id();
	}

	return $options;
}

add_filter( 'monsterinsights_frontend_tracking_options_persistent_gtag_before_pageview', 'monsterinsights_add_analytics_options_gtag' );

function monsterinsights_scroll_tracking_maybe_v4() {
	$v4_id = monsterinsights_get_v4_id_to_output();
	if ( ! $v4_id ) {
		return;
	}
	?>
	var paramName = action.toLowerCase();
	var fieldsArray = {
	send_to: '<?php echo esc_js( $v4_id ); ?>',
	non_interaction: true
	};
	fieldsArray[paramName] = label;

	if (arguments.length > 3) {
	fieldsArray.scroll_timing = timing
	MonsterInsightsObject.sendEvent('event', 'scroll_depth', fieldsArray);
	} else {
	MonsterInsightsObject.sendEvent('event', 'scroll_depth', fieldsArray);
	}
	<?php
}

// Scroll tracking.
function monsterinsights_scroll_tracking_output_after_script() {
	if ( monsterinsights_skip_tracking() ) {
		return;
	}

	$track_user   = monsterinsights_track_user();
	$tracking_id = monsterinsights_get_v4_id();

	if ( $track_user && ! empty( $tracking_id ) ) {
		ob_start();
		echo PHP_EOL;
		?>
		/* MonsterInsights Scroll Tracking */
		if ( typeof(jQuery) !== 'undefined' ) {
		jQuery( document ).ready(function(){
		function monsterinsights_scroll_tracking_load() {
		if ( ( typeof(__gaTracker) !== 'undefined' && __gaTracker && __gaTracker.hasOwnProperty( "loaded" ) && __gaTracker.loaded == true ) || ( typeof(__gtagTracker) !== 'undefined' && __gtagTracker ) ) {
		(function(factory) {
		factory(jQuery);
		}(function($) {

		/* Scroll Depth */
		"use strict";
		var defaults = {
		percentage: true
		};

		var $window = $(window),
		cache = [],
		scrollEventBound = false,
		lastPixelDepth = 0;

		/*
		* Plugin
		*/

		$.scrollDepth = function(options) {

		var startTime = +new Date();

		options = $.extend({}, defaults, options);

		/*
		* Functions
		*/

		function sendEvent(action, label, scrollDistance, timing) {
		if ( 'undefined' === typeof MonsterInsightsObject || 'undefined' === typeof MonsterInsightsObject.sendEvent ) {
		return;
		}
		<?php
		monsterinsights_scroll_tracking_maybe_v4();
		?>
		}

		function calculateMarks(docHeight) {
		return {
		'25%' : parseInt(docHeight * 0.25, 10),
		'50%' : parseInt(docHeight * 0.50, 10),
		'75%' : parseInt(docHeight * 0.75, 10),
		/* Cushion to trigger 100% event in iOS */
		'100%': docHeight - 5
		};
		}

		function checkMarks(marks, scrollDistance, timing) {
		/* Check each active mark */
		$.each(marks, function(key, val) {
		if ( $.inArray(key, cache) === -1 && scrollDistance >= val ) {
		sendEvent('Percentage', key, scrollDistance, timing);
		cache.push(key);
		}
		});
		}

		function rounded(scrollDistance) {
		/* Returns String */
		return (Math.floor(scrollDistance/250) * 250).toString();
		}

		function init() {
		bindScrollDepth();
		}

		/*
		* Public Methods
		*/

		/* Reset Scroll Depth with the originally initialized options */
		$.scrollDepth.reset = function() {
		cache = [];
		lastPixelDepth = 0;
		$window.off('scroll.scrollDepth');
		bindScrollDepth();
		};

		/* Add DOM elements to be tracked */
		$.scrollDepth.addElements = function(elems) {

		if (typeof elems == "undefined" || !$.isArray(elems)) {
		return;
		}

		$.merge(options.elements, elems);

		/* If scroll event has been unbound from window, rebind */
		if (!scrollEventBound) {
		bindScrollDepth();
		}

		};

		/* Remove DOM elements currently tracked */
		$.scrollDepth.removeElements = function(elems) {

		if (typeof elems == "undefined" || !$.isArray(elems)) {
		return;
		}

		$.each(elems, function(index, elem) {

		var inElementsArray = $.inArray(elem, options.elements);
		var inCacheArray = $.inArray(elem, cache);

		if (inElementsArray != -1) {
		options.elements.splice(inElementsArray, 1);
		}

		if (inCacheArray != -1) {
		cache.splice(inCacheArray, 1);
		}

		});

		};

		/*
		* Throttle function borrowed from:
		* Underscore.js 1.5.2
		* http://underscorejs.org
		* (c) 2009-2013 Jeremy Ashkenas, DocumentCloud and Investigative Reporters & Editors
		* Underscore may be freely distributed under the MIT license.
		*/

		function throttle(func, wait) {
		var context, args, result;
		var timeout = null;
		var previous = 0;
		var later = function() {
		previous = new Date;
		timeout = null;
		result = func.apply(context, args);
		};
		return function() {
		var now = new Date;
		if (!previous) previous = now;
		var remaining = wait - (now - previous);
		context = this;
		args = arguments;
		if (remaining <= 0) {
		clearTimeout(timeout);
		timeout = null;
		previous = now;
		result = func.apply(context, args);
		} else if (!timeout) {
		timeout = setTimeout(later, remaining);
		}
		return result;
		};
		}

		/*
		* Scroll Event
		*/

		function bindScrollDepth() {

		scrollEventBound = true;

		$window.on('scroll.scrollDepth', throttle(function() {
		/*
		* We calculate document and window height on each scroll event to
		* account for dynamic DOM changes.
		*/

		var docHeight = $(document).height(),
		winHeight = window.innerHeight ? window.innerHeight : $window.height(),
		scrollDistance = $window.scrollTop() + winHeight,

		/* Recalculate percentage marks */
		marks = calculateMarks(docHeight),

		/* Timing */
		timing = +new Date - startTime;

		checkMarks(marks, scrollDistance, timing);
		}, 500));

		}

		init();
		};

		/* UMD export */
		return $.scrollDepth;

		}));

		jQuery.scrollDepth();
		} else {
		setTimeout(monsterinsights_scroll_tracking_load, 200);
		}
		}
		monsterinsights_scroll_tracking_load();
		});
		}
		/* End MonsterInsights Scroll Tracking */
		<?php
		monsterinsights_send_gutenberg_conversion_event_script();

		echo PHP_EOL;
		$scroll_script = ob_get_clean();

		if ( wp_script_is( 'jquery', 'enqueued' ) ) {
			echo '<script type="text/javascript">' . $scroll_script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			wp_enqueue_script( 'jquery' );
			wp_add_inline_script( 'jquery', $scroll_script );
		}
	}

}

add_action( 'wp_footer', 'monsterinsights_scroll_tracking_output_after_script', 11 );

/**
 * Skip page tracking based on the meta field.
 *
 * @param bool $skipped status of page tracking skip.
 *
 * @return bool
 */
function monsterinsights_skip_page_tracking( $skipped ) {
	if ( ! is_singular() ) {
		return $skipped;
	}

	global $post;
	if ( isset( $post ) ) {
		return (bool) get_post_meta( $post->ID, '_monsterinsights_skip_tracking', true );
	}

	return $skipped;
}

add_filter( 'monsterinsights_skip_tracking', 'monsterinsights_skip_page_tracking' );

/**
 * Inject MonsterInsights data attributes into core/button markup on render.
 *
 * We rely on block comment serialization (JS) and read attributes here so
 * front-end HTML stays unchanged unless explicitly enabled on the block.
 *
 * @param string $block_content Rendered HTML of the block.
 * @param array  $block         Full block data including name and attrs.
 *
 * @return string Possibly modified block HTML
 */
function monsterinsights_render_core_button_add_attributes( $block_content, $block ) {
	if ( empty( $block ) || ! is_array( $block ) ) {
		return $block_content;
	}

	$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';

	$supported_blocks = [ 'core/button', 'core/image' ];

	if ( ! in_array( $block_name, $supported_blocks ) ) {
		return $block_content;
	}

	$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

	// Require explicit enablement to avoid affecting existing buttons.
	if ( empty( $attrs['monsterinsightsMarkAsConversionEvent'] ) ) {
		return $block_content;
	}

	$license_type = MonsterInsights()->license->get_license_type();

	// Do not allow the conversion tracking if the license is plus.
	if ( 'plus' === $license_type ) {
		return $block_content;
	}

	// Prevent double-injection.
	if ( false !== stripos( $block_content, 'data-mi-conversion-event' ) ) {
		return $block_content;
	}

	$attributes_to_inject = ' data-mi-conversion-event="1"';

	if ( ! empty( $attrs['monsterinsightsCustomEventName'] ) ) {
		$attributes_to_inject .= ' data-mi-event-name="' . esc_attr( $attrs['monsterinsightsCustomEventName'] ) . '"';
	}

	// Add attributes to the first <a ...> tag in the button block HTML only.
	$updated = preg_replace( '/<a\b(?![^>]*\bdata-mi-conversion-event\b)/i', '<a' . $attributes_to_inject . ' ', $block_content, 1 );

	return is_string( $updated ) ? $updated : $block_content;
}

add_filter( 'render_block', 'monsterinsights_render_core_button_add_attributes', 10, 2 );

/**
 * Script to send the conversion event.
 */
function monsterinsights_send_gutenberg_conversion_event_script() {
	$license_type = MonsterInsights()->license->get_license_type();

	// Do not allow the conversion tracking if the license is plus.
	if ( 'plus' === $license_type ) {
		return;
	}
	?>
	/* MonsterInsights Conversion Event */
	jQuery(document).ready(function() {
		jQuery('a[data-mi-conversion-event]')
			.off('click.monsterinsightsConversion')
			.on('click.monsterinsightsConversion', function() {
				if ( typeof(__gtagTracker) !== 'undefined' && __gtagTracker ) {
					var $link = jQuery(this);
					var eventName = $link.attr('data-mi-event-name');
					if ( typeof eventName === 'undefined' || ! eventName ) {
						// Fallback to first word of the <a> tag, lowercase, strip html
						var text = $link.text().trim();
						text = text.replace(/(<([^>]+)>)/gi, '').toLowerCase();
						var firstWord = text.split(/\s+/)[0] || '';

						if ( firstWord ) {
							eventName = 'click-' + firstWord;
						} else {
							eventName = $link.parent().hasClass('wp-block-image') ? 'image-click' : 'button-click';
						}
					}
					__gtagTracker('event', 'mi-' + eventName);
				}
			});
	});
	/* End MonsterInsights Conversion Event */
	<?php
}

/**
 * Add custom control to the Elementor button widget.
 */
function monsterinsights_add_elementor_button_control( \Elementor\Controls_Stack $element, $args ) {

	// Add your custom control or logic directly to button widget
	$element->start_controls_section(
		'monsterinsights_custom_conversion_event_section',
		[
			'label' => 'MonsterInsights',
			'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
		]
	);

	$element->add_control(
		'monsterinsights_mark_as_conversion_event',
		[
			'label'   => esc_html__( 'Mark as a conversion event', 'google-analytics-premium' ),
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => 'no',
		]
	);

	$element->add_control(
		'monsterinsights_custom_event_name',
		[
			'label'   => esc_html__( 'Custom event name', 'google-analytics-premium' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => '',
			'label_block' => true,
			'placeholder' => 'click-(elementID)',
			'condition' => [
				'monsterinsights_mark_as_conversion_event' => 'yes',
			],
		]
	);

	$element->add_control(
		'monsterinsights_mark_as_key_event',
		[
			'label'   => esc_html__( 'Mark as a key event', 'google-analytics-premium' ),
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => 'no',
			'description' => esc_html__( 'Mark this click as a key event which can be tracked in all of your reports.', 'google-analytics-premium' ),
			'condition' => [
				'monsterinsights_mark_as_conversion_event' => 'yes',
			],
		]
	);

	$element->end_controls_section();

}
add_action( 'elementor/element/button/section_style/after_section_end', 'monsterinsights_add_elementor_button_control', 10, 2 );
add_action( 'elementor/element/image/section_style_image/after_section_end', 'monsterinsights_add_elementor_button_control', 10, 2 );

/**
 * Inject data attributes into Elementor Button and Image widgets when enabled.
 *
 * @param \Elementor\Element_Base $element The widget element instance.
 */
function monsterinsights_elementor_add_conversion_event_attribute( \Elementor\Element_Base $element ) {
	$widget_name = $element->get_name();

	if ( 'button' !== $widget_name && 'image' !== $widget_name ) {
		return;
	}

	$settings = $element->get_settings_for_display();

	if ( empty( $settings['monsterinsights_mark_as_conversion_event'] ) || 'yes' !== $settings['monsterinsights_mark_as_conversion_event'] ) {
		return;
	}

	$license_type = MonsterInsights()->license->get_license_type();

	// Do not allow the conversion tracking if the license is plus.
	if ( 'plus' === $license_type ) {
		return;
	}

	if ( empty( $settings['monsterinsights_custom_event_name'] ) ) {
		$event_name = 'click-' . $element->get_id();
	} else {
		$event_name = $settings['monsterinsights_custom_event_name'];
	}

	if ( 'button' === $widget_name ) {
		$element->add_render_attribute( 'button', 'data-mi-conversion-event', '1' );
		$element->add_render_attribute( 'button', 'data-mi-event-name', $event_name );
	} else { // image
		// Add to the link wrapper when image is linked.
		$element->add_render_attribute( 'link', 'data-mi-conversion-event', '1' );
		$element->add_render_attribute( 'link', 'data-mi-event-name', $event_name );
	}
}

add_action( 'elementor/frontend/widget/before_render', 'monsterinsights_elementor_add_conversion_event_attribute' );

function monsterinsights_elementor_enqueue_editor_scripts() {
	$nonce = wp_create_nonce( 'monsterinsights_gutenberg_headline_nonce' );
	?>
	<script>
		jQuery(document).ready(function($) {
			// Delegate to document so it works for dynamically loaded Elementor controls
			$(document).on('change', 'input.elementor-switch-input[data-setting="monsterinsights_mark_as_key_event"]', function() {
				var $input = $(this);
				var isChecked = $input.prop('checked');
				if (isChecked) {
					var eventName = $('input[data-setting="monsterinsights_custom_event_name"]').val().trim();
					if ( eventName ) {
						// Send ajax request to mark as key event. Send request to relay to mark as key event.
						wp.ajax.post( 'monsterinsights_conversion_tracking_mark_as_key_event', {
							eventName: eventName,
							nonce: "<?php echo esc_js( $nonce ); ?>",
						} ).done( function( response ) {
							if ( response ) {
								elementorCommon.dialogsManager.createWidget( 'alert', {
									headerMessage: 'MonsterInsights',
									message: response.message,
									strings: { confirm: 'Close' },
								} )
								.show();
							}
						});
					} else {
						elementorCommon.dialogsManager.createWidget( 'alert', {
							headerMessage: 'MonsterInsights',
							message: 'Event name cannot be empty to mark as Key Event.',
							strings: { confirm: 'Close' },
						} )
						.show();
						$input.prop('checked', false);
					}
				}
			});
		});
	</script>
	<?php
}
add_action( 'elementor/editor/footer', 'monsterinsights_elementor_enqueue_editor_scripts', 100 );
