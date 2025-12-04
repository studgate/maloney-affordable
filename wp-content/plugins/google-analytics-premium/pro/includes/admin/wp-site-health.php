<?php
/**
 * Add MonsterInsights tests to the WP Site Health area.
 */

/**
 * Class MonsterInsights_WP_Site_Health
 */
class MonsterInsights_WP_Site_Health_Pro {

	/**
	 * Is the site licensed?
	 *
	 * @var bool
	 */
	private $is_licensed;
	/**
	 * Is the site autherd?
	 *
	 * @var bool
	 */
	private $is_authed;
	/**
	 * Which eCommerce type, if any.
	 *
	 * @var bool|string
	 */
	private $ecommerce;
	/**
	 * Is the website being tracked?
	 *
	 * @var bool|string
	 */
	private $is_tracking;

	/**
	 * MonsterInsights_WP_Site_Health constructor.
	 */
	public function __construct() {

		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );

		add_action( 'wp_ajax_health-check-monsterinsights-test_connection', array( $this, 'test_check_connection' ) );

		add_action( 'wp_ajax_health-check-monsterinsights-test_tracking_code', array(
			$this,
			'test_check_tracking_code'
		) );

	}

	/**
	 * Add MonsterInsights WP Site Health tests.
	 *
	 * @param array $tests The current filters array.
	 *
	 * @return array
	 */
	public function add_tests( $tests ) {

		$tests['direct']['monsterinsights_license'] = array(
			'label' => __( 'MonsterInsights License', 'google-analytics-premium' ),
			'test'  => array( $this, 'test_check_license' ),
		);

		$tests['direct']['monsterinsights_auth'] = array(
			'label' => __( 'MonsterInsights Authentication', 'google-analytics-premium' ),
			'test'  => array( $this, 'test_check_authentication' ),
		);

		if ( $this->is_licensed() ) {
			$tests['direct']['monsterinsights_automatic_updates'] = array(
				'label' => __( 'MonsterInsights Automatic Updates', 'google-analytics-premium' ),
				'test'  => array( $this, 'test_check_autoupdates' ),
			);
		}

		if ( $this->is_ecommerce() ) {
			$tests['direct']['monsterinsights_ecommerce'] = array(
				'label' => __( 'MonsterInsights eCommerce', 'google-analytics-premium' ),
				'test'  => array( $this, 'test_check_ecommerce' ),
			);

			$tests['direct']['monsterinsights_excluded_roles'] = array(
				'label' => __( 'MonsterInsights Tracking', 'google-analytics-premium' ),
				'test'  => array( $this, 'test_check_roles_excluded' ),
			);
		}

		if ( class_exists( 'MonsterInsights_Dimensions' ) ) {
			$tests['direct']['monsterinsights_dimensions'] = array(
				'label' => __( 'MonsterInsights Custom Dimensions', 'google-analytics-premium' ),
				'test'  => array( $this, 'test_check_dimensions' ),
			);
		}

		if ( $this->uses_amp() ) {
			$tests['direct']['monsterinsights_amp'] = array(
				'label' => __( 'MonsterInsights AMP', 'google-analytics-premium' ),
				'test'  => array( $this, 'test_check_amp' ),
			);
		}

		$tests['async']['monsterinsights_connection'] = array(
			'label' => __( 'MonsterInsights Connection', 'google-analytics-premium' ),
			'test'  => 'monsterinsights_test_connection',
		);

		if ( $this->is_tracking() ) {
			$tests['async']['monsterinsights_tracking_code'] = array(
				'label' => __( 'MonsterInsights Tracking Code', 'google-analytics-premium' ),
				'test'  => 'monsterinsights_test_tracking_code',
			);
		}

		return $tests;
	}

	/**
	 * Checks if the website is licensed.
	 *
	 * @return bool
	 */
	public function is_licensed() {

		if ( ! isset( $this->is_licensed ) ) {
			$this->is_licensed = is_network_admin() ? MonsterInsights()->license->is_network_licensed() : MonsterInsights()->license->is_site_licensed();
		}

		return $this->is_licensed;

	}

	/**
	 * Checks if the website is being tracked.
	 *
	 * @return bool
	 */
	public function is_tracking() {

		if ( ! isset( $this->is_tracking ) ) {
			$tracking_tag                = monsterinsights_get_v4_id();
			$this->is_tracking = ! empty( $tracking_tag );
		}

		return $this->is_tracking;

	}

	/**
	 * Check if any of the supported eCommerce integrations are available.
	 *
	 * @return bool
	 */
	public function is_ecommerce() {

		if ( isset( $this->ecommerce ) ) {
			return $this->ecommerce;
		}

		$this->ecommerce = false;

		if ( class_exists( 'WooCommerce' ) ) {
			$this->ecommerce = 'WooCommerce';
		} else if ( class_exists( 'Easy_Digital_Downloads' ) ) {
			$this->ecommerce = 'Easy Digital Downloads';
		} else if ( defined( 'MEPR_VERSION' ) && version_compare( MEPR_VERSION, '1.3.43', '>' ) ) {
			$this->ecommerce = 'MemberPress';
		} else if ( function_exists( 'LLMS' ) && version_compare( LLMS()->version, '3.32.0', '>=' ) ) {
			$this->ecommerce = 'LifterLMS';
		}

		return $this->ecommerce;
	}

	/**
	 * Is the site using AMP or has the AMP addon installed?
	 *
	 * @return bool
	 */
	public function uses_amp() {

		return class_exists( 'MonsterInsights_AMP' ) || defined( 'AMP__FILE__' );

	}

	/**
	 * Is Coming Soon / Maintenance / Under Construction mode being activated by another plugin?
	 *
	 * @return bool
	 */
	private function is_coming_soon_active() {
		if ( defined( 'SEED_CSP4_SHORTNAME' ) ) {
			// SeedProd
			// http://www.seedprod.com

			$settings = get_option( 'seed_csp4_settings_content' );

			// 0: Disabled
			// 1: Coming soon mode
			// 2: Maintenance mode
			return ! empty( $settings['status'] );
		} elseif ( defined( 'WPMM_PATH' ) ) {
			// WP Maintenance Mode
			// https://designmodo.com/

			$settings = get_option( 'wpmm_settings', array() );

			return isset( $settings['general']['status'] ) && 1 === $settings['general']['status'];
		} elseif ( function_exists( 'csmm_get_options' ) ) {
			// Minimal Coming Soon & Maintenance Mode
			// https://comingsoonwp.com/

			$settings = csmm_get_options();

			return isset( $settings['status'] ) && 1 === $settings['status'];
		} elseif ( defined( 'WPM_DIR' ) ) {
			// WP Maintenance
			// https://fr.wordpress.org/plugins/wp-maintenance/

			return '1' === get_option( 'wp_maintenance_active' );
		} elseif ( defined( 'ACX_CSMA_CURRENT_VERSION' ) ) {
			// Under Construction / Maintenance Mode From Acurax
			// http://www.acurax.com/products/under-construction-maintenance-mode-wordpress-plugin

			return '1' === get_option( 'acx_csma_activation_status' );
		} elseif ( defined( 'SAHU_SO_PLUGIN_URL' ) ) {
			// Site Offline
			// http://www.freehtmldesigns.com

			$settings = maybe_unserialize( get_option( 'sahu_so_dashboard' ) );

			return isset( $settings['sahu_so_status'] ) && '1' === $settings['sahu_so_status'];
		} elseif ( defined( 'CSCS_GENEROPTION_PREFIX' ) ) {
			// IgniteUp
			// http://getigniteup.com

			return '1' === get_option( CSCS_GENEROPTION_PREFIX . 'enable', '' );
		} elseif ( method_exists( 'UCP', 'is_construction_mode_enabled' ) ) {
			// Under Construction by WebFactory Ltd
			// https://underconstructionpage.com/

			return UCP::is_construction_mode_enabled( true );
		} elseif ( function_exists( 'mtnc_get_plugin_options' ) ) {
			// Maintenance by WP Maintenance
			// http://wordpress.org/plugins/maintenance/

			$settings = mtnc_get_plugin_options( true );

			return 1 === $settings['state'];
		} elseif ( class_exists( 'CMP_Coming_Soon_and_Maintenance' ) ) {
			// CMP Coming Soon & Maintenance
			// https://wordpress.org/plugins/cmp-coming-soon-maintenance/

			return get_option( 'niteoCS_status' );
		}

		return false;
	}

	/**
	 * Check if MonsterInsights is authenticated and display a specific message.
	 *
	 * @return array
	 */
	public function test_check_authentication() {
		$result = array(
			'label'       => __( 'Your website is authenticated with MonsterInsights', 'google-analytics-premium' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MonsterInsights', 'google-analytics-premium' ),
				'color' => 'blue',
			),
			'description' => __( 'MonsterInsights integrates your WordPress website with Google Analytics.', 'google-analytics-premium' ),
			'actions'     => sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				add_query_arg( 'page', 'monsterinsights_reports', admin_url( 'admin.php' ) ),
				__( 'View Reports', 'google-analytics-premium' )
			),
			'test'        => 'monsterinsights_auth',
		);

		$this->is_authed = MonsterInsights()->auth->is_authed() || MonsterInsights()->auth->is_network_authed();

		if ( ! $this->is_authed ) {
			if ( '' !== monsterinsights_get_v4_id() ) {
				// Using Manual V4.
				$result['status']      = 'recommended';
				$result['label']       = __( 'You are using Manual GA4 Measurement ID output', 'google-analytics-premium' );
				$result['description'] = __( 'We highly recommend authenticating with MonsterInsights so that you can access our new reporting area and take advantage of new MonsterInsights features.', 'google-analytics-premium' );
				$result['actions']     = sprintf(
					'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
					add_query_arg( 'page', 'monsterinsights_settings', admin_url( 'admin.php' ) ),
					__( 'Authenticate now', 'google-analytics-premium' )
				);

			} else {
				// Not authed at all.
				$result['status']      = 'critical';
				$result['label']       = __( 'Please configure your Google Analytics settings', 'google-analytics-premium' );
				$result['description'] = __( 'Your traffic is not being tracked by MonsterInsights at the moment and you are losing data. Authenticate and get access to the reporting area and advanced tracking features.', 'google-analytics-premium' );
				$result['actions']     = sprintf(
					'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
					add_query_arg( 'page', 'monsterinsights_settings', admin_url( 'admin.php' ) ),
					__( 'Authenticate now', 'google-analytics-premium' )
				);
			}
		}

		return $result;
	}

	/**
	 * Check if the license is properly set up.
	 *
	 * @return array
	 */
	public function test_check_license() {

		$result = array(
			'status' => 'good',
			'badge'  => array(
				'label' => __( 'MonsterInsights', 'google-analytics-premium' ),
				'color' => 'blue',
			),
			'test'   => 'monsterinsights_license',
		);

		if ( $this->is_licensed() ) {
			$license_type    = is_network_admin() ? MonsterInsights()->license->get_network_license_type() : MonsterInsights()->license->get_license_type();
			$result['label'] = __( 'Your MonsterInsights license is valid', 'google-analytics-premium' );
			/* Translators: The license type. */
			$result['description'] = sprintf( __( 'Your license key type for this site is %s.', 'google-analytics-premium' ), '<strong>' . ucfirst( $license_type ) . '</strong>' );
		} else {
			$result['label']  = __( 'MonsterInsights is not licensed', 'google-analytics-premium' );
			$result['status'] = 'critical';
			/* Translators: The license type. */
			$result['description'] = __( 'MonsterInsights is not licensed which means you can\'t access reports, automatic updates, and other advanced features', 'google-analytics-premium' );
			$result['actions']     = sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				add_query_arg( 'page', 'monsterinsights_settings', admin_url( 'admin.php' ) ),
				__( 'Add License now', 'google-analytics-premium' )
			);
		}

		return $result;
	}

	/**
	 * Tests that run to check if autoupdates are enabled.
	 *
	 * @return array
	 */
	public function test_check_autoupdates() {

		$result = array(
			'label'       => __( 'Your website is receiving automatic updates', 'google-analytics-premium' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MonsterInsights', 'google-analytics-premium' ),
				'color' => 'blue',
			),
			'description' => __( 'MonsterInsights automatic updates are enabled and you are getting the latest features, bugfixes, and security updates as they are released.', 'google-analytics-premium' ),
			'test'        => 'monsterinsights_automatic_updates',
		);

		$updates_option = monsterinsights_get_option( 'automatic_updates', false );

		if ( 'minor' === $updates_option ) {
			$result['label']       = __( 'Your website is receiving minor updates', 'google-analytics-premium' );
			$result['description'] = __( 'MonsterInsights minor updates are enabled and you are getting the latest bugfixes and security updates, but not major features.', 'google-analytics-premium' );
		}
		if ( 'none' === $updates_option ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Automatic updates are disabled', 'google-analytics-premium' );
			$result['description'] = __( 'MonsterInsights automatic updates are disabled. We recommend enabling automatic updates so you can get access to the latest features, bugfixes, and security updates as they are released.', 'google-analytics-premium' );
			$result['actions']     = sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				add_query_arg( 'page', 'monsterinsights_settings#/advanced', admin_url( 'admin.php' ) ),
				__( 'Update Settings', 'google-analytics-premium' )
			);
		}

		return $result;

	}

	/**
	 * Tests that run to check if eCommerce is present.
	 *
	 * @return array
	 */
	public function test_check_ecommerce() {
		$result = array(
			'label'       => __( 'You are successfully tracking eCommerce data', 'google-analytics-premium' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MonsterInsights', 'google-analytics-premium' ),
				'color' => 'blue',
			),
			// Translators: The eCommerce store currently active.
			'description' => sprintf( __( 'MonsterInsights eCommerce addon is properly set up and tracking your %s store', 'google-analytics-premium' ), $this->ecommerce ),
			'test'        => 'monsterinsights_ecommerce',
		);

		$v4 = monsterinsights_get_v4_id();
		if ( empty( $v4 ) ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'eCommerce data is not being tracked', 'google-analytics-premium' );
			$result['description'] = __( 'Please connect MonsterInsights to Google Analytics to start tracking eCommerce data.', 'google-analytics-premium' );
			$result['actions']     = sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				add_query_arg( 'page', 'monsterinsights_settings', admin_url( 'admin.php' ) ),
				__( 'Authenticate now', 'google-analytics-premium' )
			);
		} else if ( ! class_exists( 'MonsterInsights_eCommerce' ) ) {
			$result['status'] = 'recommended';
			$result['label']  = __( 'eCommerce data is not being tracked', 'google-analytics-premium' );
			// Translators: The eCommerce store currently active.
			$result['description'] = sprintf( __( 'You are using %s but the MonsterInsights eCommerce addon is not active, please Install & Activate it to start tracking eCommerce data.', 'google-analytics-premium' ), $this->ecommerce );
			$result['actions']     = sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				add_query_arg( 'page', 'monsterinsights_settings#/addons', admin_url( 'admin.php' ) ),
				__( 'View Addons', 'google-analytics-premium' )
			);

			$type = MonsterInsights()->license->get_license_type();
			if ( in_array( $type, array( 'basic', 'plus' ), true ) ) {
				$result['actions'] = sprintf(
					'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
					monsterinsights_get_upgrade_link( 'site-health', 'ecommerce', 'https://monsterinsights.com/my-account' ),
					__( 'Upgrade Now', 'google-analytics-premium' )
				);
			}
		}

		return $result;
	}

	/**
	 * Check if Custom Dimensions are set up correctly.
	 *
	 * @return array
	 */
	public function test_check_dimensions() {

		$custom_dimensions = monsterinsights_get_option( 'custom_dimensions', array() );

		$result = array(
			'label'       => __( 'Custom Dimensions are set up correctly', 'google-analytics-premium' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MonsterInsights', 'google-analytics-premium' ),
				'color' => 'blue',
			),
			// Translators: The eCommerce store currently active.
			'description' => sprintf( __( 'MonsterInsights Custom Dimensions addon is properly set up and tracking your dimensions', 'google-analytics-premium' ), count( $custom_dimensions ) ),
			'test'        => 'monsterinsights_dimensions',
		);

		if ( empty( $custom_dimensions ) ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'No Custom Dimensions are being tracked', 'google-analytics-premium' );
			$result['description'] = __( 'The MonsterInsights Custom Dimensions addon is installed & activated but no Custom Dimensions have been configured for tracking.', 'google-analytics-premium' );
			$result['actions']     = sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				add_query_arg( 'page', 'monsterinsights_settings#/conversions', admin_url( 'admin.php' ) ),
				__( 'View settings', 'google-analytics-premium' )
			);
		}

		return $result;
	}

	/**
	 * Tests for the AMP cases.
	 *
	 * @return array
	 */
	public function test_check_amp() {

		$result = array(
			'label'       => __( 'AMP tracking is set up correctly', 'google-analytics-premium' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MonsterInsights', 'google-analytics-premium' ),
				'color' => 'blue',
			),
			'description' => __( 'The MonsterInsights AMP addon is active and AMP pages are being tracked.', 'google-analytics-premium' ),
			'test'        => 'monsterinsights_amp',
		);

		// AMP plugin is installed but the MonsterInsights addon is missing.
		if ( defined( 'AMP__FILE__' ) && ! class_exists( 'MonsterInsights_AMP' ) ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'AMP pages are not being tracked', 'google-analytics-premium' );
			$result['description'] = __( 'Your website has Google AMP-enabled pages set up but they are not tracked by Google Analytics at the moment. You need to Install & Activate the MonsterInsights AMP Addon.', 'google-analytics-premium' );
			$result['actions']     = sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				add_query_arg( 'page', 'monsterinsights_settings#/addons', admin_url( 'admin.php' ) ),
				__( 'View Addons', 'google-analytics-premium' )
			);
		} else if ( class_exists( 'MonsterInsights_AMP' ) && ! defined( 'AMP__FILE__' ) ) {
			$plugins         = get_plugins();
			$install_amp_url = false;
			$button_text     = __( 'Install Plugin', 'google-analytics-premium' );
			if ( monsterinsights_can_install_plugins() ) {
				$amp_key = 'amp/amp.php';
				if ( array_key_exists( $amp_key, $plugins ) ) {
					$button_text     = __( 'Activate Plugin', 'google-analytics-premium' );
					$install_amp_url = wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . $amp_key ), 'activate-plugin_' . $amp_key );
				} else {
					$install_amp_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=amp' ), 'install-plugin_amp' );
				}
			}
			$result['status']      = 'critical';
			$result['label']       = __( 'AMP plugin is not active', 'google-analytics-premium' );
			$result['description'] = __( 'The MonsterInsights AMP tracking addon is enabled but the official Google-developed AMP plugin for WordPress is not active.', 'google-analytics-premium' );
			$result['actions']     = sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				$install_amp_url,
				$button_text
			);
		}

		return $result;

	}

	/**
	 * Checks if there are errors communicating with monsterinsights.com.
	 */
	public function test_check_connection() {
		check_ajax_referer( 'health-check-site-status' );

		if ( ! current_user_can( 'view_site_health_checks' ) ) {
			wp_send_json_error();
		}

		$result = array(
			'label'       => __( 'Can connect to MonsterInsights.com correctly', 'google-analytics-premium' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MonsterInsights', 'google-analytics-premium' ),
				'color' => 'blue',
			),
			'description' => __( 'The MonsterInsights API is reachable and no connection issues have been detected.', 'google-analytics-premium' ),
			'test'        => 'monsterinsights_connection',
		);

		$url      = 'https://api.monsterinsights.com/v2/test/';
		$params   = array(
			'sslverify'  => false,
			'timeout'    => 2,
			'user-agent' => 'MonsterInsights/' . MONSTERINSIGHTS_VERSION,
			'body'       => '',
		);
		$response = wp_remote_get( $url, $params );

		if ( is_wp_error( $response ) || $response['response']['code'] < 200 || $response['response']['code'] > 300 ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'The MonsterInsights server is not reachable.', 'google-analytics-premium' );
			$result['description'] = __( 'Your server is blocking external requests to monsterinsights.com, please check your firewall settings or contact your host for more details.', 'google-analytics-premium' );

			if ( is_wp_error( $response ) ) {
				// Translators: The error message received.
				$result['description'] .= ' ' . sprintf( __( 'Error message: %s', 'google-analytics-premium' ), $response->get_error_message() );
			}
		}

		wp_send_json_success( $result );
	}

	/**
	 * Checks if there is a duplicate tracker.
	 */
	public function test_check_tracking_code() {
		check_ajax_referer( 'health-check-site-status' );

		if ( ! current_user_can( 'view_site_health_checks' ) ) {
			wp_send_json_error();
		}

		$result = array(
			'label'       => __( 'Tracking code is properly being output.', 'google-analytics-premium' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MonsterInsights', 'google-analytics-premium' ),
				'color' => 'blue',
			),
			'description' => __( 'The Google Analytics tracking code is being output correctly, and no duplicate Google Analytics scripts have been detected.', 'google-analytics-premium' ),
			'test'        => 'monsterinsights_tracking_code',
		);

		$errors = monsterinsights_is_code_installed_frontend();

		if ( ! empty( $errors ) && is_array( $errors ) && ! empty( $errors[0] ) ) {
			if ( $this->is_coming_soon_active() ) {
				$result['status']      = 'good';
				$result['label']       = __( 'Tracking code disabled: coming soon/maintenance mode plugin present', 'google-analytics-premium' );
				$result['description'] = __( 'MonsterInsights has detected that you have a coming soon or maintenance mode plugin currently activated on your site. This plugin does not allow other plugins (like MonsterInsights) to output Javascript, and thus MonsterInsights is not currently tracking your users (expected). Once the coming soon/maintenance mode plugin is deactivated, tracking will resume automatically.', 'google-analytics-premium' );
			} else {
				$result['status']      = 'critical';
				$result['label']       = __( 'MonsterInsights has automatically detected an issue with your tracking setup', 'google-analytics-premium' );
				$result['description'] = $errors[0];
			}
		}

		wp_send_json_success( $result );
	}

	/**
	 * Test if all the user roles are excluded from tracking.
	 */
	public function test_check_roles_excluded() {

		$result = array(
			'status' => 'good',
			'badge'  => array(
				'label' => __( 'MonsterInsights', 'google-analytics-premium' ),
				'color' => 'blue',
			),
			'test'   => 'monsterinsights_excluded_roles',
		);

		$mi_roles = monsterinsights_get_option( 'ignore_users', array() );
		$wp_roles = monsterinsights_get_roles();

		if ( count( $mi_roles ) === count( $wp_roles ) ) {
			$result['label']       = __( 'MonsterInsights is not tracking any logged-in users', 'google-analytics-premium' );
			$result['status']      = 'recommended';
			$result['description'] = __( 'All the available user roles are excluded from tracking in your MonsterInsights settings. This means none of the logged-in users are being tracked.', 'google-analytics-premium' );
			$result['actions']     = sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				add_query_arg( 'page', 'monsterinsights_settings#/advanced', admin_url( 'admin.php' ) ),
				__( 'Update roles excluded from tracking', 'google-analytics-premium' )
			);
		} elseif ( count( $mi_roles ) === 0 ) {
			$result['label']       = __( 'MonsterInsights is tracking all logged-in users', 'google-analytics-premium' );
			$result['status']      = 'recommended';
			$result['description'] = __( 'No user roles are excluded from tracking in your MonsterInsights settings. This means your stats might be inflated by traffic from Administrators or Editors.', 'google-analytics-premium' );
			$result['actions']     = sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				add_query_arg( 'page', 'monsterinsights_settings#/advanced', admin_url( 'admin.php' ) ),
				__( 'Update roles excluded from tracking', 'google-analytics-premium' )
			);
		} else {
			$result['label']       = __( 'MonsterInsights is properly excluding some logged-in users', 'google-analytics-premium' );
			$result['status']      = 'good';
			$result['description'] = __( 'Some user roles are excluded from tracking in your MonsterInsights settings.', 'google-analytics-premium' );
		}

		return $result;
	}
}

new MonsterInsights_WP_Site_Health_Pro();
