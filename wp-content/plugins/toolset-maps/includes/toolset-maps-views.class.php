<?php

use OTGS\Toolset\Maps\Controller\Cache\CreateDatabaseTable;

/**
* Toolset Maps - Views integration
*
* @package ToolsetMaps
*
* @since 0.1
*
* @todo review https://github.com/wimagguc/jquery-latitude-longitude-picker-gmaps
* @todo review http://humaan.com/custom-html-markers-google-maps/
* @todo http://gis.stackexchange.com/a/15442
*/

class Toolset_Addon_Maps_Views {

	const option_name = 'wpv_addon_maps_options';

	static $is_wpv_embedded	= false;
	static $corrected_map_ids = array();

	protected $shortcode_generator;

	protected $api_used = Toolset_Addon_Maps_Common::API_GOOGLE;

	protected $enqueue_marker_clusterer_script;

	/**
	 * Toolset_Addon_Maps_Views constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		$this->enqueue_marker_clusterer_script = false;
		$this->api_used = apply_filters( 'toolset_maps_get_api_used', '' );
	}

	/**
	 * Inits filters and actions.
	 */
	public function init() {
		self::$is_wpv_embedded = apply_filters( 'toolset_is_views_embedded_available', false );

		// Assets
		$this->register_assets();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
		add_action( 'wp_footer', array( $this, 'render_dialogs' ), 50 );
		add_action( 'admin_footer', array( $this, 'render_dialogs' ), 50 );

		// Shortcodes
		add_shortcode( 'wpv-map-render', array( $this, 'map_render_shortcode' ) );
		add_shortcode( 'wpv-map-marker', array( $this, 'marker_shortcode' ) );
		// Filters
		add_filter( 'the_content', array( $this, 'run_shortcodes' ), 8 );
		add_filter( 'wpv_filter_wpv_the_content_suppressed', array( $this, 'run_shortcodes' ), 8 );
		add_filter( 'wpv-pre-do-shortcode', array( $this, 'run_shortcodes' ), 8 );

		// AJAX callbacks for updating Toolset Maps settings
		add_action( 'wp_ajax_wpv_addon_maps_update_marker', [ $this, 'update_marker' ] );
		add_action( 'wp_ajax_wpv_addon_maps_get_stored_data', [ $this, 'get_stored_data' ] );
		add_action( 'wp_ajax_wpv_addon_maps_delete_stored_addresses', [ $this, 'delete_stored_addresses' ] );
		add_action( 'wp_ajax_wpv_addon_maps_update_json_file', [ $this, 'update_json_file' ] );
		add_action( 'wp_ajax_wpv_addon_maps_update_default_map_style', [ $this, 'update_default_map_style' ] );
		add_action( 'wp_ajax_wpv_maps_check_missing_cache', [ $this, 'check_missing_cache' ] );

		// AJAX callback to update map and marker counters
		add_action( 'wp_ajax_wpv_toolset_maps_addon_update_counters', array( $this, 'update_counters' ) );

		// Register in the Views shortcodes GUI API
		add_filter( 'wpv_filter_wpv_shortcodes_gui_data', array( $this, 'shortcodes_register_data' ) );
		add_action( 'wpv_action_collect_shortcode_groups', array( $this, 'register_shortcodes_dialog_groups' ), 3 );

		// Fallback callbacks for the suggest actions for postmeta, termmeta and usermeta field keys
		// Deprecated as of Maps 1.5+, so we use select2_suggest_meta instead
		add_action( 'wp_ajax_wpv_suggest_wpv_post_field_name', array( $this, 'suggest_post_field_name' ) );
		add_action( 'wp_ajax_nopriv_wpv_suggest_wpv_post_field_name', array( $this, 'suggest_post_field_name' ) );
		add_action(
			'wp_ajax_wpv_suggest_wpv_taxonomy_field_name',
			array( $this, 'suggest_taxonomy_field_name' )
		);
		add_action(
			'wp_ajax_nopriv_wpv_suggest_wpv_taxonomy_field_name',
			array( $this, 'suggest_taxonomy_field_name' )
		);
		add_action( 'wp_ajax_wpv_suggest_wpv_user_field_name', array( $this, 'suggest_user_field_name' ) );
		add_action( 'wp_ajax_nopriv_wpv_suggest_wpv_user_field_name', array( $this, 'suggest_user_field_name' ) );

		// Delete a registered marker icon when the image is deleted
		add_action( 'delete_attachment', array( $this, 'delete_stored_assets_on_delete_attachment' ) );

		// Force disable a View cache when it contains a map shortcode
		add_filter( 'wpv_filter_disable_caching', array( $this, 'disable_views_caching' ), 10, 2 );

		// Exposes marker options array for easy access
		add_filter( 'toolset_maps_views_get_marker_options', array( $this, 'get_marker_options' ) );

		// Cron jobs.
		add_action( 'toolset_maps_cache_cron_hook', [ $this, 'execute_missing_cache_entries_cron' ] );

		// Init shortcode generator
		$this->shortcode_generator = new Toolset_Maps_Shortcode_Generator( $this );
		$this->shortcode_generator->initialize();
	}

	function admin_init() {

		if ( ! self::$is_wpv_embedded ) {

			/**
			* Backwards compatibility
			*
			* Before Views 2.0, the Toolset Maps settings are integrated in the Views Settings page.
			*
			* From Views 2.0, the Toolset Maps settings are integrated in the Toolset Settings page entirely.
			* From Toolset Maps 1.2 the Google Maps API key settings are registered globally for all Toolset integrations.
			* The marker icons and stored data settings still belong to the Views integration.
			* The legacy map setting is registered in Views entirely.
			*/
			if ( version_compare( WPV_VERSION, '2.0', '<' ) ) {

				if ( class_exists( 'WPV_Settings_Screen' ) ) {
					$WPV_Settings_Screen = WPV_Settings_Screen::get_instance();
					remove_action( 'wpv_action_views_settings_features_section',		array( $WPV_Settings_Screen, 'wpv_map_plugin_options' ), 30 );
				} else {
					global $WPV_settings;
					remove_action( 'wpv_action_views_settings_features_section',		array( $WPV_settings, 'wpv_map_plugin_options' ), 30 );
				}

				add_filter( 'wpv_filter_wpv_settings_admin_tabs',						array( $this, 'register_settings_admin_tab' ) );
				add_action( 'wpv_action_views_settings_addon_maps_section',				array( $this, 'options' ), 30 );

				add_filter( 'toolset_filter_toolset_maps_settings_link',				array( $this, 'toolset_maps_settings_link' ) );

			} else {

				// Register the custom sections in the Map tab registered in Toolset_Addon_Maps_Common
				add_filter( 'toolset_filter_toolset_register_settings_maps_section',	array( $this, 'marker_options' ), 20 );
				add_filter( 'toolset_filter_toolset_register_settings_maps_section',	array( $this, 'maps_style_file_options' ), 25 );
				add_filter(
                    'toolset_filter_toolset_register_settings_maps_section',
                    array( $this, 'cache_options' ),
                    30
                );
			}

			//add_action( 'wpv_action_wpv_add_field_on_loop_wizard_for_posts', array( $this, 'wpv_map_shortcodes_to_loop_wizard' ), 10, 2 );

			// Helpers in the Filter editor for inserting callbacks
			add_filter(
                'wpv_filter_wpv_dialog_frontend_events_tabs',
                array( $this, 'frontend_events_tab'	),
                10, 2
            );
			add_action(
                'wpv_filter_wpv_dialog_frontend_events_sections',
                array( $this, 'render_frontend_events_section' )
            );

			// Compatibility with Views parametric search: manage address fields as text fields
			add_filter(
                'wpv_filter_wpv_paranetric_search_computed_field_properties',
                array( $this, 'parametric_search_pretend_textfield_type' )
            );
		}

	}

	/**
	 * Registers all the assets for this part of Maps.
	 */
	private function register_assets() {
		$toolset_maps_dialogs_dependencies = array(
			'jquery',
			'underscore',
			'wp-util',
			'jquery-ui-dialog',
			'jquery-ui-tabs',
			'views-shortcodes-gui-script',
			'icl_media-manager-js',
			'views-addon-maps-preview-script',
		);
		if ( is_admin() ) {
			// 'wp-color-picker'  is an asset only available for wp-admin
			// SO it becomes an optional dependency, and the script itself chcks its existence
			// before initializing it on the map background selector.
			$toolset_maps_dialogs_dependencies[] = 'wp-color-picker';
		}
		if ( $this->api_used === Toolset_Addon_Maps_Common::API_GOOGLE ) {
			$toolset_maps_dialogs_dependencies[] = 'jquery-geocomplete';
		} else {
			$toolset_maps_dialogs_dependencies[] = 'toolset-maps-address-autocomplete';
		}
		wp_register_script( 'views-addon-maps-dialogs-script', TOOLSET_ADDON_MAPS_URL . '/resources/js/wpv_addon_maps_dialogs.js', $toolset_maps_dialogs_dependencies, TOOLSET_ADDON_MAPS_VERSION, true );
		$types_postmeta_fields = apply_filters( 'toolset_filter_toolset_maps_get_types_postmeta_fields', array() );
		$types_termmeta_fields = apply_filters( 'toolset_filter_toolset_maps_get_types_termmeta_fields', array() );
		$types_usermeta_fields = apply_filters( 'toolset_filter_toolset_maps_get_types_usermeta_fields', array() );
		$types_opt_array		= array(
			'toolset_map_postmeta_fields'	=> $types_postmeta_fields,
			'toolset_map_termmeta_fields'	=> $types_termmeta_fields,
			'toolset_map_usermeta_fields'	=> $types_usermeta_fields
		);
		$saved_options			= apply_filters( 'toolset_filter_toolset_maps_get_options', array() );
		$wpv_addon_maps_dialogs_localization = array(
			'insert_link'			=> __( 'Insert link', 'toolset-maps' ),
			'close_dialog'			=> __( 'Cancel', 'toolset-maps' ),
			'latitude'				=> __( 'Latitude', 'toolset-maps' ),
			'longitude'				=> __( 'Longitude', 'toolset-maps' ),
			'types_postmeta_field_label'		=> __( 'Types custom field', 'toolset-maps' ),
			'types_termmeta_field_label'		=> __( 'Types termmeta field', 'toolset-maps' ),
			'types_usermeta_field_label'		=> __( 'Types usermeta field', 'toolset-maps' ),
			'types_field_options'	=> $types_opt_array,
			'generic_field_label'	=> array(
                'posts'		=> __( 'Field slug', 'toolset-maps' ),
                'taxonomy'	=> __( 'Taxonomy field slug', 'toolset-maps' ),
                'users'		=> __( 'User field slug', 'toolset-maps' ),
            ),
			'id_attribute_label'	=> array(
                'posts'		=> __( 'Post ID', 'toolset-maps' ),
                'taxonomy'	=> __( 'Term ID', 'toolset-maps' ),
                'users'		=> __( 'User ID', 'toolset-maps' ),
            ),
			'geolocation_field_labels' => array(
                'radio_immediate' => __( 'Render the map immediately and then add visitor location', 'toolset-maps' ),
                'radio_wait' => __( 'Wait until visitors share their location and only then render the map', 'toolset-maps' )
			),
			'marker_source_desc'	=> array(
                'posts_attr_id'			=> __( 'You can use $parent for the native parent post, $posttype for the Types parent post, or a post ID. Defaults to the current post.', 'toolset-maps' ),
                'taxonomy_attr_id'		=> __( 'You can use a term ID.', 'toolset-maps' ),
                'taxonomy_attr_id_v'	=> __( 'You can use a term ID. Defaults to the current taxonomy term in the View loop.', 'toolset-maps' ),
                'users_attr_id'			=> __( 'You can use $current for the current user, $author for the author of the current post, or a user ID. Defaults to the current user.', 'toolset-maps' ),
                'users_attr_id_v'		=> __( 'You can use $current for the current user, $author for the author of the current post, or a user ID. Defaults to the current user in the View loop.', 'toolset-maps' ),
            ),
			'add_marker_icon'		=> __( 'Add another marker icon', 'toolset-maps' ),
			'use_same_image'		=> __( 'Use the same marker icon', 'toolset-maps' ),
			'user_another_image'	=> __( 'Use a different marker icon', 'toolset-maps' ),
			'add_a_map_first'		=> __( 'Remember that you need to add a map first. Then, use its ID here to add markers into that map.', 'toolset-maps' ),
			'clusters'				=> array(
                'extra_options_title'		=> __( 'Conditions for replacing markers with clusters', 'toolset-maps' ),
                'extra_options_min_size'	=> __( 'Minimal number of markers in a cluster:', 'toolset-maps' ),
                'extra_options_grid_size'	=> __( 'Minimal distance, in pixels, between markers:', 'toolset-maps' ),
                'extra_options_max_zoom'	=> __( 'Maximal map zoom level that allows clustering:', 'toolset-maps' ),
                'extra_options_description'	=> __( 'You can leave all these options blank to use defaults.', 'toolset-maps' )
            ),
			'counters'				=> array(
                'map'		=> $saved_options['map_counter'],
                'marker'	=> $saved_options['marker_counter']
            ),
			'background_hex_format'	=> __( 'Use HEX format.', 'toolset-maps' ),
			'can_manage_options'	=> current_user_can( 'manage_options' ) ? 'yes' : 'no',
			'nonce'					=> wp_create_nonce( 'toolset_views_addon_maps_dialogs' ),
			'global_nonce'			=> wp_create_nonce( 'toolset_views_addon_maps_global' ),
            'add_style_json'        => __( 'Upload a different map style (JSON file)', 'toolset-maps' ),
            'default_json'          => array_search( $this->get_saved_option( 'default_map_style' ), $this->get_style_options() )
		);
		wp_localize_script( 'views-addon-maps-dialogs-script', 'wpv_addon_maps_dialogs_local', $wpv_addon_maps_dialogs_localization );
	}

	function enqueue_scripts( $hook ) {
		if (
			isset( $_GET['page'] )
			&& $_GET['page'] == 'views-settings'
			&& isset( $_GET['tab'] )
			&& $_GET['tab'] == 'addon_maps'
		) {
			// Legacy, needed before Views 2.0
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_media();
			wp_enqueue_script( 'views-addon-maps-settings-script' );
		}

		// Added only if views-admin-css is loaded
		wp_add_inline_style(
			'views-admin-css',
			file_get_contents( TOOLSET_ADDON_MAPS_PATH . '/resources/css/wpv_addon_maps.css' )
		);

		if (
			wp_script_is( 'views-shortcodes-gui-script' ) &&
			! $this->is_block_editor()
		) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_media();
			wp_enqueue_script( 'views-addon-maps-dialogs-script' );
		}
	}

	/**
	 * Tries to determine if block editor is currently being rendered.
	 *
	 * @return bool
	 */
	private function is_block_editor() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$current_screen = get_current_screen();

		if ( null === $current_screen ) {
			return false;
		}

		return ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() );
	}

	function render_dialogs() {
		if ( wp_script_is( 'views-shortcodes-gui-script' ) ) {
			?>
			<div id="js-wpv-addon-maps-dialogs" style="display:none">

				<div id="js-wpv-addon-maps-dialog-reload" class="toolset-shortcode-gui-dialog-container wpv-shortcode-gui-dialog-container">
					<div class="wpv-dialog js-wpv-dialog" data-kind="reload">
						<div class="wpv-shortcode-gui-dialog-container">
							<div id="js-wpv-addon-maps-reload-settings" class="wpv-shortcode-gui-attribute-wrapper">
								<h3><?php _e( 'Map ID', 'toolset-maps' ); ?></h3>
								<ul>
									<li>
										<label for="wpv-addon-maps-reload"><?php _e( 'ID of the map to reload&#42;', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-reload" class="large-text js-wpv-addon-maps-links" value="" data-attribute="map" />
									</li>
								</ul>
								<h3><?php _e( 'Display', 'toolset-maps' ); ?></h3>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-tag"><?php _e( 'Use this HTML element', 'toolset-maps' ); ?></label>
										<select id="wpv-addon-maps-link-tag" class="large-text js-wpv-addon-maps-tag" autocomplete="off">
											<option value="link"><?php _e( 'Link', 'toolset-maps' ); ?></option>
											<option value="button"><?php _e( 'Button', 'toolset-maps' ); ?></option>
										</select>
									</li>
								</ul>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-anchor"><?php _e( 'Use this text&#42;', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-link-anchor" class="large-text js-wpv-addon-maps-anchor" value="" autocomplete="off" />
									</li>
								</ul>
								<h3><?php _e( 'Extra classnames and styles', 'toolset-maps' ); ?></h3>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-class"><?php _e( 'Classnames', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-link-class" class="large-text js-wpv-addon-maps-class" value="" autocomplete="off" />
									</li>
								</ul>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-style"><?php _e( 'Styles', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-link-style" class="large-text js-wpv-addon-maps-style" value="" autocomplete="off" />
									</li>
								</ul>
								<div class="tab-metadata">
									<p class="description">
										<?php _e( '&#42; required', 'toolset-maps' ); ?>
									</p>
								</div>
							</div>

						</div>
					</div>
				</div>

				<div id="js-wpv-addon-maps-dialog-focus" class="toolset-shortcode-gui-dialog-container wpv-shortcode-gui-dialog-container">
					<div class="wpv-dialog js-wpv-dialog" data-kind="focus">
						<div class="wpv-shortcode-gui-tabs js-wpv-addon-maps-focus-tabs">
							<ul>
								<li>
									<a href="#js-wpv-addon-maps-focus-settings"><?php
										/* translators: "Options" tab for "Focus on Marker" dialogue */
										echo esc_html( __( 'Options', 'toolset-maps' ) );
									?></a>
								</li>
								<li>
									<a href="#js-wpv-addon-maps-focus-interaction"><?php
										/* translators: "Interaction" tab for "Focus on Marker" dialogue */
										echo esc_html( __( 'Interaction', 'toolset-maps' ) );
									?></a>
								</li>
							</ul>
							<div id="js-wpv-addon-maps-focus-settings" class="wpv-shortcode-gui-attribute-wrapper">
								<h3><?php _e( 'Map and marker', 'toolset-maps' ); ?></h3>
								<ul>
									<li>
										<label for="wpv-addon-maps-focus-map"><?php _e( 'ID of the map&#42;', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-focus-map" class="large-text js-wpv-addon-maps-links" value="" data-attribute="map" />
									</li>
									<li>
										<label for="wpv-addon-maps-focus-marker"><?php _e( 'ID of the marker to zoom in&#42;', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-focus-marker" class="large-text js-wpv-addon-maps-links" value="" data-attribute="marker" autocomplete="off" />

									</li>
								</ul>
								<h3><?php _e( 'Display', 'toolset-maps' ); ?></h3>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-tag"><?php _e( 'Use this HTML element', 'toolset-maps' ); ?></label>
										<select id="wpv-addon-maps-link-tag" class="large-text js-wpv-addon-maps-tag" autocomplete="off">
											<option value="link"><?php _e( 'Link', 'toolset-maps' ); ?></option>
											<option value="button"><?php _e( 'Button', 'toolset-maps' ); ?></option>
										</select>
									</li>
								</ul>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-anchor-focus"><?php _e( 'Use this text&#42;', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-link-anchor-focus" class="large-text js-wpv-addon-maps-anchor" value="" autocomplete="off" />
									</li>
								</ul>
								<h3><?php _e( 'Extra classnames and styles', 'toolset-maps' ); ?></h3>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-class-focus"><?php _e( 'Classnames', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-link-class-focus" class="large-text js-wpv-addon-maps-class" value="" autocomplete="off" />
									</li>
								</ul>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-style-focus"><?php
											/* translators: label for a text box to input additional CSS styles */
											_e( 'Styles', 'toolset-maps' );
										?></label>
										<input type="text" id="wpv-addon-maps-link-style-focus" class="large-text js-wpv-addon-maps-style" value="" autocomplete="off" />
									</li>
								</ul>
								<div class="tab-metadata">
									<p class="description">
										<?php _e( '&#42; required', 'toolset-maps' ); ?>
									</p>
								</div>
							</div>
							<div id="js-wpv-addon-maps-focus-interaction" class="wpv-shortcode-gui-attribute-wrapper">
								<h3><?php _e( 'Mouse interaction', 'toolset-maps' ); ?></h3>
								<ul>
									<li>
										<label for="wpv-addon-maps-focus-hover">
											<input type="checkbox" id="wpv-addon-maps-focus-hover" class="js-wpv-addon-maps-focus-interaction" value="hover" autocomplete="off" />
											<?php _e( 'Hovering this item acts like hovering on the marker itself', 'toolset-maps' ); ?>
										</label>
									</li>
									<li>
										<label for="wpv-addon-maps-focus-click">
											<input type="checkbox" id="wpv-addon-maps-focus-click" class="js-wpv-addon-maps-focus-interaction" value="click" autocomplete="off" />
											<?php _e( 'Clicking this item will also open the marker popup', 'toolset-maps' ); ?>
										</label>
									</li>
								</ul>
							</div>
						</div>
					</div>
				</div>

				<div id="js-wpv-addon-maps-dialog-restore" class="toolset-shortcode-gui-dialog-container wpv-shortcode-gui-dialog-container">
					<div class="wpv-dialog js-wpv-dialog" data-kind="restore">
						<div class="wpv-shortcode-gui-dialog-container">
							<div id="js-wpv-addon-maps-restore-settings" class="wpv-shortcode-gui-attribute-wrapper">
								<h3><?php _e( 'Map ID', 'toolset-maps' ); ?></h3>
								<ul>
									<li>
										<label for="wpv-addon-maps-restore"><?php _e( 'ID of the map to restore&#42;', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-restore" class="large-text js-wpv-addon-maps-links" value="" data-attribute="map" />
									</li>
								</ul>
								<h3><?php _e( 'Display', 'toolset-maps' ); ?></h3>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-tag"><?php _e( 'Use this HTML element', 'toolset-maps' ); ?></label>
										<select id="wpv-addon-maps-link-tag" class="large-text js-wpv-addon-maps-tag" autocomplete="off">
											<option value="link"><?php _e( 'Link', 'toolset-maps' ); ?></option>
											<option value="button"><?php _e( 'Button', 'toolset-maps' ); ?></option>
										</select>
									</li>
								</ul>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-anchor-restore"><?php _e( 'Use this text&#42;', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-link-anchor-restore" class="large-text js-wpv-addon-maps-anchor" value="" autocomplete="off" />
									</li>
								</ul>
								<h3><?php _e( 'Extra classnames and styles', 'toolset-maps' ); ?></h3>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-class-restore"><?php _e( 'Classnames', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-link-class-restore" class="large-text js-wpv-addon-maps-class" value="" autocomplete="off" />
									</li>
								</ul>
								<ul>
									<li>
										<label for="wpv-addon-maps-link-style-restore"><?php _e( 'Styles', 'toolset-maps' ); ?></label>
										<input type="text" id="wpv-addon-maps-link-style-restore" class="large-text js-wpv-addon-maps-style" value="" autocomplete="off" />
									</li>
								</ul>
								<div class="tab-metadata">
									<p class="description">
										<?php _e( '&#42; required', 'toolset-maps' ); ?>
									</p>
								</div>
							</div>
						</div>
					</div>
				</div>

			</div>

            <!-- wp.template -->
            <script type="text/html" id="tmpl-toolset-views-maps-dialogs-no-geolocation-message">
                <div class="custom-combo-target">
                    <p><?php _e( 'Your site is running on unsecure HTTP, so you cannot get the location of visitors.', 'toolset-maps' ); ?></p>
                    <p><a href="https://css-tricks.com/moving-to-https-on-wordpress/" target="_blank"><?php echo esc_html( __( 'How to move the site to HTTPS', 'toolset-maps' ) ); ?></a></p>
                </div>
            </script>
            <script type="text/html" id="tmpl-toolset-views-maps-dialogs-upload-button-template">
                <span style="display:none"><input type="text" autocomplete="off" id="js-wpv-toolset-maps-add-{{{ data.context }}}-{{{ data.type }}}" data-context="{{{ data.context }}}" data-type="{{{ data.type }}}"></span>
                <button class="button button-secondary js-wpv-toolset-maps-media-manager js-wpv-media-manager" data-content="js-wpv-toolset-maps-add-{{{ data.context }}}-{{{ data.type }}}" data-id="0">
                    <i class="icon-plus fa fa-plus"></i> {{{ data.button_text }}}
                </button>
            </script>
            <script type="text/html" id="tmpl-toolset-views-maps-dialogs-street-view">
                <div class="wpv-shortcode-gui-attribute-wrapper js-wpv-shortcode-gui-attribute-wrapper js-wpv-shortcode-gui-attribute-wrapper-for-location" data-type="radio" data-attribute="location" style="display:none">
                    <h3><?php _e('Open Street View on this location', 'toolset-maps') ?></h3>
                    <ul id="wpv-map-render-location">
                        <li>
                            <label><input type="radio" name="wpv-map-render-location" value="marker_id" class="js-shortcode-gui-field" checked="checked"><?php _e('Marker Id', 'toolset-maps') ?></label>
                            <div style="" class="custom-combo-target">
                                <label for="wpv-map-render-marker_id" class="toolset-google-map-label"><?php _e('Id', 'toolset-maps') ?></label>
                                <input id="wpv-map-render-marker_id" type="text" value="street-view" name="wpv-map-render-marker_id" data-type="text" data-attribute="marker_id" data-default class="js-shortcode-gui-field" />
                                <p class="description"><?php _e('Location will come from marker with this Id.', 'toolset-maps') ?></p>
                            </div>
                        </li>
                        <li>
                            <label><input type="radio" name="wpv-map-render-location" value="first" class="js-shortcode-gui-field"><?php _e('First marker on the map', 'toolset-maps') ?></label>
                            <div style="display:none" class="custom-combo-target">
                                <p class="description"><?php _e('Location will come from first marker added to this map.', 'toolset-maps') ?></p>
                            </div>
                        </li>
                        <li>
                            <label><input type="radio" name="wpv-map-render-location" value="address" class="js-shortcode-gui-field"><?php _e('Address', 'toolset-maps') ?></label>
                            <div style="display:none" class="custom-combo-target">
                                <label for="wpv-map-street-view-address" class="toolset-google-map-label"><?php _e('Address', 'toolset-maps') ?></label>
                                <input id="wpv-map-street-view-address" type="text" autocomplete="off" name="wpv-map-street-view-address" class="js-shortcode-gui-field" data-type="text"/>
                                <p class="description"><?php _e('Street View will open on this address.', 'toolset-maps') ?></p>
                            </div>
                        </li>
                    </ul>
                    <p class="description"><?php _e('If you choose a marker to provide location for Street View, this special marker will not be shown on map as an usual marker.', 'toolset-maps') ?></p>
                </div>
            </script>

			<?php
		}

	}

	/**
     * Check if deleted attachment is an icon or json style and remove it from saved options
	 * @param int $attachment_id
     *
     * @since 1.4
     * @since 1.4.2 Support portable relative URLs for marker images too.
	 */
	public function delete_stored_assets_on_delete_attachment( $attachment_id ) {
		$saved_options = apply_filters( 'toolset_filter_toolset_maps_get_options', array() );
		$attachment_url = wp_get_attachment_url( $attachment_id );
		$relative_url = $this->make_full_upload_link_relative( $attachment_url );

		if ( in_array( $attachment_url, $saved_options['marker_images'] ) ) {
			$this->delete_saved_option( $attachment_url, 'marker_images', $saved_options );
		} elseif ( in_array( $relative_url, $saved_options['marker_images'] ) ) {
			$this->delete_saved_option( $relative_url, 'marker_images', $saved_options );
		} elseif ( in_array( $relative_url, $saved_options['map_style_files'] ) ) {
		    $this->delete_saved_option( $relative_url, 'map_style_files', $saved_options);
        }
	}

	/**
     * Deletes a saved option
	 *
	 * @param string $option_value
	 * @param string $asset marker_images|map_style_files
	 * @param array $saved_options
	 */
	protected function delete_saved_option( $option_value, $asset, array $saved_options ) {
		$saved_options = $this->unset_saved_option( $option_value, $asset, $saved_options);

		do_action( 'toolset_filter_toolset_maps_update_options', $saved_options );
	}

	/**
     * Unsets save option from $saved_options and returns changed file
	 *
	 * @param string $option_value
	 * @param string $asset marker_images|map_style_files
	 * @param array $saved_options
	 *
	 * @return array
	 */
	protected function unset_saved_option( $option_value, $asset, array $saved_options ) {
		$key = array_search( $option_value, $saved_options[$asset] );

		if ( $key !== false ) {
			unset( $saved_options[$asset][$key] );
		}

		return $saved_options;
	}

	/**
	 * Force disable the Views caching mechanism when it contains a map or marker shortcode.
	 *
	 * This ensures that the frontend maps script is enqueued.
	 *
	 * @param bool $state
	 * @param int  $view_id
	 *
	 * @return bool
	 *
	 * @since 1.3.1
	 */
	function disable_views_caching( $state, $view_id ) {
		$view_settings = apply_filters( 'wpv_filter_wpv_get_view_settings', array(), $view_id );
		$view_filter_html = isset( $view_settings[ 'filter_meta_html' ] ) ? $view_settings[ 'filter_meta_html' ] : '';

		$view_layout_settings = apply_filters( 'wpv_filter_wpv_get_view_layout_settings', array(), $view_id );
		$view_layout_html = isset( $view_layout_settings[ 'layout_meta_html' ] ) ? $view_layout_settings[ 'layout_meta_html' ] : '';

		if (
			strpos( $view_filter_html, '[wpv-map' ) !== false
			|| strpos( $view_layout_html, '[wpv-map' ) !== false
		) {
			return true;
		}

		return $state;
	}

	function register_settings_admin_tab( $tabs ) {
		$tabs['addon_maps'] = array(
			'slug'	=> 'addon_maps',
			'title'	=> 'Toolset Maps',
		);
		return $tabs;
	}

	// Legacy, neded for Views before 2.0
	function options( $WPV_settings ) {

		

		$saved_options = apply_filters( 'toolset_filter_toolset_maps_get_options', array() );
		?>
		<div class="wpv-setting-container js-wpv-setting-container">
			<span style="display:inline-block;padding:0 8px 0 5px;margin-right:10px;background:#f1f1f1;border-radius:5px;box-shadow:inset 0 0 10px #c5c5c5;color:#F05A28;">
				<i class="icon-toolset-map-logo ont-color-orange ont-icon-36"></i>
			</span>
			<?php
				$analytics_strings = array(
					'utm_source'	=> 'toolsetmapsplugin',
					'utm_campaign'	=> 'toolsetmaps',
					'utm_medium'	=> 'views-integration-settings',
					'utm_term'		=> 'Check the documentation'
				);
				echo __( "<strong>Toolset Maps</strong> will include the Google Maps API on your site.", 'toolset-maps' )
					. WPV_MESSAGE_SPACE_CHAR
					. '<a href="' . Toolset_Addon_Maps_Common::get_documentation_promotional_link( array( 'query' => $analytics_strings ) ) . '" target="_blank">'
					. __( 'Check the documentation', 'toolset-maps' )
					. '</a>.';
			?>
		</div>

		<div class="wpv-setting-container js-wpv-setting-container">
			<div class="wpv-settings-header">
				<h2><?php _e( 'Google Maps API key', 'toolset-maps' ); ?></h2>
			</div>
			<div class="wpv-setting">
				<?php
				$this->render_api_key_options( $saved_options );
				?>
			</div>
		</div>

		<div class="wpv-setting-container js-wpv-setting-container">
			<div class="wpv-settings-header">
				<h2><?php _e( 'Map markers', 'toolset-maps' ); ?></h2>
			</div>
			<div class="wpv-setting">
				<?php
				$this->render_marker_options( $saved_options );
				?>
			</div>
		</div>

		<div class="wpv-setting-container js-wpv-setting-container">
			<div class="wpv-settings-header">
				<h2><?php _e( 'Cached data', 'toolset-maps' ); ?></h2>
			</div>
			<div class="wpv-setting">
				<?php
				$this->render_cache_options();
				?>
			</div>
		</div>

		<div class="wpv-setting-container js-wpv-setting-container">
			<div class="wpv-settings-header">
				<h2><?php _e( 'Legacy mode', 'toolset-maps' ); ?></h2>
			</div>
			<div class="wpv-setting">
				<p>
					<?php _e( "Enable the old Views Maps plugin if you were already displaying some Google Maps with it.", 'toolset-maps' ); ?>
				</p>
				<div class="js-wpv-map-plugin-form">
					<p>
						<label>
							<input type="checkbox" name="wpv-map-plugin" class="js-wpv-map-plugin" value="1" <?php checked( $WPV_settings->wpv_map_plugin ); ?> autocomplete="off" />
							<?php _e( "Enable the old Views Map Plugin", 'toolset-maps' ); ?>
						</label>
					</p>
					<?php
					wp_nonce_field( 'wpv_map_plugin_nonce', 'wpv_map_plugin_nonce' );
					?>
				</div>

				<p class="update-button-wrap">
					<span class="js-wpv-messages"></span>
					<button class="js-wpv-map-plugin-settings-save button-secondary" disabled="disabled">
						<?php _e( 'Save', 'toolset-maps' ); ?>
					</button>
				</p>
			</div>
		</div>

		<?php
	}

	function marker_options( $sections ) {
		$saved_options = apply_filters( 'toolset_filter_toolset_maps_get_options', array() );
		ob_start();
		$this->render_marker_options( $saved_options );
		$section_content = ob_get_clean();

		$sections['maps-markers'] = array(
			'slug'		=> 'maps-markers',
			'title'		=> __( 'Map markers', 'toolset-maps' ),
			'content'	=> $section_content
		);
		return $sections;
	}

	function render_marker_options( $saved_options ) {
		?>
		<p><?php _e( 'Add custom markers here, and use them later when inserting a map or an individual marker.', 'toolset-maps' ); ?></p>
		<div class="wpv-add-item-settings js-wpv-add-item-settings-wrapper">
			<ul class="wpv-taglike-list js-wpv-add-item-settings-list js-wpv-addon-maps-custom-marker-list">
				<?php
				if ( count( $saved_options['marker_images'] ) > 0 ) {
					foreach ( $saved_options['marker_images'] as $marker_img ) {
						// Since 1.4.2, we are using relative links for uploaded markers, but we may have markers uploaded
                        // before update, so check and decide what to do.
						if ( $this->is_link_relative( $marker_img ) ) {
							$marker_img = $this->make_relative_upload_link_full( $marker_img );
						}
						?>
						<li class="js-wpv-addon-maps-custom-marker-item">
							<img src="<?php echo esc_attr( $marker_img ); ?>" class="js-wpv-addon-maps-custom-marker-item-img" />
							<i class="icon-remove-sign fa fa-times-circle js-wpv-addon-maps-custom-marker-delete"></i>
						</li>
						<?php
					}
				}
				?>
			</ul>
			<form class="js-wpv-add-item-settings-form js-wp-addon-maps-custom-marker-form-add">
				<input type="text" id="wpv-addpn-maps-custom-marker-newurl" class="hidden js-wpv-add-item-settings-form-newname js-wpv-addon-maps-custom-marker-newurl" autocomplete="off" />
				<button id="js-wpv-addon-maps-marker-add" class="button button-secondary js-wpv-media-manager" data-content="wpv-addpn-maps-custom-marker-newurl"><i class="icon-plus fa fa-plus"></i> <?php _e( 'Add a new marker', 'toolset-maps' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
     * Adds options for map json style files handling
	 * @param array $sections
	 * @return array
	 */
	public function maps_style_file_options( array $sections ) {
        $sections['style-files'] = array(
            'slug'    => 'style-files',
            'title'   => __( 'Map styles', 'toolset-maps' ),
            'content' => $this->render_maps_style_file_options_content()
        );

        return $sections;
	}

	/**
     * Content depending on API key availability
	 * @return string
	 */
	protected function render_maps_style_file_options_content() {
		if ( $this->get_saved_option( 'api_key' ) ) {
		    return $this->render_view( 'style_file_options_content' );
		} else {
		    return $this->render_view( 'style_file_options_warning' );
        }
	}

	/**
     * Given view name, returns its rendered HTML
	 * @param string $view
     * @return string HTML
	 */
	protected function render_view( $view ) {
		ob_start();
		include( TOOLSET_ADDON_MAPS_PATH . "/application/views/$view.phtml" );
		$html = ob_get_contents();

		ob_end_clean();

		return $html;
	}

	/**
	 * @param array $options
	 * @param string $default_text Text of option you want preselected
	 * @return string
	 */
	protected function render_option_html( array $options, $default_text = '' ) {
        $option_html = '';

        foreach ( $options as $text => $value ) {
	        $option_html .= sprintf(
		        '<option value="%s" %s>%s</option>;',
		        esc_attr( $value ),
		        selected( $default_text, $text, false ),
		        esc_html( $text )
	        );
        }

        return $option_html;
	}

	function render_api_key_options( $saved_options ) {
		?>
		<p>
			<?php _e( "Set your Google Maps API key.", 'toolset-maps' ); ?>
		</p>
		<div class="js-wpv-map-plugin-form">
			<p>
				<input id="js-wpv-map-api-key" type="text" name="wpv-map-api-key"
                   class="regular-text js-wpv-map-api-key" value="<?php echo esc_attr( $saved_options['api_key'] ); ?>"
                   autocomplete="off" placeholder="<?php echo esc_attr( __( 'API key', 'toolset-maps' ) ); ?>"
                   size="40"
                />
			</p>
			<p>
				<?php
				echo sprintf(
					__( 'An API key is <strong>required</strong> to use Toolset Maps. You will need to create a <a href="%1$s" target="_blank">project in the Developers console</a>, then create an API key and enable it for some specific API services.', 'toolset-maps' ),
					'https://console.developers.google.com'
				);
				?>
			</p>
			<p>
				<?php
				$analytics_strings = array(
					'utm_source'	=> 'toolsetmapsplugin',
					'utm_campaign'	=> 'toolsetmaps',
					'utm_medium'	=> 'views-integration-settings-for-api-key',
					'utm_term'		=> 'our documentation'
				);
				echo sprintf(
					__( 'You can find more information in %1$sour documentation%2$s.', 'toolset-maps' ),
					'<a href="' . Toolset_Addon_Maps_Common::get_documentation_promotional_link( array( 'query' => $analytics_strings, 'anchor' => 'api-key' ), TOOLSET_ADDON_MAPS_DOC_LINK ) . '" target="_blank">',
					'</a>'
				);
				?>
			</p>
		</div>
		<p class="update-button-wrap">
			<span class="js-wpv-messages"></span>
			<button class="js-wpv-map-api-key-save button-secondary" disabled="disabled">
				<?php _e( 'Save', 'toolset-maps' ); ?>
			</button>
		</p>
		<?php
	}

	function cache_options( $sections ) {
		ob_start();
		$this->render_cache_options();
		$section_content = ob_get_clean();

		$sections['maps-cache'] = array(
			'slug'		=> 'maps-cache',
			'title'		=> __( 'Cached data', 'toolset-maps' ),
			'content'	=> $section_content
		);
		return $sections;
	}

	/**
	 * Renders the cache options HTML.
	 */
	private function render_cache_options() {
		?>
		<p>
			<?php esc_html_e( 'We cache all the addresses used in your maps so we do not need to hit the map APIs every time, and to make distance filtering and ordering fast. You can review, delete and check the cached data here.', 'toolset-maps' ); ?>
		</p>
		<p>
			<?php
			$analytics_strings = array(
				'utm_source' => 'toolsetmapsplugin',
				'utm_campaign' => 'toolsetmaps',
				'utm_medium' => 'views-integration-settings-for-cached-data',
				'utm_term' => 'our documentation',
			);
			$documentation_link = Toolset_Addon_Maps_Common::get_documentation_promotional_link(
				array( 'query' => $analytics_strings ),
				'https://toolset.com/documentation/user-guides/data-caching-for-google-maps-addresses/'
			);
			echo sprintf(
				// translators: placeholders are just for documentation link.
				esc_html__( 'You can find more information in %1$sour documentation%2$s.', 'toolset-maps' ),
				'<a href="' . esc_attr( $documentation_link ) . '" target="_blank">',
				'</a>'
			);
			?>
		</p>
		<p class="update-button-wrap js-wpv-map-load-stored-data-before">
			<button id="js-wpv-map-load-stored-data" class="button-secondary"><?php esc_html_e( 'Load stored data', 'toolset-maps' ); ?></button>
			<button id="js-wpv-maps-check-missing-cache" class="button-secondary"><?php esc_html_e( 'Check for missing cache entries', 'toolset-maps' ); ?></button>
		</p>
		<div class="js-wpv-map-load-stored-data-after" style="display:none"></div>
		<?php
	}

	function update_marker() {
		wpv_ajax_authenticate( 'toolset_views_addon_maps_global', array( 'parameter_source' => 'post', 'type_of_death' => 'data' ) );
		if (
			! isset( $_POST['csaction'] )
			|| ! isset( $_POST['cstarget'] )
		) {
			wp_send_json_error();
		}
		$action = ( in_array( $_POST['csaction'], array( 'add', 'delete' ) ) ) ? $_POST['csaction'] : '';
		$target = esc_url( $_POST['cstarget'] );
		$target_url_relative = $this->make_full_upload_link_relative( $target );

		if (
			empty( $action )
			|| empty( $target )
		) {
			wp_send_json_error();
		}
		$saved_options = apply_filters( 'toolset_filter_toolset_maps_get_options', array() );
		switch ( $action ) {
			case 'add':
				if ( ! in_array( $target_url_relative, $saved_options['marker_images'] ) ) {
					$saved_options['marker_images'][] = $target_url_relative;
					do_action( 'toolset_filter_toolset_maps_update_options', $saved_options );
				} else {
					wp_send_json_error();
				}
				break;
			case 'delete':
			    // Compatibility with old, absolute marker URLs
				$key = array_search( $target, $saved_options['marker_images'] );
				// Or try if this marker was saved with new, relative marker URL (since 1.4.2)
				if ( $key === false ) {
					$key = array_search( $target_url_relative, $saved_options['marker_images'] );
                }

				if ( $key !== false ) {
					unset( $saved_options['marker_images'][$key] );
					do_action( 'toolset_filter_toolset_maps_update_options', $saved_options );
				}
				break;
			default:
				wp_send_json_error();
				break;
		}

		wp_send_json_success();
	}

	/**
	 * Ajax action to update uploaded json files in saved options
     * @since 1.4
	 */
	function update_json_file() {
		wpv_ajax_authenticate(
            'toolset_views_addon_maps_global',
            array( 'parameter_source' => 'post', 'type_of_death' => 'data' )
        );
        if (
            ! isset ( $_POST['csaction'] )
            || ! isset ( $_POST['cstargeturl'] )
            || ! isset ( $_POST['cstargetname'] )
        ) {
            wp_send_json_error();
        }

        $action = $_POST['csaction'];
        $target_url = esc_url( $_POST['cstargeturl'] );
        $target_name = esc_html( $_POST['cstargetname'] );

        if (
            empty( $target_url )
            || empty( $target_name )
        ) {
            wp_send_json_error();
        }

		$saved_options = apply_filters( 'toolset_filter_toolset_maps_get_options', array() );
		$target_url_relative = $this->make_full_upload_link_relative( $target_url );

		switch ( $action ) {
			case 'add':
				if ( ! in_array( $target_url_relative, $saved_options['map_style_files'] ) ) {
					$saved_options['map_style_files'][$target_name] = $target_url_relative;
					do_action( 'toolset_filter_toolset_maps_update_options', $saved_options );
				} else {
					wp_send_json_error();
				}
				break;
			case 'delete':
				if ( in_array( $target_url_relative, $saved_options['map_style_files'] ) ) {
				    $deleted = wp_delete_attachment(
                        attachment_url_to_postid( $target_url ),
                        true
                    );
				    // $this->delete_stored_assets_on_delete_attachment() should be triggered by WP if delete successful
				    if ( $deleted === false ) {
				        wp_send_json_error();
                    }
				} else {
					wp_send_json_error();
				}
				break;
			default:
				wp_send_json_error();
				break;
		}
		wp_send_json_success();
    }

	/**
	 * Ajax action to update default map style
     * @since 1.4
	 */
    public function update_default_map_style() {
	    wpv_ajax_authenticate(
		    'toolset_views_addon_maps_global',
		    array( 'parameter_source' => 'post', 'type_of_death' => 'data' )
	    );
	    if ( ! isset ( $_POST['style'] ) ) {
		    wp_send_json_error();
	    }

	    $style = esc_html( $_POST['style'] );

        $this->set_saved_option( 'default_map_style', $style );
        wp_send_json_success();
    }

	/**
	 * @return string Base URL for WP upload
	 */
    protected function get_upload_baseurl() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['baseurl'];
    }

	/**
	 * @param string $link Full URL given
	 * @return string link URL relative to upload base URL returned
	 */
	protected function make_full_upload_link_relative( $link ) {
		return str_replace( $this->get_upload_baseurl(), '', $link );
    }

	/**
	 * @param string $link URL relative to upload base URL given
	 * @return string Full URL returned
	 */
    protected function make_relative_upload_link_full( $link ) {
        return $this->get_upload_baseurl() . $link;
    }

	/**
     * Makes a schemaless link
	 * @param string $link
	 * @return string
	 */
    protected function remove_schema( $link ) {
	    return str_replace( array( 'http:', 'https:' ), '', $link );
    }

	/**
	 * @param string $link
	 *
	 * @return string
     *
     * @since 1.4.2
	 */
    protected function make_relative_upload_link_schemaless( $link ) {
        return $this->remove_schema(
            $this->make_relative_upload_link_full( $link )
        );
    }

	/**
	 * Ajax call to show address cache data
	 *
	 * @since unknown
	 * @since 2.0 aware of possible database saved cache
	 */
	public function get_stored_data() {
		wpv_ajax_authenticate(
			'toolset_views_addon_maps_global',
			array(
				'parameter_source' => 'get',
				'type_of_death' => 'data',
			)
		);
		if ( Toolset_Addon_Maps_Common::are_coordinates_migrated() ) {
			$coordinates_set = Toolset_Addon_Maps_Common::get_cached_coordinates();
		} else {
			$coordinates_set = Toolset_Addon_Maps_Common::get_stored_coordinates();
		}
		$alternate = 'alternate';
		ob_start();
		?>
		<table id="wpv-maps-stored-data-table" class="widefat">
			<thead>
				<tr>
					<th><?php echo esc_html( __( 'Address', 'toolset-maps' ) ); ?></th>
					<th><?php echo esc_html( __( 'Latitude', 'toolset-maps' ) ); ?></th>
					<th><?php echo esc_html( __( 'Longitude', 'toolset-maps' ) ); ?></th>
					<th></th>
				</tr>
			</thead>
				<tbody>
				<?php
				if ( Toolset_Addon_Maps_Common::are_coordinates_migrated() ) {
					foreach ( $coordinates_set as $address_passed => $data_set ) {
						?>
						<tr class="<?php echo esc_attr( $alternate ); ?>">
							<td><?php echo esc_html( $address_passed ); ?></td>
							<td><?php echo esc_html( $data_set['lat'] ); ?></td>
							<td><?php echo esc_html( $data_set['lon'] ); ?></td>
							<td><i class="fa fa-times wpv-map-delete-stored-address js-wpv-map-delete-stored-address"
								data-key="<?php echo esc_attr( $address_passed ); ?>"></i></td>
						</tr>
						<?php
						$alternate = ( 'alternate' === $alternate ) ? '' : 'alternate';
					}
				} else {
					foreach ( $coordinates_set as $data_key => $data_set ) {
						// Note that the address_passed might not exist as it was added after the first beta
						// Because we were first storing the same Maps PI returned address
						// On hashes based on different addresses (because of lower/uppercases, commas, etc)
						// And that leads to different hashes pointing to the same addresses, hence different entries
						?>
						<tr class="<?php echo esc_attr( $alternate ); ?>">
							<td><?php echo isset( $data_set['address_passed'] ) ? esc_html( $data_set['address_passed'] ) : esc_html( $data_set['address'] ); ?></td>
							<td><?php echo esc_html( $data_set['lat'] ); ?></td>
							<td><?php echo esc_html( $data_set['lon'] ); ?></td>
							<td>
								<i class="fa fa-times wpv-map-delete-stored-address js-wpv-map-delete-stored-address" data-key="<?php echo esc_attr( $data_key ); ?>"></i>
							</td>
						</tr>
						<?php
						$alternate = ( 'alternate' === $alternate ) ? '' : 'alternate';
					}
				}
				?>
			</tbody>
		</table>
		<p class="toolset-alert toolset-alert-info" style="line-height: 1.2em;">
		<?php esc_html_e( 'If you delete too many addresses, the maps on your site may take several page refreshes to display correctly, and distance filtering and ordering may not show all results until the cache is filled again.', 'toolset-maps' ); ?>
		</p>
		<?php
		$table = ob_get_clean();
		$data = array( 'table' => $table );
		wp_send_json_success( $data );
	}

	/**
	 * Gets all fields of type google_address.
	 *
	 * @since 2.0.9
	 * @return string[] Field meta_keys.
	 */
	private function get_address_fields() {
		static $fields = [];

		if ( ! empty( $fields ) ) {
			return $fields;
		} else {
			$field_definitions = [];

			foreach ( Toolset_Element_Domain::all() as $domain ) {
				$post_field_definition_factory = Toolset_Field_Definition_Factory::get_factory_by_domain( $domain );

				$field_definitions = array_merge(
					$post_field_definition_factory->query_definitions( [
						'filter' => 'types',
						'field_type' => 'google_address',
					] ),
					$field_definitions
				);
			}

			$fields = array_map(
				static function ( $field_definition ) {
					return $field_definition->get_meta_key();
				},
				$field_definitions
			);
		}

		return $fields;
	}

	/**
	 * Returns the common part of cache checking SQL.
	 *
	 * @since 2.0.9
	 * @param string $meta_table
	 * @param string $meta_alias
	 * @param string $cache_table
	 * @param string $field_string
	 * @return string
	 */
	private function get_common_cache_checking_sql( $meta_table, $meta_alias, $cache_table, $field_string ) {
		return "SELECT $meta_alias.meta_value
			FROM $meta_table $meta_alias
			LEFT JOIN $cache_table address_cache ON $meta_alias.meta_value = address_cache.address_passed
			WHERE
				$meta_alias.`meta_key` IN ( $field_string )
				AND $meta_alias.`meta_value` <> ''
				AND address_cache.address_passed IS NULL";
	}

	/**
	 * Returns the full unioned cache checking SQL.
	 *
	 * @since 2.0.9
	 * @return string
	 */
	private function get_full_cache_checking_sql() {
		global $wpdb;

		$cache_table = esc_sql( $wpdb->prefix . CreateDatabaseTable::TABLE_NAME );
		$field_string = Toolset_Utils::prepare_mysql_in( $this->get_address_fields() );

		return $this->get_common_cache_checking_sql( $wpdb->postmeta, 'postmeta', $cache_table, $field_string )
			. ' UNION DISTINCT '
			. $this->get_common_cache_checking_sql( $wpdb->usermeta, 'usermeta', $cache_table, $field_string )
			. ' UNION DISTINCT '
			. $this->get_common_cache_checking_sql( $wpdb->termmeta, 'termmeta', $cache_table, $field_string );
	}

	/**
	 * Ajax call to check if there are addresses missing from cache.
	 *
	 * @since 2.0.9
	 */
	public function check_missing_cache() {
		wpv_ajax_authenticate(
			'toolset_views_addon_maps_global',
			[
				'parameter_source' => 'get',
				'type_of_death' => 'data',
			]
		);

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$addresses = $wpdb->get_col( $this->get_full_cache_checking_sql() );
		$missing_cache_number = count( $addresses );

		if ( $missing_cache_number > 0 ) {
			$new_entries_added = $this->get_missing_cache_entries( $addresses );
			$message = sprintf(
				// translators: both placeholders are for number of cache entries - found and processed.
				__( '%1$d missing cache entries found. %2$d cache entries processed.', 'toolset-maps' ),
				$missing_cache_number,
				$new_entries_added
			);

			if ( $missing_cache_number > $new_entries_added ) {
				$message = $message . ' '
					. __( 'Remaining cache entries will be processed automatically using cron.', 'toolset-maps' );
				$this->schedule_missing_cache_entries_cron();
			} else {
				$this->unschedule_missing_cache_entries_cron();
			}
		} else {
			$message = __( 'No missing cache entries found.', 'toolset-maps' );
		}

		wp_send_json_success( $message );
	}

	/**
	 * Fills cache table where address lat/lng is missing.
	 *
	 * @since 2.0.9
	 * @param array $addresses When manually checking, we already have these, and they are passed. Otherwise, gets them.
	 * @return int Number of entries added.
	 */
	private function get_missing_cache_entries( array $addresses = [] ) {
		if ( empty( $addresses ) ) {
			global $wpdb;

			$query = $wpdb->prepare(
				$this->get_full_cache_checking_sql() . ' LIMIT %d',
				Toolset_Addon_Maps_Common::API_CALL_THROTTLE_MAX
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			$addresses = $wpdb->get_col( $query );
		} else {
			$addresses = array_slice( $addresses, 0, Toolset_Addon_Maps_Common::API_CALL_THROTTLE_MAX );
		}

		foreach ( $addresses as $address ) {
			Toolset_Addon_Maps_Common::get_coordinates( $address, true );
		}

		return count( $addresses );
	}

	/**
	 * Cron method to add missing cache entries. Unschedules itself if there is nothing more left to do.
	 *
	 * @since 2.0.9
	 */
	public function execute_missing_cache_entries_cron() {
		$new_entries_added = $this->get_missing_cache_entries();

		if ( 0 === $new_entries_added ) {
			$this->unschedule_missing_cache_entries_cron();
		} else {
			$this->schedule_missing_cache_entries_cron();
		}
	}

	/**
	 * Schedules the next run of missing cache entries cron.
	 *
	 * @since 2.0.9
	 */
	private function schedule_missing_cache_entries_cron() {
		if ( ! wp_next_scheduled( 'toolset_maps_cache_cron_hook' ) ) {
			wp_schedule_event( time(), 'hourly', 'toolset_maps_cache_cron_hook' );
		}
	}

	/**
	 * Unschedules the next run of missing cache entries cron.
	 *
	 * @since 2.0.9
	 */
	private function unschedule_missing_cache_entries_cron() {
		wp_unschedule_event(
			wp_next_scheduled( 'toolset_maps_cache_cron_hook' ),
			'toolset_maps_cache_cron_hook'
		);
	}

	/**
	 * Ajax call to delete an item from address cache data
	 *
	 * @since unknown
	 * @since 2.0 aware of possible database saved cache
	 */
	function delete_stored_addresses() {
		wpv_ajax_authenticate(
			'toolset_views_addon_maps_global',
			array(
				'parameter_source' => 'post',
				'type_of_death' => 'data',
			)
		);
		$keys = ( isset( $_POST['keys'] ) && is_array( $_POST['keys']  ) ) ? $_POST['keys'] : array();
		$keys = array_map( 'sanitize_text_field', $keys );

		if ( Toolset_Addon_Maps_Common::are_coordinates_migrated() ) {
			foreach ( $keys as $key ) {
				Toolset_Addon_Maps_Common::delete_cached_coordinates( $key );
			}
		} else {
			if ( ! empty( $keys ) ) {
				$coordinates_set = Toolset_Addon_Maps_Common::get_stored_coordinates();
				$save_after      = false;
				foreach ( $keys as $key ) {
					if ( isset( $coordinates_set[ $key ] ) ) {
						unset( $coordinates_set[ $key ] );
						$save_after = true;
					}
				}
				if ( $save_after ) {
					Toolset_Addon_Maps_Common::save_stored_coordinates( $coordinates_set );
				}
			}
		}
		wp_send_json_success();
	}

	function update_counters() {
		wpv_ajax_authenticate( 'toolset_views_addon_maps_dialogs', array( 'parameter_source' => 'post', 'type_of_death' => 'data' ) );
		$update = false;
		$update_data = array();
		if (
			isset( $_POST['map_counter'] )
			&& intval( $_POST['map_counter'] > 0 )
		) {
			$update = true;
			$update_data['map_counter'] = intval( $_POST['map_counter'] );
		}
		if (
			isset( $_POST['marker_counter'] )
			&& intval( $_POST['marker_counter'] > 0 )
		) {
			$update = true;
			$update_data['marker_counter'] = intval( $_POST['marker_counter'] );
		}
		if ( $update ) {
			$saved_options = apply_filters( 'toolset_filter_toolset_maps_get_options', array() );
			foreach ( $update_data as $key => $value ) {
				$saved_options[ $key ] = $value;
			}
			do_action( 'toolset_filter_toolset_maps_update_options', $saved_options );
		}
		wp_send_json_success();
	}

	function map_render_shortcode( $atts, $content='' ) {
        $map_defaults = array_merge(
			Toolset_Addon_Maps_Common::$map_defaults,
            array(
				'map_id'    => '',
				'debug'		=> 'false'
            )
        );

		$map_atts = shortcode_atts( $map_defaults, $atts );

		$map_id = $map_atts['map_id'];
		$map_width = $map_atts['map_width'];
		$map_height = $map_atts['map_height'];
        $cluster = $map_atts['cluster'];
        $debug = $map_atts['debug'];
        $style_json = $map_atts['style_json'];
        $general_zoom = $map_atts['general_zoom'];
        $general_center_lat = $map_atts['general_center_lat'];
        $general_center_lon = $map_atts['general_center_lon'];
        $fitbounds = $map_atts['fitbounds'];
        $single_zoom = $map_atts['single_zoom'];
        $single_center = $map_atts['single_center'];
        $map_type = $map_atts['map_type'];
        $show_layer_interests = $map_atts['show_layer_interests'];
        $marker_icon = $map_atts['marker_icon'];
        $marker_icon_hover = $map_atts['marker_icon_hover'];
        $draggable = $map_atts['draggable'];
        $scrollwheel = $map_atts['scrollwheel'];
        $double_click_zoom = $map_atts['double_click_zoom'];
        $map_type_control = $map_atts['map_type_control'];
        $full_screen_control = $map_atts['full_screen_control'];
        $zoom_control = $map_atts['zoom_control'];
        $street_view_control = $map_atts['street_view_control'];
        $background_color = $map_atts['background_color'];
        $cluster_grid_size = $map_atts['cluster_grid_size'];
        $cluster_max_zoom = $map_atts['cluster_max_zoom'];
        $cluster_click_zoom = $map_atts['cluster_click_zoom'];
        $cluster_min_size = $map_atts['cluster_min_size'];
        $spiderfy = $map_atts['spiderfy'];

		$return = '';

		

		if ( empty( $map_id ) ) {
			if ( $debug == 'true' ) {
				$logging_string = "####################<br />Map data<br />------------"
					. "<br />Error: empty map_id attribute"
					. "<br />####################<br />";
				$return .= $logging_string;
			}
			return $return;
		}

		if ( preg_match( "/^[0-9.]+$/", $map_width ) ) {
			$map_width .= 'px';
		}

		if ( preg_match( "/^[0-9.]+$/", $map_height ) ) {
			$map_height .= 'px';
		}

		$map_id = $this->get_unique_map_id( $map_id );

		if ( ! wp_script_is( 'views-addon-maps-script' ) ) {
			wp_enqueue_script( 'views-addon-maps-script' );
			Toolset_Addon_Maps_Common::maybe_enqueue_azure_css();
		}

		if ( $cluster == 'on' ) {
			$this->enqueue_marker_clusterer_script = true;
			if ( ! wp_script_is( 'marker-clusterer-script' ) ) {
				wp_enqueue_script( 'marker-clusterer-script' );
			}
		}

		if ( 'on' == $spiderfy ) {
		    if ( ! wp_script_is( 'overlapping-marker-spiderfier' ) ) {
		        wp_enqueue_script( 'overlapping-marker-spiderfier' );
            }
        }

		// Allow map JSON style to be changed programatically
        $style_json = apply_filters( 'wpv_map_json_style', $style_json, $map_id );

		$return = Toolset_Addon_Maps_Common::render_map(
			$map_id,
			array(
				'map_width'				=> $map_width,
				'map_height'			=> $map_height,
				'general_zoom'			=> $general_zoom,
				'general_center_lat'	=> $general_center_lat,
				'general_center_lon'	=> $general_center_lon,
				'fitbounds'				=> $fitbounds,
				'single_zoom'			=> $single_zoom,
				'single_center'			=> $single_center,
				'map_type'				=> $map_type,
				'show_layer_interests'	=> $show_layer_interests,
				'marker_icon'			=> $marker_icon,
				'marker_icon_hover'		=> $marker_icon_hover,
				'draggable'				=> $draggable,
				'scrollwheel'			=> $scrollwheel,
				'double_click_zoom'		=> $double_click_zoom,
				'map_type_control'		=> $map_type_control,
				'full_screen_control'	=> $full_screen_control,
				'zoom_control'			=> $zoom_control,
				'street_view_control'	=> $street_view_control,
				'background_color'		=> $background_color,
				'cluster'				=> $cluster,
				'cluster_grid_size'		=> $cluster_grid_size,
				'cluster_max_zoom'		=> $cluster_max_zoom,
				'cluster_click_zoom'	=> $cluster_click_zoom,
				'cluster_min_size'		=> $cluster_min_size,
                'style_json'            => $style_json,
                'spiderfy'              => $spiderfy,
                'street_view'           => $map_atts['street_view'],
                'marker_id'             => $map_atts['marker_id'],
                'location'              => $map_atts['location'],
                'address'               => $map_atts['address'],
				'heading'               => $map_atts['heading'],
				'pitch'                 => $map_atts['pitch'],
			),
            $content
		);

		if ( $debug == 'true' ) {
			$logging_string = "####################<br />Map data<br />------------"
				. "<br />Original attributes: "
				. "<code><pre>" . print_r( $atts, true ) . "</pre></code>"
				. "Used IDs: "
				. "<br />* map_id: " . $map_id
				. "<br />####################<br />";
			$return .= $logging_string;
		}

		return $return;
	}

	function marker_shortcode( $atts, $content = null ) {
        $marker_atts = shortcode_atts(
            array(
                'map_id'			=> '',
                'marker_id'			=> '',
                'marker_title'		=> '',
                'marker_field'		=> '',
                'marker_termmeta'	=> '',
                'marker_usermeta'	=> '',
                'lat'				=> '',
                'lon'				=> '',
                'address'			=> '',
                'marker_icon'		=> '',
                'marker_icon_hover'	=> '',
				'id'				=> '',
				'item'              => '',
                'debug'				=> 'false',
                'current_visitor_location' => '',
                'map_render'        => 'immediate',
                'street_view'       => 'no'
            ),
            $atts
		);

		$debug = $marker_atts['debug'];
		$lat = $marker_atts['lat'];
		$lon = $marker_atts['lon'];
		$address = $marker_atts['address'];
		$marker_field = $marker_atts['marker_field'];
		$marker_usermeta = $marker_atts['marker_usermeta'];
		$marker_termmeta = $marker_atts['marker_termmeta'];
		$current_visitor_location = $marker_atts['current_visitor_location'];
		$map_render = $marker_atts['map_render'];
		$marker_title = $marker_atts['marker_title'];
		$marker_icon = $marker_atts['marker_icon'];
		$marker_icon_hover = $marker_atts['marker_icon_hover'];
		$map_id = $marker_atts['map_id'];
		$marker_id = $marker_atts['marker_id'];
		$id = $marker_atts['id'];
		$item = $marker_atts['item'];
		$street_view = $marker_atts['street_view'];

		if ( ! empty( $id ) ) {
			$item = $id;
		}

		$return = '';
		$markers_array = array();

		

		if ( empty( $map_id ) ) {
			if ( $debug == 'true' ) {
				$logging_string = "####################<br />Marker data<br />------------"
					. "<br />Error: empty map_id attribute"
					. "<br />####################<br />";
				$return .= $logging_string;
			}
			return $return;
		}

		$map_id = $this->get_corrected_map_id( $map_id );

		// First, the case where lat and lon attributes were passed
		// Then, a custom address was used or a custom field was selected: get the address data and take care of multiple values
		if (
			$lat != ''
			&& $lon != ''
		) {
			$markers_array[] = array(
				'lat'	=> $lat,
				'lon'	=> $lon
			);
		} else if (
			$address != ''
			|| $marker_field != ''
			|| $marker_usermeta != ''
			|| $marker_termmeta != ''
		) {
			$addresss_array = array();
			if ( $address != '' ) {
				$addresss_array[] = $address;
			} else if ( $marker_field != '' ) {
				if (
					! empty( $item )
					&& class_exists( 'Toolset_Relationship_Service' )
				) {
					// Try to load the new M2M "item" attribute selector
					// It will take care of the "id" attribute too if needed
					$relationship_service = new Toolset_Relationship_Service();
					$attr_item_chain = new Toolset_Shortcode_Attr_Item_M2M(
						new Toolset_Shortcode_Attr_Item_Legacy(
							new Toolset_Shortcode_Attr_Item_Id(),
							$relationship_service
						),
						$relationship_service
					);
					if ( $item_id = $attr_item_chain->get( $marker_atts ) ) {
						$item = get_post( $item_id );
						if ( $item ) {
							$marker_id = ( empty( $marker_id ) ) ? $item->ID : $marker_id;
							$marker_title = ( empty( $marker_title ) ) ? $item->post_title : $marker_title;
							$addresss_array = get_post_meta( $item->ID, $marker_field );
						}
					}
				} else {
					// Use the legacy post selector based on the "id" attribute
					if ( class_exists( 'WPV_wpcf_switch_post_from_attr_id' ) ) {
						$post_id_atts = new WPV_wpcf_switch_post_from_attr_id( $atts );
					}
					global $post;
					if ( ! empty( $post ) ) {
						$marker_id = ( empty( $marker_id ) ) ? $post->ID : $marker_id;
						$marker_title = ( empty( $marker_title ) ) ? $post->post_title : $marker_title;
						$addresss_array = get_post_meta( $post->ID, $marker_field );
					}
				}
			} else if ( $marker_termmeta != '' ) {
				$marker_term = false;
				if ( empty( $item ) ) {
					global $WP_Views;
					if ( isset( $WP_Views->taxonomy_data['term'] ) ) {
						$marker_term = $WP_Views->taxonomy_data['term'];
					}
				} else {
					$marker_term = get_term( $item );
				}
				if ( $marker_term ) {
					$marker_id = ( empty( $marker_id ) ) ? $marker_term->term_id : $marker_id;
					$marker_title = ( empty( $marker_title ) ) ? $marker_term->name : $marker_title;
					$addresss_array = get_term_meta( $marker_term->term_id, $marker_termmeta );
				}
			} else if ( $marker_usermeta != '' ) {
				$marker_user = false;
				if ( empty( $item ) ) {
					global $WP_Views;
					if ( isset( $WP_Views->users_data['term'] ) ) {
						$marker_user = $WP_Views->users_data['term'];
					} else {
						if ( is_user_logged_in() ) {
							global $current_user;
							$marker_user = $current_user;
						}
					}
				} else {
					switch ( $item ) {
						case '$author':
							global $post;
							$marker_user = get_user_by( 'id', $post->post_author );
							break;
						case '$current':
							if ( is_user_logged_in() ) {
								global $current_user;
								$marker_user = $current_user;
							}
							break;
						default:
							$marker_user = get_user_by( 'id', $item );
							break;
					}
				}
				if ( $marker_user ) {
					$marker_id = ( empty( $marker_id ) ) ? $marker_user->ID : $marker_id;
					$marker_title = ( empty( $marker_title ) ) ? $marker_user->user_nicename : $marker_title;
					$addresss_array = get_user_meta( $marker_user->ID, $marker_usermeta );
				}
			}
			foreach ( $addresss_array as $addresss_candidate ) {
				$addresss_candidate_data = Toolset_Addon_Maps_Common::get_coordinates( $addresss_candidate );
				if ( is_array( $addresss_candidate_data ) ) {
					$markers_array[] = array(
						'lat'	=> $addresss_candidate_data['lat'],
						'lon'	=> $addresss_candidate_data['lon']
					);
				} else {
					if ( $debug == 'true' ) {
						$logging_string = "####################<br />Marker data<br />------------"
							. "<br />Marker address: " . $addresss_candidate
							. "<br />Error connecting the Google Maps API: " . $addresss_candidate_data
							. "<br />####################<br />";
						$return .= $logging_string;
					}
				}
			}
		} elseif ($current_visitor_location != '') {
			// Special case when we need to get coordinates from browser - collect_map_data method will recognize
			// and process it.
			$markers_array[] = array(
				'lat' => 'geo',
				'lon' => $map_render
			);
		} else {
			if ( $debug == 'true' ) {
				$logging_string = "####################<br />Marker data<br />------------"
					. "<br />Marker source unknown"
					. "<br />####################<br />";
				$return .= $logging_string;
			}
		}

		foreach ( $markers_array as $marker_candidate ) {
			$marker_id_corrected = $this->get_unique_marker_id( $map_id, $marker_id );
			$return .= Toolset_Addon_Maps_Common::render_marker(
				$map_id,
				array(
					'id'			=> $marker_id_corrected,
					'title'			=> $marker_title,
					'lat'			=> $marker_candidate['lat'],
					'lon'			=> $marker_candidate['lon'],
					'icon'			=> $marker_icon,
					'icon_hover'	=> $marker_icon_hover,
                    'street_view'   => $street_view
				),
				$content
			);
			if ( $debug == 'true' ) {
				$used_atts = array(
					'map_id' => $map_id,
					'marker_id' => $marker_id_corrected,
					'marker_lat' => $marker_candidate['lat'],
					'marker_lon' => $marker_candidate['lon'],
				);
				$logging_string = "####################<br />Marker data<br />------------"
					. "<br />Original attributes: "
					. "<code><pre>" . print_r( $atts, true ) . "</pre></code>"
					. "Used attributes: "
					. "<code><pre>" . print_r( $used_atts, true ) . "</pre></code>"
					. "####################<br />";
				$return .= $logging_string;
			}
		}

		return $return;
	}

	function get_unique_map_id( $map_id ) {
		$used_map_ids = Toolset_Addon_Maps_Common::$used_map_ids;
		$map_id_corrected = $map_id;
		$loop_counter = 0;
		while ( in_array( $map_id_corrected, $used_map_ids ) ) {
			$loop_counter = $loop_counter + 1;
			$map_id_corrected = $map_id . '-' . $loop_counter;
		}

		if ( $map_id_corrected !== $map_id ) {
		    $this->keep_corrected_map_id( $map_id, $map_id_corrected );
		}

		return $map_id_corrected;
	}

	/**
     * When a map id is corrected (unique), keep association with old id, so it can be picked up by markers.
     * @since 1.4
	 * @param string $map_id
	 * @param string $map_id_corrected
	 */
	protected function keep_corrected_map_id( $map_id, $map_id_corrected ) {
        self::$corrected_map_ids[$map_id] = $map_id_corrected;
	}

	/**
     * If a map id has been corrected, return the corrected one. Otherwise, return map id as is.
	 * @param string $map_id
	 *
	 * @return string
	 */
	protected function get_corrected_map_id( $map_id ) {
        return isset( self::$corrected_map_ids[$map_id] )
            ? self::$corrected_map_ids[$map_id]
            : $map_id;
	}

	function get_unique_marker_id( $map_id, $marker_id ) {
		$used_marker_ids = Toolset_Addon_Maps_Common::$used_marker_ids;
		if ( ! isset( $used_marker_ids[ $map_id ] ) ) {
			$used_marker_ids[ $map_id ] = array();
		}
		$marker_id_corrected = $marker_id;
		$loop_counter = 0;
		while ( in_array( $marker_id_corrected, $used_marker_ids[ $map_id ] ) ) {
			$loop_counter = $loop_counter + 1;
			$marker_id_corrected = $marker_id . '-' . $loop_counter;
		}
		return $marker_id_corrected;
	}

	function run_shortcodes( $content ) {
		if ( strpos( $content, '[wpv-map' ) !== false ) {
			global $shortcode_tags;
			// Back up current registered shortcodes and clear them all out
			$orig_shortcode_tags = $shortcode_tags;
			remove_all_shortcodes();
			add_shortcode( 'wpv-map-render', array( $this, 'map_render_shortcode' ) );
			add_shortcode( 'wpv-map-marker', array( $this, 'marker_shortcode' ) );
			$content = do_shortcode( $content );
			$shortcode_tags = $orig_shortcode_tags;
		}
		return $content;
	}

	function shortcodes_register_data( $views_shortcodes ) {
		$views_shortcodes['wpv-map-render'] = array(
			'callback' => array( $this, 'shortcodes_get_map_render_data' )
		);
		$views_shortcodes['wpv-geolocation'] = array(
		    'callback' => array( $this, 'shortcodes_get_geolocation_data' )
        );

		return $views_shortcodes;
	}

	function get_marker_options() {
		$saved_options = apply_filters( 'toolset_filter_toolset_maps_get_options', array() );

        // Set API-specific default marker
        $default_marker = TOOLSET_ADDON_MAPS_URL . '/resources/images/markers/default.png';
        switch($this->api_used) {
            case Toolset_Addon_Maps_Common::API_AZURE:
                $api_specific_marker = TOOLSET_ADDON_MAPS_URL . '/resources/images/markers/default-azure.png';
                $default_marker = file_exists(TOOLSET_ADDON_MAPS_PATH . '/resources/images/markers/default-azure.png')
                    ? $api_specific_marker
                    : $default_marker;
                break;

            case Toolset_Addon_Maps_Common::API_OSM:
                $api_specific_marker = TOOLSET_ADDON_MAPS_URL . '/resources/images/markers/default-osm.png';
                $default_marker = file_exists(TOOLSET_ADDON_MAPS_PATH . '/resources/images/markers/default-osm.png')
                    ? $api_specific_marker
                    : $default_marker;
                break;
        }
		$marker_options = array(
            '' => '<span class="wpv-icon-img js-wpv-icon-img" 
                  data-img="' . $default_marker . '" 
                  style="background-image:url(' . $default_marker . ');"></span>',
		);

		$marker_builtin = Toolset_Addon_Maps_Common::get_marker_icons();
		foreach ( $marker_builtin as $bimg ) {
			$marker_options[$bimg] = '<span class="wpv-icon-img js-wpv-icon-img" data-img="' . esc_attr( $bimg ) . '" style="background-image:url(' . esc_attr( $bimg ) . ');"></span>';
		}
		foreach ( $saved_options['marker_images'] as $img ) {
		    // Since 1.4.2, we are using relative links for uploaded markers, but we may have markers uploaded before
            // update, so check and decide what to do.
		    if ( $this->is_link_relative( $img ) ) {
		        $img = $this->make_relative_upload_link_schemaless( $img );
            }
			$marker_options[$img] = '<span class="wpv-icon-img js-wpv-icon-img" data-img="' . esc_attr( $img ) . '" style="background-image:url(' . esc_attr( $img ) . ');"></span>';
		}
		return $marker_options;
	}

	/**
     * A link is relative if it starts with a slash (and not with h).
     *
	 * @param string $link
	 *
	 * @return bool
     *
     * @since 1.4.2
	 */
	protected function is_link_relative( $link ) {
        return substr( $link, 0, 1 ) === '/';
	}

	/**
     * Makes an option array for styles select
	 * @return array
	 */
	protected function get_style_options() {
		return array_merge(
		    $this->get_preloaded_json_styles(),
            array_flip( $this->get_uploaded_json_styles() )
        );
    }

	/**
     * Makes an option array for styles select which also contains the special 'Default' style
	 * @return array
	 */
    protected function get_style_options_with_default() {
        return array_merge(
			array( '' => __( 'Default', 'toolset-maps' ) ),
            $this->get_style_options()
        );
    }

	/**
     * Get uploaded JSON files as key - value. Turn saved relative URLs into full ones for this site.
	 * @return array
	 */
    protected function get_uploaded_json_styles() {
	    return array_map(
            array( $this, 'make_relative_upload_link_full' ),
            $this->get_saved_option( 'map_style_files' )
        );
    }

	/**
     * Usually we need just one option from the saved options array
	 * @param string $key
	 * @return mixed
	 */
    protected function get_saved_option( $key ) {
		$saved_options = apply_filters( 'toolset_filter_toolset_maps_get_options', array() );

		if ( isset( $saved_options[$key] ) ) {
			return $saved_options[ $key ];
		} else {
		    return null;
        }
    }

	/**
	 * @param string $key
	 * @param mixed $value
	 */
    protected function set_saved_option( $key, $value ) {
		$saved_options = apply_filters( 'toolset_filter_toolset_maps_get_options', array() );
		$saved_options[$key] = $value;
		do_action( 'toolset_filter_toolset_maps_update_options', $saved_options );
    }

	/**
     * Massage preloaded JSON file names into format for options
	 * @return array
	 */
    protected function get_preloaded_json_styles() {
		$styles_options = array();

	    foreach ( $this->get_preloaded_json_style_names() as $style_name ) {
			$file_location = TOOLSET_ADDON_MAPS_URL . "/resources/json/$style_name.json";
			$styles_options[$file_location] = $style_name;
		}

		return $styles_options;
    }

	/**
     * Style names = style filenames (without extension)
	 * @return array
	 */
    protected function get_preloaded_json_style_names() {
	    return array_map(
            function ( $path ) {
                return pathinfo( $path, PATHINFO_FILENAME );
            },
            $this->scan_preloaded_json_style_files()
        );
    }

	/**
     * Scans for available JSON style files in the resources/json folder, so we can just add and remove files without
     * changing the code.
	 * @return array
	 */
    protected function scan_preloaded_json_style_files() {
	    return array_diff(
            scandir(TOOLSET_ADDON_MAPS_PATH . '/resources/json'),
            array( '.', '..' )
        );
    }

	function get_missing_api_key_warning() {
		$return = '';
		$return .= '<div class="toolset-alert toolset-alert-wrning">';
		$return .= '<p>';
		$return .= __( 'An API key is <strong>required</strong> to use Toolset Maps.', 'toolset-maps' );
		$return .= '</p>';
		$return .= '<p>';
		$analytics_strings = array(
			'utm_source'	=> 'toolsetmapsplugin',
			'utm_campaign'	=> 'toolsetmaps',
			'utm_medium'	=> 'views-integration-settings-for-api-key',
			'utm_term'		=> 'our documentation'
		);
		$return .= sprintf(
			__( 'You can find more information in %1$sour documentation%2$s.', 'toolset-maps' ),
			'<a href="' . Toolset_Addon_Maps_Common::get_documentation_promotional_link( array( 'query' => $analytics_strings, 'anchor' => 'api-key' ), TOOLSET_ADDON_MAPS_DOC_LINK ) . '" target="_blank">',
			'</a>'
		);
		$return .= '</p>';
		$return .= '</div>';
		return $return;
	}

	/**
     * @since 1.5
     *
	 * @return bool
	 */
	public function is_api_key_set() {
		$api_used = apply_filters( 'toolset_maps_get_api_used', '' );

		if ( Toolset_Addon_Maps_Common::API_GOOGLE === $api_used ) {
            // Google
			$maps_api_key = apply_filters( 'toolset_filter_toolset_maps_get_api_key', '' );
            return ! empty( $maps_api_key );

        } elseif ( Toolset_Addon_Maps_Common::API_AZURE === $api_used ) {
            // Azure
            $maps_api_key = apply_filters( 'toolset_filter_toolset_maps_get_azure_api_key', '' );
            return ! empty( $maps_api_key );

		} else {
            // Open Street Maps or unknown - no key required
            return true;
		}
	}

	/**
     * @since 1.5
     *
	 * @param array $data
	 *
	 * @return array
	 */
	protected function shortcodes_render_missing_key_dialog( array $data ) {
		$data['attributes']['map-api-key'] = array(
			'label'			=> __('Google Maps API', 'toolset-maps'),
			'header'		=> __('Missing an API key', 'toolset-maps'),
			'fields'		=> array(
				'missing_api_key' => array(
					'label'			=> __( 'Missing an API key', 'toolset-maps'),
					'type'			=> 'callback',
					'callback'		=> array( $this, 'get_missing_api_key_warning' )
				),
			)
		);
		return $data;
    }

	function shortcodes_get_map_render_data() {
		$can_manage_options = current_user_can( 'manage_options' );
		$data = array(
			'name'			=> __( 'Map', 'toolset-maps' ),
			'label'			=> __( 'Map', 'toolset-maps' ),
			'attributes'	=> array()
		);

        if ( ! $this->is_api_key_set() ) {
            return $this->shortcodes_render_missing_key_dialog( $data );
        }

		$data['attributes']['map-options'] = array(
			'label'			=> __('Map', 'toolset-maps'),
			'header'		=> __('Map', 'toolset-maps'),
			'fields'		=> array(
				'map_id' => array(
					'label'			=> __( 'Map ID', 'toolset-maps'),
					'type'			=> 'text',
					'default'		=> '',
					'required'		=> true,
					'description'	=> __( 'This is the map unique identifier, used to also add markers to this map.', 'toolset-maps' )
				),
				'map_width' => array(
					'label'			=> __( 'Map width', 'toolset-maps'),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder' 	=> '100%',
					'description' 	=> __('You can use percentages or units. Raw numbers default to pixels. Percentages depend on the size of the parent container.','toolset-maps'),
				),
				'map_height' => array(
					'label'			=> __( 'Map height', 'toolset-maps'),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> '250px',
					'description'	=> __('You can use percentages or units. Raw numbers default to pixels. Percentages depend on the size of the parent container.','toolset-maps'),
				),
			),
		);

		$data['attributes']['zoom-options'] = array(
			'label'			=> __('Map zoom and center', 'toolset-maps'),
			'header'		=> __('Map zoom and center', 'toolset-maps'),
			'description'	=> __( 'The zoom levels can take a value from 0 up to a number that depends on the displayed location. It should be safe to use any number below 18. Zoom and center options can be combined in several ways.', 'toolset-maps' ),
			'fields'		=> array(
				'fitbounds' => array(
					'label'			=> __( 'Adjust automatically', 'toolset-maps'),
					'type'			=> 'radio',
					'options'		=> array(
						'on'		=> __('Adjust automatically to show all markers at once', 'toolset-maps'),
						'off'		=> __('Set zoom center and center manually', 'toolset-maps'),
					),
					'default'		=> 'on',
					'description'	=> __( 'Whether to set the highest zoom level and the best map center that can show all markers at once.', 'toolset-maps' ),
				),
				'general_zoom' => array(
					'label'			=> __( 'Use this zoom level when there is more than one marker', 'toolset-maps'),
					'type'			=> 'number',
					'default'		=> '5',
				),
				'single_zoom' => array(
					'label'			=> __( 'Use this zoom level when there is only one marker', 'toolset-maps'),
					'type'			=> 'number',
					'default'		=> '14',
				),
				'general_center_lat' => array(
					'label'			=> __( 'Coordinates for the map center', 'toolset-maps'),
					'type'			=> 'text',
					'default'		=> '0',
					'description'	=> __('Latitude for the center of the map.','toolset-maps'),
				),
				'general_center_lon' => array(
					'label'			=> __( 'General centering - longitude', 'toolset-maps'),
					'type'			=> 'text',
					'default'		=> '0',
					'description'	=> __('Longitude for the center of the map.','toolset-maps'),
				),
				'single_center' => array(
					'label'			=> __( 'Map center with just one marker', 'toolset-maps'),
					'type'			=> 'radio',
					'options'		=> array(
						'on'		=> __('Center the map in the marker when there is only one', 'toolset-maps'),
						'off'		=> __('Force the map center setting even with just one marker', 'toolset-maps'),
					),
					'default'		=> 'on',
					'description'	=> __( 'Will override the center coordinates set above.', 'toolset-maps' )
				),
			),
		);

		$data['attributes']['marker-clustering'] = array(
			'label'			=> __('Marker clustering & spiderfying', 'toolset-maps'),
			'header'		=> __('Marker clustering & spiderfying', 'toolset-maps'),
			'fields'		=> array(
				'cluster' => array(
					'label'			=> __( 'Cluster markers', 'toolset-maps'),
					'type'			=> 'radio',
					'options'		=> array(
						'on'		=> __(
                            'When several markers are close, display as one \'cluster\' marker',
                            'toolset-maps'
                        ),
						'off'		=> __('Always show each marker separately', 'toolset-maps'),
					),
					'default'		=> 'off',
				),
				'cluster_click_zoom' => array(
					'label'			=> __( 'Clicking on a cluster icon', 'toolset-maps'),
					'type'			=> 'radio',
					'options'		=> array(
						'on'		=> __('Zoom the map when clicking on a cluster icon', 'toolset-maps'),
						'off'		=> __('Do nothing', 'toolset-maps'),
					),
					'default'		=> 'on',
				),
                'spiderfy' => array(
                    'label'         => __('Spiderfy overlapping markers', 'toolset-maps'),
                    'type'          => 'radio',
                    'options'       => array(
                        'on'        => __('Spiderfy overlapping markers', 'toolset-maps'),
                        'off'       => __('Do nothing', 'toolset-maps')
                    ),
                    'default'       => 'off',
                    'description'   => __(
                        'If enabled, marker pins that overlap each other spring apart gracefully when you click them.',
                        'toolset-maps'
                    )
                )
			),
		);

		$data['attributes']['extra-options'] = array(
			'label'			=> __('Map interaction', 'toolset-maps'),
			'header'		=> __('Map interaction', 'toolset-maps'),
			'fields'		=> array(
				'draggable' => array(
					'label'			=> __( 'Dragging a map', 'toolset-maps'),
					'type'			=> 'radio',
					'options'		=> array(
						'on'		=> __('Move inside the map by dragging it', 'toolset-maps'),
						'off'		=> __('Do nothing', 'toolset-maps'),
					),
					'default'		=> 'on',
				),
				'scrollwheel' => array(
					'label'			=> __( 'Scroll inside a map', 'toolset-maps'),
					'type'			=> 'radio',
					'options'		=> array(
						'on'		=> __('Scroll inside the map to zoom it', 'toolset-maps'),
						'off'		=> __('Do nothing', 'toolset-maps'),
					),
					'default'		=> 'on',
				),
				'double_click_zoom' => array(
					'label'			=> __( 'Double click on on a map', 'toolset-maps'),
					'type'			=> 'radio',
					'options'		=> array(
						'on'		=> __('Double click on the map to zoom it', 'toolset-maps'),
						'off'		=> __('Do nothing', 'toolset-maps'),
					),
					'default'		=> 'on',
				),
			),
		);

		$data['attributes']['control-options'] = array(
			'label'			=> __('Map controls and types', 'toolset-maps'),
			'header'		=> __('Map controls and types', 'toolset-maps'),
			'fields'		=> array(
                'map_type' => array(
                    'label'         => __('Map type', 'toolset-maps'),
                    'type'          => 'radio',
                    'options'       => array(
                        'roadmap'   => __('Display the default road map view', 'toolset-maps'),
                        'satellite' => __('Display Google Earth satellite images', 'toolset-maps'),
                        'hybrid'    => __('Display a mixture of normal and satellite views', 'toolset-maps'),
                        'terrain'   => __('Display a physical map based on terrain information', 'toolset-maps')
                    ),
                    'default'       => 'roadmap'
                ),
				'map_type_control' => array(
					'label'			=> __( 'Map type control', 'toolset-maps'),
					'type'			=> 'radio',
					'options'		=> array(
						'on'		=> __('Display the map type control to switch between roadmap, satellite or terrain views', 'toolset-maps'),
						'off'		=> __('Do not show the map type control', 'toolset-maps'),
					),
					'default'		=> 'on',
				),
				'zoom_control' => array(
					'label'			=> __( 'Zoom controls', 'toolset-maps'),
					'type'			=> 'radio',
					'options'		=> array(
						'on'		=> __('Display zoom controls to zoom in and out', 'toolset-maps'),
						'off'		=> __('Do not show zoom controls', 'toolset-maps'),
					),
					'default'		=> 'on',
				),
				'street_view_control' => array(
					'label'			=> __( 'Street view control', 'toolset-maps'),
					'type'			=> 'radio',
					'options'		=> array(
						'on'		=> __('Display a street view control', 'toolset-maps'),
						'off'		=> __('Do not show a street view control', 'toolset-maps'),
					),
					'default'		=> 'on',
				),
			),
		);

		$marker_options = $this->get_marker_options();
		$styling_fields = array();
		if (
			count( $marker_options ) > 1
			|| $can_manage_options
		) {
			$styling_fields['marker_icon'] = array(
				'label'		=> __( 'Icon for markers in this map', 'toolset-maps'),
				'type'		=> 'radiohtml',
				'options'	=> $marker_options,
				'default'	=> '',
			);
			$styling_fields['marker_icon_hover'] = array(
				'label'		=> __( 'Icon when hovering markers on this map', 'toolset-maps'),
				'type'		=> 'radiohtml',
				'options'	=> $marker_options,
				'default'	=> '',
			);
		}

		$styling_fields['background_color'] = array(
			'label'			=> __( 'Background color of this map', 'toolset-maps'),
			'type'			=> 'text',
			'default'		=> '#e5e3df',
			'description'	=> __('Will only be visible when the map needs to redraw a section.','toolset-maps'),
		);

		$styling_fields['style_json'] = array(
		    'label'         => __( 'Map style', 'toolset-maps' ),
            'type'          => 'select',
            'options'       => $this->get_style_options_with_default(),
            'default'       => ''
        );

		$data['attributes']['style-options'] = array(
			'label'		=> __('Map style and marker icon', 'toolset-maps'),
			'header'	=> __('Map style and marker icon', 'toolset-maps'),
			'fields'	=> $styling_fields,
            'content'   => array(
				'label'			=> __( 'Text to show while the map is loading', 'toolset-maps'),
				'type'			=> 'textarea',
				'default'		=> '',
				'description'	=> __( 'This text will only appear before the map is shown. You can use HTML here.', 'toolset-maps' ),
            )
		);

		if ( $can_manage_options ) {
			$analytics_strings = array(
				'utm_source'	=> 'toolsetmapsplugin',
				'utm_campaign'	=> 'toolsetmaps',
				'utm_medium'	=> 'map-shortcode-dialog',
				'utm_term'		=> 'Learn about using custom markers'
			);
			$data['attributes']['style-options']['documentation'] = sprintf(
				__( '%1$sLearn about using custom markers »%2$s', 'toolset-maps' ),
				'<a href="' . Toolset_Addon_Maps_Common::get_documentation_promotional_link( array( 'query' => $analytics_strings, 'anchor' => 'marker-icon' ), TOOLSET_ADDON_MAPS_DOC_LINK . 'displaying-markers-on-google-maps/' ) . '" target="_blank" title="' . esc_attr( __( 'Learn about using custom markers', 'toolset-maps' ) ) . '">',
				'</a>'
			);
		}

		// Street view tab
		$data['attributes']['street-view'] = array(
			'label' => __('Street View', 'toolset-maps'),
			'header' => __('Street View', 'toolset-maps'),
			'fields' => array(
				'street_view' => array(
					'label' => __('Automatically open Street View when this map loads', 'toolset-maps'),
					'type'  => 'radio',
					'options'	=> array(
						'off'	=> __('No', 'toolset-maps'),
						'on'	=> __('Yes', 'toolset-maps')
					),
					'default'	=> 'off'
				)
			)
		);

		return $data;
	}

	/**
	 * Under assumption that site settings are not wrong, answers if frontend is served over https
	 * @return bool
	 */
	public function is_frontend_served_over_https(){
		return ( parse_url( get_home_url(), PHP_URL_SCHEME ) === 'https' );
	}

	/**
     * Registers wpv-geolocation shortcode in Views shortcode creation wizard.
     *
     * @since 1.5
     *
	 * @return array
	 */
	public function shortcodes_get_geolocation_data() {
		$data = array(
			'name'			=> __( 'Geolocation', 'toolset-maps' ),
			'label'			=> __( 'Geolocation', 'toolset-maps' ),
			'attributes'	=> array()
		);

		if ( ! $this->is_api_key_set() ) {
			return $this->shortcodes_render_missing_key_dialog( $data );
		}

		$data['attributes']['geolocation-options'] = array(
			'label' => __('Geolocation', 'toolset-maps'),
			'header' => __('Geolocation', 'toolset-maps'),
			'fields' => array(
				'message_when_missing' => array(
					'label' => __('Message to show before geolocation is obtained', 'toolset-maps'),
					'type' => 'text',
					'default' => __('Your location is needed to show this content.', 'toolset-maps'),
					'required' => false,
					'description'	=> __(
                        'Message to show while waiting for visitor to give permission for geolocation.',
                        'toolset-maps'
                    )
				)
            ),
			'content' => array(
				'label' => __( 'Content', 'toolset-maps' ),
				'type'	=> 'textarea',
				'description'	=> __(
					'All shortcodes rendered inside this one will have backend access to geolocation data.',
					'toolset-maps'
				)
            )
        );

        return $data;
	}

	function register_shortcodes_dialog_groups() {

		$group_id	= 'toolset-maps';
		$group_data	= array(
			'name'		=> 'Toolset Maps',
			'fields'	=> array()
		);
		$map_shortcodes = array(
			'wpv-map-render'	=> __( 'Map', 'toolset-maps' ),
            'marker'            => __( 'Marker', 'toolset-maps' ), // This one is just a placeholder for TC one.
            'wpv-geolocation'   => __( 'Geolocation', 'toolset-maps' )
		);

		foreach ( $map_shortcodes as $map_shortcode_slug => $map_shortcode_title ) {
		    $dialog_data = "{ shortcode:'$map_shortcode_slug', title:'$map_shortcode_title', params:{}, overrides:{} }";

			$group_data['fields'][ $map_shortcode_slug ] = array(
				'name'		=> $map_shortcode_title,
				'shortcode'	=> $map_shortcode_slug,
				'callback'	=> "WPViews.shortcodes_gui.wpv_insert_shortcode_dialog_open( $dialog_data )"
			);
		}

		$group_data['fields']['focus'] = array(
			'name'		=> __( '"Focus on marker" button', 'toolset-maps' ),
			'shortcode'	=> '',
			'callback'	=> "WPViews.addon_maps_dialogs.wpv_open_dialog('focus', '"
						   /* translators: this is the title of dialog that sets options for focusing map on a marker */
						   . esc_js( __( 'Map focus on marker', 'toolset-maps' ) )
						   . "')"
		);
		$group_data['fields']['restore'] = array(
			'name'		=> __( '"Zoom out" button', 'toolset-maps' ),
			'shortcode'	=> '',
			'callback'	=> "WPViews.addon_maps_dialogs.wpv_open_dialog('restore', '"
                             . esc_js( __( 'Map restore zoom', 'toolset-maps' ) ) . "')"
		);

		do_action( 'wpv_action_wpv_register_dialog_group', $group_id, $group_data );
	}

	/**
	 * @deprecated 1.5
	 */
	function suggest_post_field_name() {
		_doing_it_wrong(
			__FUNCTION__,
			__(
				'wpv_suggest_wpv_post_field_name is deprecated and should not be used. Use select2_suggest_meta instead.',
				'toolset-maps'
			),
			'1.5'
		);
		wp_send_json_error();
	}

	/**
	 * @deprecated 1.5
	 */
	function suggest_taxonomy_field_name() {
		_doing_it_wrong(
			__FUNCTION__,
			__(
				'wpv_suggest_wpv_taxonomy_field_name is deprecated and should not be used. Use select2_suggest_meta instead.',
				'toolset-maps'
			),
			'1.5'
		);
		wp_send_json_error();
	}

	/**
	 * @deprecated 1.5
	 */
	function suggest_user_field_name() {
		_doing_it_wrong(
			__FUNCTION__,
			__(
				'wp_ajax_wpv_suggest_wpv_user_field_name is deprecated and should not be used. Use select2_suggest_meta instead.',
				'toolset-maps'
			),
			'1.5'
		);
		wp_send_json_error();
	}

	function frontend_events_tab( $tabs, $query_type ) {
		if ( $query_type == 'posts' ) {
			$tabs['wpv-dialog-frontend-events-addon-maps-container'] = array(
				'title' => __( 'Google Maps', 'toolset-maps' )
			);
		}
		return $tabs;
	}

	function render_frontend_events_section( $query_type ) {
		if ( $query_type == 'posts' ) {
		?>
		<div id="wpv-dialog-frontend-events-addon-maps-container">
			<h2><?php _e( 'Google Maps events', 'toolset-maps' ); ?></h2>
			<p>
				<?php _e( 'The Google Maps addon triggers some events.', 'toolset-maps' ); ?>
			</p>
			<ul>
				<li>
					<label for="wpv-frontent-event-map-init-started">
						<input type="checkbox" id="wpv-frontent-event-map-init-started" value="1" class="js-wpv-frontend-event-gui" data-event="js_event_wpv_addon_maps_init_map_started" />
						<?php _e( 'The Google Map is going to be initiated', 'toolset-maps' ); ?>
					</label>
					<span class="wpv-helper-text"><?php _e( 'This happens when a map init starts', 'toolset-maps' ); ?></span>
				</li>
				<li>
					<label for="wpv-frontent-event-map-init-inited">
						<input type="checkbox" id="wpv-frontent-event-map-init-inited" value="1" class="js-wpv-frontend-event-gui" data-event="js_event_wpv_addon_maps_init_map_inited" />
						<?php _e( 'The Google Map was just initiated', 'toolset-maps' ); ?>
					</label>
					<span class="wpv-helper-text"><?php _e( 'This happens when a map is initiated but before the markers have been initiated', 'toolset-maps' ); ?></span>
				</li>
				<li>
					<label for="wpv-frontent-event-map-init-completed">
						<input type="checkbox" id="wpv-frontent-event-map-init-completed" value="1" class="js-wpv-frontend-event-gui" data-event="js_event_wpv_addon_maps_init_map_completed" />
						<?php _e( 'The Google Map was just completely initiated', 'toolset-maps' ); ?>
					</label>
					<span class="wpv-helper-text"><?php _e( 'This happens when a map reload is completely rendered including its markers', 'toolset-maps' ); ?></span>
				</li>
				<li>
					<label for="wpv-frontent-event-map-reload-started">
						<input type="checkbox" id="wpv-frontent-event-map-reload-started" value="1" class="js-wpv-frontend-event-gui" data-event="js_event_wpv_addon_maps_reload_map_started" />
						<?php _e( 'The Google Map is going to be reloaded', 'toolset-maps' ); ?>
					</label>
					<span class="wpv-helper-text"><?php _e( 'This happens when a map reload starts', 'toolset-maps' ); ?></span>
				</li>
				<li>
					<label for="wpv-frontent-event-map-reload-completed">
						<input type="checkbox" id="wpv-frontent-event-map-reload-completed" value="1" class="js-wpv-frontend-event-gui" data-event="js_event_wpv_addon_maps_reload_map_completed" />
						<?php _e( 'The Google Map was just reloaded', 'toolset-maps' ); ?>
					</label>
					<span class="wpv-helper-text"><?php _e( 'This happens when a map reload is completed', 'toolset-maps' ); ?></span>
				</li>
			</ul>
		</div>
		<?php
		}
	}

	function parametric_search_pretend_textfield_type( $field_properties ) {
		if (
			isset( $field_properties['type'] )
			&& $field_properties['type'] == TOOLSET_ADDON_MAPS_FIELD_TYPE
		) {
			$field_properties['type'] = 'textfield';
		}
		return $field_properties;
	}

	function toolset_maps_settings_link( $toolset_maps_settings_link ) {
		$toolset_maps_settings_link = admin_url( 'admin.php?page=views-settings&tab=addon_maps' );
		return $toolset_maps_settings_link;
	}
}

$Toolset_Addon_Maps_Views = new Toolset_Addon_Maps_Views();
