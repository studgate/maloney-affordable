<?php

namespace WPFormsGeolocation\Admin\Settings;

use WPFormsGeolocation\Map;
use WPFormsGeolocation\Front\Fields;
use WPFormsGeolocation\PlacesProviders\ProvidersFactory;

/**
 * Class Preview.
 *
 * @since 2.3.0
 */
class Preview {

	/**
	 * Provider factory.
	 *
	 * @since 2.3.0
	 *
	 * @var ProvidersFactory
	 */
	private $providers_factory;

	/**
	 * Fields.
	 *
	 * @since 2.3.0
	 *
	 * @var Fields
	 */
	private $fields;

	/**
	 * Settings.
	 *
	 * @since 2.3.0
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Map.
	 *
	 * @since 2.3.0
	 *
	 * @var Map
	 */
	private $map;

	/**
	 * Preview constructor.
	 *
	 * @since 2.3.0
	 *
	 * @param ProvidersFactory $providers_factory Provider factory.
	 * @param Fields           $fields            Fields.
	 * @param Settings         $settings          Settings.
	 * @param Map              $map               Map.
	 */
	public function __construct( ProvidersFactory $providers_factory, Fields $fields, Settings $settings, Map $map ) {

		$this->providers_factory = $providers_factory;
		$this->fields            = $fields;
		$this->settings          = $settings;
		$this->map               = $map;
	}

	/**
	 * Hooks.
	 *
	 * @since 2.3.0
	 */
	public function hooks() {

		if ( ! wpforms_is_admin_page( 'settings', Settings::SLUG ) ) {
			return;
		}

		add_action(
			'admin_init',
			function () {

				$provider = $this->providers_factory->get_current_provider();

				if ( ! $provider || ! $provider->is_active() ) {
					return;
				}

				add_action( 'admin_print_footer_scripts', [ $this, 'enqueue_assets' ], 9 );

				$provider_name = $this->settings->get_current_provider();

				add_filter( "wpforms_geolocation_admin_settings_settings_get_provider_options_{$provider_name}", [ $this, 'add_preview' ], 1000 );
			},
			11
		);
	}

	/**
	 * Enqueue CSS & JS that related to the current provider.
	 *
	 * @since 2.3.0
	 */
	public function enqueue_assets() {

		$provider  = $this->providers_factory->get_current_provider();
		$form_data = [
			'id'     => 1,
			'fields' => [
				[
					'id'                          => 1,
					'type'                        => 'text',
					'enable_address_autocomplete' => 1,
					'display_map'                 => 1,
				],
			],
		];
		$forms     = [ $form_data ];

		$this->fields->init_autocomplete( $form_data );
		$this->fields->settings( $forms );
		$provider->enqueue_styles( $forms );
		$provider->enqueue_scripts( $forms );
	}

	/**
	 * Add preview for the current provider.
	 *
	 * @since 2.3.0
	 *
	 * @param array $settings The current provider settings.
	 *
	 * @return array
	 */
	public function add_preview( $settings ) {

		ob_start();
		?>
		<div class="wpforms-container" style="max-width: 400px;">
			<div class="wpforms-form">
				<div class="wpforms-field">
					<input type="text" id="wpforms-autocomplete-preview" data-autocomplete="1" data-display-map="1"
						   placeholder="<?php esc_html_e( 'Enter a location', 'wpforms-geolocation' ); ?>">
					<?php echo wp_kses( $this->map->get_map( 'medium' ), [ 'div' => [ 'class' => [] ] ] ); ?>
				</div>
				<?php // We need to add a hidden button to prevent submission when the user presses the `Enter` key. ?>
				<input type="submit" class="wpforms-submit" style="display: none">
			</div>
		</div>
		<?php

		$settings['preview'] = [
			'id'      => 'preview',
			'content' => ob_get_clean(),
			'type'    => 'content',
			'name'    => esc_html__( 'Preview', 'wpforms-geolocation' ),
		];

		return $settings;
	}
}
