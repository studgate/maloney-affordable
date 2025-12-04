<?php

namespace WPFormsUserJourney;

use WPForms_Updater;

/**
 * WPForms User Journey loader class.
 *
 * @since 1.0.0
 */
final class Loader {

	/**
	 * Database class.
	 *
	 * @since 1.0.0
	 *
	 * @var DB
	 */
	public $db;

	/**
	 * View class.
	 *
	 * @since 1.0.3
	 *
	 * @var View
	 */
	public $view;

	/**
	 * URL to a plugin directory. Used for assets.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $url = '';

	/**
	 * Initiate main plugin instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Loader
	 */
	public static function get_instance() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();

			$instance->init();
		}

		return $instance;
	}

	/**
	 * Init the Loader.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		$this->url = plugin_dir_url( __DIR__ );

		$this->setup();
		$this->hooks();

		return $this;
	}

	/**
	 * Plugin hooks.
	 *
	 * @since 1.0.0
	 */
	private function hooks() {

		add_filter( 'wpforms_helpers_templates_get_theme_template_paths', [ $this, 'register_template_path' ], 100 );
	}

	/**
	 * All the actual plugin loading is done here.
	 *
	 * @since 1.0.0
	 */
	private function setup() {

		$this->load_database();
		$this->load_admin_entries();
		$this->load_admin_export();
		$this->load_admin_form();
		$this->load_frontend();
		$this->load_view();
		$this->load_smart_tags();
		$this->load_processing();
	}

	/**
	 * Add templates path for working of wpforms_render function.
	 *
	 * @since 1.0.0
	 *
	 * @param array $paths List of paths.
	 *
	 * @return array
	 */
	public function register_template_path( $paths ) {

		$paths[] = WPFORMS_USER_JOURNEY_PATH . 'templates';

		return $paths;
	}

	/**
	 * Load database functionality.
	 *
	 * @since 1.0.0
	 */
	private function load_database() {

		$this->db = new DB();
	}

	/**
	 * Load admin entries functionality.
	 *
	 * @since 1.0.0
	 */
	private function load_admin_entries() {

		if ( wpforms_is_admin_page( 'entries' ) ) {
			( new Admin\Entries() )->init();
		}
	}

	/**
	 * Load admin export functionality.
	 *
	 * @since 1.4.0
	 */
	private function load_admin_export() {

		if (
			wpforms_is_admin_page( 'tools', 'export' ) ||
			wpforms_is_ajax( 'wpforms_tools_entries_export_step' )
		) {
			( new Admin\Export() )->init();
		}
	}

	/**
	 * Load admin form functionality.
	 *
	 * @since 1.0.0
	 */
	private function load_admin_form() {

		if ( wpforms_is_admin_page() ) {
			( new Admin\Form() )->init();
		}
	}

	/**
	 * Load frontend functionality.
	 *
	 * @since 1.0.0
	 */
	private function load_frontend() {

		if ( ! is_admin() ) {
			( new Frontend() )->init();
		}
	}

	/**
	 * Load view.
	 *
	 * @since 1.0.3
	 */
	private function load_view() {

		$this->view = new View();
	}

	/**
	 * Load smart tags.
	 *
	 * @since 1.0.3
	 */
	private function load_smart_tags() {

		( new SmartTags() )->init();
	}

	/**
	 * Load form processing.
	 *
	 * @since 1.0.0
	 */
	private function load_processing() {

		if ( ! is_admin() || wpforms_is_frontend_ajax() ) {
			( new Process() )->init();
		}
	}

	/**
	 * Load the plugin updater.
	 *
	 * @since .0.0
	 * @deprecated 1.4.0
	 *
	 * @todo Remove with core 1.9.2
	 *
	 * @param string $key License key.
	 */
	public function updater( $key ) {

		_deprecated_function( __METHOD__, '1.4.0 of the WPForms User Journey plugin' );

		new WPForms_Updater(
			[
				'plugin_name' => 'WPForms User Journey',
				'plugin_slug' => 'wpforms-user-journey',
				'plugin_path' => plugin_basename( WPFORMS_USER_JOURNEY_FILE ),
				'plugin_url'  => trailingslashit( $this->url ),
				'remote_url'  => WPFORMS_UPDATER_API,
				'version'     => WPFORMS_USER_JOURNEY_VERSION,
				'key'         => $key,
			]
		);
	}
}
