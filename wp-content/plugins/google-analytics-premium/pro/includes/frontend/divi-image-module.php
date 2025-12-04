<?php
/**
 * MonsterInsights Divi Image Module
 */

class MonsterInsights_Divi_Image_Module extends ET_Builder_Module_Image {
	function init() {
		$this->name       = 'MonsterInsights Image';
		$this->plural     = 'MonsterInsights Images';
		$this->slug       = 'monsterinsights_divi_image_module';
		$this->vb_support = 'partial';

		$this->settings_modal_toggles = array(
			'general'    => array(
				'toggles' => array(
					'main_content' => et_builder_i18n( 'Image' ),
					'link'         => et_builder_i18n( 'Link' ),
				),
			),
			'advanced'   => array(
				'toggles' => array(
					'overlay'   => et_builder_i18n( 'Overlay' ),
					'alignment' => esc_html__( 'Alignment', 'et_builder' ),
					'width'     => array(
						'title'    => et_builder_i18n( 'Sizing' ),
						'priority' => 65,
					),
				),
			),
			'custom_css' => array(
				'toggles' => array(
					'animation'  => array(
						'title'    => esc_html__( 'Animation', 'et_builder' ),
						'priority' => 90,
					),
					'attributes' => array(
						'title'    => esc_html__( 'Attributes', 'et_builder' ),
						'priority' => 95,
					),

					'monsterinsights_advanced_toggle' => array(
						'title'    => 'MonsterInsights',
						'priority' => 250,
					),
				),
			),
		);

		$this->advanced_fields = array(
			'margin_padding' => array(
				'css' => array(
					'important' => array( 'custom_margin' ),
				),
			),
			'borders'        => array(
				'default' => array(
					'css' => array(
						'main' => array(
							'border_radii'  => '%%order_class%% .et_pb_image_wrap',
							'border_styles' => '%%order_class%% .et_pb_image_wrap',
						),
					),
				),
			),
			'box_shadow'     => array(
				'default' => array(
					'css' => array(
						'main'    => '%%order_class%% .et_pb_image_wrap',
						'overlay' => 'inset',
					),
				),
			),
			'max_width'      => array(
				'options' => array(
					'width'     => array(
						'depends_show_if' => 'off',
					),
					'max_width' => array(
						'depends_show_if' => 'off',
					),
				),
			),
			'height'         => array(
				'css' => array(
					'main' => '%%order_class%% .et_pb_image_wrap img',
				),
			),
			'fonts'          => false,
			'text'           => false,
			'button'         => false,
			'link_options'   => false,
		);

		$this->help_videos = array(
			array(
				'id'   => 'cYwqxoHnjNA',
				'name' => esc_html__( 'An introduction to the Image module', 'et_builder' ),
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

		if ( empty( $attrs['monsterinsights_mark_as_key_event'] ) ) {
			$filename = pathinfo($this->props['title_text'], PATHINFO_FILENAME); // get the filename from the title text.
			$custom_event_name = 'click-' . sanitize_title($filename);
		} else {
			$custom_event_name = trim( (string) $this->props['monsterinsights_custom_event_name'] );
		}

		if ( $mark_as_conversion_event == 'on' ) {
			// Add data-mi-conversion-event="1" to the first <a> tag if not already present.
			if ( false === strpos( $output, 'data-mi-conversion-event=' ) ) {
				$output = preg_replace( '/<a\b([^>]*)>/', '<a$1 data-mi-conversion-event="1">', $output, 1 );
			}

			// Add data-mi-event-name to the first <a> tag if a custom event name is provided and not already present.
			if ( $custom_event_name !== '' && false === strpos( $output, 'data-mi-event-name=' ) ) {
				$output = preg_replace( '/<a\b([^>]*)>/', '<a$1 data-mi-event-name="' . $custom_event_name . '">', $output, 1 );
			}
		}

		return $output;
	}
}
