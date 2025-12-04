<?php
if ( ! class_exists( 'ET_Builder_Module_Button' ) ) {
	return;
}

/**
 * Create a custom button module for Divi.
 *
 */
class MonsterInsights_Divi_Button_Module extends ET_Builder_Module_Button {

	/**
	 * This function is used to initialize the module.
	 * We have copy this function from the ET_Builder_Module_Button class.
	 * And then we have modified it to our needs.
	 *
	 * @return void
	 */
	public function init() {
		$this->name             = 'MonsterInsights Button';
		$this->plural           = 'MonsterInsights Buttons';
		$this->slug             = 'monsterinsights_divi_button_module';
		$this->vb_support       = 'partial'; // Visual Builder support (off|partial|on)
		$this->main_css_element = '%%order_class%%';
		$this->wrapper_settings = array(
			// Flag that indicates that this module's wrapper where order class is declared
			// has another wrapper (mostly for button alignment purpose).
			'order_class_wrapper' => true,
		);

		$this->custom_css_fields = array(
			'main_element' => array(
				'label'                    => et_builder_i18n( 'Main Element' ),
				'no_space_before_selector' => true,
			),
		);

		$this->settings_modal_toggles = array(
			'general'  => array(
				'toggles' => array(
					'main_content' => et_builder_i18n( 'Text' ),
					'link'         => et_builder_i18n( 'Link' ),
				),
			),
			'advanced' => array(
				'toggles' => array(
					'alignment' => esc_html__( 'Alignment', 'et_builder' ),
					'text'      => array(
						'title'    => et_builder_i18n( 'Text' ),
						'priority' => 49,
					),
				),
			),
			'custom_css'  => array(
				'toggles' => array(
					'monsterinsights_advanced_toggle' => array(
						'title'    => 'MonsterInsights',
						'priority' => 250,
					),
				),
			),
		);

		$this->advanced_fields = array(
			'borders'         => array(
				'default' => false,
			),
			'button'          => array(
				'button' => array(
					'label'          => et_builder_i18n( 'Button' ),
					'css'            => array(
						'main'         => $this->main_css_element,
						'limited_main' => "{$this->main_css_element}.et_pb_button",
					),
					'box_shadow'     => false,
					'margin_padding' => false,
				),
			),
			'margin_padding'  => array(
				'css' => array(
					'padding'   => "{$this->main_css_element}_wrapper {$this->main_css_element}, {$this->main_css_element}_wrapper {$this->main_css_element}:hover",
					'margin'    => "{$this->main_css_element}_wrapper",
					'important' => 'all',
				),
			),
			'text'            => array(
				'use_text_orientation'  => false,
				'use_background_layout' => true,
				'options'               => array(
					'background_layout' => array(
						'default_on_front' => 'light',
						'hover'            => 'tabs',
					),
				),
			),
			'text_shadow'     => array(
				// Text Shadow settings are already included on button's advanced style
				'default' => false,
			),
			'background'      => false,
			'fonts'           => false,
			'max_width'       => false,
			'height'          => false,
			'link_options'    => false,
			'position_fields' => array(
				'css' => array(
					'main' => "{$this->main_css_element}_wrapper",
				),
			),
			'transform'       => array(
				'css' => array(
					'main' => "{$this->main_css_element}_wrapper",
				),
			),
		);

		$this->help_videos = array(
			array(
				'id'   => 'XpM2G7tQQIE',
				'name' => esc_html__( 'An introduction to the Button module', 'et_builder' ),
			),
		);
	}

	public function get_fields() {
		$fields = parent::get_fields();

		$fields['monsterinsights_mark_as_conversion_event'] = array(
			'label' => 'MonsterInsights Mark as Conversion Event',
			'description' => 'Mark this button as a conversion event which can be tracked in all of your reports.',
			'type' => 'yes_no_button',
			'default' => 'off',
			'options' => array(
				'off' => 'No',
				'on' => 'Yes',
			),
			'tab_slug' => 'custom_css',
			'toggle_slug' => 'monsterinsights_advanced_toggle',
			'affects'          => array(
				'monsterinsights_custom_event_name',
				'monsterinsights_mark_as_key_event'
			),
		);

		$fields['monsterinsights_custom_event_name'] = array(
			'label' => 'MonsterInsights Custom Event Name',
			'type' => 'text',
			'placeholder' => 'click-(elementID)',
			'tab_slug' => 'custom_css',
			'toggle_slug' => 'monsterinsights_advanced_toggle',
			'depends_show_if'  => 'on',
		);

		$fields['monsterinsights_mark_as_key_event'] = array(
			'label' => 'MonsterInsights Mark as Key Event',
			'description' => 'Mark this click as a key event which can be tracked in all of your reports.',
			'type' => 'yes_no_button',
			'default' => 'off',
			'options' => array(
				'off' => 'No',
				'on' => 'Yes',
			),
			'tab_slug' => 'custom_css',
			'toggle_slug' => 'monsterinsights_advanced_toggle',
			'depends_show_if'  => 'on',
		);

		return $fields;
	}

	/**
	 * This function is used to render the module output.
	 * We have modified it to our needs.
	 */
	public function render( $attrs, $content, $render_slug ) {
		$output = parent::render( $attrs, $content, $render_slug );

		if ( empty( $attrs['monsterinsights_mark_as_conversion_event'] ) ) {
			return $output;
		}

		$mark_as_conversion_event = $this->props['monsterinsights_mark_as_conversion_event'];
		$custom_event_name = $this->props['monsterinsights_custom_event_name'];

		if ( $mark_as_conversion_event == 'on' ) {
			// Add data-mi-conversion-event="1" to the first <a> tag if not already present.
			if ( false === strpos( $output, 'data-mi-conversion-event=' ) ) {
				$output = preg_replace( '/<a\b([^>]*)>/', '<a$1 data-mi-conversion-event="1">', $output, 1 );
			}

			// Add data-mi-event-name to the first <a> tag if a custom event name is provided and not already present.
			$custom_event_name = trim( (string) $custom_event_name );
			if ( $custom_event_name !== '' && false === strpos( $output, 'data-mi-event-name=' ) ) {
				$output = preg_replace( '/<a\b([^>]*)>/', '<a$1 data-mi-event-name="' . $custom_event_name . '">', $output, 1 );
			}
		}

		return $output;
	}
}
