<?php
/**
 * Updater class.
 *
 * @since 6.0.0
 *
 * @package MonsterInsights
 * @subpackage Updater
 * @author  Chris Christoff
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MonsterInsights_Updater {

	/**
	 * Plugin name.
	 *
	 * @since 6.0.0
	 *
	 * @var bool|string
	 */
	public $plugin_name = false;

	/**
	 * Plugin slug.
	 *
	 * @since 6.0.0
	 *
	 * @var bool|string
	 */
	public $plugin_slug = false;

	/**
	 * Plugin path.
	 *
	 * @since 6.0.0
	 *
	 * @var bool|string
	 */
	public $plugin_path = false;

	/**
	 * URL of the plugin.
	 *
	 * @since 6.0.0
	 *
	 * @var bool|string
	 */
	public $plugin_url = false;

	/**
	 * Remote URL for getting plugin updates.
	 *
	 * @since 6.0.0
	 *
	 * @var bool|string
	 */
	public $remote_url = false;

	/**
	 * Version number of the plugin.
	 *
	 * @since 6.0.0
	 *
	 * @var bool|int
	 */
	public $version = false;

	/**
	 * License key for the plugin.
	 *
	 * @since 6.0.0
	 *
	 * @var bool|string
	 */
	public $key = false;

	/**
	 * Holds the update data returned from the API.
	 *
	 * @since 6.0.0
	 *
	 * @var bool|object
	 */
	public $update = false;

	/**
	 * Holds the plugin info details for the update.
	 *
	 * @since 6.0.0
	 *
	 * @var bool|object
	 */
	public $info = false;

	/**
	 * Primary class constructor.
	 *
	 * @param array $config Array of updater config args.
	 *
	 * @since 6.0.0
	 *
	 */
	public function __construct( array $config ) {
		// Set class properties.
		$accepted_args = array(
			'plugin_name',
			'plugin_slug',
			'plugin_path',
			'plugin_url',
			'remote_url',
			'version',
			'key'
		);
		foreach ( $accepted_args as $arg ) {
			$this->$arg = $config[ $arg ];
		}

		// If the user cannot update plugins, stop processing here.
		if ( ! current_user_can( 'update_plugins' ) && ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) ) {
			return;
		}

		// If it's our site, then return so we don't redirect loop.
		if ( strpos( site_url(), 'monsterinsights.com' ) !== false ) {
			return;
		}

		// Load the updater hooks and filters.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update_plugins_filter' ) );

		add_filter( 'http_request_args', array( $this, 'http_request_args' ), 10, 2 );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );

		// ManageWP premium update filters
		//add_filter( 'mwp_premium_update_notification', array( $this, 'premium_update_push' ) );
		//add_filter( 'mwp_premium_perform_update', array( $this, 'premium_update' ) );

		// Add additional info if the license is expired.
		add_action( 'in_plugin_update_message-' . $this->plugin_path, array(
			$this,
			'maybe_show_license_expired_message'
		), 10, 2 );

		// Filter transient data before receive.
		add_filter( 'site_transient_update_plugins', array( $this, 'site_transient_update_plugins' ) );
	}

	public function maybe_show_license_expired_message( $plugin_data, $response ) {
		// If there's no download link but there is an update available there's an issue with the license.
		if ( empty( $response->package ) ) {
			$settings_url = is_network_admin() ? network_admin_url( 'admin.php?page=monsterinsights_network' ) : admin_url( 'admin.php?page=monsterinsights_settings' );
			// Translators: First one is a link to the settings page, second one is closing tag, third is a link to MonsterInsights.com
			echo '<br />' . sprintf( __( 'In order to enable updates, you need to have a valid license key on the %1$ssettings page%2$s. If your license key is expired or you need a new key, then %3$sclick here to purchase MonsterInsights Pro%2$s.', 'google-analytics-premium' ), '<a href="' . esc_url( $settings_url ) . '">', '</a>', '<a href="' . monsterinsights_get_url( 'Plugin update', $this->plugin_name ) . '">' ); // phpcs:ignore
		}
	}

	/**
	 * Infuse plugin update details when WordPress runs its update checker.
	 *
	 * @param object $value The WordPress update object.
	 *
	 * @return object $value Amended WordPress update object on success, default if object is empty.
	 * @since 6.0.0
	 *
	 */
	public function update_plugins_filter( $value ) {

		// If no update object exists, return early.
		if ( empty( $value ) ) {
			return $value;
		}

		// Run update check by pinging the external API. If it fails, return the default update object.
		if ( ! $this->update ) {
			$this->update = monsterinsights_perform_remote_request( 'get-plugin-update', array( 'tgm-updater-plugin' => $this->plugin_slug ) );
			if ( ! $this->update || ! empty( $this->update->error ) ) {
				$this->update = false;

				return $value;
			}
		}

		// Infuse the update object with our data if the version from the remote API is newer.
		if ( isset( $this->update->new_version ) && version_compare( $this->version, $this->update->new_version, '<' ) ) {
			// The $plugin_update object contains new_version, package, slug and last_update keys.
			//$this->update->full_slug             = $this->plugin_slug;
			//$this->update->name                  = $this->plugin_name;
			$this->update->monsterinsights_plugin  = true;
			$this->update->old_version             = $this->version;
			$this->update->plugin                  = $this->plugin_path;
			$value->response[ $this->plugin_path ] = $this->update;
		}

		// Return the update object.
		return $value;

	}

	/**
	 * Disables SSL verification to prevent download package failures.
	 *
	 * @param array $args Array of request args.
	 * @param string $url The URL to be pinged.
	 *
	 * @return array $args Amended array of request args.
	 * @since 6.0.0
	 *
	 */
	public function http_request_args( $args, $url ) {

		// If this is an SSL request and we are performing an upgrade routine, disable SSL verification.
		if ( strpos( $url, 'https://' ) !== false && strpos( $url, 'tgm-updater-action=get-plugin-update' ) ) {
			$args['sslverify'] = false;
		}

		return $args;

	}

	/**
	 * Filters the plugins_api function to get our own custom plugin information
	 * from our private repo.
	 *
	 * @param object $api The original plugins_api object.
	 * @param string $action The action sent by plugins_api.
	 * @param array $args Additional args to send to plugins_api.
	 *
	 * @return object $api   New stdClass with plugin information on success, default response on failure.
	 * @since 6.0.0
	 *
	 */
	public function plugins_api( $api, $action = '', $args = null ) {

		$plugin = ( 'plugin_information' == $action ) && isset( $args->slug ) && ( $this->plugin_slug == $args->slug );

		// If our plugin matches the request, set our own plugin data, else return the default response.
		if ( $plugin ) {
			return $this->set_plugins_api( $api );
		} else {
			return $api;
		}

	}

	/**
	 * Pings a remote API to retrieve plugin information for WordPress to display.
	 *
	 * @param object $default_api The default API object.
	 *
	 * @return object $api        Return custom plugin information to plugins_api.
	 * @since 6.0.0
	 *
	 */
	public function set_plugins_api( $default_api ) {

		// Perform the remote request to retrieve our plugin information. If it fails, return the default object.
		if ( ! $this->info ) {
			$this->info = monsterinsights_perform_remote_request( 'get-plugin-info', array( 'tgm-updater-plugin' => $this->plugin_slug ) );
			if ( ! $this->info || ! empty( $this->info->error ) ) {
				$this->info = false;

				return $default_api;
			}
		}

		// Create a new stdClass object and populate it with our plugin information.
		$api                 = new stdClass;
		$api->name           = isset( $this->info->name ) ? $this->info->name : '';
		$api->slug           = isset( $this->info->slug ) ? $this->info->slug : '';
		$api->version        = isset( $this->info->version ) ? $this->info->version : '';
		$api->author         = isset( $this->info->author ) ? $this->info->author : '';
		$api->author_profile = isset( $this->info->author_profile ) ? $this->info->author_profile : '';
		$api->requires       = isset( $this->info->requires ) ? $this->info->requires : '';
		$api->tested         = isset( $this->info->tested ) ? $this->info->tested : '';
		$api->last_updated   = isset( $this->info->last_updated ) ? $this->info->last_updated : '';
		$api->homepage       = isset( $this->info->homepage ) ? $this->info->homepage : '';

		$changelog   = isset( $this->info->changelog ) ? $this->info->changelog : '';
		$description = isset( $this->info->description ) ? $this->info->description : '';

		if ( ! empty( $changelog ) ) {
			if ( ! empty( $description ) ) {
				$api->sections['description'] = $description;
				$api->sections['changelog']   = $changelog;
			} else {
				$api->sections['changelog'] = $changelog;
			}
		} else if ( ! empty( $description ) ) {
			$api->sections['description'] = $description;
		} else {
			$api->sections = array();
		}

		$api->download_link = isset( $this->info->download_link ) ? $this->info->download_link : '';

		// Return the new API object with our custom data.
		return $api;

	}

	// Integration with ManageWP
	public function premium_update_push( $premium_update ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		$update = $this->set_plugins_api( array() );
		if ( ! empty( $update ) && version_compare( MONSTERINSIGHTS_VERSION, $update->version, '<' ) ) {
			$plugin_data                = get_plugin_data( $update->slug );
			$plugin_data['type']        = 'plugin';
			$plugin_data['slug']        = $update->slug;
			$plugin_data['new_version'] = $update->version;
			$premium_update[]           = $plugin_data;
		}

		return $premium_update;
	}

	// Integration with ManageWP
	public function premium_update( $premium_update ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		$update = $this->set_plugins_api( array() );
		if ( ! empty( $update ) && version_compare( MONSTERINSIGHTS_VERSION, $update->version, '<' ) ) {
			$plugin_data         = get_plugin_data( $update->slug );
			$plugin_data['type'] = 'plugin';
			$plugin_data['slug'] = $update->slug;
			$plugin_data['url']  = $update->download_link; // OR provide your own callback function for managing the update
			array_push( $premium_update, $plugin_data );
		}

		return $premium_update;
	}

	/**
	 * Remove plugin path if already updated.
	 *
	 * @param $update stdClass
	 *
	 * @return stdClass
	 */
	public function site_transient_update_plugins( $update ) {
		if ( ! $update ) {
			return $update;
		}

		// Check our plugin is available.
		if ( isset( $update->response ) && isset( $update->response[ $this->plugin_path ] ) ) {
			$plugin_update = $update->response[ $this->plugin_path ];

			// Check plugin version is already updated.
			if ( version_compare( $this->version, $plugin_update->new_version, '>=' ) ) {
				// Remove plugin from showing update notice.
				unset( $update->response[ $this->plugin_path ] );
			}
		}

		return $update;
	}
}
