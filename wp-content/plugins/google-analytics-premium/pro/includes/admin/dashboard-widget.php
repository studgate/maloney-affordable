<?php
/**
 * The PRO instance of the Dashboard widget.
 *
 * @since 7.1
 *
 * @package MonsterInsights
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MonsterInsights_Dashboard_Widget
 */
class MonsterInsights_Dashboard_Widget_Pro {

	const WIDGET_KEY = 'monsterinsights_reports_widget';

	/**
	 * The options variable for the widget.
	 *
	 * @var array $options
	 */
	private $options = [];

	/**
	 * The default options for the widget.
	 *
	 * @var array $default_options
	 */
	public static $default_options = array(
		'width'       => 'regular',
		'interval'    => '30',
		'compact'     => 'false',
		'reports'     => array(
			'overview'  => array(
				'toppages'    => true,
				'newvsreturn' => true,
				'devices'     => true,
			),
			'publisher' => array(
				'landingpages'   => false,
				'exitpages'      => false,
				'outboundlinks'  => false,
				'affiliatelinks' => false,
				'downloadlinks'  => false,
			),
			'ecommerce' => array(
				'infobox'            => false, // E-commerce Overview.
				'products'           => false, // Top Products.
				'conversions'        => false, // Top Products.
				'addremove'          => false, // Total Add/Remove.
				'days'               => false, // Time to purchase.
				'sessions'           => false, // Sessions to purchase.
				'newcustomers'       => false,
				'abandonedcheckouts' => false,
			),
		),
		'notice30day' => false,
	);

	private static $version_specific_reports = array(
		'v4' => array(
			'ecommerce' => array(
				'newcustomers',
				'abandonedcheckouts',
			),
		),
		'ua' => array(
			'publisher' => array(
				'exitpages',
			),
			'ecommerce' => array(
				'days',
				'sessions',
			),
		),
	);

	/**
	 * MonsterInsights_Dashboard_Widget_Pro constructor.
	 */
	public function __construct() {

		// Allow dashboard widget to be hidden on multisite installs.
		$show_widget = is_multisite() ? apply_filters( 'monsterinsights_show_dashboard_widget', true ) : true;
		if ( ! $show_widget ) {
			return false;
		}

		// Check if reports should be visible.
		$dashboards_disabled = monsterinsights_get_option( 'dashboards_disabled', false );
		if ( ! current_user_can( 'monsterinsights_view_dashboard' ) || 'disabled' === $dashboards_disabled ) {
			return false;
		}

		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'widget_scripts' ), 1000 );

		add_action( 'wp_ajax_monsterinsights_save_widget_state', array( $this, 'save_widget_state' ) );

		// Reminder notice.
//		add_action( 'admin_footer', array( $this, 'load_notice' ) );

		add_action( 'wp_ajax_monsterinsights_mark_notice_closed', array( $this, 'mark_notice_closed' ) );
	}

	/**
	 * Register the dashboard widget.
	 */
	public function register_dashboard_widget() {
		global $wp_meta_boxes;

		wp_add_dashboard_widget(
			self::WIDGET_KEY,
			esc_html__( 'MonsterInsights', 'google-analytics-premium' ),
			array( $this, 'dashboard_widget_content' )
		);

		// Attempt to place the widget at the top.
		$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
		$widget_instance  = array( self::WIDGET_KEY => $normal_dashboard[ self::WIDGET_KEY ] );
		unset( $normal_dashboard[ self::WIDGET_KEY ] );
		$sorted_dashboard                             = array_merge( $widget_instance, $normal_dashboard );
		$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard; // phpcs:ignore
	}

	/**
	 * Load the widget content.
	 */
	public function dashboard_widget_content() {
		$is_authed    = ( MonsterInsights()->auth->is_authed() || MonsterInsights()->auth->is_network_authed() );
		$not_licensed = ( ! MonsterInsights()->license->is_site_licensed() && ! MonsterInsights()->license->is_network_licensed() );

		if ( ! $is_authed || $not_licensed ) {
			$this->widget_content_no_auth();
		} else {
			monsterinsights_settings_error_page( 'monsterinsights-dashboard-widget', '', '0' );
			monsterinsights_settings_inline_js();
		}

	}

	/**
	 * Load widget-specific scripts.
	 */
	public function widget_scripts() {
		$version_path = 'pro';
		$screen       = get_current_screen();

		if ( isset( $screen->id ) && 'dashboard' === $screen->id ) {
			if ( ! defined( 'MONSTERINSIGHTS_LOCAL_JS_URL' ) ) {
				MonsterInsights_Admin_Assets::enqueue_script_specific_css( 'src/modules/widget/widget.js' );
			}
			$widget_js_url = MonsterInsights_Admin_Assets::get_js_url( 'src/modules/widget/widget.js' );
			wp_register_script( 'monsterinsights-vue-widget', $widget_js_url, array( 'wp-i18n' ), monsterinsights_get_asset_version(), true );
			wp_enqueue_script( 'monsterinsights-vue-widget' );

			$plugins                = get_plugins();
			$wp_forms_url           = false;
			$wpforms_installed      = false;
			$userfeedback_url       = false;
			$userfeedback_installed = false;
			if ( monsterinsights_can_install_plugins() ) {
				$wpforms_key = 'wpforms-lite/wpforms.php';
				if ( array_key_exists( $wpforms_key, $plugins ) ) {
					$wp_forms_url      = wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . $wpforms_key ), 'activate-plugin_' . $wpforms_key );
					$wpforms_installed = true;
				} else {
					$wp_forms_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=wpforms-lite' ), 'install-plugin_wpforms-lite' );
				}

				$userfeedback_keys     = array ( 'userfeedback-lite/userfeedback.php' => 1, 'userfeedback/userfeedback.php' => 2 );
				$userfeedback_versions = array_intersect_key($userfeedback_keys, $plugins);

				if ( ! empty( $userfeedback_versions ) ) {
					$uf_plugin_key          = array_keys( $userfeedback_versions )[0];
					$userfeedback_url       = wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . $uf_plugin_key ), 'activate-plugin_' . $uf_plugin_key );
					$userfeedback_installed = true;
				} else {
					$userfeedback_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=userfeedback-lite' ), 'install-plugin_userfeedback-lite' );
				}
			}

			$auth      = MonsterInsights()->auth;
			$is_authed = ( $auth->is_authed() || $auth->is_network_authed() );
			wp_localize_script(
				'monsterinsights-vue-widget',
				'monsterinsights',
				array(
					'ajax'                   => admin_url( 'admin-ajax.php' ),
					'nonce'                  => wp_create_nonce( 'mi-admin-nonce' ),
					'network'                => is_network_admin(),
					'assets'                 => plugins_url( $version_path . '/assets/vue', MONSTERINSIGHTS_PLUGIN_FILE ),
					'shareasale_id'          => monsterinsights_get_shareasale_id(),
					'shareasale_url'         => monsterinsights_get_shareasale_url( monsterinsights_get_shareasale_id(), '' ),
					'addons_url'             => is_multisite() ? network_admin_url( 'admin.php?page=monsterinsights_network#/addons' ) : admin_url( 'admin.php?page=monsterinsights_settings#/addons' ),
					'widget_state'           => $this->get_options(),
					'wpforms_enabled'        => function_exists( 'wpforms' ),
					'wpforms_installed'      => $wpforms_installed,
					'wpforms_url'            => $wp_forms_url,
					'userfeedback_enabled'   => class_exists( 'UserFeedback_Base' ),
					'userfeedback_installed' => $userfeedback_installed,
					'userfeedback_url'       => $userfeedback_url,
					'authed'                 => $is_authed,
					// They wouldn't see this either way if not authed. This is used in Reports.
					// Used to add notices for future deprecations.
					'versions'               => monsterinsights_get_php_wp_version_warning_data(),
					'plugin_version'         => MONSTERINSIGHTS_VERSION,
					'is_admin'               => true,
					'reports_url'            => add_query_arg( 'page', 'monsterinsights_reports', admin_url( 'admin.php' ) ),
					'install_nonce'          => wp_create_nonce( 'monsterinsights-install' ),
					'activate_nonce'         => wp_create_nonce( 'monsterinsights-activate' ),
					'getting_started_url'    => is_multisite() ? network_admin_url( 'admin.php?page=monsterinsights_network#/about/getting-started' ) : admin_url( 'admin.php?page=monsterinsights_settings#/about/getting-started' ),
					'wizard_url'             => admin_url( 'index.php?page=monsterinsights-onboarding' ),
					'timezone'               => date( 'e' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We want this to depend on the runtime timezone.
					'roles_manage_options'   => monsterinsights_get_manage_options_roles(),
				)
			);

			$this->remove_conflicting_asset_files();

			$text_domain = monsterinsights_is_pro_version() ? 'google-analytics-premium' : 'google-analytics-premium';

			wp_scripts()->add_inline_script(
				'monsterinsights-vue-widget',
				monsterinsights_get_printable_translations( $text_domain ),
				'translation'
			);
		}
	}

	/**
	 * Remove assets added by other plugins which conflict.
	 */
	public function remove_conflicting_asset_files() {
		$scripts = array(
			'jetpack-onboarding-vendor', // Jetpack Onboarding Bluehost.
		);

		if ( ! empty( $scripts ) ) {
			foreach ( $scripts as $script ) {
				wp_dequeue_script( $script ); // Remove JS file.
				wp_deregister_script( $script );
			}
		}
	}

	/**
	 * Store the widget state in the db using an Ajax call.
	 */
	public function save_widget_state() {

		check_ajax_referer( 'mi-admin-nonce', 'nonce' );

		$default         = self::$default_options;
		$current_options = $this->get_options();

		$reports = $default['reports'];
		if ( isset( $_POST['reports'] ) ) {
			$reports = json_decode( sanitize_text_field( wp_unslash( $_POST['reports'] ) ), true );
		}

		$options = array(
			'width'       => ! empty( $_POST['width'] ) ? sanitize_text_field( wp_unslash( $_POST['width'] ) ) : $default['width'],
			'interval'    => ! empty( $_POST['interval'] ) ? sanitize_text_field( wp_unslash( $_POST['interval'] ) ) : $default['interval'],
			'compact'     => ! empty( $_POST['compact'] ) ? 'true' === sanitize_text_field( wp_unslash( $_POST['compact'] ) ) : $default['compact'],
			'reports'     => $reports,
			'notice30day' => $current_options['notice30day'],
		);

		array_walk( $options, 'sanitize_text_field' );
		update_user_meta( get_current_user_id(), 'monsterinsights_user_preferences', $options );

		wp_send_json_success();

	}

	/**
	 * Load & store the dashboard widget settings.
	 *
	 * @return array
	 */
	public function get_options() {
		if ( empty( $this->options ) ) {
			$this->options = self::wp_parse_args_recursive( get_user_meta( get_current_user_id(), 'monsterinsights_user_preferences', true ), self::$default_options );
		}

		return apply_filters( 'monsterinsights_dashboard_widget_options', $this->options );

	}

	/**
	 * Recursive wp_parse_args.
	 *
	 * @param string|array|object $a Value to merge with $b.
	 * @param array $b The array with the default values.
	 *
	 * @return array
	 */
	public static function wp_parse_args_recursive( $a, $b ) {
		$a      = (array) $a;
		$b      = (array) $b;
		$result = $b;
		foreach ( $a as $k => &$v ) {
			if ( is_array( $v ) && isset( $result[ $k ] ) ) {
				$result[ $k ] = self::wp_parse_args_recursive( $v, $result[ $k ] );
			} else {
				$result[ $k ] = $v;
			}
		}

		return $result;
	}


	/**
	 * Message to display when the plugin is not authenticated.
	 */
	public function widget_content_no_auth() {

		$url = is_network_admin() ? network_admin_url( 'admin.php?page=monsterinsights-onboarding' ) : admin_url( 'admin.php?page=monsterinsights-onboarding' );
		if ( ! MonsterInsights()->license->site_license_expired() && ! MonsterInsights()->license->network_license_expired() ) {
			?>
			<div class="mi-dw-not-authed">
				<h2><?php esc_html_e( 'Website Analytics is not Setup', 'google-analytics-premium' ); ?></h2>
				<?php if ( current_user_can( 'monsterinsights_save_settings' ) ) { ?>
					<p><?php esc_html_e( 'To see your website stats, please connect MonsterInsights to Google Analytics.', 'google-analytics-premium' ); ?></p>
					<a class="mi-dw-btn-large monsterinsights-setup-wizard-link"><?php esc_html_e( 'Setup Website Analytics', 'google-analytics-premium' ); ?></a>
					   <p><?php esc_html_e( 'Note: You will be transfered to MonsterInsights.com to complete the setup wizard.', 'google-analytics-premium' ); ?></p>
				<?php } else { ?>
					<p><?php esc_html_e( 'To see your website stats, please ask your webmaster to connect MonsterInsights to Google Analytics.', 'google-analytics-premium' ); ?></p>
				<?php } ?>
			</div>
			<?php
		} else {
			$type             = MonsterInsights()->license->get_license_type();
			$reactivation_url = monsterinsights_get_url( 'admin-notices', 'expired-license', "https://www.monsterinsights.com/my-account/" );
			?>
			<div class="mi-dw-license-expired">
				<div class="monsterinsights-expired-license-icon">
					<svg width="36" height="31" viewBox="0 0 36 31" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path
							d="M34.2678 26.5948L20.3736 2.45353C19.3315 0.658854 16.6106 0.600962 15.5685 2.45353L1.67428 26.5948C0.632212 28.3894 1.96374 30.7051 4.10577 30.7051H31.8363C33.9784 30.7051 35.3099 28.4473 34.2678 26.5948ZM18 21.5581C19.4473 21.5581 20.6631 22.7738 20.6631 24.2212C20.6631 25.7264 19.4473 26.8842 18 26.8842C16.4948 26.8842 15.3369 25.7264 15.3369 24.2212C15.3369 22.7738 16.4948 21.5581 18 21.5581ZM15.4527 12.0058C15.3948 11.6006 15.7422 11.2532 16.1474 11.2532H19.7947C20.1999 11.2532 20.5473 11.6006 20.4894 12.0058L20.0841 19.8792C20.0262 20.2845 19.7368 20.516 19.3894 20.516H16.5527C16.2053 20.516 15.9159 20.2845 15.858 19.8792L15.4527 12.0058Z"
							fill="#E64949"/>
					</svg>
				</div>
				<h2><?php
				/* translators: Placeholder adds the type of license. */
				echo sprintf( esc_html__( 'Your MonsterInsights %1$s License has expired.', 'google-analytics-premium' ), ucfirst( $type ) ); // phpcs:ignore
				?></h2>
				<?php if ( current_user_can( 'monsterinsights_save_settings' ) ) { ?>
					<p><?php esc_html_e( 'To ensure your site continues to track, view reports, and receive new updates, you must reactivate your license.', 'google-analytics-premium' ); ?></p>
					<a target="_blank" href="<?php echo esc_url( $reactivation_url ); ?>"
					   class="mi-dw-btn-large"><?php esc_html_e( 'Reactivate License', 'google-analytics-premium' ); ?></a>
				<?php } else { ?>
					<p><?php esc_html_e( 'To ensure your site continues to track, view reports, and receive new updates, please ask your webmaster to reactivate the license.', 'google-analytics-premium' ); ?></p>
				<?php } ?>
			</div>
			<?php
		}
	}

	/**
	 * Reminder notice markup.
	 */
	public function load_notice() {

		$screen = get_current_screen();
		$v4_id  = monsterinsights_get_v4_id();
		if ( isset( $screen->id ) && 'dashboard' === $screen->id && ! empty( $v4_id ) ) {
			?>
			<div id="monsterinsights-reminder-notice"></div>
			<?php
		}

	}

	/**
	 * Mark notice as dismissed.
	 */
	public function mark_notice_closed() {

		check_ajax_referer( 'mi-admin-nonce', 'nonce' );
		$options                = $this->get_options();
		$options['notice30day'] = time();
		update_user_meta( get_current_user_id(), 'monsterinsights_user_preferences', $options );

		wp_send_json_success();
	}
}
