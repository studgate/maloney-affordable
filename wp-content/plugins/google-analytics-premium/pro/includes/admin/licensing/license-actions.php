<?php

/**
 * License Actions class.
 *
 * @since 6.0.0
 *
 * @package MonsterInsights
 * @author  Chris Christoff
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MonsterInsights_License_Actions {
	/**
	 * Holds the license key.
	 *
	 * @since 6.0.0
	 *
	 * @var string
	 */
	public $key;

	/**
	 * Holds any license error messages.
	 *
	 * @since 6.0.0
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * Holds any license success messages.
	 *
	 * @since 6.0.0
	 *
	 * @var array
	 */
	public $success = array();

	/**
	 * Primary class constructor.
	 *
	 * @since 6.0.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_action( 'wp_ajax_monsterinsights_verify_license', array( $this, 'ajax_verify_key' ) );

		add_action( 'wp_ajax_monsterinsights_deactivate_license', array( $this, 'ajax_deactivate_key' ) );
		add_action(
			'wp_ajax_monsterinsights_deactivate_expired_license',
			array(
				$this,
				'ajax_deactivate_expired_key',
			)
		);

		add_action( 'wp_ajax_monsterinsights_validate_license', array( $this, 'ajax_validate_key' ) );
	}

	public function admin_init() {
		// Possibly verify the key.
		$this->maybe_verify_key();
		$this->maybe_deactivate_expired_license_addons();

		// Add potential admin notices for actions around the admin.
		add_action( 'admin_notices', array( $this, 'monsterinsights_notices' ), 11 );
		add_action( 'network_admin_notices', array( $this, 'monsterinsights_notices' ), 11 );

		// Grab the license key. If it is not set (even after verification), return early.
		$this->key = is_network_admin() ? MonsterInsights()->license->get_network_license_key() : MonsterInsights()->license->get_site_license_key();
		if ( ! $this->key ) {
			return;
		}

		// Possibly handle validating, deactivating and refreshing license keys.
		$this->maybe_validate_key();
		$this->maybe_deactivate_key();
		$this->maybe_refresh_key();
	}

	/**
	 * Maybe verifies a license key entered by the user.
	 *
	 * @return null Return early if the key fails to be verified.
	 * @since 6.0.0
	 */
	public function maybe_verify_key() {

		if ( empty( $_POST['monsterinsights-license-key'] ) || strlen( $_POST['monsterinsights-license-key'] ) < 10 || empty( $_POST['monsterinsights-verify-submit'] ) ) { // phpcs:ignore
			return;
		}

		if ( ! wp_verify_nonce( $_POST['monsterinsights-key-nonce'], 'monsterinsights-key-nonce' ) ) { // phpcs:ignore
			return;
		}

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			return;
		}

		$this->verify_key();
	}

	/**
	 * Ajax handler for verifying the License key.
	 */
	public function ajax_verify_key() {

		check_ajax_referer( 'mi-admin-nonce', 'nonce' );

		$network_admin = isset( $_POST['network'] ) && $_POST['network']; // phpcs:ignore
		$license       = ! empty( $_POST['license'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['license'] ) ) ) : false;

		if ( ! $license ) {
			wp_send_json_error(
				array(
					'error' => esc_html__( 'Please enter a license key.', 'google-analytics-premium' ),
				)
			);
		}

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json_error(
				array(
					'error' => esc_html__( 'You are not allowed to verify a license key.', 'google-analytics-premium' ),
				)
			);
		}

		$forced = isset( $_POST['forced'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['forced'] ) );

		$verify = monsterinsights_perform_remote_request( 'verify-key', array( 'tgm-updater-key' => $license ) );

		if ( ! $verify ) {
			wp_send_json_error(
				array(
					'error' => esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'google-analytics-premium' ),
				)
			);
		}

		if ( ! empty( $verify->error ) ) {
			wp_send_json_error(
				array(
					'error' => $verify->error,
				)
			);
		}

		// Otherwise, our request has been done successfully. Update the option and set the success message.
		$option                = $network_admin ? MonsterInsights()->license->get_network_license() : MonsterInsights()->license->get_site_license();
		$option['key']         = $license;
		$option['type']        = isset( $verify->type ) ? $verify->type : $option['type'];
		$option['is_expired']  = false;
		$option['is_disabled'] = false;
		$option['is_invalid']  = false;
		$option['is_agency']   = isset( $verify->is_agency ) ? boolval( $verify->is_agency ) : false;

		$network_admin ? MonsterInsights()->license->set_network_license( $option ) : MonsterInsights()->license->set_site_license( $option );
		delete_transient( '_monsterinsights_addons' );
		monsterinsights_get_addons_data( $option['key'] );

		$message = isset( $verify->success ) ? $verify->success : esc_html__( 'Congratulations! This site is now receiving automatic updates.', 'google-analytics-premium' );

		wp_send_json_success(
			array(
				'message'      => $message,
				'license_type' => $option['type'],
				'is_agency'    => $option['is_agency'],
			)
		);
	}


	/**
	 * Verifies a license key entered by the user.
	 *
	 * @param string $key Optional key, if not set uses POST param.
	 *
	 * @since 6.0.0
	 */
	public function verify_key( $key = '' ) {

		if ( empty( $key ) && ! empty( $_POST['monsterinsights-license-key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- Verified in calling methods
			$key = $_POST['monsterinsights-license-key']; // phpcs:ignore
		}
		// Perform a request to verify the key.
		$verify = monsterinsights_perform_remote_request( 'verify-key', array( 'tgm-updater-key' => trim( $key ) ) );

		// If it returns false, send back a generic error message and return.
		if ( ! $verify ) {
			$this->errors[] = esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'google-analytics-premium' );

			return;
		}

		// If an error is returned, set the error and return.
		if ( ! empty( $verify->error ) ) {
			$this->errors[] = $verify->error;

			return;
		}

		// Otherwise, our request has been done successfully. Update the option and set the success message.
		$option                = is_network_admin() ? MonsterInsights()->license->get_network_license() : MonsterInsights()->license->get_site_license();
		$option['key']         = trim( $key );
		$option['type']        = isset( $verify->type ) ? $verify->type : $option['type'];
		$option['is_expired']  = false;
		$option['is_disabled'] = false;
		$option['is_invalid']  = false;
		$option['is_agency']   = isset( $verify->is_agency ) ? boolval( $verify->is_agency ) : false;
		$this->success[]       = isset( $verify->success ) ? $verify->success : esc_html__( 'Congratulations! This site is now receiving automatic updates.', 'google-analytics-premium' );
		is_network_admin() ? MonsterInsights()->license->set_network_license( $option ) : MonsterInsights()->license->set_site_license( $option );
		delete_transient( '_monsterinsights_addons' );
		monsterinsights_get_addons_data( $option['key'] );
		// Make sure users can now update their plugins if they previously an expired key.
		wp_clean_plugins_cache( true );
	}

	/**
	 * Maybe validates a license key entered by the user.
	 *
	 * @return null Return early if the transient has not expired yet.
	 * @since 6.0.0
	 */
	public function maybe_validate_key() {
		$check = is_network_admin() ? MonsterInsights()->license->time_to_check_network_license() : MonsterInsights()->license->time_to_check_site_license();
		if ( $check ) {
			$this->validate_key();
		}
	}

	/**
	 * Ajax handler for validating the current key.
	 */
	public function ajax_validate_key() {

		check_ajax_referer( 'mi-admin-nonce', 'nonce' );

		$this->validate_key( true );

		if ( ! empty( $this->errors ) ) {
			wp_send_json_error(
				array(
					'error' => $this->errors[0],
				)
			);
		}

		if ( ! empty( $this->success ) ) {
			wp_send_json_success(
				array(
					'message' => $this->success[0],
				)
			);
		}

		wp_die();
	}

	/**
	 * Validates a license key entered by the user.
	 *
	 * @param bool $forced Force to set contextual messages (false by default).
	 *
	 * @since 6.0.0
	 */
	public function validate_key( $forced = false, $network = null ) {

		if ( is_null( $network ) ) {
			$network = is_network_admin();
		}

		$validate = monsterinsights_perform_remote_request( 'validate-key', array( 'tgm-updater-key' => $this->key ) );

		// If there was a basic API error in validation, only set the transient for 10 minutes before retrying.
		if ( ! $validate ) {
			// If forced, set contextual success message.
			if ( $forced ) {
				$this->errors[] = esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'google-analytics-premium' );
			}

			return;
		}

		$option                = $network ? MonsterInsights()->license->get_network_license() : MonsterInsights()->license->get_site_license();
		$option['is_expired']  = false;
		$option['expiry_date'] = '';
		$option['is_disabled'] = false;
		$option['is_invalid']  = false;

		// If a key or author error is returned, the license no longer exists or the user has been deleted, so reset license.
		if ( isset( $validate->key ) || isset( $validate->author ) ) {
			$option['is_invalid'] = true;
			$network ? MonsterInsights()->license->set_network_license( $option ) : MonsterInsights()->license->set_site_license( $option );
			$this->errors[] = esc_html__( 'Your license key for MonsterInsights is invalid. The key no longer exists or the user associated with the key has been deleted. Please use a different key.', 'google-analytics-premium' );

			return;
		}

		// If the license has expired, set the transient and expired flag and return.
		if ( isset( $validate->expired ) ) {
			$option['is_expired']  = true;
			$option['expiry_date'] = ( $validate->expiry_date ) ? $validate->expiry_date : '';
			$network ? MonsterInsights()->license->set_network_license( $option ) : MonsterInsights()->license->set_site_license( $option );
			// Translators: The link to the MonsterInsights website.
			$this->errors[] = sprintf( esc_html__( 'Your license key for MonsterInsights has expired. %1$sPlease click here to renew your license key.%2$s', 'google-analytics-premium' ), '<a href="' . monsterinsights_get_url( 'admin-notices', 'expired-license', 'https://www.monsterinsights.com/login/' ) . '" target="_blank" rel="noopener noreferrer" referrer="no-referrer">', '</a>' );

			return;
		}

		// If the license is disabled, set the transient and disabled flag and return.
		if ( isset( $validate->disabled ) ) {
			$option['is_disabled'] = true;
			$network ? MonsterInsights()->license->set_network_license( $option ) : MonsterInsights()->license->set_site_license( $option );
			$this->errors[] = esc_html__( 'Your license key for MonsterInsights has been disabled. Please use a different key.', 'google-analytics-premium' );

			return;
		}

		// If forced, set contextual success message.
		if ( ( ! empty( $validate->key ) || ! empty( $this->key ) ) && $forced ) {
			$key = ! empty( $validate->key ) ? $validate->key : $this->key;
			delete_transient( '_monsterinsights_addons' );
			monsterinsights_get_addons_data( $key );
			$this->success[] = esc_html__( 'Congratulations! Your key has been refreshed successfully.', 'google-analytics-premium' );
		}

		$option                = array();
		$option                = $network ? MonsterInsights()->license->get_network_license() : MonsterInsights()->license->get_site_license();
		$option['type']        = isset( $validate->type ) ? $validate->type : $option['type'];
		$option['is_expired']  = false;
		$option['expiry_date'] = '';
		$option['is_disabled'] = false;
		$option['is_invalid']  = false;
		$option['is_agency']   = isset( $validate->is_agency ) ? boolval( $validate->is_agency ) : false;
		$network ? MonsterInsights()->license->set_network_license( $option ) : MonsterInsights()->license->set_site_license( $option );
	}

	/**
	 * Maybe deactivates a license key entered by the user.
	 *
	 * @return null Return early if the key fails to be deactivated.
	 * @since 6.0.0
	 */
	public function maybe_deactivate_key() {

		if ( empty( $_POST['monsterinsights-license-key'] ) || empty( $_POST['monsterinsights-deactivate-submit'] ) ) { // phpcs:ignore
			return;
		}

		if ( ! wp_verify_nonce( $_POST['monsterinsights-key-nonce'], 'monsterinsights-key-nonce' ) ) { // phpcs:ignore
			return;
		}

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			return;
		}

		$this->deactivate_key();
	}

	/**
	 * Deactivate the license key with ajax.
	 */
	public function ajax_deactivate_key() {
		check_ajax_referer( 'mi-admin-nonce', 'nonce' );

		$network_admin = isset( $_POST['network'] ) && $_POST['network']; // phpcs:ignore

		$license = ! empty( $_POST['license'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['license'] ) ) ) : false;

		if ( ! $license ) {
			wp_send_json_error(
				array(
					'error' => esc_html__( 'Please refresh the page and try again.', 'google-analytics-premium' ),
				)
			);
		}

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json_error(
				array(
					'error' => esc_html__( 'You are not allowed to deactivate the license key.', 'google-analytics-premium' ),
				)
			);
		}

		$deactivate = monsterinsights_perform_remote_request( 'deactivate-key', array( 'tgm-updater-key' => $license ) );

		if ( ! $deactivate ) {
			wp_send_json_error(
				array(
					'error' => esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'google-analytics-premium' ),
				)
			);
		}

		if ( ! empty( $deactivate->error ) ) {
			wp_send_json_error(
				array(
					'error' => $deactivate->error,
				)
			);
		}

		$network_admin ? MonsterInsights()->license->delete_network_license() : MonsterInsights()->license->delete_site_license();

		wp_send_json_success(
			array(
				'message' => isset( $deactivate->success ) ? $deactivate->success : esc_html__( 'Congratulations! You have deactivated the key from this site successfully.', 'google-analytics-premium' ),
			)
		);
	}

	/**
	 * Deactivate Expired License from the site.
	 *
	 * We needed this function specifically for Expired License due to a bug
	 * in EDD where EDD does not deactivate a license if it is expired.
	 *
	 * @return void
	 * @since 8.5.0
	 */
	public function ajax_deactivate_expired_key() {
		check_ajax_referer( 'mi-admin-nonce', 'nonce' );

		$network_admin = isset( $_POST['network'] ) && $_POST['network']; // phpcs:ignore

		$license = ! empty( $_POST['license'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['license'] ) ) ) : false;

		if ( ! $license ) {
			wp_send_json_error(
				array(
					'error' => esc_html__( 'Please refresh the page and try again.', 'google-analytics-premium' ),
				)
			);
		}

		$network_admin ? MonsterInsights()->license->delete_network_license() : MonsterInsights()->license->delete_site_license();

		wp_send_json_success();

		wp_die();
	}

	/**
	 * Deactivates a license key entered by the user.
	 *
	 * @since 6.0.0
	 */
	public function deactivate_key() {

		// Perform a request to deactivate the key.
		$deactivate = monsterinsights_perform_remote_request( 'deactivate-key', array( 'tgm-updater-key' => $_POST['monsterinsights-license-key'] ) ); // phpcs:ignore

		// If it returns false, send back a generic error message and return.
		if ( ! $deactivate ) {
			$this->errors[] = esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'google-analytics-premium' );

			return;
		}

		// If an error is returned, set the error and return.
		if ( ! empty( $deactivate->error ) && ! monsterinsights_is_debug_mode() ) {
			$this->errors[] = $deactivate->error;

			return;
		}

		// Otherwise, our request has been done successfully. Reset the option and set the success message.
		$this->success[] = isset( $deactivate->success ) ? $deactivate->success : esc_html__( 'Congratulations! You have deactivated the key from this site successfully.', 'google-analytics-premium' );
		is_network_admin() ? MonsterInsights()->license->delete_network_license() : MonsterInsights()->license->delete_site_license();
	}

	/**
	 * Maybe refreshes a license key.
	 *
	 * @return null Return early if the key fails to be refreshed.
	 * @since 6.0.0
	 */
	public function maybe_refresh_key() {

		if ( empty( $_POST['monsterinsights-license-key'] ) || empty( $_POST['monsterinsights-refresh-submit'] ) ) { // phpcs:ignore
			return;
		}

		if ( ! wp_verify_nonce( $_POST['monsterinsights-key-nonce'], 'monsterinsights-key-nonce' ) ) { // phpcs:ignore
			return;
		}

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			return;
		}

		// Refreshing is simply a word alias for validating a key. Force true to set contextual messages.
		$this->validate_key( true );
	}

	/**
	 * Outputs any notices generated by the class.
	 *
	 * @since 7.0.0
	 */
	public function monsterinsights_notices() {
		if ( ! monsterinsights_is_pro_version() ) {
			return;
		}

		$screen = get_current_screen();
		if ( empty( $screen->id ) || strpos( $screen->id, 'monsterinsights' ) === false ) {
			return;
		}

		if ( ! empty( $this->errors ) ) { ?>
		<div class="error">
			<p><?php echo wp_kses_post( implode( '<br>', $this->errors ) ); ?></p>
		</div>
		<?php } elseif ( ! empty( $this->success ) ) { ?>
		<div class="updated">
			<p><?php echo wp_kses_post( implode( '<br>', $this->success ) ); ?></p>
		</div>
		<?php
		}
	}

	/**
	 * Deactivate all PRO addons if the license is expired.
	 *
	 * @return void
	 * @since 8.5.0
	 */
	public function maybe_deactivate_expired_license_addons() {
		if ( MonsterInsights()->license->license_expired() ) {

			$addons_data       = monsterinsights_get_addons();
			$parsed_addons     = array();
			$installed_plugins = get_plugins();

			if ( ! empty( $addons_data ) ) {
				foreach ( $addons_data as $addons_type => $addons ) {
					foreach ( $addons as $addon ) {
						$slug = 'monsterinsights-' . $addon->slug;
						if ( 'monsterinsights-ecommerce' === $slug && 'm' === $slug[0] ) {
							$addon = MonsterInsights()->routes->get_addon( $installed_plugins, $addons_type, $addon, $slug );
							if ( empty( $addon->installed ) ) {
								$slug  = 'ga-ecommerce';
								$addon = MonsterInsights()->routes->get_addon( $installed_plugins, $addons_type, $addon, $slug );
							}
						} else {
							$addon = MonsterInsights()->routes->get_addon( $installed_plugins, $addons_type, $addon, $slug );
						}
						$parsed_addons[ $addon->slug ] = $addon;
					}
				}
			} else {
				$active_plugins = get_option( 'active_plugins' );
				$plugin_info    = array();

				foreach ( $active_plugins as $active_plugin ) {
					list( $plugin_slug, $plugin_file ) = explode( '/', $active_plugin );
					$plugin_info[ $plugin_slug ]       = $active_plugin;
				}

				if ( ! empty( $plugin_info ) ) {
					foreach ( $plugin_info as $slug => $info ) {
						if ( strpos( $slug, 'monsterinsights-' ) !== false ) {
							$plugin_name            = ucwords( str_replace( '-', ' ', $slug ) );
							$parsed_addons[ $info ] = (object) array(
								'active'   => true,
								'basename' => $info,
								'title'    => $plugin_name,
							);
						}
					}
				}
			}

			if ( ! empty( $parsed_addons ) ) {
				foreach ( $parsed_addons as $addon_data ) {
					if ( $addon_data->active ) {
						if ( is_multisite() && is_plugin_active_for_network( $addon_data->basename ) ) {
							deactivate_plugins( $addon_data->basename, false, true );
						} else {
							deactivate_plugins( $addon_data->basename, false );
						}

						add_action(
							'admin_notices',
							function () use ( $addon_data ) {
								?>
<div class="notice notice-warning is-dismissible">
	<p>
		<?php echo sprintf( esc_html__( 'Cannot activate %1$s%2$s%3$s. MonsterInsights PRO addon works with a valid and active licenses only.', 'monsterinsights' ), '<strong>', $addon_data->title, '</strong>' ); // phpcs:ignore ?>
	</p>
</div>
<?php
							}
						);
					}
				}
			}
		}
	}
}
