<?php
/**
 * Admin_UI class file.
 *
 * @package WPE_Update_Source_Selector
 */

namespace WPE_Update_Source_Selector;

use WP_Screen;

/**
 * Class: Admin_UI
 *
 * Sets up and manages the admin dashboard page.
 */
class Admin_UI {
	/**
	 * Instance of the main class for easy access.
	 *
	 * @var WPE_Update_Source_Selector
	 */
	private $wpe_uss;

	/**
	 * The hook suffix for our admin page.
	 *
	 * @var string
	 */
	private $hook_suffix;

	/**
	 * Admin constructor.
	 *
	 * @param WPE_Update_Source_Selector $wpe_uss Instance of the main class.
	 *
	 * @return void
	 */
	public function __construct( WPE_Update_Source_Selector $wpe_uss ) {
		$this->wpe_uss = $wpe_uss;

		$this->init();
	}

	/**
	 * Initialize the admin UI.
	 *
	 * @return void
	 */
	protected function init() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'admin_notices', array( $this, 'maybe_add_host_override_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'maybe_add_host_override_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'maybe_add_settings_saved_notice' ) );
		add_filter( 'wpe_uss_get_settings_page_url', array( $this, 'get_page_url' ) );
	}

	/**
	 * Add the settings page to the top-level Settings menu item.
	 *
	 * @handles admin_menu
	 * @handles network_admin_menu
	 *
	 * @return void
	 */
	public function admin_menu() {
		/**
		 * Filter whether the admin UI is enabled or not.
		 *
		 * @param bool $enable_admin_ui Whether admin UI may be shown to users with appropriate capabilities, default false.
		 */
		$enable_admin_ui = apply_filters( 'wpe_uss_enable_admin_ui', false );

		if ( empty( $enable_admin_ui ) ) {
			return;
		}

		$hook_suffix = add_submenu_page(
			static::get_parent_slug(),
			static::get_page_title(),
			static::get_menu_title(),
			static::get_capability(),
			$this->wpe_uss->get_plugin_slug(),
			array( $this, 'render_page' )
		);

		// Bail if user has invalid capabilities.
		if ( empty( $hook_suffix ) ) {
			return;
		}

		$this->hook_suffix = $hook_suffix;

		add_action( 'load-' . $this->hook_suffix, array( $this, 'load_page' ) );
	}

	/**
	 * Adds a class to the body HTML tag.
	 *
	 * Filters the body class string for admin pages and adds our own class for easier styling.
	 *
	 * @handles admin_body_class
	 *
	 * @param mixed $classes The body class string.
	 *
	 * @return mixed The modified body class string.
	 */
	public function admin_body_class( $classes ) {
		if ( $this->our_screen() && is_string( $classes ) ) {
			$classes .= ' wpe-update-source-selector';
		}

		return $classes;
	}

	/**
	 * Which parent page name should we use?
	 *
	 * @return string
	 */
	public static function get_parent_slug(): string {
		return is_multisite() ? 'settings.php' : 'options-general.php';
	}

	/**
	 * Get the plugin's base admin URL.
	 *
	 * @return string
	 */
	public static function get_page_url(): string {
		global $wpe_uss;

		return add_query_arg(
			'page',
			$wpe_uss->get_plugin_slug(),
			network_admin_url( static::get_parent_slug() )
		);
	}

	/**
	 * Get the plugin title to be used in page headings.
	 *
	 * @return string
	 */
	public static function get_page_title(): string {
		return apply_filters(
			'wpe_uss_settings_page_title',
			__( 'WordPress Update Source', 'wpe-update-source-selector' )
		);
	}

	/**
	 * Get the plugin title to be used in admin menu.
	 *
	 * @return string
	 */
	public static function get_menu_title(): string {
		return apply_filters(
			'wpe_uss_settings_menu_title',
			__( 'Update Source', 'wpe-update-source-selector' )
		);
	}

	/**
	 * Get the capability needed to access the admin page.
	 *
	 * @return string
	 */
	public static function get_capability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * In our settings screen?
	 *
	 * @param WP_Screen|null $screen Optional screen to check.
	 *
	 * @return bool
	 */
	public function our_screen( ?WP_Screen $screen = null ): bool {
		if ( ! is_admin() || empty( $this->hook_suffix ) ) {
			return false;
		}

		if ( empty( $screen ) ) {
			$screen = get_current_screen();
		}

		if ( empty( $screen ) || false === strpos( $screen->id, $this->hook_suffix ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Display the main settings page for the plugin.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! $this->our_screen() || ! user_can_access_admin_page() ) {
			wp_die();
		}

		// Sanitize inputs.
		$current_tab = ! empty( $_REQUEST['tab'] ) && is_string( $_REQUEST['tab'] ) ? sanitize_key( $_REQUEST['tab'] ) : '';
		$nonce       = ! empty( $_REQUEST['_wpe_uss_nonce'] ) && is_string( $_REQUEST['_wpe_uss_nonce'] ) ? sanitize_key( $_REQUEST['_wpe_uss_nonce'] ) : '';

		// Check nonce.
		if ( ! empty( $nonce ) ) {
			check_admin_referer( 'wpe-update-source-selector-nonce', '_wpe_uss_nonce' );
		}

		$tabs = array(
			/* translators: Tab heading for WP Engine Update Source Selector settings page. */
			'settings' => _x( 'Settings', 'WP Engine Update Source Selector', 'wpe-update-source-selector' ),
			/* translators: Tab heading for WP Engine Update Source Selector settings page. */
			'about'    => _x( 'About', 'WP Engine Update Source Selector', 'wpe-update-source-selector' ),
		);

		$current_tab = $this->update_tabs( $tabs, $current_tab );

		$sources             = $this->wpe_uss->get_sources();
		$current_source      = $this->wpe_uss->get_alt_source();
		$default_source_desc = $this->wpe_uss->get_default_source_desc();
		$site_preference     = $this->wpe_uss->get_site_preference();
		$disabled            = $this->wpe_uss->host_override_set();

		$args = compact(
			'tabs',
			'current_tab',
			'sources',
			'current_source',
			'default_source_desc',
			'site_preference',
			'disabled'
		);

		do_action( 'wpe_uss_pre_render_page', $args );

		$this->render_view( 'admin', $args );

		do_action( 'wpe_uss_post_render_page', $args );
	}

	/**
	 * Handle loading of page.
	 *
	 * @handles load-{$hook_suffix}
	 *
	 * @return void
	 */
	public function load_page() {
		do_action( 'wpe_uss_pre_load_page' );

		// Handle save?
		$this->handle_post_request();

		$this->enqueue_style( 'wpe-update-source-selector-main', 'assets/css/main' );
		$this->enqueue_script( 'wpe-update-source-selector-main', 'assets/js/main', array( 'jquery' ) );

		/**
		 * Enables filtering of the data supplied to the main JS.
		 *
		 * @param array $args Args to be localized for script.
		 */
		$wpe_uss_js_args = apply_filters( 'wpe_uss_localize_script_args', array() );

		if ( empty( $wpe_uss_js_args ) || ! is_array( $wpe_uss_js_args ) ) {
			$wpe_uss_js_args = array();
		}

		wp_localize_script(
			'wpe-update-source-selector-main',
			'wpeUSS',
			$wpe_uss_js_args
		);

		do_action( 'wpe_uss_post_load_page' );
	}

	/**
	 * Render a view template file.
	 *
	 * @param string              $view View filename without the extension.
	 * @param array<string,mixed> $args Arguments to pass to the view.
	 *
	 * @return void
	 */
	private function render_view( string $view, array $args = array() ) {
		$file_to_include = $this->wpe_uss->get_base_path() . 'views/' . $view . '.php';

		if ( ! file_exists( $file_to_include ) ) {
			wp_die( esc_html( __METHOD__ . ': View "' . $view . '" not found.' ) );
		}

		if ( ! empty( $args ) ) {
			extract( $args, EXTR_PREFIX_ALL, 'wpe_uss' );
		}

		require $file_to_include;
	}

	/**
	 * Enqueue script.
	 *
	 * @param string        $handle Unique name for the script.
	 * @param string        $path   Relative path to the script.
	 * @param array<string> $deps   Optional, array of scripts that the script depends on.
	 * @param bool          $footer Optional, should the script be place before body close, default true.
	 *
	 * @return void
	 */
	protected function enqueue_script( string $handle, string $path, array $deps = array(), bool $footer = true ) {
		$version = $this->get_asset_version();
		$suffix  = $this->get_asset_suffix();

		// We use the plugin's readme as the relative base as it is the only
		// file that must be in the root of the plugin's directory.
		$src = plugins_url( $path . $suffix . '.js', $this->wpe_uss->get_readme_path() );
		wp_enqueue_script( $handle, $src, $deps, $version, $footer );
	}

	/**
	 * Enqueue style.
	 *
	 * @param string        $handle Unique name for the style.
	 * @param string        $path   Relative path to the style.
	 * @param array<string> $deps   Optional, array of styles that the style depends on.
	 *
	 * @return void
	 */
	protected function enqueue_style( string $handle, string $path, array $deps = array() ) {
		$version = $this->get_asset_version();
		$suffix  = $this->get_asset_suffix();

		// We use the plugin's readme as the relative base as it is the only
		// file that must be in the root of the plugin's directory.
		$src = plugins_url( $path . $suffix . '.css', $this->wpe_uss->get_readme_path() );
		wp_enqueue_style( $handle, $src, $deps, $version );
	}

	/**
	 * Get the version used for script enqueuing.
	 *
	 * @return string
	 */
	protected function get_asset_version(): string {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? gmdate( 'YmdHis' ) : $this->wpe_uss->get_plugin_info( 'Version' );
	}

	/**
	 * Get the filename suffix used for script enqueuing.
	 *
	 * @return string
	 */
	protected function get_asset_suffix(): string {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	/**
	 * Checks and updates the array of tabs.
	 *
	 * The current tab is returned, which may have changed.
	 *
	 * @param array<string,string> $tabs        Tabs to be displayed, passed by reference.
	 * @param string               $current_tab The currently active tab's slug.
	 *
	 * @return string
	 */
	private function update_tabs( array &$tabs, string $current_tab ): string {
		/**
		 * Filters the extra tabs for the navigation bar.
		 *
		 * Add a custom page to the screen, based on a tab slug and label.
		 *
		 * @param array<string,string> $tabs An associative array of tab titles, keyed by their slug.
		 */
		$tabs = apply_filters( 'wpe_uss_navigation_tabs', $tabs );

		foreach ( $tabs as $slug => $tab ) {
			if ( ! file_exists( $this->wpe_uss->get_base_path() . 'views/tabs/' . $slug . '.php' ) ) {
				unset( $tabs[ $slug ] );
			}
		}

		if ( empty( $tabs ) ) {
			wp_die( esc_html__( 'No tabs available to display.', 'wpe-update-source-selector' ) );
		}

		if ( empty( $current_tab ) || ! array_key_exists( $current_tab, $tabs ) ) {
			$current_tab = array_key_first( $tabs );
		}

		return $current_tab;
	}

	/**
	 * Handle form save.
	 *
	 * @return void
	 */
	protected function handle_post_request() {
		// Form posted?
		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! $this->our_screen() || ! user_can_access_admin_page() ) {
			wp_die();
		}

		// Must validate nonce.
		check_admin_referer( 'wpe-update-source-selector-nonce', '_wpe_uss_nonce' );

		// Sanitize inputs.
		$action    = ! empty( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? trim( sanitize_key( $_REQUEST['action'] ) ) : '';
		$select    = ! empty( $_REQUEST['select-source'] ) && is_string( $_REQUEST['select-source'] ) ? trim( sanitize_key( $_REQUEST['select-source'] ) ) : '';
		$preferred = ! empty( $_REQUEST['preferred-source'] ) && is_string( $_REQUEST['preferred-source'] ) ? trim( sanitize_key( $_REQUEST['preferred-source'] ) ) : '';

		if ( 'save' !== $action || empty( $select ) ) {
			return;
		}

		// If selecting a source, validate preference.
		if ( 'yes' === $select && ( empty( $preferred ) || ! $this->wpe_uss->valid_source( $preferred ) ) ) {
			return;
		}

		if ( 'yes' !== $select ) {
			$this->wpe_uss->delete_site_preference();
		} else {
			$this->wpe_uss->set_site_preference( $preferred );
		}

		// If we get this far, save should have happened, and display will verify.
		$args = array(
			'updated' => 1,
		);

		$url = static::get_page_url();
		$url = add_query_arg( $args, $url );

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Maybe display a notice regarding the host override.
	 *
	 * @handles admin_notices
	 *
	 * @return void
	 */
	public function maybe_add_host_override_notice(): void {
		if ( ! $this->our_screen() || ! $this->wpe_uss->host_override_set() ) {
			return;
		}

		$title = __(
			'Your host is temporarily managing your source',
			'wpe-update-source-selector'
		);

		$core_source_name = $this->wpe_uss->get_core_source()::get_name();
		$alt_source_name  = $this->wpe_uss->get_alt_source()::get_name();

		$msg = sprintf(
		/* translators: Param is current source name. */
			__(
				'To ensure continued availability of WordPress core, theme, and plugin updates, %s has been set as your active source. Settings on this page cannot be changed at this time.',
				'wpe-update-source-selector'
			),
			$alt_source_name
		);

		$args = $this->get_override_notice_args( $title, $msg, $core_source_name, $alt_source_name );

		$this->render_view( 'override-notice', $args );
	}

	/**
	 * Creates any array of args suitable for passing to the override notice renderer.
	 *
	 * @param string $title            Override notice's title.
	 * @param string $msg              Override notice's body text.
	 * @param string $core_source_name Name for Core update source.
	 * @param string $alt_source_name  Name for alternative update source.
	 *
	 * @return array<string,string>
	 */
	private function get_override_notice_args(
		string $title,
		string $msg,
		string $core_source_name,
		string $alt_source_name
	): array {
		$default_args = array(
			'dashicon' => 'dashicons-warning',
			'imgsrc'   => '',
			'title'    => $title,
			'msg'      => $msg,
		);

		/**
		 * Allows filtering of the data used to create the host override notice.
		 *
		 * @param array<string,string> $args               An associative array with keys dashicon, imgsrc, title and msg.
		 *                                                 `dashicon` is an optional string for dashicon to show before the title, e.g. "dashicons-warning".
		 *                                                 `imgsrc` is an optional URL to be used as the src for an img tag, can be a data URL. Takes priority over the dashicon.
		 *                                                 `title` is an optional string to set as the warning notice's title. If not supplied, icon will not be shown either.
		 *                                                 `msg` is a required string used as the warning notice's main text. If not supplied, a default is used.
		 * @param string               $core_source_name   The name of the core source used in the message.
		 * @param string               $alt_source_name    The name of the alternative source used in the message.
		 */
		$args = apply_filters(
			'wpe_uss_host_override_notice',
			$default_args,
			$core_source_name,
			$alt_source_name
		);

		// Sanitize the args before render.
		if ( ! is_array( $args ) || empty( $args ) ) {
			$args = $default_args;
		} else {
			$args = array_intersect_key( $args, $default_args );

			// Dash icon is optional.
			if ( empty( $args['dashicon'] ) || ! is_string( $args['dashicon'] ) ) {
				$args['dashicon'] = '';
			}

			// Image src is optional.
			if ( empty( $args['imgsrc'] ) || ! is_string( $args['imgsrc'] ) ) {
				$args['imgsrc'] = '';
			}

			// Title is optional.
			if ( empty( $args['title'] ) || ! is_string( $args['title'] ) ) {
				$args['title'] = '';
			}

			// Message required.
			if ( empty( $args['msg'] ) || ! is_string( $args['msg'] ) ) {
				$args['msg'] = $default_args['msg'];
			}
		}

		return $args;
	}

	/**
	 * Polyfill for displaying "Settings saved." consistently between single-site and multisite environments.
	 *
	 * TL;DR: options-head.php is loaded for options-general.php (single sites only) which does this, but not on multisite.
	 *
	 * @see     https://github.com/WordPress/WordPress/blob/c2d709e9d6cbe7f9b3c37da0a7c9aae788158124/wp-admin/admin-header.php#L265-L266
	 * @see     https://github.com/WordPress/WordPress/blob/9b68e5953406024c75b92f7ebe2aef0385c8956e/wp-admin/options-head.php#L13-L16
	 *
	 * @handles network_admin_notices
	 *
	 * @return void
	 */
	public function maybe_add_settings_saved_notice() {
		$nonce = ! empty( $_REQUEST['_wpe_uss_nonce'] ) && is_string( $_REQUEST['_wpe_uss_nonce'] ) ? sanitize_key( $_REQUEST['_wpe_uss_nonce'] ) : '';

		// Check nonce.
		if ( ! empty( $nonce ) ) {
			check_admin_referer( 'wpe-update-source-selector-nonce', '_wpe_uss_nonce' );
		}

		$updated = ! empty( $_REQUEST['updated'] ) && is_string( $_REQUEST['updated'] ) ? trim( sanitize_key( $_REQUEST['updated'] ) ) : '';
		$page    = ! empty( $_REQUEST['page'] ) && is_string( $_REQUEST['page'] ) ? trim( sanitize_key( $_REQUEST['page'] ) ) : '';

		if ( ! empty( $updated ) && $this->wpe_uss->get_plugin_slug() === $page && $this->our_screen() ) {
			// For back-compat with plugins that don't use the Settings API and just set updated=1 in the redirect.
			add_settings_error(
				'general',
				'settings_updated',
				__( 'Settings saved.', 'wpe-update-source-selector' ),
				'updated'
			);
		}
	}
}
