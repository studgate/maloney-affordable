<?php
/**
 * Plugin Settings
 * Allows clients to configure frontend features and appearance
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 * 
 * @package Maloney_Listings
 * @author Responsab LLC
 * @link https://www.responsab.com
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Settings {
    
    private $option_group = 'maloney_listings_settings';
    private $option_name = 'maloney_listings_frontend_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_color_picker'));
    }
    
    /**
     * Enqueue color picker script for settings page
     */
    public function enqueue_color_picker($hook) {
        // Hook name for submenu under custom post type: {post_type}_page_{menu_slug}
        if ($hook === 'listing_page_listings-settings') {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_add_inline_script('wp-color-picker', '
                jQuery(document).ready(function($) {
                    $(".color-picker").wpColorPicker();
                });
            ');
        }
    }
    
    /**
     * Check if current user is a developer
     */
    private function is_developer() {
        $current_user = wp_get_current_user();
        
        // Developer usernames
        $developer_usernames = array('ralph', 'responseab-oct25');
        
        // Developer emails
        $developer_emails = array('ralph@responsab.com', 'ralph@responseab.com');
        
        // Developer user IDs (if needed)
        $developer_user_ids = array(); // e.g., array(1)
        
        if (in_array($current_user->user_login, $developer_usernames, true)) {
            return true;
        }
        
        if (!empty($developer_emails) && in_array($current_user->user_email, $developer_emails, true)) {
            return true;
        }
        
        if (!empty($developer_user_ids) && in_array($current_user->ID, $developer_user_ids, true)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        // Only add settings page for developers
        if (!$this->is_developer()) {
            return;
        }
        
        add_submenu_page(
            'edit.php?post_type=listing',
            __('Listings Settings', 'maloney-listings'),
            __('Settings', 'maloney-listings'),
            'manage_options',
            'listings-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings and fields
     */
    public function register_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array($this, 'sanitize_settings')
        );
        
        // Map Features Section
        add_settings_section(
            'map_features',
            __('Map Features', 'maloney-listings'),
            array($this, 'render_map_features_section'),
            'listings-settings'
        );
        
        add_settings_field(
            'enable_search_area',
            __('Enable "Search This Area" Feature', 'maloney-listings'),
            array($this, 'render_enable_search_area_field'),
            'listings-settings',
            'map_features'
        );
        
        add_settings_field(
            'enable_directions',
            __('Enable Directions Button', 'maloney-listings'),
            array($this, 'render_enable_directions_field'),
            'listings-settings',
            'map_features'
        );
        
        add_settings_field(
            'enable_street_view',
            __('Enable Street View Button', 'maloney-listings'),
            array($this, 'render_enable_street_view_field'),
            'listings-settings',
            'map_features'
        );
        
        // Color Settings Section
        add_settings_section(
            'color_settings',
            __('Color Settings', 'maloney-listings'),
            array($this, 'render_color_settings_section'),
            'listings-settings'
        );
        
        add_settings_field(
            'rental_color',
            __('Rental Badge/Pin Color', 'maloney-listings'),
            array($this, 'render_rental_color_field'),
            'listings-settings',
            'color_settings'
        );
        
        add_settings_field(
            'condo_color',
            __('Condominium Badge/Pin Color', 'maloney-listings'),
            array($this, 'render_condo_color_field'),
            'listings-settings',
            'color_settings'
        );
        
        // Filter Settings Section
        add_settings_section(
            'filter_settings',
            __('Filter Settings', 'maloney-listings'),
            array($this, 'render_filter_settings_section'),
            'listings-settings'
        );
        
        add_settings_field(
            'enable_bathrooms_filter',
            __('Enable Bathrooms Filter', 'maloney-listings'),
            array($this, 'render_enable_bathrooms_filter_field'),
            'listings-settings',
            'filter_settings'
        );
        
        add_settings_field(
            'enable_income_limits_filter',
            __('Enable Income Limits Filter', 'maloney-listings'),
            array($this, 'render_enable_income_limits_filter_field'),
            'listings-settings',
            'filter_settings'
        );
        
        add_settings_field(
            'enable_concessions_filter',
            __('Enable Concessions Filter', 'maloney-listings'),
            array($this, 'render_enable_concessions_filter_field'),
            'listings-settings',
            'filter_settings'
        );
        
        add_settings_field(
            'hide_unit_size_field',
            __('Hide Unit Size Filter', 'maloney-listings'),
            array($this, 'render_hide_unit_size_field'),
            'listings-settings',
            'filter_settings'
        );
        
        add_settings_field(
            'just_listed_period',
            __('"Just Listed" Period', 'maloney-listings'),
            array($this, 'render_just_listed_period_field'),
            'listings-settings',
            'filter_settings'
        );
    }
    
    /**
     * Sanitize settings before saving
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Map features
        $sanitized['enable_search_area'] = isset($input['enable_search_area']) ? '1' : '0';
        $sanitized['enable_directions'] = isset($input['enable_directions']) ? '1' : '0';
        $sanitized['enable_street_view'] = isset($input['enable_street_view']) ? '1' : '0';
        
        // Colors - validate hex colors
        $sanitized['rental_color'] = $this->sanitize_hex_color($input['rental_color'] ?? '#E86962');
        $sanitized['condo_color'] = $this->sanitize_hex_color($input['condo_color'] ?? '#E4C780');
        
        // Filter settings
        $sanitized['enable_bathrooms_filter'] = isset($input['enable_bathrooms_filter']) ? '1' : '0';
        $sanitized['enable_income_limits_filter'] = isset($input['enable_income_limits_filter']) ? '1' : '0';
        $sanitized['enable_concessions_filter'] = isset($input['enable_concessions_filter']) ? '1' : '0';
        $sanitized['hide_unit_size_field'] = isset($input['hide_unit_size_field']) ? '1' : '0';
        $sanitized['just_listed_period'] = sanitize_text_field($input['just_listed_period'] ?? '7');
        
        return $sanitized;
    }
    
    /**
     * Sanitize hex color value
     */
    private function sanitize_hex_color($color) {
        $color = sanitize_text_field($color);
        // Remove # if present
        $color = ltrim($color, '#');
        // Validate hex color (3 or 6 characters)
        if (preg_match('/^[0-9A-Fa-f]{3}$|^[0-9A-Fa-f]{6}$/', $color)) {
            return '#' . $color;
        }
        // Return default if invalid
        return '#E86962'; // Default rental color
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check developer access
        if (!$this->is_developer()) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php _e('Listings Settings', 'maloney-listings'); ?></h1>
            <p><?php _e('Configure frontend features and appearance for your listings.', 'maloney-listings'); ?></p>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_group);
                do_settings_sections('listings-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render map features section description
     */
    public function render_map_features_section() {
        echo '<p>' . __('Control which map features are displayed on the frontend.', 'maloney-listings') . '</p>';
    }
    
    /**
     * Render color settings section description
     */
    public function render_color_settings_section() {
        echo '<p>' . __('Customize the colors used for rental and condominium badges and map pins.', 'maloney-listings') . '</p>';
    }
    
    /**
     * Render filter settings section description
     */
    public function render_filter_settings_section() {
        echo '<p>' . __('Configure filter options and behavior.', 'maloney-listings') . '</p>';
    }
    
    /**
     * Render enable search area field
     */
    public function render_enable_search_area_field() {
        $settings = $this->get_settings();
        $value = isset($settings['enable_search_area']) ? $settings['enable_search_area'] : '0';
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_search_area]" value="1" <?php checked($value, '1'); ?> />
            <?php _e('Show "Search This Area" button on the listings map', 'maloney-listings'); ?>
        </label>
        <p class="description"><?php _e('When enabled, users can search for listings within the visible map area.', 'maloney-listings'); ?></p>
        <?php
    }
    
    /**
     * Render enable directions field
     */
    public function render_enable_directions_field() {
        $settings = $this->get_settings();
        $value = isset($settings['enable_directions']) ? $settings['enable_directions'] : '1';
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_directions]" value="1" <?php checked($value, '1'); ?> />
            <?php _e('Show Directions button on individual listing pages', 'maloney-listings'); ?>
        </label>
        <p class="description"><?php _e('When enabled, a Directions button will appear on the map for each listing.', 'maloney-listings'); ?></p>
        <?php
    }
    
    /**
     * Render enable street view field
     */
    public function render_enable_street_view_field() {
        $settings = $this->get_settings();
        $value = isset($settings['enable_street_view']) ? $settings['enable_street_view'] : '1';
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_street_view]" value="1" <?php checked($value, '1'); ?> />
            <?php _e('Show Street View button on individual listing pages', 'maloney-listings'); ?>
        </label>
        <p class="description"><?php _e('When enabled, a Street View button will appear on the map for each listing.', 'maloney-listings'); ?></p>
        <?php
    }
    
    /**
     * Render rental color field
     */
    public function render_rental_color_field() {
        $settings = $this->get_settings();
        $value = isset($settings['rental_color']) ? $settings['rental_color'] : '#E86962';
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[rental_color]" value="<?php echo esc_attr($value); ?>" class="color-picker" data-default-color="#E86962" />
        <p class="description"><?php _e('Color used for rental property badges and map pins. Default: #E86962', 'maloney-listings'); ?></p>
        <?php
    }
    
    /**
     * Render condo color field
     */
    public function render_condo_color_field() {
        $settings = $this->get_settings();
        $value = isset($settings['condo_color']) ? $settings['condo_color'] : '#E4C780';
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[condo_color]" value="<?php echo esc_attr($value); ?>" class="color-picker" data-default-color="#E4C780" />
        <p class="description"><?php _e('Color used for condominium badges and map pins. Default: #E4C780', 'maloney-listings'); ?></p>
        <?php
    }
    
    /**
     * Render enable bathrooms filter field
     */
    public function render_enable_bathrooms_filter_field() {
        $settings = $this->get_settings();
        $value = isset($settings['enable_bathrooms_filter']) ? $settings['enable_bathrooms_filter'] : '1';
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_bathrooms_filter]" value="1" <?php checked($value, '1'); ?> />
            <?php _e('Show Bathrooms filter on the listings page', 'maloney-listings'); ?>
        </label>
        <p class="description"><?php _e('When enabled, users can filter listings by number of bathrooms. Disable if bathroom data is not yet available.', 'maloney-listings'); ?></p>
        <?php
    }
    
    /**
     * Render enable income limits filter field
     */
    public function render_enable_income_limits_filter_field() {
        $settings = $this->get_settings();
        $value = isset($settings['enable_income_limits_filter']) ? $settings['enable_income_limits_filter'] : '1';
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_income_limits_filter]" value="1" <?php checked($value, '1'); ?> />
            <?php _e('Show Income Limits filter on the listings page', 'maloney-listings'); ?>
        </label>
        <p class="description"><?php _e('When enabled, users can filter rental properties by income limits from Current Rental Availability entries.', 'maloney-listings'); ?></p>
        <?php
    }
    
    /**
     * Render enable concessions filter field
     */
    public function render_enable_concessions_filter_field() {
        $settings = $this->get_settings();
        $value = isset($settings['enable_concessions_filter']) ? $settings['enable_concessions_filter'] : '0';
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_concessions_filter]" value="1" <?php checked($value, '1'); ?> />
            <?php _e('Show Concessions filter on the listings page', 'maloney-listings'); ?>
        </label>
        <p class="description"><?php _e('When enabled, users can filter listings by concessions (e.g., "1 Month free"). Multiple concessions can be selected. The filter will appear after Income Limit in the advanced filters section.', 'maloney-listings'); ?></p>
        <?php
    }
    
    /**
     * Render hide unit size field
     */
    public function render_hide_unit_size_field() {
        $settings = $this->get_settings();
        $value = isset($settings['hide_unit_size_field']) ? $settings['hide_unit_size_field'] : '0';
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[hide_unit_size_field]" value="1" <?php checked($value, '1'); ?> />
            <?php _e('Hide the "Unit Size" filter on the listings page', 'maloney-listings'); ?>
        </label>
        <p class="description"><?php _e('When enabled, the "Unit Size" filter section (Studio, 1BR, 2BR, etc.) will be hidden from the Available Units filter on the listings page. Users will still be able to filter by beds and baths, and the system will automatically match available units based on the selected bedroom and bathroom counts.', 'maloney-listings'); ?></p>
        <?php
    }
    
    /**
     * Render just listed period field
     */
    public function render_just_listed_period_field() {
        $settings = $this->get_settings();
        $value = isset($settings['just_listed_period']) ? $settings['just_listed_period'] : '7';
        $options = array(
            '1' => __('One day', 'maloney-listings'),
            '3' => __('3 days', 'maloney-listings'),
            '7' => __('7 days', 'maloney-listings'),
            '14' => __('14 days', 'maloney-listings'),
        );
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[just_listed_period]">
            <?php foreach ($options as $days => $label) : ?>
                <option value="<?php echo esc_attr($days); ?>" <?php selected($value, $days); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php _e('Select the time period that defines a "Just Listed" property. Default: 7 days', 'maloney-listings'); ?></p>
        <?php
    }
    
    /**
     * Get settings with defaults
     */
    public function get_settings() {
        $defaults = array(
            'enable_search_area' => '0', // Changed to unchecked by default
            'enable_directions' => '1',
            'enable_street_view' => '1',
            'rental_color' => '#E86962',
            'condo_color' => '#E4C780',
            'enable_bathrooms_filter' => '1', // Checked by default
            'enable_income_limits_filter' => '1', // Checked by default
            'enable_concessions_filter' => '0', // Disabled by default
            'hide_unit_size_field' => '0', // Show by default
            'just_listed_period' => '7',
        );
        
        $settings = get_option($this->option_name, array());
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Get a specific setting value or all settings
     */
    public static function get_setting($key = null, $default = null) {
        $instance = new self();
        $settings = $instance->get_settings();
        if ($key === null) {
            return $settings;
        }
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}

