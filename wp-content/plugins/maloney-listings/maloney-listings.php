<?php
/**
 * Plugin Name: Maloney Affordable Listings
 * Plugin URI: 
 * Description: Comprehensive listing management system for condos and rentals
 * Version: 1.0.0
 * Author: Responsab LLC
 * Author URI: https://www.responsab.com
 * License: Proprietary - See LICENSE file
 * License URI: https://www.responsab.com
 * Text Domain: maloney-listings
 * 
 * Copyright (c) 2025 Responsab LLC. All Rights Reserved.
 * 
 * NOTICE: This plugin is proprietary software developed exclusively for 
 * use on https://www.maloneyaffordable.com/. Unauthorized use, copying, 
 * modification, or distribution is strictly prohibited. For licensing 
 * inquiries, contact Responsab LLC at https://www.responsab.com
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MALONEY_LISTINGS_VERSION', '1.0.0');
define('MALONEY_LISTINGS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MALONEY_LISTINGS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-post-types.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-taxonomies.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-custom-fields.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-frontend.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-admin.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-ajax.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-map.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-vacancy-notifications.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-geocoding.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-field-discovery.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-migration.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-toolset-helpers.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-toolset-integration.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-blocks.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-data-normalization.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-settings.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-template-migration.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-available-units-migration.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-available-units-fields.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-condo-listings-fields.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-condo-listings-migration.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-zip-code-extraction.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-toolset-template-blocks.php';
require_once MALONEY_LISTINGS_PLUGIN_DIR . 'includes/class-template-tools.php';

// Initialize plugin
class Maloney_Listings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Check if Toolset Types is required and active (after plugins are loaded)
        add_action('plugins_loaded', array($this, 'check_toolset_requirement'), 20);
        
        // Initialize post types
        new Maloney_Listings_Post_Types();
        
        // Initialize taxonomies
        new Maloney_Listings_Taxonomies();
        
        // Initialize custom fields
        new Maloney_Listings_Custom_Fields();
        
        // Initialize frontend
        new Maloney_Listings_Frontend();
        
        // Initialize admin
        new Maloney_Listings_Admin();
        // No inline visibility pills; rely on Toolset taxonomy visibility
        
        // Initialize Toolset integration
        new Maloney_Listings_Toolset_Integration();
        
        // Initialize AJAX handlers
        new Maloney_Listings_AJAX();
        
        // Initialize map functionality
        new Maloney_Listings_Map();
        
        // Initialize vacancy notifications
        new Maloney_Listings_Vacancy_Notifications();
        
        // Initialize geocoding
        new Maloney_Listings_Geocoding();
        
        // Initialize zip code extraction
        new Maloney_Listings_Zip_Code_Extraction();
        
        // Initialize shortcodes
        new Maloney_Listings_Shortcodes();
        
        // Initialize blocks
        new Maloney_Listings_Blocks();

        // Normalize/derive numeric fields used by filters
        new Maloney_Listings_Data_Normalization();
        
        // Initialize settings
        new Maloney_Listings_Settings();
        
        // Admin template tools
        // Template Tools (Beta) removed - using comprehensive Template Blocks page instead
        // if (is_admin()) { new Maloney_Listings_Template_Tools(); }
        
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Check if Toolset Types is required and show warning if not active
     * Runs after plugins_loaded to ensure Toolset Types has initialized
     */
    public function check_toolset_requirement() {
        // Toolset Types is required for custom fields and available units functionality
        // Check multiple ways to detect if Toolset Types is active
        $toolset_active = false;
        
        // Method 1: Check if function exists (most reliable)
        if (function_exists('wpcf_admin_fields_get_fields')) {
            $toolset_active = true;
        }
        
        // Method 2: Check if class exists
        if (!$toolset_active && class_exists('WPCF_Loader')) {
            $toolset_active = true;
        }
        
        // Method 3: Check if plugin is active using WordPress function
        if (!$toolset_active && function_exists('is_plugin_active')) {
            // Include plugin.php if not already included
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            if (is_plugin_active('types/wpcf.php') || is_plugin_active('toolset-types/types/wpcf.php')) {
                $toolset_active = true;
            }
        }
        
        // Method 4: Check if constant is defined
        if (!$toolset_active && defined('WPCF_VERSION')) {
            $toolset_active = true;
        }
        
        // Method 5: Check if Toolset Types classes are loaded
        if (!$toolset_active && (class_exists('Types_Main') || class_exists('Toolset_Types_Main'))) {
            $toolset_active = true;
        }
        
        if (!$toolset_active) {
            add_action('admin_notices', array($this, 'toolset_missing_notice'));
        }
    }
    
    /**
     * Display admin notice if Toolset Types is not active
     */
    public function toolset_missing_notice() {
        $screen = get_current_screen();
        // Only show on relevant admin pages
        if (!$screen || (!in_array($screen->id, array('edit-listing', 'listing', 'listings_page_listings-management', 'listings_page_migrate-listings', 'listings_page_add-current-availability')) && strpos($screen->id, 'listings') === false)) {
            return;
        }
        
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Maloney Affordable Listings:', 'maloney-listings'); ?></strong>
                <?php _e('Toolset Types plugin is required for this plugin to function properly. Please install and activate Toolset Types to use custom fields, available units, and other features.', 'maloney-listings'); ?>
                <?php if (current_user_can('install_plugins')) : ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=toolset+types&tab=search&type=term'); ?>" class="button button-primary" style="margin-left: 10px;"><?php _e('Install Toolset Types', 'maloney-listings'); ?></a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
    
    public function activate() {
        // Create database tables
        Maloney_Listings_Vacancy_Notifications::create_table();
        
        // DISABLED: Create available units fields - fields should already exist in Toolset
        // if (class_exists('Maloney_Listings_Available_Units_Fields')) {
        //     $fields_setup = new Maloney_Listings_Available_Units_Fields();
        //     $fields_setup->create_fields();
        // }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
Maloney_Listings::get_instance();
