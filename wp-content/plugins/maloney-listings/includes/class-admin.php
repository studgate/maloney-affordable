<?php
/**
 * Admin Management Screen
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Admin {
    
    /**
     * Check if current user is the developer
     * Only the developer can see advanced/debug tools
     */
    private function is_developer() {
        $current_user = wp_get_current_user();
        
        // Developer usernames
        $developer_usernames = array('ralph', 'responseab-oct25');
        
        // Developer emails
        $developer_emails = array('ralph@responsab.com', 'ralph@responseab.com');
        
        // Developer user IDs (optional - add if needed)
        $developer_user_ids = array();
        
        if (in_array($current_user->user_login, $developer_usernames, true)) {
            return true;
        }
        
        if (in_array($current_user->user_email, $developer_emails, true)) {
            return true;
        }
        
        if (!empty($developer_user_ids) && in_array($current_user->ID, $developer_user_ids, true)) {
            return true;
        }
        
        return false;
    }
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_management_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        // Admin list filters (Type/Status/Location)
        add_action('restrict_manage_posts', array($this, 'add_admin_list_filters'));
        add_filter('parse_query', array($this, 'apply_admin_list_filters'));
        add_action('pre_get_posts', array($this, 'modify_query_for_has_units_filter'));
        add_filter('posts_results', array($this, 'filter_posts_by_has_units'), 10, 2);
        add_filter('found_posts', array($this, 'adjust_found_posts_for_has_units'), 10, 2);
        add_action('wp_ajax_validate_all_coordinates', array($this, 'ajax_validate_all_coordinates'));
        add_action('wp_ajax_search_rental_properties', array($this, 'ajax_search_rental_properties'));
        add_action('wp_ajax_batch_geocode_zip_codes', array($this, 'ajax_batch_geocode_zip_codes'));
        
        // DISABLED: Auto-create available units fields - fields should already exist in Toolset
        // add_action('admin_init', array($this, 'maybe_create_available_units_fields'), 20);
        
        // Add availability meta box to listing edit page
        add_action('add_meta_boxes', array($this, 'add_availability_meta_box'));
        add_action('save_post_listing', array($this, 'save_availability_meta_box'), 10, 2);
        
        // Add condo listings meta box to listing edit page
        add_action('add_meta_boxes', array($this, 'add_condo_listings_meta_box'));
        add_action('save_post_listing', array($this, 'save_condo_listings_meta_box'), 10, 2);
        
        // Add description under title in listings admin page
        add_action('admin_notices', array($this, 'add_listings_page_description'));
        
        // Remove Custom Fields meta box for listings
        add_action('add_meta_boxes', array($this, 'remove_custom_fields_meta_box'), 99);
        
        // Removed: Direct JSON dump debug handler (development tool, not needed in production)
    }
    
    /**
     * Auto-create available units fields if they don't exist
     * Runs on admin_init to ensure fields are created even if Toolset wasn't active during plugin activation
     */
    public function maybe_create_available_units_fields() {
        // Only run for admins and only once per page load
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Only run if Toolset is active
        if (!function_exists('wpcf_admin_fields_save_field')) {
            return;
        }
        
        // Only run if class exists
        if (!class_exists('Maloney_Listings_Available_Units_Fields')) {
            return;
        }
        
        // Always call create_fields() - it will only create missing fields and update the group
        // This ensures new fields (like bathrooms) are added even if other fields already exist
        $fields_setup = new Maloney_Listings_Available_Units_Fields();
        $fields_setup->create_fields(); // This will create missing fields and update the group
    }
    
    public function add_management_page() {
        // Developer-only menu items
        if ($this->is_developer()) {
            add_submenu_page(
                'edit.php?post_type=listing',
                __('Listings Management', 'maloney-listings'),
                __('Manage Listings', 'maloney-listings'),
                'manage_options',
                'listings-management',
                array($this, 'render_management_page')
            );
            
            // DISABLED: Vacancy Notifications
            // add_submenu_page(
            //     'edit.php?post_type=listing',
            //     __('Vacancy Notifications', 'maloney-listings'),
            //     __('Vacancy Notifications', 'maloney-listings'),
            //     'manage_options',
            //     'vacancy-notifications',
            //     array($this, 'render_vacancy_notifications_page')
            // );
            
            // DISABLED: Field Discovery
            // add_submenu_page(
            //     'edit.php?post_type=listing',
            //     __('Field Discovery', 'maloney-listings'),
            //     __('Field Discovery', 'maloney-listings'),
            //     'manage_options',
            //     'field-discovery',
            //     array($this, 'render_field_discovery_page')
            // );
            
            // DISABLED: Fix Toolset Meta
            // add_submenu_page(
            //     'edit.php?post_type=listing',
            //     __('Fix Toolset Meta', 'maloney-listings'),
            //     __('Fix Toolset Meta', 'maloney-listings'),
            //     'manage_options',
            //     'fix-toolset-meta',
            //     array($this, 'render_fix_toolset_meta_page')
            // );
            
            add_submenu_page(
                'edit.php?post_type=listing',
                __('Extract Zip Codes', 'maloney-listings'),
                __('Extract Zip Codes', 'maloney-listings'),
                'manage_options',
                'extract-zip-codes',
                array($this, 'render_extract_zip_codes_page')
            );
            
            add_submenu_page(
                'edit.php?post_type=listing',
                __('Setup Toolset Templates', 'maloney-listings'),
                __('Setup Toolset Templates', 'maloney-listings'),
                'manage_options',
                'setup-toolset-templates',
                array($this, 'render_template_setup_page')
            );
            
            add_submenu_page(
                'edit.php?post_type=listing',
                __('Manage Template Blocks', 'maloney-listings'),
                __('Template Blocks', 'maloney-listings'),
                'manage_options',
                'template-blocks',
                array($this, 'render_template_blocks_page')
            );
            
        }
        
        // Add "Current Availability" under "Add New Listing" using admin_menu filter
        add_action('admin_menu', array($this, 'add_current_availability_menu'), 100);
        // Add "Current Condo Listings" under "Add New Listing" using admin_menu filter
        add_action('admin_menu', array($this, 'add_current_condo_listings_menu'), 100);
        
        // Migrate Existing Condos and Rental Properties - after Add New (position 2)
        if ($this->is_developer()) {
            add_submenu_page(
                'edit.php?post_type=listing',
                __('Migrate Existing Condos and Rental Properties', 'maloney-listings'),
                __('Migrate Existing Condos and Rental Properties', 'maloney-listings'),
                'manage_options',
                'migrate-listings',
                array($this, 'render_migration_page')
            );
        }
        
        // Migrate Available Units - after Current Availability (position 4)
        add_submenu_page(
            'edit.php?post_type=listing',
            __('Migrate Available Units', 'maloney-listings'),
            __('Migrate Available Units', 'maloney-listings'),
            'manage_options',
            'migrate-available-units',
            array($this, 'render_available_units_migration_page')
        );
        
        // Migrate Condo Listings - after Current Condo Listings (position 5)
        if ($this->is_developer()) {
            add_submenu_page(
                'edit.php?post_type=listing',
                __('Migrate Condo Listings', 'maloney-listings'),
                __('Migrate Condo Listings', 'maloney-listings'),
                'manage_options',
                'migrate-condo-listings',
                array($this, 'render_condo_listings_migration_page')
            );
        }
        
        // Geocode Addresses - after Current Condo Listings (position 6)
        add_submenu_page(
            'edit.php?post_type=listing',
            __('Geocode Addresses', 'maloney-listings'),
            __('Geocode Addresses', 'maloney-listings'),
            'manage_options',
            'geocode-addresses',
            array($this, 'render_geocode_page')
        );
        
        // Reorder menu items using admin_menu filter (late priority to run after all items are added)
        add_action('admin_menu', array($this, 'reorder_listings_menu'), 999);
    }
    
    /**
     * Reorder listings menu items
     */
    public function reorder_listings_menu() {
        global $submenu;
        
        $parent_slug = 'edit.php?post_type=listing';
        if (!isset($submenu[$parent_slug])) {
            return;
        }
        
        // Define desired order (menu slugs)
        $order = array(
            'edit.php?post_type=listing', // All Listings
            'post-new.php?post_type=listing', // Add New
            'add-current-availability', // Current Availability
            'migrate-listings', // Migrate Existing Condos and Rental Properties
            'add-current-condo-listings', // Current Condo Listings
            'migrate-available-units', // Migrate Available Units
            'migrate-condo-listings', // Migrate Condo Listings
            'geocode-addresses', // Geocode Addresses
            'listings-management', // Manage Listings
            'extract-zip-codes', // Extract Zip Codes
            'setup-toolset-templates', // Setup Toolset Templates
            'template-blocks', // Template Blocks
            'listings-settings', // Settings
        );
        
        $ordered = array();
        $unordered = array();
        $found_slugs = array();
        
        // First, add items in desired order
        foreach ($order as $slug) {
            foreach ($submenu[$parent_slug] as $key => $item) {
                if ($item[2] === $slug) {
                    $ordered[] = $item;
                    $found_slugs[] = $slug;
                    unset($submenu[$parent_slug][$key]);
                }
            }
        }
        
        // Then add remaining items (not in the order list)
        foreach ($submenu[$parent_slug] as $item) {
            if (!in_array($item[2], $found_slugs)) {
                $unordered[] = $item;
            }
        }
        
        // Combine ordered and unordered
        $submenu[$parent_slug] = array_merge($ordered, $unordered);
    }
    
    /**
     * Add Current Availability menu item under "Add New Listing"
     */
    public function add_current_availability_menu() {
        $parent_slug = 'edit.php?post_type=listing';
        // Add after "Add New" (position 2)
        add_submenu_page(
            $parent_slug,
            __('Current Availability', 'maloney-listings'),
            __('Current Availability', 'maloney-listings'),
            'manage_options',
            'add-current-availability',
            array($this, 'render_add_availability_page')
        );
    }
    
    /**
     * Add Current Condo Listings menu item under "Add New Listing"
     */
    public function add_current_condo_listings_menu() {
        $parent_slug = 'edit.php?post_type=listing';
        // Add after "Current Availability" (position 3)
        add_submenu_page(
            $parent_slug,
            __('Current Condo Listings', 'maloney-listings'),
            __('Current Condo Listings', 'maloney-listings'),
            'manage_options',
            'add-current-condo-listings',
            array($this, 'render_add_condo_listings_page')
        );
    }
    
    public function render_template_setup_page() {
        if (!$this->is_developer()) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        $message = '';
        $message_type = 'info';
        
        // Handle form submission
        if (isset($_POST['migrate_templates']) && check_admin_referer('migrate_templates_action', 'migrate_templates_nonce')) {
            if (class_exists('Maloney_Listings_Template_Migration')) {
                // Setup default template first
                Maloney_Listings_Template_Migration::setup_default_template();
                
                // Migrate conditional templates
                $result = Maloney_Listings_Template_Migration::migrate_conditional_templates();
                
                if ($result) {
                    $message = __('Conditional templates have been successfully migrated to the listing post type!', 'maloney-listings');
                    $message_type = 'success';
                } else {
                    $message = __('Template migration failed. Please check that Toolset Views is active.', 'maloney-listings');
                    $message_type = 'error';
                }
            } else {
                $message = __('Template migration class not found.', 'maloney-listings');
                $message_type = 'error';
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Setup Toolset Content Templates', 'maloney-listings'); ?></h1>
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php _e('Migrate Conditional Templates', 'maloney-listings'); ?></h2>
                <p><?php _e('This will migrate all conditional Content Templates from the old post types (condominiums, rental-properties) to the new unified listing post type.', 'maloney-listings'); ?></p>
                <p><?php _e('The migration will:', 'maloney-listings'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('Copy all conditional templates from condominiums and rental-properties', 'maloney-listings'); ?></li>
                    <li><?php _e('Add listing_type taxonomy conditions to each template', 'maloney-listings'); ?></li>
                    <li><?php _e('Preserve all existing field-based conditions (status, income limits, etc.)', 'maloney-listings'); ?></li>
                </ul>
                
                <?php if (!class_exists('WPV_Settings')) : ?>
                    <div class="inline notice notice-error">
                        <p><?php _e('Toolset Views plugin is not active. Please activate it before running the migration.', 'maloney-listings'); ?></p>
                    </div>
                <?php else : ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('migrate_templates_action', 'migrate_templates_nonce'); ?>
                        <p>
                            <button type="submit" name="migrate_templates" class="button button-primary button-large">
                                <?php _e('Migrate Templates', 'maloney-listings'); ?>
                            </button>
                        </p>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Current Template Status', 'maloney-listings'); ?></h2>
                <?php
                global $WPV_settings;
                if (class_exists('WPV_Settings')) {
                    if (!isset($WPV_settings)) {
                        $WPV_settings = WPV_Settings::get_instance();
                    }
                    
                    $default_key = 'views_template_for_listing';
                    $conditions_key = 'views_template_conditions_for_listing';
                    
                    $default_template_id = isset($WPV_settings[$default_key]) ? $WPV_settings[$default_key] : 0;
                    $conditions = isset($WPV_settings[$conditions_key]) ? $WPV_settings[$conditions_key] : array();
                    
                    echo '<p><strong>' . __('Default Template:', 'maloney-listings') . '</strong> ';
                    if ($default_template_id > 0) {
                        $template = get_post($default_template_id);
                        if ($template) {
                            echo esc_html($template->post_title) . ' (ID: ' . $default_template_id . ')';
                        } else {
                            echo __('Not found', 'maloney-listings');
                        }
                    } else {
                        echo __('Not set', 'maloney-listings');
                    }
                    echo '</p>';
                    
                    echo '<p><strong>' . __('Conditional Templates:', 'maloney-listings') . '</strong> ' . count($conditions) . '</p>';
                    
                    if (!empty($conditions)) {
                        echo '<ul style="list-style: disc; margin-left: 20px;">';
                        foreach ($conditions as $index => $condition) {
                            $template_id = isset($condition['content_template_id']) ? $condition['content_template_id'] : 0;
                            $template = $template_id > 0 ? get_post($template_id) : null;
                            $parsed = isset($condition['parsed_conditions']) ? $condition['parsed_conditions'] : '';
                            
                            echo '<li>';
                            if ($template) {
                                echo esc_html($template->post_title) . ' (ID: ' . $template_id . ')';
                            } else {
                                echo __('Template ID:', 'maloney-listings') . ' ' . $template_id;
                            }
                            if ($parsed) {
                                echo ' - <code>' . esc_html($parsed) . '</code>';
                            }
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                } else {
                    echo '<p>' . __('Toolset Views is not active.', 'maloney-listings') . '</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    // Backend list filters in Listings list table
    public function add_admin_list_filters($post_type) {
        if ($post_type !== 'listing') return;
        // Listing Type
        $this->render_admin_tax_filter('listing_type', __('Type', 'maloney-listings'));
        // Status filter
        $this->render_status_filter();
        // Has Units (Current Availability) filter
        $this->render_has_units_filter();
    }
    
    private function render_status_filter() {
        $selected = isset($_GET['listing_status_filter']) ? sanitize_text_field($_GET['listing_status_filter']) : '';
        
        // Get all unique status values from both rentals and condos
        $statuses = array();
        
        // Rental statuses
        $rental_statuses = array(
            '1' => 'Active Rental',
            '2' => 'Open Lottery',
            '3' => 'Closed Lottery',
            '4' => 'Inactive Rental',
            '5' => 'Custom Lottery',
            '6' => 'Upcoming Lottery',
        );
        
        // Condo statuses
        $condo_statuses = array(
            '1' => 'FCFS Condo Sales',
            '2' => 'Active Condo Lottery',
            '3' => 'Closed Condo Lottery',
            '4' => 'Inactive Condo Property',
            '5' => 'Upcoming Condo',
        );
        
        // Combine and deduplicate by display name
        $all_statuses = array();
        foreach ($rental_statuses as $value => $label) {
            $all_statuses['rental_' . $value] = $label;
        }
        foreach ($condo_statuses as $value => $label) {
            // Only add if not already in the list (some statuses might be similar)
            if (!in_array($label, $all_statuses)) {
                $all_statuses['condo_' . $value] = $label;
            } else {
                // If similar status exists, combine them
                $key = array_search($label, $all_statuses);
                if ($key) {
                    $all_statuses[$key . ',condo_' . $value] = $label;
                }
            }
        }
        
        echo '<label class="screen-reader-text" for="filter_listing_status">' . __('Status', 'maloney-listings') . '</label>';
        echo '<select id="filter_listing_status" name="listing_status_filter">';
        echo '<option value="">' . __('All Statuses', 'maloney-listings') . '</option>';
        
        // Group by type for better organization
        echo '<optgroup label="' . esc_attr__('Rental Statuses', 'maloney-listings') . '">';
        foreach ($rental_statuses as $value => $label) {
            $option_value = 'rental_' . $value;
            printf('<option value="%s" %s>%s</option>', esc_attr($option_value), selected($selected, $option_value, false), esc_html($label));
        }
        echo '</optgroup>';
        
        echo '<optgroup label="' . esc_attr__('Condo Statuses', 'maloney-listings') . '">';
        foreach ($condo_statuses as $value => $label) {
            $option_value = 'condo_' . $value;
            printf('<option value="%s" %s>%s</option>', esc_attr($option_value), selected($selected, $option_value, false), esc_html($label));
        }
        echo '</optgroup>';
        
        echo '</select>';
    }
    
    private function render_has_units_filter() {
        $selected = isset($_GET['has_units']) ? sanitize_text_field($_GET['has_units']) : '';
        echo '<label class="screen-reader-text" for="filter_has_units">' . __('Has Units', 'maloney-listings') . '</label>';
        echo '<select id="filter_has_units" name="has_units">';
        echo '<option value="">' . __('Has Units', 'maloney-listings') . '</option>';
        echo '<option value="yes" ' . selected($selected, 'yes', false) . '>' . __('Yes', 'maloney-listings') . '</option>';
        echo '<option value="no" ' . selected($selected, 'no', false) . '>' . __('No', 'maloney-listings') . '</option>';
        echo '</select>';
    }

    // Removed: render_debug_listing_page(), handle_direct_json_dump(), render_diagnostics_page(), 
    // count_simple(), count_bedrooms_query() - development/debugging tools, not needed in production

    private function render_admin_tax_filter($taxonomy, $label) {
        $selected = isset($_GET[$taxonomy]) ? sanitize_text_field($_GET[$taxonomy]) : '';
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
        if (is_wp_error($terms) || empty($terms)) return;
        echo '<label class="screen-reader-text" for="filter_' . esc_attr($taxonomy) . '">' . esc_html($label) . '</label>';
        echo '<select id="filter_' . esc_attr($taxonomy) . '" name="' . esc_attr($taxonomy) . '">';
        echo '<option value="">' . esc_html($label) . '</option>';
        foreach ($terms as $t) {
            printf('<option value="%s" %s>%s</option>', esc_attr($t->slug), selected($selected, $t->slug, false), esc_html($t->name));
        }
        echo '</select>';
    }

    public function apply_admin_list_filters($query) {
        global $pagenow;
        if (!is_admin() || $pagenow !== 'edit.php') return;
        if (!isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'listing') return;
        
        // Filter by listing_type
        if (!empty($_GET['listing_type'])) {
            $term = sanitize_text_field($_GET['listing_type']);
            $tax_query = isset($query->query_vars['tax_query']) ? $query->query_vars['tax_query'] : array();
            $tax_query[] = array('taxonomy'=>'listing_type','field'=>'slug','terms'=>$term);
            $tax_query['relation'] = 'AND';
            $query->set('tax_query', $tax_query);
        }
        
        // Filter by status
        if (!empty($_GET['listing_status_filter'])) {
            $status_filter = sanitize_text_field($_GET['listing_status_filter']);
            
            // Parse the filter value (format: rental_1, condo_2, etc.)
            if (strpos($status_filter, 'rental_') === 0) {
                $status_value = str_replace('rental_', '', $status_filter);
                $meta_query = isset($query->query_vars['meta_query']) ? $query->query_vars['meta_query'] : array();
                $meta_query[] = array(
                    'key' => 'wpcf-status',
                    'value' => $status_value,
                    'compare' => '='
                );
                $query->set('meta_query', $meta_query);
            } elseif (strpos($status_filter, 'condo_') === 0) {
                $status_value = str_replace('condo_', '', $status_filter);
                $meta_query = isset($query->query_vars['meta_query']) ? $query->query_vars['meta_query'] : array();
                $meta_query[] = array(
                    'key' => 'wpcf-condo-status',
                    'value' => $status_value,
                    'compare' => '='
                );
                $query->set('meta_query', $meta_query);
            }
        }
        
        // Filter by has units (current availability) - handled in modify_query_for_has_units_filter
    }
    
    /**
     * Modify query to get all posts when filtering by has units
     * This allows us to filter all posts, then paginate the filtered results
     */
    public function modify_query_for_has_units_filter($query) {
        global $pagenow;
        if (!is_admin() || $pagenow !== 'edit.php') return;
        if (!isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'listing') return;
        
        if (!empty($_GET['has_units'])) {
            $has_units = sanitize_text_field($_GET['has_units']);
            // Store filter preference for post-processing
            $query->set('_has_units_filter', $has_units);
            // Store original pagination settings
            $query->set('_original_posts_per_page', $query->get('posts_per_page'));
            $query->set('_original_paged', $query->get('paged'));
            // Get ALL posts (we'll paginate after filtering)
            // Use nopaging = true to disable pagination completely
            $query->set('nopaging', true);
            $query->set('posts_per_page', -1);
            $query->set('offset', 0);
            
            // Ensure we're only looking at rentals when filtering by units
            $tax_query = isset($query->query_vars['tax_query']) ? $query->query_vars['tax_query'] : array();
            $has_rental_filter = false;
            foreach ($tax_query as $tq) {
                if (isset($tq['taxonomy']) && $tq['taxonomy'] === 'listing_type' && isset($tq['terms']) && in_array('rental', (array)$tq['terms'])) {
                    $has_rental_filter = true;
                    break;
                }
            }
            if (!$has_rental_filter) {
                $tax_query[] = array(
                    'taxonomy' => 'listing_type',
                    'field' => 'slug',
                    'terms' => 'rental',
                );
                $tax_query['relation'] = 'AND';
                $query->set('tax_query', $tax_query);
            }
        }
    }
    
    /**
     * Post-process query results to filter by has units (current availability)
     * This is necessary because we need to check repetitive Toolset fields
     * Also handles pagination of filtered results
     */
    public function filter_posts_by_has_units($posts, $query) {
        global $pagenow;
        if (!is_admin() || $pagenow !== 'edit.php') return $posts;
        if (!isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'listing') return $posts;
        
        $has_units_filter = isset($query->query_vars['_has_units_filter']) ? $query->query_vars['_has_units_filter'] : '';
        if (empty($has_units_filter)) return $posts;
        
        if (!class_exists('Maloney_Listings_Available_Units_Fields')) {
            return $posts;
        }
        
        $filtered_posts = array();
        foreach ($posts as $post) {
            $post_id = $post->ID;
            
            // Check if this is a rental
            $listing_type_terms = get_the_terms($post_id, 'listing_type');
            $is_rental = false;
            if ($listing_type_terms && !is_wp_error($listing_type_terms)) {
                foreach ($listing_type_terms as $term) {
                    if (stripos($term->slug, 'rental') !== false) {
                        $is_rental = true;
                        break;
                    }
                }
            }
            
            if (!$is_rental) {
                // Skip non-rentals when filtering by units
                continue;
            }
            
            // Get total available units
            $total_available = Maloney_Listings_Available_Units_Fields::get_total_available($post_id);
            
            if ($has_units_filter === 'yes' && $total_available > 0) {
                $filtered_posts[] = $post;
            } elseif ($has_units_filter === 'no' && $total_available <= 0) {
                $filtered_posts[] = $post;
            }
        }
        
        // Store all filtered posts for pagination
        $query->set('_filtered_posts', $filtered_posts);
        
        // Apply pagination to filtered results
        $original_posts_per_page = isset($query->query_vars['_original_posts_per_page']) ? intval($query->query_vars['_original_posts_per_page']) : 20;
        $original_paged = isset($query->query_vars['_original_paged']) ? intval($query->query_vars['_original_paged']) : 1;
        
        if ($original_posts_per_page > 0) {
            $offset = ($original_paged - 1) * $original_posts_per_page;
            $paginated_posts = array_slice($filtered_posts, $offset, $original_posts_per_page);
            return $paginated_posts;
        }
        
        return $filtered_posts;
    }
    
    /**
     * Adjust the found_posts count to reflect filtered results
     */
    public function adjust_found_posts_for_has_units($found_posts, $query) {
        global $pagenow;
        if (!is_admin() || $pagenow !== 'edit.php') return $found_posts;
        if (!isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'listing') return $found_posts;
        
        $has_units_filter = isset($query->query_vars['_has_units_filter']) ? $query->query_vars['_has_units_filter'] : '';
        if (empty($has_units_filter)) return $found_posts;
        
        // Get the count of filtered posts
        $filtered_posts = isset($query->query_vars['_filtered_posts']) ? $query->query_vars['_filtered_posts'] : array();
        return count($filtered_posts);
    }
    
    public function enqueue_admin_assets($hook) {
        // Management/Geocode pages
        if (strpos($hook, 'listings-management') !== false || strpos($hook, 'vacancy-notifications') !== false || strpos($hook, 'geocode-addresses') !== false || strpos($hook, 'add-current-availability') !== false || strpos($hook, 'migrate-condo-listings') !== false) {
            wp_enqueue_style('maloney-listings-admin', MALONEY_LISTINGS_PLUGIN_URL . 'assets/css/admin.css', array(), MALONEY_LISTINGS_VERSION);
            wp_enqueue_script('maloney-listings-admin', MALONEY_LISTINGS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), MALONEY_LISTINGS_VERSION, true);
            
            // Enqueue jQuery UI autocomplete for current-availability page
            if (strpos($hook, 'add-current-availability') !== false) {
                wp_enqueue_script('jquery-ui-autocomplete');
                // Load jQuery UI CSS from local file (better performance, no external dependencies)
                wp_enqueue_style('jquery-ui', MALONEY_LISTINGS_PLUGIN_URL . 'assets/vendor/jquery-ui/jquery-ui.css', array(), '1.13.2');
            }
        }

        // No editor scripts needed — creation happens server-side now
    }
    
    /**
     * AJAX handler for property autocomplete search
     */
    public function ajax_search_rental_properties() {
        check_ajax_referer('maloney_listings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $search_term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        
        if (empty($search_term)) {
            wp_send_json_success(array());
        }
        
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => 20,
            'post_status' => 'publish',
            's' => $search_term,
            'tax_query' => array(
                array(
                    'taxonomy' => 'listing_type',
                    'field' => 'slug',
                    'terms' => 'rental',
                ),
            ),
        );
        
        $properties = get_posts($args);
        $results = array();
        
        foreach ($properties as $prop) {
            $town = get_post_meta($prop->ID, 'wpcf-city', true);
            if (empty($town)) {
                $town = get_post_meta($prop->ID, '_listing_city', true);
            }
            
            $label = $prop->post_title;
            if ($town) {
                $label .= ' (' . $town . ')';
            }
            
            $results[] = array(
                'label' => $label,
                'value' => $prop->post_title,
                'id' => $prop->ID,
                'town' => $town,
            );
        }
        
        wp_send_json_success($results);
    }


    // Removed: render_index_health_page() - development/debugging tool, not needed in production
    
    public function render_add_availability_page() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }
        
        $message = '';
        $message_type = 'info';
        $selected_property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
        $add_new = isset($_GET['add_new']) ? true : false;
        $selected_property = null;
        
        if ($selected_property_id) {
            $selected_property = get_post($selected_property_id);
            if (!$selected_property || $selected_property->post_type !== 'listing') {
                $selected_property = null;
                $selected_property_id = 0;
            }
        }
        
        // If add_new is set, show property selector first
        if ($add_new && !$selected_property_id) {
            // Show property selector for adding new entry
        }
        
        // Handle form submission
        if (isset($_POST['save_availability']) && check_admin_referer('save_availability_action', 'save_availability_nonce')) {
            $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;
            
            if ($property_id) {
                // Get existing availability entries
                $entries = isset($_POST['availability']) && is_array($_POST['availability']) ? $_POST['availability'] : array();
                
                // Clear existing repetitive field values
                delete_post_meta($property_id, 'wpcf-availability-property');
                delete_post_meta($property_id, 'wpcf-availability-town');
                delete_post_meta($property_id, 'wpcf-availability-bedrooms');
                delete_post_meta($property_id, 'wpcf-availability-bathrooms');
                delete_post_meta($property_id, 'wpcf-availability-rent');
                delete_post_meta($property_id, 'wpcf-availability-minimum-income');
                delete_post_meta($property_id, 'wpcf-availability-income-limit');
                delete_post_meta($property_id, 'wpcf-availability-concessions');
                delete_post_meta($property_id, 'wpcf-availability-concessions-count');
                delete_post_meta($property_id, 'wpcf-availability-type');
                delete_post_meta($property_id, 'wpcf-availability-units-available');
                delete_post_meta($property_id, 'wpcf-availability-accessible-units');
                delete_post_meta($property_id, 'wpcf-availability-view-apply');
                
                // Get property data for auto-fill
                $property = get_post($property_id);
                $property_town = get_post_meta($property_id, 'wpcf-city', true);
                if (empty($property_town)) {
                    $property_town = get_post_meta($property_id, '_listing_city', true);
                }
                $property_link = get_permalink($property_id);
                
                // Save new entries
                foreach ($entries as $entry) {
                    if (empty($entry['bedrooms']) || empty($entry['units_available'])) {
                        continue; // Skip incomplete entries
                    }
                    
                    // Auto-fill property, town, and link
                    add_post_meta($property_id, 'wpcf-availability-property', $property_id);
                    add_post_meta($property_id, 'wpcf-availability-town', $property_town);
                    add_post_meta($property_id, 'wpcf-availability-bedrooms', sanitize_text_field($entry['bedrooms']));
                    if (!empty($entry['bathrooms'])) {
                        add_post_meta($property_id, 'wpcf-availability-bathrooms', sanitize_text_field($entry['bathrooms']));
                    }
                    add_post_meta($property_id, 'wpcf-availability-rent', sanitize_text_field($entry['rent']));
                    add_post_meta($property_id, 'wpcf-availability-minimum-income', sanitize_text_field($entry['minimum_income']));
                    add_post_meta($property_id, 'wpcf-availability-income-limit', sanitize_text_field($entry['income_limit']));
                    // Save concessions (multiple selections) - save all for this entry before moving to next entry
                    // Store count first, then the concession IDs
                    $concession_count = 0;
                    if (!empty($entry['concessions']) && is_array($entry['concessions'])) {
                        $concession_count = count($entry['concessions']);
                        foreach ($entry['concessions'] as $concession_id) {
                            add_post_meta($property_id, 'wpcf-availability-concessions', intval($concession_id));
                        }
                    }
                    // Store count marker to help with retrieval
                    if ($concession_count > 0) {
                        add_post_meta($property_id, 'wpcf-availability-concessions-count', $concession_count);
                    }
                    add_post_meta($property_id, 'wpcf-availability-type', sanitize_text_field($entry['type']));
                    add_post_meta($property_id, 'wpcf-availability-units-available', intval($entry['units_available']));
                    add_post_meta($property_id, 'wpcf-availability-accessible-units', sanitize_textarea_field($entry['accessible_units']));
                    add_post_meta($property_id, 'wpcf-availability-view-apply', esc_url_raw($property_link));
                }
                
                $message = sprintf(__('Availability data saved for %s', 'maloney-listings'), $property->post_title);
                $message_type = 'success';
                $selected_property_id = $property_id;
                $selected_property = $property;
            }
        }
        
        // Get all availability entries
        $all_availability = array();
        if (class_exists('Maloney_Listings_Available_Units_Fields')) {
            if ($selected_property_id) {
                // Get entries for selected property only
                $all_availability = Maloney_Listings_Available_Units_Fields::get_availability_data($selected_property_id);
            } else {
                // Get all entries from all properties
                $all_availability = Maloney_Listings_Available_Units_Fields::get_all_availability_entries();
            }
        }
        
        // Sort entries: default by property name, then by date posted
        usort($all_availability, function($a, $b) {
            // First sort by property name
            $prop_a = isset($a['property']) ? strtolower($a['property']) : '';
            $prop_b = isset($b['property']) ? strtolower($b['property']) : '';
            if ($prop_a !== $prop_b) {
                return strcmp($prop_a, $prop_b);
            }
            // If same property, sort by source_post_id (which relates to post date)
            $id_a = isset($a['source_post_id']) ? $a['source_post_id'] : (isset($a['property_id']) ? $a['property_id'] : 0);
            $id_b = isset($b['source_post_id']) ? $b['source_post_id'] : (isset($b['property_id']) ? $b['property_id'] : 0);
            return $id_b - $id_a; // Newer first
        });
        
        // Get all rental properties for autocomplete
        $all_rental_properties = get_posts(array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'listing_type',
                    'field' => 'slug',
                    'terms' => 'rental',
                ),
            ),
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Current Availability', 'maloney-listings'); ?></h1>
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($add_new && !$selected_property_id) : ?>
                <div class="card" style="max-width: 100% !important; margin-top: 20px;">
                    <h2><?php _e('Select Property to Add Availability', 'maloney-listings'); ?></h2>
                    <p>
                        <label for="property_autocomplete_new"><?php _e('Search Property:', 'maloney-listings'); ?></label><br>
                        <input type="text" id="property_autocomplete_new" placeholder="<?php _e('Type to search...', 'maloney-listings'); ?>" style="width: 500px; padding: 8px 12px; font-size: 14px;">
                        <input type="hidden" id="selected_property_id_new" value="">
                    </p>
                    <p class="description"><?php _e('Search for a rental property to add availability entries. Start typing to see matching properties.', 'maloney-listings'); ?></p>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Prepare property data for autocomplete
                    var properties = [
                        <?php 
                        // Get all rental properties for autocomplete
                        $all_rental_properties_new = get_posts(array(
                            'post_type' => 'listing',
                            'posts_per_page' => -1,
                            'post_status' => 'publish',
                            'tax_query' => array(
                                array(
                                    'taxonomy' => 'listing_type',
                                    'field' => 'slug',
                                    'terms' => 'rental',
                                ),
                            ),
                            'orderby' => 'title',
                            'order' => 'ASC',
                        ));
                        foreach ($all_rental_properties_new as $prop) : 
                            $town = get_post_meta($prop->ID, 'wpcf-city', true);
                            if (empty($town)) {
                                $town = get_post_meta($prop->ID, '_listing_city', true);
                            }
                            $label = $prop->post_title;
                            if ($town) {
                                $label .= ' (' . $town . ')';
                            }
                        ?>
                        {
                            label: '<?php echo esc_js($label); ?>',
                            value: '<?php echo esc_js($prop->post_title); ?>',
                            id: <?php echo $prop->ID; ?>,
                            town: '<?php echo esc_js($town); ?>'
                        },
                        <?php endforeach; ?>
                    ];
                    
                    // Autocomplete for new entry property search
                    $('#property_autocomplete_new').autocomplete({
                        source: function(request, response) {
                            var term = request.term.toLowerCase();
                            var matches = properties.filter(function(item) {
                                return item.label.toLowerCase().indexOf(term) !== -1 || 
                                       item.value.toLowerCase().indexOf(term) !== -1 ||
                                       (item.town && item.town.toLowerCase().indexOf(term) !== -1);
                            });
                            response(matches);
                        },
                        minLength: 1,
                        select: function(event, ui) {
                            event.preventDefault();
                            $('#selected_property_id_new').val(ui.item.id);
                            window.location.href = '<?php echo admin_url('edit.php?post_type=listing&page=add-current-availability'); ?>&property_id=' + ui.item.id;
                        }
                    });
                    
                    // Also handle Enter key on new autocomplete
                    $('#property_autocomplete_new').on('keydown', function(e) {
                        if (e.keyCode === 13) {
                            e.preventDefault();
                            var selectedId = $('#selected_property_id_new').val();
                            if (selectedId) {
                                window.location.href = '<?php echo admin_url('edit.php?post_type=listing&page=add-current-availability'); ?>&property_id=' + selectedId;
                            }
                        }
                    });
                });
                </script>
            <?php else : ?>
                <div class="card" style="max-width: 100% !important; margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="margin: 0;"><?php _e('Filter by Property', 'maloney-listings'); ?></h2>
                        <a href="<?php echo admin_url('edit.php?post_type=listing&page=add-current-availability&add_new=1'); ?>" class="button button-primary" style="margin-left: auto;"><?php _e('+ Add New Current Availability', 'maloney-listings'); ?></a>
                    </div>
                    <p>
                        <label for="property_autocomplete"><?php _e('Search Property:', 'maloney-listings'); ?></label><br>
                        <input type="text" id="property_autocomplete" placeholder="<?php _e('Type to search...', 'maloney-listings'); ?>" style="width: 500px; padding: 8px 12px; font-size: 14px;">
                        <input type="hidden" id="selected_property_id" value="<?php echo esc_attr($selected_property_id); ?>">
                        <a href="<?php echo admin_url('edit.php?post_type=listing&page=add-current-availability'); ?>" class="button" style="margin-left: 10px;"><?php _e('Show All', 'maloney-listings'); ?></a>
                    </p>
                    <p class="description" style="margin-top: 8px; font-style: italic; color: #666;">
                        <?php _e('Search for a property to filter the availability entries below. To add a new availability entry for a property, click the "+ Add New Current Availability" button above.', 'maloney-listings'); ?>
                    </p>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Prepare property data for autocomplete (for filter field)
                    var properties = [
                        <?php 
                        // Get all rental properties for autocomplete
                        if (!isset($all_rental_properties) || empty($all_rental_properties)) {
                            $all_rental_properties = get_posts(array(
                                'post_type' => 'listing',
                                'posts_per_page' => -1,
                                'post_status' => 'publish',
                                'tax_query' => array(
                                    array(
                                        'taxonomy' => 'listing_type',
                                        'field' => 'slug',
                                        'terms' => 'rental',
                                    ),
                                ),
                                'orderby' => 'title',
                                'order' => 'ASC',
                            ));
                        }
                        foreach ($all_rental_properties as $prop) : 
                            $town = get_post_meta($prop->ID, 'wpcf-city', true);
                            if (empty($town)) {
                                $town = get_post_meta($prop->ID, '_listing_city', true);
                            }
                            $label = $prop->post_title;
                            if ($town) {
                                $label .= ' (' . $town . ')';
                            }
                        ?>
                        {
                            label: '<?php echo esc_js($label); ?>',
                            value: '<?php echo esc_js($prop->post_title); ?>',
                            id: <?php echo $prop->ID; ?>,
                            town: '<?php echo esc_js($town); ?>'
                        },
                        <?php endforeach; ?>
                    ];
                    
                    // Autocomplete for property search (filter view) - always initialize if field exists
                    if ($('#property_autocomplete').length) {
                        $('#property_autocomplete').autocomplete({
                            source: function(request, response) {
                                var term = request.term.toLowerCase();
                                var matches = properties.filter(function(item) {
                                    return item.label.toLowerCase().indexOf(term) !== -1 || 
                                           item.value.toLowerCase().indexOf(term) !== -1 ||
                                           (item.town && item.town.toLowerCase().indexOf(term) !== -1);
                                });
                                response(matches);
                            },
                            minLength: 1,
                            select: function(event, ui) {
                                $('#selected_property_id').val(ui.item.id);
                                window.location.href = '<?php echo admin_url('edit.php?post_type=listing&page=add-current-availability'); ?>&property_id=' + ui.item.id;
                            }
                        });
                    }
                });
                </script>
            <?php endif; ?>
            
            <div class="card" style="margin-top: 20px; overflow-x: auto; width: 100% !important; max-width: 100% !important;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2 style="margin: 0;"><?php echo $selected_property ? esc_html($selected_property->post_title) . ' - ' : ''; ?><?php _e('All Availability Entries', 'maloney-listings'); ?> (<?php echo count($all_availability); ?>)</h2>
                </div>
                
                <?php if (!empty($all_availability)) : ?>
                    <table class="fixed wp-list-table widefat striped availability-table" style="border-collapse: collapse; width: 100%; table-layout: auto;">
                        <thead>
                            <tr style="background: #f0f0f1;">
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600; cursor: pointer;" data-sort="property" class="sortable">
                                    <?php _e('Property', 'maloney-listings'); ?> <span class="sort-indicator">▼</span>
                                </th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600; cursor: pointer;" data-sort="town" class="sortable">
                                    <?php _e('Town', 'maloney-listings'); ?> <span class="sort-indicator"></span>
                                </th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600; cursor: pointer;" data-sort="bedrooms" class="sortable">
                                    <?php _e('Unit Size', 'maloney-listings'); ?> <span class="sort-indicator"></span>
                                </th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600; cursor: pointer;" data-sort="rent" class="sortable">
                                    <?php _e('Rent', 'maloney-listings'); ?> <span class="sort-indicator"></span>
                                </th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600; cursor: pointer;" data-sort="minimum_income" class="sortable">
                                    <?php _e('Min Income', 'maloney-listings'); ?> <span class="sort-indicator"></span>
                                </th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600; cursor: pointer;" data-sort="income_limit" class="sortable">
                                    <?php _e('Income Limit', 'maloney-listings'); ?> <span class="sort-indicator"></span>
                                </th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600; cursor: pointer;" data-sort="type" class="sortable">
                                    <?php _e('Type', 'maloney-listings'); ?> <span class="sort-indicator"></span>
                                </th>
                                <th style="padding: 12px; text-align: center; border-bottom: 2px solid #c3c4c7; font-weight: 600; cursor: pointer;" data-sort="units_available" class="sortable">
                                    <?php _e('Units Available', 'maloney-listings'); ?> <span class="sort-indicator"></span>
                                </th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600; max-width: 300px;"><?php _e('Accessible Units', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: center; border-bottom: 2px solid #c3c4c7; font-weight: 600;"><?php _e('Actions', 'maloney-listings'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_availability as $entry) : 
                                $property_id = isset($entry['source_post_id']) ? $entry['source_post_id'] : (isset($entry['property_id']) ? $entry['property_id'] : 0);
                                if (empty($entry['property']) && $property_id) {
                                    $prop = get_post($property_id);
                                    $entry['property'] = $prop ? $prop->post_title : '';
                                }
                                
                                // Get post date for sorting
                                $post_date = '';
                                if ($property_id) {
                                    $prop_post = get_post($property_id);
                                    $post_date = $prop_post ? $prop_post->post_date : '';
                                }
                            ?>
                                <tr style="border-bottom: 1px solid #c3c4c7;" data-property="<?php echo esc_attr(strtolower($entry['property'])); ?>" data-town="<?php echo esc_attr(strtolower($entry['town'])); ?>" data-bedrooms="<?php echo esc_attr($entry['bedrooms']); ?>" data-rent="<?php echo esc_attr(floatval($entry['rent'])); ?>" data-minimum-income="<?php echo esc_attr(floatval($entry['minimum_income'])); ?>" data-income-limit="<?php echo esc_attr($entry['income_limit']); ?>" data-type="<?php echo esc_attr(strtolower($entry['type'])); ?>" data-units-available="<?php echo esc_attr(intval($entry['units_available'])); ?>" data-post-date="<?php echo esc_attr($post_date); ?>">
                                    <td style="padding: 12px; font-weight: 600;">
                                        <?php 
                                        $entry_property_link = '';
                                        if ($property_id) {
                                            $entry_property_link = get_permalink($property_id);
                                        } elseif (!empty($entry['view_apply'])) {
                                            $entry_property_link = $entry['view_apply'];
                                        }
                                        if (!empty($entry_property_link)) : ?>
                                            <strong><a href="<?php echo esc_url($entry_property_link); ?>" target="_blank" style="color: #2271b1; text-decoration: none;"><?php echo esc_html($entry['property']); ?></a></strong>
                                        <?php else : ?>
                                            <strong style="color: #2271b1;"><?php echo esc_html($entry['property']); ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px;"><?php echo esc_html($entry['town']); ?></td>
                                    <td style="padding: 12px;"><?php echo esc_html($entry['bedrooms']); ?></td>
                                    <td style="padding: 12px;"><?php echo $entry['rent'] ? '$' . number_format(floatval($entry['rent']), 0) : '<span style="color: #999;">—</span>'; ?></td>
                                    <td style="padding: 12px;"><?php echo $entry['minimum_income'] ? '$' . number_format(floatval($entry['minimum_income']), 0) : '<span style="color: #999;">—</span>'; ?></td>
                                    <td style="padding: 12px;"><?php echo !empty($entry['income_limit']) ? esc_html($entry['income_limit']) : '<span style="color: #999;">—</span>'; ?></td>
                                    <td style="padding: 12px;"><?php echo !empty($entry['type']) ? esc_html($entry['type']) : '<span style="color: #999;">—</span>'; ?></td>
                                    <td style="padding: 12px; text-align: center;"><strong style="color: #2271b1; font-size: 16px;"><?php echo esc_html($entry['units_available']); ?></strong></td>
                                    <td style="padding: 12px; max-width: 300px; word-wrap: break-word; font-size: 13px; line-height: 1.4;"><?php echo !empty($entry['accessible_units']) && $entry['accessible_units'] !== '0' ? esc_html($entry['accessible_units']) : '0'; ?></td>
                                    <td style="padding: 12px; text-align: center;">
                                        <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                                            <a href="<?php echo admin_url('edit.php?post_type=listing&page=add-current-availability&property_id=' . $property_id); ?>" class="button button-small"><?php _e('Edit', 'maloney-listings'); ?></a>
                                            <?php if (!empty($entry['view_apply'])) : ?>
                                                <a href="<?php echo esc_url($entry['view_apply']); ?>" target="_blank" class="button button-small"><?php _e('View', 'maloney-listings'); ?></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php _e('No availability entries found.', 'maloney-listings'); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($add_new && !$selected_property_id) : ?>
                <div class="card" style="max-width: 800px; margin-top: 20px;">
                    <h2><?php _e('Select Property to Add Availability', 'maloney-listings'); ?></h2>
                    <p>
                        <label for="property_autocomplete_new"><?php _e('Search Property:', 'maloney-listings'); ?></label><br>
                        <input type="text" id="property_autocomplete_new" placeholder="<?php _e('Type to search...', 'maloney-listings'); ?>" style="width: 400px; padding: 6px 10px; font-size: 14px;">
                    </p>
                    <p class="description"><?php _e('Search for a rental property to add availability entries.', 'maloney-listings'); ?></p>
                </div>
            <?php elseif ($selected_property) : 
                $property_town = get_post_meta($selected_property_id, 'wpcf-city', true);
                if (empty($property_town)) {
                    $property_town = get_post_meta($selected_property_id, '_listing_city', true);
                }
                $property_link = get_permalink($selected_property_id);
            ?>
                <div class="card" style="max-width: 1200px; margin-top: 20px;">
                    <h2><?php echo esc_html($selected_property->post_title); ?> - <?php _e('Current Availability', 'maloney-listings'); ?></h2>
                    <p><strong><?php _e('Property:', 'maloney-listings'); ?></strong> <?php echo esc_html($selected_property->post_title); ?></p>
                    <p><strong><?php _e('Town:', 'maloney-listings'); ?></strong> <?php echo esc_html($property_town); ?></p>
                    <p><strong><?php _e('Link:', 'maloney-listings'); ?></strong> <a href="<?php echo esc_url($property_link); ?>" target="_blank"><?php echo esc_html($property_link); ?></a></p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('save_availability_action', 'save_availability_nonce'); ?>
                        <input type="hidden" name="property_id" value="<?php echo esc_attr($selected_property_id); ?>">
                        
                        <div id="availability_entries">
                            <?php if (!empty($all_availability)) : ?>
                                <?php foreach ($all_availability as $index => $entry) : ?>
                                    <div class="availability-entry" style="border: 1px solid #ddd; margin: 10px 0; background: #f9f9f9;">
                                        <h3 style="margin: 0; padding: 15px; background: #e5e5e5; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center;" class="entry-header">
                                            <span style="display: flex; align-items: center; gap: 10px;">
                                                <span class="entry-toggle" style="font-size: 12px; color: #666;">▼</span>
                                                <?php _e('Entry', 'maloney-listings'); ?> #<?php echo ($index + 1); ?>
                                            </span>
                                            <button type="button" class="button remove-entry" style="margin: 0;"><?php _e('Remove', 'maloney-listings'); ?></button>
                                        </h3>
                                        <div class="entry-content" style="padding: 15px; display: block;">
                                        <table class="form-table">
                                            <tr>
                                                <th><label><?php _e('Unit Size', 'maloney-listings'); ?></label></th>
                                                <td>
                                                    <select name="availability[<?php echo $index; ?>][bedrooms]" required>
                                                        <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                                        <?php
                                                        $options_found_add_page = false;
                                                        $saved_value_add_page = isset($entry['bedrooms']) ? $entry['bedrooms'] : '';
                                                        $found_saved_value_add_page = false;
                                                        
                                                        // Get all available unit size options from the Toolset field
                                                        if (function_exists('wpcf_admin_fields_get_fields')) {
                                                            $fields_add_page = wpcf_admin_fields_get_fields();
                                                            if (isset($fields_add_page['availability-bedrooms']) && isset($fields_add_page['availability-bedrooms']['data']['options']) && !empty($fields_add_page['availability-bedrooms']['data']['options'])) {
                                                                foreach ($fields_add_page['availability-bedrooms']['data']['options'] as $key => $option) {
                                                                    $value = is_array($option) && isset($option['value']) ? $option['value'] : (is_array($option) && isset($option['title']) ? $option['title'] : $key);
                                                                    $label = is_array($option) && isset($option['title']) ? $option['title'] : (is_array($option) && isset($option['value']) ? $option['value'] : $key);
                                                                    
                                                                    // Check if this is the saved value
                                                                    if ($value === $saved_value_add_page || $label === $saved_value_add_page) {
                                                                        $found_saved_value_add_page = true;
                                                                    }
                                                                    
                                                                    ?>
                                                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($entry['bedrooms'], $value); ?>><?php echo esc_html($label); ?></option>
                                                                    <?php
                                                                    $options_found_add_page = true;
                                                                }
                                                            }
                                                        }
                                                        
                                                        // If saved value exists but wasn't found in options, add it
                                                        if (!empty($saved_value_add_page) && !$found_saved_value_add_page) {
                                                            ?>
                                                            <option value="<?php echo esc_attr($saved_value_add_page); ?>" selected><?php echo esc_html($saved_value_add_page); ?></option>
                                                            <?php
                                                        }
                                                        
                                                        // Fallback to default options if Toolset field not found or has no options
                                                        if (!$options_found_add_page) {
                                                            $default_options = array('Studio', '1-Bedroom', '2-Bedroom', '3-Bedroom', '4-Bedroom', '4+ Bedroom', '5-Bedroom', '6-Bedroom');
                                                            foreach ($default_options as $opt) {
                                                                ?>
                                                                <option value="<?php echo esc_attr($opt); ?>" <?php selected($entry['bedrooms'], $opt); ?>><?php echo esc_html($opt); ?></option>
                                                                <?php
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Bathrooms', 'maloney-listings'); ?></label></th>
                                                <td>
                                                    <select name="availability[<?php echo $index; ?>][bathrooms]">
                                                        <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                                        <option value="1" <?php selected($entry['bathrooms'], '1'); ?>>1</option>
                                                        <option value="1.5" <?php selected($entry['bathrooms'], '1.5'); ?>>1.5</option>
                                                        <option value="2" <?php selected($entry['bathrooms'], '2'); ?>>2</option>
                                                        <option value="2.5" <?php selected($entry['bathrooms'], '2.5'); ?>>2.5</option>
                                                        <option value="3" <?php selected($entry['bathrooms'], '3'); ?>>3</option>
                                                        <option value="3.5" <?php selected($entry['bathrooms'], '3.5'); ?>>3.5</option>
                                                        <option value="4" <?php selected($entry['bathrooms'], '4'); ?>>4</option>
                                                        <option value="4.5" <?php selected($entry['bathrooms'], '4.5'); ?>>4.5</option>
                                                        <option value="5+" <?php selected($entry['bathrooms'], '5+'); ?>>5+</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Total Monthly Leasing Price', 'maloney-listings'); ?></label></th>
                                                <td><input type="number" name="availability[<?php echo $index; ?>][rent]" value="<?php echo esc_attr($entry['rent']); ?>" step="0.01"></td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Minimum Income', 'maloney-listings'); ?></label></th>
                                                <td><input type="number" name="availability[<?php echo $index; ?>][minimum_income]" value="<?php echo esc_attr($entry['minimum_income']); ?>" step="0.01"></td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Income Limit (AMI %)', 'maloney-listings'); ?></label></th>
                                                <td>
                                                    <select name="availability[<?php echo $index; ?>][income_limit]">
                                                        <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                                        <?php
                                                        $income_limit_terms = get_terms(array(
                                                            'taxonomy' => 'income_limit',
                                                            'hide_empty' => false,
                                                            'orderby' => 'name',
                                                            'order' => 'ASC',
                                                        ));
                                                        if (!is_wp_error($income_limit_terms) && !empty($income_limit_terms)) {
                                                            foreach ($income_limit_terms as $term) {
                                                                ?>
                                                                <option value="<?php echo esc_attr($term->name); ?>" <?php selected($entry['income_limit'], $term->name); ?>><?php echo esc_html($term->name); ?></option>
                                                                <?php
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <?php
                                            // Concessions field (only show if enabled in settings)
                                            $settings = Maloney_Listings_Settings::get_setting(null, array());
                                            $enable_concessions = isset($settings['enable_concessions_filter']) ? $settings['enable_concessions_filter'] === '1' : false;
                                            if ($enable_concessions) :
                                                $concessions_terms = get_terms(array(
                                                    'taxonomy' => 'concessions',
                                                    'hide_empty' => false,
                                                    'orderby' => 'name',
                                                    'order' => 'ASC',
                                                ));
                                                if (!is_wp_error($concessions_terms) && !empty($concessions_terms)) :
                                                    // Get existing concessions for this entry
                                                    $entry_concessions = isset($entry['concessions']) ? (is_array($entry['concessions']) ? $entry['concessions'] : array($entry['concessions'])) : array();
                                            ?>
                                            <tr>
                                                <th><label><?php _e('Concessions', 'maloney-listings'); ?></label></th>
                                                <td>
                                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                                        <?php foreach ($concessions_terms as $term) : ?>
                                                            <label style="display: flex; align-items: center; gap: 8px;">
                                                                <input type="checkbox" name="availability[<?php echo $index; ?>][concessions][]" value="<?php echo esc_attr($term->term_id); ?>" <?php checked(in_array($term->term_id, $entry_concessions)); ?> />
                                                                <?php echo esc_html($term->name); ?>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
                                                endif;
                                            endif;
                                            ?>
                                            <tr>
                                                <th><label><?php _e('Type', 'maloney-listings'); ?></label></th>
                                                <td>
                                                    <select name="availability[<?php echo $index; ?>][type]">
                                                        <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                                        <option value="Lottery" <?php selected($entry['type'], 'Lottery'); ?>>Lottery</option>
                                                        <option value="FCFS" <?php selected($entry['type'], 'FCFS'); ?>>FCFS</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Units Available', 'maloney-listings'); ?></label></th>
                                                <td><input type="number" name="availability[<?php echo $index; ?>][units_available]" value="<?php echo esc_attr($entry['units_available']); ?>" min="0" required></td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Accessible Units', 'maloney-listings'); ?></label></th>
                                                <td><textarea name="availability[<?php echo $index; ?>][accessible_units]" rows="3" style="width: 100%;"><?php echo esc_textarea($entry['accessible_units']); ?></textarea></td>
                                            </tr>
                                        </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <p>
                            <button type="button" id="add_entry" class="button button-primary"><?php _e('+ Add Entry', 'maloney-listings'); ?></button>
                        </p>
                        
                        <p class="submit">
                            <input type="submit" name="save_availability" class="button button-primary" value="<?php _e('Save Availability', 'maloney-listings'); ?>">
                        </p>
                    </form>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Get unit size options for JavaScript
                    var unitSizeOptionsAddPage = [
                        <?php
                        $unit_size_options_add_page_js = array();
                        if (function_exists('wpcf_admin_fields_get_fields')) {
                            $fields_add_page_js_var = wpcf_admin_fields_get_fields();
                            if (isset($fields_add_page_js_var['availability-bedrooms']) && isset($fields_add_page_js_var['availability-bedrooms']['data']['options']) && !empty($fields_add_page_js_var['availability-bedrooms']['data']['options'])) {
                                foreach ($fields_add_page_js_var['availability-bedrooms']['data']['options'] as $key => $option) {
                                    $value = is_array($option) && isset($option['value']) ? $option['value'] : (is_array($option) && isset($option['title']) ? $option['title'] : $key);
                                    $label = is_array($option) && isset($option['title']) ? $option['title'] : (is_array($option) && isset($option['value']) ? $option['value'] : $key);
                                    $unit_size_options_add_page_js[] = array('value' => $value, 'label' => $label);
                                }
                            }
                        }
                        // Fallback to default options if Toolset field not found or has no options
                        if (empty($unit_size_options_add_page_js)) {
                            $default_options = array('Studio', '1-Bedroom', '2-Bedroom', '3-Bedroom', '4-Bedroom', '4+ Bedroom', '5-Bedroom', '6-Bedroom');
                            foreach ($default_options as $opt) {
                                $unit_size_options_add_page_js[] = array('value' => $opt, 'label' => $opt);
                            }
                        }
                        foreach ($unit_size_options_add_page_js as $opt) {
                            echo '{value: "' . esc_js($opt['value']) . '", label: "' . esc_js($opt['label']) . '"},';
                        }
                        ?>
                    ];
                    
                    // Prepare property data for autocomplete
                    var properties = [
                        <?php 
                        // Get all rental properties for autocomplete (if not already loaded)
                        if (!isset($all_rental_properties) || empty($all_rental_properties)) {
                            $all_rental_properties = get_posts(array(
                                'post_type' => 'listing',
                                'posts_per_page' => -1,
                                'post_status' => 'publish',
                                'tax_query' => array(
                                    array(
                                        'taxonomy' => 'listing_type',
                                        'field' => 'slug',
                                        'terms' => 'rental',
                                    ),
                                ),
                                'orderby' => 'title',
                                'order' => 'ASC',
                            ));
                        }
                        foreach ($all_rental_properties as $prop) : 
                            $town = get_post_meta($prop->ID, 'wpcf-city', true);
                            if (empty($town)) {
                                $town = get_post_meta($prop->ID, '_listing_city', true);
                            }
                            $label = $prop->post_title;
                            if ($town) {
                                $label .= ' (' . $town . ')';
                            }
                        ?>
                        {
                            label: '<?php echo esc_js($label); ?>',
                            value: '<?php echo esc_js($prop->post_title); ?>',
                            id: <?php echo $prop->ID; ?>,
                            town: '<?php echo esc_js($town); ?>'
                        },
                        <?php endforeach; ?>
                    ];
                    
                    // Autocomplete for property search (filter view)
                    $('#property_autocomplete').autocomplete({
                        source: function(request, response) {
                            var term = request.term.toLowerCase();
                            var matches = properties.filter(function(item) {
                                return item.label.toLowerCase().indexOf(term) !== -1 || 
                                       item.value.toLowerCase().indexOf(term) !== -1 ||
                                       (item.town && item.town.toLowerCase().indexOf(term) !== -1);
                            });
                            response(matches);
                        },
                        minLength: 1,
                        select: function(event, ui) {
                            $('#selected_property_id').val(ui.item.id);
                            window.location.href = '<?php echo admin_url('edit.php?post_type=listing&page=add-current-availability'); ?>&property_id=' + ui.item.id;
                        }
                    });
                    
                    // Autocomplete for new entry property search
                    $('#property_autocomplete_new').autocomplete({
                        source: function(request, response) {
                            var term = request.term.toLowerCase();
                            var matches = properties.filter(function(item) {
                                return item.label.toLowerCase().indexOf(term) !== -1 || 
                                       item.value.toLowerCase().indexOf(term) !== -1 ||
                                       (item.town && item.town.toLowerCase().indexOf(term) !== -1);
                            });
                            response(matches);
                        },
                        minLength: 1,
                        select: function(event, ui) {
                            event.preventDefault();
                            $('#selected_property_id_new').val(ui.item.id);
                            window.location.href = '<?php echo admin_url('edit.php?post_type=listing&page=add-current-availability'); ?>&property_id=' + ui.item.id;
                        }
                    });
                    
                    // Also handle Enter key on new autocomplete
                    $('#property_autocomplete_new').on('keydown', function(e) {
                        if (e.keyCode === 13) {
                            e.preventDefault();
                            var selectedId = $('#selected_property_id_new').val();
                            if (selectedId) {
                                window.location.href = '<?php echo admin_url('edit.php?post_type=listing&page=add-current-availability'); ?>&property_id=' + selectedId;
                            }
                        }
                    });
                    
                    // Table sorting
                    var currentSort = { column: 'property', direction: 'asc' };
                    var $table = $('.availability-table');
                    var $tbody = $table.find('tbody');
                    var $rows = $tbody.find('tr').toArray();
                    
                    function updateSortIndicators(column, direction) {
                        $table.find('th.sortable .sort-indicator').text('');
                        var $th = $table.find('th[data-sort="' + column + '"]');
                        $th.find('.sort-indicator').text(direction === 'asc' ? '▲' : '▼');
                    }
                    
                    function sortTable(column, direction) {
                        $rows.sort(function(a, b) {
                            var $a = $(a);
                            var $b = $(b);
                            var valA, valB;
                            
                            if (column === 'property') {
                                valA = $a.data('property') || '';
                                valB = $b.data('property') || '';
                            } else if (column === 'town') {
                                valA = $a.data('town') || '';
                                valB = $b.data('town') || '';
                            } else if (column === 'bedrooms') {
                                valA = $a.data('bedrooms') || '';
                                valB = $b.data('bedrooms') || '';
                            } else if (column === 'rent') {
                                valA = parseFloat($a.data('rent')) || 0;
                                valB = parseFloat($b.data('rent')) || 0;
                            } else if (column === 'minimum_income') {
                                valA = parseFloat($a.data('minimum-income')) || 0;
                                valB = parseFloat($b.data('minimum-income')) || 0;
                            } else if (column === 'income_limit') {
                                valA = $a.data('income-limit') || '';
                                valB = $b.data('income-limit') || '';
                            } else if (column === 'type') {
                                valA = $a.data('type') || '';
                                valB = $b.data('type') || '';
                            } else if (column === 'units_available') {
                                valA = parseInt($a.data('units-available')) || 0;
                                valB = parseInt($b.data('units-available')) || 0;
                            } else {
                                return 0;
                            }
                            
                            // For property, also sort by post date as secondary
                            if (column === 'property') {
                                var dateA = $a.data('post-date') || '';
                                var dateB = $b.data('post-date') || '';
                                if (valA === valB && dateA && dateB) {
                                    return direction === 'asc' ? dateB.localeCompare(dateA) : dateA.localeCompare(dateB);
                                }
                            }
                            
                            if (typeof valA === 'string') {
                                return direction === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                            } else {
                                return direction === 'asc' ? valA - valB : valB - valA;
                            }
                        });
                        
                        $tbody.empty().append($rows);
                        updateSortIndicators(column, direction);
                        currentSort = { column: column, direction: direction };
                    }
                    
                    // Set default sort
                    updateSortIndicators('property', 'asc');
                    
                    // Handle column header clicks
                    $table.find('th.sortable').on('click', function() {
                        var column = $(this).data('sort');
                        var direction = (currentSort.column === column && currentSort.direction === 'asc') ? 'desc' : 'asc';
                        sortTable(column, direction);
                    });
                    
                    // Get unit size options for JavaScript
                    var unitSizeOptionsAddPage = [
                        <?php
                        $unit_size_options_add_page = array();
                        if (function_exists('wpcf_admin_fields_get_fields')) {
                            $fields_add_page_js = wpcf_admin_fields_get_fields();
                            if (isset($fields_add_page_js['availability-bedrooms']) && isset($fields_add_page_js['availability-bedrooms']['data']['options']) && !empty($fields_add_page_js['availability-bedrooms']['data']['options'])) {
                                foreach ($fields_add_page_js['availability-bedrooms']['data']['options'] as $key => $option) {
                                    $value = is_array($option) && isset($option['value']) ? $option['value'] : (is_array($option) && isset($option['title']) ? $option['title'] : $key);
                                    $label = is_array($option) && isset($option['title']) ? $option['title'] : (is_array($option) && isset($option['value']) ? $option['value'] : $key);
                                    $unit_size_options_add_page[] = array('value' => $value, 'label' => $label);
                                }
                            }
                        }
                        // Fallback to default options if Toolset field not found or has no options
                        if (empty($unit_size_options_add_page)) {
                            $default_options = array('Studio', '1-Bedroom', '2-Bedroom', '3-Bedroom', '4-Bedroom', '4+ Bedroom', '5-Bedroom', '6-Bedroom');
                            foreach ($default_options as $opt) {
                                $unit_size_options_add_page[] = array('value' => $opt, 'label' => $opt);
                            }
                        }
                        foreach ($unit_size_options_add_page as $opt) {
                            echo '{value: "' . esc_js($opt['value']) . '", label: "' . esc_js($opt['label']) . '"},';
                        }
                        ?>
                    ];
                    
                    var entryIndex = <?php echo !empty($all_availability) ? count($all_availability) : 0; ?>;
                    
                        // Collapsible entry functionality
                        $(document).on('click', '.entry-header', function(e) {
                            if ($(e.target).hasClass('remove-entry') || $(e.target).closest('.remove-entry').length) {
                                return; // Don't toggle if clicking remove button
                            }
                            var $entry = $(this).closest('.availability-entry');
                            var $content = $entry.find('.entry-content');
                            var $toggle = $(this).find('.entry-toggle');
                            
                            if ($content.is(':visible')) {
                                $content.slideUp(200);
                                $toggle.text('▶');
                            } else {
                                $content.slideDown(200);
                                $toggle.text('▼');
                            }
                        });
                        
                        $('#add_entry').on('click', function() {
                            var entryHtml = '<div class="availability-entry" style="border: 1px solid #ddd; margin: 10px 0; background: #f9f9f9;">' +
                                '<h3 style="margin: 0; padding: 15px; background: #e5e5e5; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center;" class="entry-header">' +
                                '<span style="display: flex; align-items: center; gap: 10px;">' +
                                '<span class="entry-toggle" style="font-size: 12px; color: #666;">▼</span>' +
                                '<?php _e('Entry', 'maloney-listings'); ?> #' + (entryIndex + 1) +
                                '</span>' +
                                '<button type="button" class="button remove-entry" style="margin: 0;"><?php _e('Remove', 'maloney-listings'); ?></button>' +
                                '</h3>' +
                                '<div class="entry-content" style="padding: 15px; display: block;">' +
                                '<table class="form-table">' +
                            '<tr><th><label><?php _e('Unit Size', 'maloney-listings'); ?></label></th>' +
                            '<td><select name="availability[' + entryIndex + '][bedrooms]" required>' +
                            '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                            (function() {
                                var options = '';
                                if (typeof unitSizeOptionsAddPage !== 'undefined') {
                                    for (var i = 0; i < unitSizeOptionsAddPage.length; i++) {
                                        options += '<option value="' + unitSizeOptionsAddPage[i].value + '">' + unitSizeOptionsAddPage[i].label + '</option>';
                                    }
                                } else {
                                    // Fallback if unitSizeOptionsAddPage not defined
                                    var defaultOptions = ['Studio', '1-Bedroom', '2-Bedroom', '3-Bedroom', '4-Bedroom', '4+ Bedroom', '5-Bedroom', '6-Bedroom'];
                                    for (var j = 0; j < defaultOptions.length; j++) {
                                        options += '<option value="' + defaultOptions[j] + '">' + defaultOptions[j] + '</option>';
                                    }
                                }
                                return options;
                            })() +
                            '</select></td></tr>' +
                            '<tr><th><label><?php _e('Bathrooms', 'maloney-listings'); ?></label></th>' +
                            '<td><select name="availability[' + entryIndex + '][bathrooms]">' +
                            '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                            '<option value="1">1</option>' +
                            '<option value="1.5">1.5</option>' +
                            '<option value="2">2</option>' +
                            '<option value="2.5">2.5</option>' +
                            '<option value="3">3</option>' +
                            '<option value="3.5">3.5</option>' +
                            '<option value="4">4</option>' +
                            '<option value="4.5">4.5</option>' +
                            '<option value="5+">5+</option>' +
                            '</select></td></tr>' +
                            '<tr><th><label><?php _e('Total Monthly Leasing Price', 'maloney-listings'); ?></label></th>' +
                            '<td><input type="number" name="availability[' + entryIndex + '][rent]" step="0.01"></td></tr>' +
                            '<tr><th><label><?php _e('Minimum Income', 'maloney-listings'); ?></label></th>' +
                            '<td><input type="number" name="availability[' + entryIndex + '][minimum_income]" step="0.01"></td></tr>' +
                            '<tr><th><label><?php _e('Income Limit (AMI %)', 'maloney-listings'); ?></label></th>' +
                            '<td><select name="availability[' + entryIndex + '][income_limit]">' +
                            '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                            (function() {
                                var options = '';
                                for (var i = 0; i < incomeLimitTerms.length; i++) {
                                    options += '<option value="' + incomeLimitTerms[i] + '">' + incomeLimitTerms[i] + '</option>';
                                }
                                return options;
                            })() +
                            '</select></td></tr>' +
                            (enableConcessions && concessionsTerms.length > 0 ? 
                                '<tr><th><label><?php _e('Concessions', 'maloney-listings'); ?></label></th>' +
                                '<td><div style="display: flex; flex-direction: column; gap: 8px;">' +
                                (function() {
                                    var html = '';
                                    for (var i = 0; i < concessionsTerms.length; i++) {
                                        html += '<label style="display: flex; align-items: center; gap: 8px;">' +
                                                '<input type="checkbox" name="availability[' + entryIndex + '][concessions][]" value="' + concessionsTerms[i].id + '" />' +
                                                concessionsTerms[i].name +
                                                '</label>';
                                    }
                                    return html;
                                })() +
                                '</div></td></tr>' : '') +
                            '<tr><th><label><?php _e('Type', 'maloney-listings'); ?></label></th>' +
                            '<td><select name="availability[' + entryIndex + '][type]">' +
                            '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                            '<option value="Lottery">Lottery</option>' +
                            '<option value="FCFS">FCFS</option>' +
                            '</select></td></tr>' +
                            '<tr><th><label><?php _e('Units Available', 'maloney-listings'); ?></label></th>' +
                            '<td><input type="number" name="availability[' + entryIndex + '][units_available]" min="0" required></td></tr>' +
                                '<tr><th><label><?php _e('Accessible Units', 'maloney-listings'); ?></label></th>' +
                                '<td><textarea name="availability[' + entryIndex + '][accessible_units]" rows="3" style="width: 100%;"></textarea></td></tr>' +
                                '</table></div></div>';
                        
                        $('#availability_entries').append(entryHtml);
                        entryIndex++;
                    });
                    
                    $(document).on('click', '.remove-entry', function() {
                        $(this).closest('.availability-entry').remove();
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render the Current Condo Listings admin page
     * Similar to render_add_availability_page but for condos
     */
    public function render_add_condo_listings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }
        
        $message = '';
        $message_type = 'info';
        $selected_property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
        $add_new = isset($_GET['add_new']) ? true : false;
        $selected_property = null;
        
        if ($selected_property_id) {
            $selected_property = get_post($selected_property_id);
            if (!$selected_property || $selected_property->post_type !== 'listing') {
                $selected_property = null;
                $selected_property_id = 0;
            }
        }
        
        
        // Handle form submission
        if (isset($_POST['save_condo_listings']) && check_admin_referer('save_condo_listings_action', 'save_condo_listings_nonce')) {
            $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;
            
            if ($property_id) {
                // Get existing condo listing entries
                $entries = isset($_POST['condo_listings']) && is_array($_POST['condo_listings']) ? $_POST['condo_listings'] : array();
                
                // Clear existing repetitive field values
                delete_post_meta($property_id, 'wpcf-condo-listings-property');
                delete_post_meta($property_id, 'wpcf-condo-listings-town');
                delete_post_meta($property_id, 'wpcf-condo-listings-bedrooms');
                delete_post_meta($property_id, 'wpcf-condo-listings-bathrooms');
                delete_post_meta($property_id, 'wpcf-condo-listings-price');
                delete_post_meta($property_id, 'wpcf-condo-listings-income-limit');
                delete_post_meta($property_id, 'wpcf-condo-listings-type');
                delete_post_meta($property_id, 'wpcf-condo-listings-units-available');
                delete_post_meta($property_id, 'wpcf-condo-listings-accessible-units');
                delete_post_meta($property_id, 'wpcf-condo-listings-view-apply');
                
                // Get property data for auto-fill
                $property = get_post($property_id);
                $property_town = get_post_meta($property_id, 'wpcf-city', true);
                if (empty($property_town)) {
                    $property_town = get_post_meta($property_id, '_listing_city', true);
                }
                $property_link = get_permalink($property_id);
                
                // Save new entries
                foreach ($entries as $entry) {
                    if (empty($entry['bedrooms']) || empty($entry['units_available'])) {
                        continue; // Skip incomplete entries
                    }
                    
                    // Auto-fill property, town, and link
                    add_post_meta($property_id, 'wpcf-condo-listings-property', $property_id);
                    add_post_meta($property_id, 'wpcf-condo-listings-town', !empty($entry['town']) ? sanitize_text_field($entry['town']) : $property_town);
                    add_post_meta($property_id, 'wpcf-condo-listings-bedrooms', sanitize_text_field($entry['bedrooms']));
                    if (!empty($entry['bathrooms'])) {
                        add_post_meta($property_id, 'wpcf-condo-listings-bathrooms', sanitize_text_field($entry['bathrooms']));
                    }
                    add_post_meta($property_id, 'wpcf-condo-listings-price', sanitize_text_field($entry['price']));
                    add_post_meta($property_id, 'wpcf-condo-listings-income-limit', sanitize_text_field($entry['income_limit']));
                    add_post_meta($property_id, 'wpcf-condo-listings-type', sanitize_text_field($entry['type']));
                    add_post_meta($property_id, 'wpcf-condo-listings-units-available', intval($entry['units_available']));
                    add_post_meta($property_id, 'wpcf-condo-listings-accessible-units', sanitize_textarea_field($entry['accessible_units']));
                    add_post_meta($property_id, 'wpcf-condo-listings-view-apply', esc_url_raw(!empty($entry['view_apply']) ? $entry['view_apply'] : $property_link));
                }
                
                $message = sprintf(__('Condo listings data saved for %s', 'maloney-listings'), $property->post_title);
                $message_type = 'success';
                $selected_property_id = $property_id;
                $selected_property = $property;
            }
        }
        
        // Get all condo listing entries
        $all_condo_listings = array();
        if (class_exists('Maloney_Listings_Condo_Listings_Fields')) {
            if ($selected_property_id) {
                // Get entries for selected property only
                $all_condo_listings = Maloney_Listings_Condo_Listings_Fields::get_condo_listings_data($selected_property_id);
            } else {
                // Get all entries from all properties
                $all_condo_listings = Maloney_Listings_Condo_Listings_Fields::get_all_condo_listings_entries();
            }
        }
        
        // Sort entries: default by property name, then by date posted
        usort($all_condo_listings, function($a, $b) {
            // First sort by property name
            $prop_a = isset($a['property']) ? strtolower($a['property']) : '';
            $prop_b = isset($b['property']) ? strtolower($b['property']) : '';
            if ($prop_a !== $prop_b) {
                return strcmp($prop_a, $prop_b);
            }
            // If same property, sort by source_post_id (which relates to post date)
            $id_a = isset($a['source_post_id']) ? $a['source_post_id'] : (isset($a['property_id']) ? $a['property_id'] : 0);
            $id_b = isset($b['source_post_id']) ? $b['source_post_id'] : (isset($b['property_id']) ? $b['property_id'] : 0);
            return $id_b - $id_a; // Newer first
        });
        
        // Get all condo properties for autocomplete
        $all_condo_properties = get_posts(array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'listing_type',
                    'field' => 'slug',
                    'terms' => array('condo', 'condominium', 'condominiums'),
                ),
            ),
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Current Condo Listings', 'maloney-listings'); ?></h1>
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!$selected_property_id && !$add_new) : ?>
                <div class="card" style="max-width: 100% !important; margin-top: 20px; margin-bottom: 20px;">
                    <p><?php _e('To migrate condo listings from Ninja Table 3596, go to', 'maloney-listings'); ?> <a href="<?php echo admin_url('edit.php?post_type=listing&page=migrate-condo-listings'); ?>"><?php _e('Listings → Migrate Condo Listings', 'maloney-listings'); ?></a></p>
                </div>
            <?php endif; ?>
            
            <?php if ($add_new && !$selected_property_id) : ?>
                <div class="card" style="max-width: 100% !important; margin-top: 20px;">
                    <h2><?php _e('Select Property to Add Condo Listing', 'maloney-listings'); ?></h2>
                    <p>
                        <label for="property_autocomplete_new"><?php _e('Search Property:', 'maloney-listings'); ?></label><br>
                        <input type="text" id="property_autocomplete_new" placeholder="<?php _e('Type to search...', 'maloney-listings'); ?>" style="width: 500px; padding: 8px 12px; font-size: 14px;">
                        <input type="hidden" id="selected_property_id_new" value="">
                    </p>
                    <p class="description"><?php _e('Search for a condo property to add listing entries. Start typing to see matching properties.', 'maloney-listings'); ?></p>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Prepare property data for autocomplete
                    var properties = [
                        <?php 
                        foreach ($all_condo_properties as $prop) : 
                            $town = get_post_meta($prop->ID, 'wpcf-city', true);
                            if (empty($town)) {
                                $town = get_post_meta($prop->ID, '_listing_city', true);
                            }
                            $label = $prop->post_title;
                            if ($town) {
                                $label .= ' (' . $town . ')';
                            }
                        ?>
                        {
                            label: '<?php echo esc_js($label); ?>',
                            value: '<?php echo esc_js($prop->post_title); ?>',
                            id: <?php echo $prop->ID; ?>,
                            town: '<?php echo esc_js($town); ?>'
                        },
                        <?php endforeach; ?>
                    ];
                    
                    // Autocomplete for new entry property search
                    $('#property_autocomplete_new').autocomplete({
                        source: function(request, response) {
                            var term = request.term.toLowerCase();
                            var matches = properties.filter(function(item) {
                                return item.label.toLowerCase().indexOf(term) !== -1 || 
                                       item.value.toLowerCase().indexOf(term) !== -1 ||
                                       (item.town && item.town.toLowerCase().indexOf(term) !== -1);
                            });
                            response(matches);
                        },
                        minLength: 1,
                        select: function(event, ui) {
                            event.preventDefault();
                            $('#selected_property_id_new').val(ui.item.id);
                            window.location.href = '<?php echo admin_url('edit.php?post_type=listing&page=add-current-condo-listings'); ?>&property_id=' + ui.item.id;
                        }
                    });
                    
                    // Also handle Enter key on new autocomplete
                    $('#property_autocomplete_new').on('keydown', function(e) {
                        if (e.keyCode === 13) {
                            e.preventDefault();
                            var selectedId = $('#selected_property_id_new').val();
                            if (selectedId) {
                                window.location.href = '<?php echo admin_url('edit.php?post_type=listing&page=add-current-condo-listings'); ?>&property_id=' + selectedId;
                            }
                        }
                    });
                });
                </script>
            <?php else : ?>
                <div class="card" style="max-width: 100% !important; margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="margin: 0;"><?php _e('Filter by Property', 'maloney-listings'); ?></h2>
                        <a href="<?php echo admin_url('edit.php?post_type=listing&page=add-current-condo-listings&add_new=1'); ?>" class="button button-primary" style="margin-left: auto;"><?php _e('+ Add New Condo Listing', 'maloney-listings'); ?></a>
                    </div>
                    <p>
                        <label for="property_autocomplete"><?php _e('Search Property:', 'maloney-listings'); ?></label><br>
                        <input type="text" id="property_autocomplete" placeholder="<?php _e('Type to search...', 'maloney-listings'); ?>" style="width: 500px; padding: 8px 12px; font-size: 14px;">
                        <input type="hidden" id="selected_property_id" value="<?php echo esc_attr($selected_property_id); ?>">
                        <a href="<?php echo admin_url('edit.php?post_type=listing&page=add-current-condo-listings'); ?>" class="button" style="margin-left: 10px;"><?php _e('Show All', 'maloney-listings'); ?></a>
                    </p>
                    <p class="description" style="margin-top: 8px; font-style: italic; color: #666;">
                        <?php _e('Search for a property to filter the condo listing entries below. To add a new condo listing entry for a property, click the "+ Add New Condo Listing" button above.', 'maloney-listings'); ?>
                    </p>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Prepare property data for autocomplete (for filter field)
                    var properties = [
                        <?php 
                        foreach ($all_condo_properties as $prop) : 
                            $town = get_post_meta($prop->ID, 'wpcf-city', true);
                            if (empty($town)) {
                                $town = get_post_meta($prop->ID, '_listing_city', true);
                            }
                            $label = $prop->post_title;
                            if ($town) {
                                $label .= ' (' . $town . ')';
                            }
                        ?>
                        {
                            label: '<?php echo esc_js($label); ?>',
                            value: '<?php echo esc_js($prop->post_title); ?>',
                            id: <?php echo $prop->ID; ?>,
                            town: '<?php echo esc_js($town); ?>'
                        },
                        <?php endforeach; ?>
                    ];
                    
                    // Autocomplete for property search (filter view) - always initialize if field exists
                    if ($('#property_autocomplete').length) {
                        $('#property_autocomplete').autocomplete({
                            source: function(request, response) {
                                var term = request.term.toLowerCase();
                                var matches = properties.filter(function(item) {
                                    return item.label.toLowerCase().indexOf(term) !== -1 || 
                                           item.value.toLowerCase().indexOf(term) !== -1 ||
                                           (item.town && item.town.toLowerCase().indexOf(term) !== -1);
                                });
                                response(matches);
                            },
                            minLength: 1,
                            select: function(event, ui) {
                                $('#selected_property_id').val(ui.item.id);
                                window.location.href = '<?php echo admin_url('edit.php?post_type=listing&page=add-current-condo-listings'); ?>&property_id=' + ui.item.id;
                            }
                        });
                    }
                });
                </script>
            <?php endif; ?>
            
            <div class="card" style="margin-top: 20px; overflow-x: auto; width: 100% !important; max-width: 100% !important;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2 style="margin: 0;"><?php echo $selected_property ? esc_html($selected_property->post_title) . ' - ' : ''; ?><?php _e('All Condo Listing Entries', 'maloney-listings'); ?> (<?php echo count($all_condo_listings); ?>)</h2>
                </div>
                
                <?php if (!empty($all_condo_listings)) : ?>
                    <table class="fixed wp-list-table widefat striped condo-listings-table" style="border-collapse: collapse; width: 100%; table-layout: auto;">
                        <thead>
                            <tr style="background: #f0f0f1;">
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600;"><?php _e('Property', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600;"><?php _e('Town', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600;"><?php _e('Unit Size', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600;"><?php _e('Price', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600;"><?php _e('Income Limit', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600;"><?php _e('Type', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: center; border-bottom: 2px solid #c3c4c7; font-weight: 600;"><?php _e('Units Available', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #c3c4c7; font-weight: 600; max-width: 300px;"><?php _e('Accessible Units', 'maloney-listings'); ?></th>
                                <th style="padding: 12px; text-align: center; border-bottom: 2px solid #c3c4c7; font-weight: 600;"><?php _e('Actions', 'maloney-listings'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_condo_listings as $entry) : 
                                $property_id = isset($entry['source_post_id']) ? $entry['source_post_id'] : (isset($entry['property_id']) ? $entry['property_id'] : 0);
                                if (empty($entry['property']) && $property_id) {
                                    $prop = get_post($property_id);
                                    $entry['property'] = $prop ? $prop->post_title : '';
                                }
                            ?>
                                <tr style="border-bottom: 1px solid #c3c4c7;">
                                    <td style="padding: 12px; font-weight: 600;">
                                        <?php 
                                        $entry_property_link = '';
                                        if ($property_id) {
                                            $entry_property_link = get_permalink($property_id);
                                        } elseif (!empty($entry['view_apply'])) {
                                            $entry_property_link = $entry['view_apply'];
                                        }
                                        if (!empty($entry_property_link)) : ?>
                                            <strong><a href="<?php echo esc_url($entry_property_link); ?>" target="_blank" style="color: #2271b1; text-decoration: none;"><?php echo esc_html($entry['property']); ?></a></strong>
                                        <?php else : ?>
                                            <strong style="color: #2271b1;"><?php echo esc_html($entry['property']); ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px;"><?php echo esc_html($entry['town']); ?></td>
                                    <td style="padding: 12px;"><?php echo esc_html($entry['bedrooms']); ?></td>
                                    <td style="padding: 12px;"><?php echo $entry['price'] ? '$' . number_format(floatval($entry['price']), 0) : '<span style="color: #999;">—</span>'; ?></td>
                                    <td style="padding: 12px;"><?php echo !empty($entry['income_limit']) ? esc_html($entry['income_limit']) : '<span style="color: #999;">—</span>'; ?></td>
                                    <td style="padding: 12px;"><?php echo !empty($entry['type']) ? esc_html($entry['type']) : '<span style="color: #999;">—</span>'; ?></td>
                                    <td style="padding: 12px; text-align: center;"><strong style="color: #2271b1; font-size: 16px;"><?php echo esc_html($entry['units_available']); ?></strong></td>
                                    <td style="padding: 12px; max-width: 300px; word-wrap: break-word; font-size: 13px; line-height: 1.4;"><?php echo !empty($entry['accessible_units']) && $entry['accessible_units'] !== '0' ? esc_html($entry['accessible_units']) : '0'; ?></td>
                                    <td style="padding: 12px; text-align: center;">
                                        <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                                            <a href="<?php echo admin_url('edit.php?post_type=listing&page=add-current-condo-listings&property_id=' . $property_id); ?>" class="button button-small"><?php _e('Edit', 'maloney-listings'); ?></a>
                                            <?php if (!empty($entry['view_apply'])) : ?>
                                                <a href="<?php echo esc_url($entry['view_apply']); ?>" target="_blank" class="button button-small"><?php _e('View', 'maloney-listings'); ?></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php _e('No condo listing entries found.', 'maloney-listings'); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($selected_property) : 
                $property_town = get_post_meta($selected_property_id, 'wpcf-city', true);
                if (empty($property_town)) {
                    $property_town = get_post_meta($selected_property_id, '_listing_city', true);
                }
                $property_link = get_permalink($selected_property_id);
            ?>
                <div class="card" style="max-width: 1200px; margin-top: 20px;">
                    <h2><?php echo esc_html($selected_property->post_title); ?> - <?php _e('Current Condo Listings', 'maloney-listings'); ?></h2>
                    <p><strong><?php _e('Property:', 'maloney-listings'); ?></strong> <?php echo esc_html($selected_property->post_title); ?></p>
                    <p><strong><?php _e('Town:', 'maloney-listings'); ?></strong> <?php echo esc_html($property_town); ?></p>
                    <p><strong><?php _e('Link:', 'maloney-listings'); ?></strong> <a href="<?php echo esc_url($property_link); ?>" target="_blank"><?php echo esc_html($property_link); ?></a></p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('save_condo_listings_action', 'save_condo_listings_nonce'); ?>
                        <input type="hidden" name="property_id" value="<?php echo esc_attr($selected_property_id); ?>">
                        
                        <div id="condo_listings_entries">
                            <?php if (!empty($all_condo_listings)) : ?>
                                <?php foreach ($all_condo_listings as $index => $entry) : ?>
                                    <div class="condo-listing-entry" style="border: 1px solid #ddd; margin: 10px 0; background: #f9f9f9;">
                                        <h3 style="margin: 0; padding: 15px; background: #e5e5e5; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center;" class="entry-header">
                                            <span style="display: flex; align-items: center; gap: 10px;">
                                                <span class="entry-toggle" style="font-size: 12px; color: #666;">▼</span>
                                                <?php _e('Entry', 'maloney-listings'); ?> #<?php echo ($index + 1); ?>
                                            </span>
                                            <button type="button" class="button remove-entry" style="margin: 0;"><?php _e('Remove', 'maloney-listings'); ?></button>
                                        </h3>
                                        <div class="entry-content" style="padding: 15px; display: block;">
                                        <table class="form-table">
                                            <tr>
                                                <th><label><?php _e('Town', 'maloney-listings'); ?></label></th>
                                                <td><input type="text" name="condo_listings[<?php echo $index; ?>][town]" value="<?php echo esc_attr($entry['town']); ?>" placeholder="City | Neighborhood" style="width: 100%;"></td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Unit Size', 'maloney-listings'); ?></label></th>
                                                <td>
                                                    <select name="condo_listings[<?php echo $index; ?>][bedrooms]" required>
                                                        <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                                        <?php
                                                        $default_options = array('Studio', '1-Bedroom', '2-Bedroom', '3-Bedroom', '4-Bedroom', '4+ Bedroom', '5-Bedroom', '6-Bedroom');
                                                        foreach ($default_options as $opt) {
                                                            ?>
                                                            <option value="<?php echo esc_attr($opt); ?>" <?php selected($entry['bedrooms'], $opt); ?>><?php echo esc_html($opt); ?></option>
                                                            <?php
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Bathrooms', 'maloney-listings'); ?></label></th>
                                                <td>
                                                    <select name="condo_listings[<?php echo $index; ?>][bathrooms]">
                                                        <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                                        <option value="1" <?php selected($entry['bathrooms'], '1'); ?>>1</option>
                                                        <option value="1.5" <?php selected($entry['bathrooms'], '1.5'); ?>>1.5</option>
                                                        <option value="2" <?php selected($entry['bathrooms'], '2'); ?>>2</option>
                                                        <option value="2.5" <?php selected($entry['bathrooms'], '2.5'); ?>>2.5</option>
                                                        <option value="3" <?php selected($entry['bathrooms'], '3'); ?>>3</option>
                                                        <option value="3.5" <?php selected($entry['bathrooms'], '3.5'); ?>>3.5</option>
                                                        <option value="4" <?php selected($entry['bathrooms'], '4'); ?>>4</option>
                                                        <option value="4.5" <?php selected($entry['bathrooms'], '4.5'); ?>>4.5</option>
                                                        <option value="5+" <?php selected($entry['bathrooms'], '5+'); ?>>5+</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Price', 'maloney-listings'); ?></label></th>
                                                <td><input type="number" name="condo_listings[<?php echo $index; ?>][price]" value="<?php echo esc_attr($entry['price']); ?>" step="0.01"></td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Income Limit (AMI %)', 'maloney-listings'); ?></label></th>
                                                <td>
                                                    <input type="text" name="condo_listings[<?php echo $index; ?>][income_limit]" value="<?php echo esc_attr($entry['income_limit']); ?>" placeholder="e.g., 80% or 80% (Minimum) - 100% (Maximum)" style="width: 100%;">
                                                    <p class="description"><?php _e('Enter income limit as percentage (e.g., "80%") or range (e.g., "80% (Minimum) - 100% (Maximum)")', 'maloney-listings'); ?></p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Type', 'maloney-listings'); ?></label></th>
                                                <td>
                                                    <select name="condo_listings[<?php echo $index; ?>][type]">
                                                        <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                                        <option value="Lottery" <?php selected($entry['type'], 'Lottery'); ?>>Lottery</option>
                                                        <option value="FCFS" <?php selected($entry['type'], 'FCFS'); ?>>FCFS</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Units Available', 'maloney-listings'); ?></label></th>
                                                <td><input type="number" name="condo_listings[<?php echo $index; ?>][units_available]" value="<?php echo esc_attr($entry['units_available']); ?>" min="0" required></td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Accessible Units', 'maloney-listings'); ?></label></th>
                                                <td><textarea name="condo_listings[<?php echo $index; ?>][accessible_units]" rows="3" style="width: 100%;"><?php echo esc_textarea($entry['accessible_units']); ?></textarea></td>
                                            </tr>
                                            <tr>
                                                <th><label><?php _e('Learn More Link', 'maloney-listings'); ?></label></th>
                                                <td><input type="url" name="condo_listings[<?php echo $index; ?>][view_apply]" value="<?php echo esc_attr($entry['view_apply']); ?>" style="width: 100%;"></td>
                                            </tr>
                                        </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <p>
                            <button type="button" id="add_entry" class="button button-primary"><?php _e('+ Add Entry', 'maloney-listings'); ?></button>
                        </p>
                        
                        <p class="submit">
                            <input type="submit" name="save_condo_listings" class="button button-primary" value="<?php _e('Save Condo Listings', 'maloney-listings'); ?>">
                        </p>
                    </form>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Add entry button
                    var entryIndex = <?php echo count($all_condo_listings); ?>;
                    $('#add_entry').on('click', function() {
                        var newEntry = '<div class="condo-listing-entry" style="border: 1px solid #ddd; margin: 10px 0; background: #f9f9f9;">' +
                            '<h3 style="margin: 0; padding: 15px; background: #e5e5e5; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center;" class="entry-header">' +
                            '<span style="display: flex; align-items: center; gap: 10px;">' +
                            '<span class="entry-toggle" style="font-size: 12px; color: #666;">▼</span>' +
                            '<?php _e('Entry', 'maloney-listings'); ?> #' + (entryIndex + 1) +
                            '</span>' +
                            '<button type="button" class="button remove-entry" style="margin: 0;"><?php _e('Remove', 'maloney-listings'); ?></button>' +
                            '</h3>' +
                            '<div class="entry-content" style="padding: 15px; display: block;">' +
                            '<table class="form-table">' +
                            '<tr><th><label><?php _e('Town', 'maloney-listings'); ?></label></th>' +
                            '<td><input type="text" name="condo_listings[' + entryIndex + '][town]" placeholder="City | Neighborhood" style="width: 100%;"></td></tr>' +
                            '<tr><th><label><?php _e('Unit Size', 'maloney-listings'); ?></label></th>' +
                            '<td><select name="condo_listings[' + entryIndex + '][bedrooms]" required>' +
                            '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                            '<option value="Studio">Studio</option>' +
                            '<option value="1-Bedroom">1-Bedroom</option>' +
                            '<option value="2-Bedroom">2-Bedroom</option>' +
                            '<option value="3-Bedroom">3-Bedroom</option>' +
                            '<option value="4-Bedroom">4-Bedroom</option>' +
                            '<option value="4+ Bedroom">4+ Bedroom</option>' +
                            '<option value="5-Bedroom">5-Bedroom</option>' +
                            '<option value="6-Bedroom">6-Bedroom</option>' +
                            '</select></td></tr>' +
                            '<tr><th><label><?php _e('Bathrooms', 'maloney-listings'); ?></label></th>' +
                            '<td><select name="condo_listings[' + entryIndex + '][bathrooms]">' +
                            '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                            '<option value="1">1</option>' +
                            '<option value="1.5">1.5</option>' +
                            '<option value="2">2</option>' +
                            '<option value="2.5">2.5</option>' +
                            '<option value="3">3</option>' +
                            '<option value="3.5">3.5</option>' +
                            '<option value="4">4</option>' +
                            '<option value="4.5">4.5</option>' +
                            '<option value="5+">5+</option>' +
                            '</select></td></tr>' +
                            '<tr><th><label><?php _e('Price', 'maloney-listings'); ?></label></th>' +
                            '<td><input type="number" name="condo_listings[' + entryIndex + '][price]" step="0.01"></td></tr>' +
                            '<tr><th><label><?php _e('Income Limit (AMI %)', 'maloney-listings'); ?></label></th>' +
                            '<td><input type="text" name="condo_listings[' + entryIndex + '][income_limit]" placeholder="e.g., 80% or 80% (Minimum) - 100% (Maximum)" style="width: 100%;"></td></tr>' +
                            '<tr><th><label><?php _e('Type', 'maloney-listings'); ?></label></th>' +
                            '<td><select name="condo_listings[' + entryIndex + '][type]">' +
                            '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                            '<option value="Lottery">Lottery</option>' +
                            '<option value="FCFS">FCFS</option>' +
                            '</select></td></tr>' +
                            '<tr><th><label><?php _e('Units Available', 'maloney-listings'); ?></label></th>' +
                            '<td><input type="number" name="condo_listings[' + entryIndex + '][units_available]" min="0" required></td></tr>' +
                            '<tr><th><label><?php _e('Accessible Units', 'maloney-listings'); ?></label></th>' +
                            '<td><textarea name="condo_listings[' + entryIndex + '][accessible_units]" rows="3" style="width: 100%;"></textarea></td></tr>' +
                            '<tr><th><label><?php _e('Learn More Link', 'maloney-listings'); ?></label></th>' +
                            '<td><input type="url" name="condo_listings[' + entryIndex + '][view_apply]" style="width: 100%;"></td></tr>' +
                            '</table></div></div>';
                        $('#condo_listings_entries').append(newEntry);
                        entryIndex++;
                    });
                    
                    // Remove entry button
                    $(document).on('click', '.remove-entry', function() {
                        $(this).closest('.condo-listing-entry').remove();
                    });
                    
                    // Toggle entry content
                    $(document).on('click', '.entry-header', function() {
                        $(this).siblings('.entry-content').slideToggle();
                        $(this).find('.entry-toggle').text($(this).find('.entry-toggle').text() === '▼' ? '▲' : '▼');
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function render_available_units_migration_page() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }
        
        $message = '';
        $message_type = 'info';
        $results = null;
        $preview_not_imported = null;
        
        // Preview units that couldn't be imported (without running migration)
        if (class_exists('Maloney_Listings_Available_Units_Migration')) {
            $preview_migration = new Maloney_Listings_Available_Units_Migration();
            $preview_data = $this->preview_not_importable_units($preview_migration);
            if (!empty($preview_data)) {
                $preview_not_imported = $preview_data;
            }
        }
        
        // Auto-create fields if they don't exist
        if (class_exists('Maloney_Listings_Available_Units_Fields')) {
            $fields_setup = new Maloney_Listings_Available_Units_Fields();
            if (!$fields_setup->fields_exist()) {
                $fields_result = $fields_setup->create_fields();
                if ($fields_result['success']) {
                    if ($fields_result['created'] > 0) {
                        $message = sprintf(
                            __('Created %d custom fields for available units.', 'maloney-listings'),
                            $fields_result['created']
                        );
                        $message_type = 'success';
                    }
                    if (!empty($fields_result['errors'])) {
                        $message .= ' ' . implode(' ', $fields_result['errors']);
                        if ($message_type === 'success') {
                            $message_type = 'warning';
                        }
                    }
                } else {
                    $message = $fields_result['message'];
                    $message_type = 'error';
                }
            }
        }
        
        // Handle migration if form submitted
        if (isset($_POST['migrate_available_units']) && check_admin_referer('migrate_available_units_action', 'migrate_available_units_nonce')) {
            if (class_exists('Maloney_Listings_Available_Units_Migration')) {
                $migration = new Maloney_Listings_Available_Units_Migration();
                $results = $migration->run_migration();
                
                if ($results['updated'] > 0) {
                    $message = sprintf(
                        __('Migration completed! Processed %d properties, updated %d listings. %d properties not found.', 'maloney-listings'),
                        $results['processed'],
                        $results['updated'],
                        $results['not_found']
                    );
                    $message_type = 'success';
                } else {
                    $message = __('Migration completed but no listings were updated.', 'maloney-listings');
                    $message_type = 'warning';
                }
                
                if (!empty($results['errors'])) {
                    $message .= ' ' . __('Some errors occurred during migration.', 'maloney-listings');
                }
            } else {
                $message = __('Migration class not found.', 'maloney-listings');
                $message_type = 'error';
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Migrate Available Units from Ninja Table', 'maloney-listings'); ?></h1>
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($results && !empty($results['errors'])) : ?>
                <div class="notice notice-warning">
                    <p><strong><?php _e('Errors:', 'maloney-listings'); ?></strong></p>
                    <ul>
                        <?php foreach ($results['errors'] as $error) : ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php _e('Migrate Available Units Data', 'maloney-listings'); ?></h2>
                <p><?php _e('This will migrate available units data from Ninja Table 790 (Current Rental Availability) to the listing custom fields.', 'maloney-listings'); ?></p>
                <p><?php _e('The migration will:', 'maloney-listings'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('Read data from Ninja Table 790', 'maloney-listings'); ?></li>
                    <li><?php _e('Group rows by property name and unit type', 'maloney-listings'); ?></li>
                    <li><?php _e('Aggregate units available counts per unit type', 'maloney-listings'); ?></li>
                    <li><?php _e('Match properties to existing listings by name', 'maloney-listings'); ?></li>
                    <li><?php _e('Update available units fields for rental properties only', 'maloney-listings'); ?></li>
                </ul>
                
                <p><strong><?php _e('Note:', 'maloney-listings'); ?></strong> <?php _e('This migration will only update rental properties. Condos will be skipped.', 'maloney-listings'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('migrate_available_units_action', 'migrate_available_units_nonce'); ?>
                    <p>
                        <button type="submit" name="migrate_available_units" class="button button-primary button-large">
                            <?php _e('Run Migration', 'maloney-listings'); ?>
                        </button>
                    </p>
                </form>
            </div>
            
            <?php if ($results && !empty($results['not_imported_units'])) : ?>
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Units That Could Not Be Imported', 'maloney-listings'); ?></h2>
                <p><?php _e('The following units from Ninja Table could not be imported:', 'maloney-listings'); ?></p>
                <table class="fixed wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Property Name', 'maloney-listings'); ?></th>
                            <th><?php _e('Bedrooms/Unit Type', 'maloney-listings'); ?></th>
                            <th><?php _e('Units Available', 'maloney-listings'); ?></th>
                            <th><?php _e('Rent', 'maloney-listings'); ?></th>
                            <th><?php _e('Reason', 'maloney-listings'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['not_imported_units'] as $unit) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($unit['property']); ?></strong></td>
                            <td><?php echo esc_html($unit['bedrooms']); ?></td>
                            <td><?php echo esc_html($unit['units_available']); ?></td>
                            <td><?php echo esc_html($unit['rent']); ?></td>
                            <td>
                                <span style="color: #d63638;">
                                    <?php echo esc_html($unit['reason']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top: 15px;">
                    <strong><?php _e('What to do:', 'maloney-listings'); ?></strong>
                </p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('If a property is "not found", create the listing first, then run the migration again.', 'maloney-listings'); ?></li>
                    <li><?php _e('If a property is "not a rental listing", check the listing type - it may need to be changed to "Rental".', 'maloney-listings'); ?></li>
                    <li><?php _e('You can manually add these units using the "Current Rental Availability" field group on the listing edit page.', 'maloney-listings'); ?></li>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if ($preview_not_imported && !empty($preview_not_imported)) : ?>
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Units That Cannot Be Imported (Preview)', 'maloney-listings'); ?></h2>
                <p><?php _e('The following units from Ninja Table cannot be imported because the properties are not found or are not rental listings:', 'maloney-listings'); ?></p>
                <table class="fixed wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Property Name', 'maloney-listings'); ?></th>
                            <th><?php _e('Bedrooms/Unit Type', 'maloney-listings'); ?></th>
                            <th><?php _e('Units Available', 'maloney-listings'); ?></th>
                            <th><?php _e('Rent', 'maloney-listings'); ?></th>
                            <th><?php _e('Reason', 'maloney-listings'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_not_imported as $unit) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($unit['property']); ?></strong></td>
                            <td><?php echo esc_html($unit['bedrooms']); ?></td>
                            <td><?php echo esc_html($unit['units_available']); ?></td>
                            <td><?php echo esc_html($unit['rent']); ?></td>
                            <td>
                                <span style="color: #d63638;">
                                    <?php echo esc_html($unit['reason']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top: 15px;">
                    <strong><?php _e('What to do:', 'maloney-listings'); ?></strong>
                </p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('If a property is "not found", create the listing first, then run the migration again.', 'maloney-listings'); ?></li>
                    <li><?php _e('If a property is "not a rental listing", check the listing type - it may need to be changed to "Rental".', 'maloney-listings'); ?></li>
                    <li><?php _e('You can manually add these units using the "Current Rental Availability" field group on the listing edit page.', 'maloney-listings'); ?></li>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Custom Fields Status', 'maloney-listings'); ?></h2>
                <?php
                if (class_exists('Maloney_Listings_Available_Units_Fields')) {
                    $fields_setup = new Maloney_Listings_Available_Units_Fields();
                    if ($fields_setup->fields_exist()) {
                        echo '<p style="color: green;"><strong>' . __('✓ All required custom fields are created.', 'maloney-listings') . '</strong></p>';
                    } else {
                        echo '<p style="color: orange;"><strong>' . __('⚠ Some custom fields are missing. They will be created automatically when you run the migration.', 'maloney-listings') . '</strong></p>';
                    }
                } else {
                    echo '<p style="color: red;"><strong>' . __('✗ Toolset Types plugin is not active. Please activate Toolset Types to create fields.', 'maloney-listings') . '</strong></p>';
                }
                ?>
                <p><?php _e('The following Toolset custom fields are used for available units:', 'maloney-listings'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><code>current-rental-availability</code> - Textarea field storing JSON array of available units. Format: <code>[{"unit_type":"Studio","count":2,"accessible":"..."},{"unit_type":"1-Bedroom","count":3,"accessible":""},...]</code></li>
                    <li><code>total-available-units</code> - Number field for total available units (auto-calculated)</li>
                </ul>
                <p><strong><?php _e('Note:', 'maloney-listings'); ?></strong> <?php _e('The flexible structure supports any unit type (Studio, 1-Bedroom, 2-Bedroom, 3-Bedroom, 4+ Bedroom, 5-Bedroom, 6-Bedroom, etc.) without needing separate fields. These fields are automatically created by the plugin and added to the "Rental Properties" field group, so they only show for rental listings.', 'maloney-listings'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render the Condo Listings Migration page
     * Similar to render_available_units_migration_page but for condos
     */
    public function render_condo_listings_migration_page() {
        // Check if user is developer (restricted page)
        if (!$this->is_developer()) {
            wp_die('You do not have permission to access this page.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }
        
        $message = '';
        $message_type = 'info';
        $results = null;
        
        // Handle clear stored results
        if (isset($_POST['clear_migration_results']) && check_admin_referer('clear_migration_results_action', 'clear_migration_results_nonce')) {
            delete_transient('maloney_condo_listings_migration_results');
            $message = __('Stored migration results cleared.', 'maloney-listings');
            $message_type = 'success';
        }
        
        // Auto-create fields if they don't exist
        if (class_exists('Maloney_Listings_Condo_Listings_Fields')) {
            $fields_setup = new Maloney_Listings_Condo_Listings_Fields();
            if (!$fields_setup->fields_exist()) {
                $fields_result = $fields_setup->create_fields();
                if ($fields_result['success']) {
                    if ($fields_result['created'] > 0) {
                        $message = sprintf(
                            __('Created %d custom fields for condo listings.', 'maloney-listings'),
                            $fields_result['created']
                        );
                        $message_type = 'success';
                    }
                    if (!empty($fields_result['errors'])) {
                        $message .= ' ' . implode(' ', $fields_result['errors']);
                        if ($message_type === 'success') {
                            $message_type = 'warning';
                        }
                    }
                } else {
                    $message = $fields_result['message'];
                    $message_type = 'error';
                }
            }
        }
        
        // Handle migration if form submitted
        if (isset($_POST['migrate_condo_listings']) && check_admin_referer('migrate_condo_listings_action', 'migrate_condo_listings_nonce')) {
            if (class_exists('Maloney_Listings_Condo_Listings_Migration')) {
                $migration = new Maloney_Listings_Condo_Listings_Migration();
                $results = $migration->run_migration();
                
                // Store results in transient (persist for 7 days)
                set_transient('maloney_condo_listings_migration_results', $results, 7 * DAY_IN_SECONDS);
                
                if ($results['updated'] > 0) {
                    $message = sprintf(
                        __('Migration completed! Processed %d properties, updated %d listings. %d properties not found.', 'maloney-listings'),
                        $results['processed'],
                        $results['updated'],
                        $results['not_found']
                    );
                    $message_type = 'success';
                } else {
                    $message = __('Migration completed but no listings were updated.', 'maloney-listings');
                    $message_type = 'warning';
                }
                
                if (!empty($results['errors'])) {
                    $message .= ' ' . __('Some errors occurred during migration.', 'maloney-listings');
                }
                
                if (!empty($results['not_imported_units'])) {
                    $message .= ' ' . sprintf(__('%d units could not be imported. See details below.', 'maloney-listings'), count($results['not_imported_units']));
                }
            } else {
                $message = __('Migration class not found.', 'maloney-listings');
                $message_type = 'error';
            }
        } else {
            // Get stored results if available (for persistence across page reloads)
            $stored_results = get_transient('maloney_condo_listings_migration_results');
            if ($stored_results !== false) {
                $results = $stored_results;
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Migrate Condo Listings from Ninja Table', 'maloney-listings'); ?></h1>
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($results && !empty($results['errors'])) : ?>
                <div class="notice notice-warning">
                    <p><strong><?php _e('Errors:', 'maloney-listings'); ?></strong></p>
                    <ul>
                        <?php foreach ($results['errors'] as $error) : ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php _e('Migrate Condo Listings Data', 'maloney-listings'); ?></h2>
                <p><?php _e('This will migrate condo listings data from Ninja Table 3596 (Current Condo Listings) to the listing custom fields.', 'maloney-listings'); ?></p>
                <p><?php _e('The migration will:', 'maloney-listings'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('Read data from Ninja Table 3596', 'maloney-listings'); ?></li>
                    <li><?php _e('Match properties by name', 'maloney-listings'); ?></li>
                    <li><?php _e('Only update condo/condominium listings', 'maloney-listings'); ?></li>
                    <li><?php _e('Create repetitive field entries for each unit', 'maloney-listings'); ?></li>
                </ul>
                <p><strong><?php _e('Note:', 'maloney-listings'); ?></strong> <?php _e('This migration will only update condo properties. Rentals will be skipped.', 'maloney-listings'); ?></p>
                
                <form method="post" action="" style="margin-top: 20px;">
                    <?php wp_nonce_field('migrate_condo_listings_action', 'migrate_condo_listings_nonce'); ?>
                    <input type="submit" name="migrate_condo_listings" class="button button-primary button-large" value="<?php _e('Migrate from Ninja Table 3596', 'maloney-listings'); ?>" onclick="return confirm('<?php _e('This will import all condo listings from Ninja Table 3596. Continue?', 'maloney-listings'); ?>');">
                </form>
            </div>
            
            <?php if ($results && !empty($results['not_imported_units'])) : ?>
                <div class="card" style="margin-top: 20px; border-left: 4px solid #d63638; max-width: 100% !important;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="color: #d63638; margin: 0;"><?php _e('Units That Could Not Be Imported', 'maloney-listings'); ?> (<?php echo count($results['not_imported_units']); ?>)</h2>
                        <form method="post" action="" style="margin: 0;">
                            <?php wp_nonce_field('clear_migration_results_action', 'clear_migration_results_nonce'); ?>
                            <input type="submit" name="clear_migration_results" class="button" value="<?php _e('Clear Results', 'maloney-listings'); ?>" onclick="return confirm('<?php _e('Are you sure you want to clear the stored migration results?', 'maloney-listings'); ?>');">
                        </form>
                    </div>
                    <p><?php _e('The following units from Ninja Table 3596 could not be imported:', 'maloney-listings'); ?></p>
                    <div style="overflow-x: auto; width: 100%;">
                        <table class="wp-list-table widefat striped" style="margin-top: 15px; width: 100%; table-layout: auto;">
                        <thead>
                            <tr>
                                <th style="padding: 10px; font-weight: 600;"><?php _e('Property Name (Ninja Table)', 'maloney-listings'); ?></th>
                                <th style="padding: 10px; font-weight: 600;"><?php _e('Property Found (WordPress)', 'maloney-listings'); ?></th>
                                <th style="padding: 10px; font-weight: 600;"><?php _e('Town', 'maloney-listings'); ?></th>
                                <th style="padding: 10px; font-weight: 600;"><?php _e('Unit Size', 'maloney-listings'); ?></th>
                                <th style="padding: 10px; font-weight: 600;"><?php _e('Price', 'maloney-listings'); ?></th>
                                <th style="padding: 10px; font-weight: 600;"><?php _e('Units Available', 'maloney-listings'); ?></th>
                                <th style="padding: 10px; font-weight: 600;"><?php _e('Reason', 'maloney-listings'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['not_imported_units'] as $unit) : ?>
                                <tr>
                                    <td style="padding: 10px;">
                                        <strong><?php echo esc_html($unit['property']); ?></strong>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?php if (!empty($unit['property_found'])) : ?>
                                            <span style="color: #2271b1;"><?php echo esc_html($unit['property_found']); ?></span>
                                        <?php else : ?>
                                            <span style="color: #d63638;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px;"><?php echo !empty($unit['town']) ? esc_html($unit['town']) : '—'; ?></td>
                                    <td style="padding: 10px;"><?php echo esc_html($unit['bedrooms']); ?></td>
                                    <td style="padding: 10px;"><?php echo !empty($unit['price']) ? esc_html($unit['price']) : '—'; ?></td>
                                    <td style="padding: 10px;"><?php echo esc_html($unit['units_available']); ?></td>
                                    <td style="padding: 10px;">
                                        <span style="color: #d63638; font-weight: 600;">
                                            <?php 
                                            switch($unit['reason']) {
                                                case 'Property not found in listings':
                                                    _e('Property not found in listings', 'maloney-listings');
                                                    echo '<br><small style="color: #666;">' . __('The property name in Ninja Table does not match any listing in the system. Please check the property name spelling.', 'maloney-listings') . '</small>';
                                                    break;
                                                case 'Property is not a condo listing':
                                                    _e('Property is not a condo listing', 'maloney-listings');
                                                    echo '<br><small style="color: #666;">' . __('The property exists but is not marked as a condo/condominium listing type. Go to the listing edit page and assign the correct listing type.', 'maloney-listings') . '</small>';
                                                    break;
                                                case 'Failed to update listing':
                                                    _e('Failed to update listing', 'maloney-listings');
                                                    echo '<br><small style="color: #666;">' . __('An error occurred while updating the listing. Please try again or add manually.', 'maloney-listings') . '</small>';
                                                    break;
                                                default:
                                                    echo esc_html($unit['reason']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <p style="margin-top: 15px; font-style: italic; color: #666;">
                        <strong><?php _e('How to fix:', 'maloney-listings'); ?></strong><br>
                        <?php _e('• If "Property not found": Check the property name in Ninja Table matches exactly with the listing title in WordPress.', 'maloney-listings'); ?><br>
                        <?php _e('• If "Property is not a condo listing": Go to the listing edit page and ensure it has the "Condo" or "Condominium" listing type assigned.', 'maloney-listings'); ?><br>
                        <?php _e('• After fixing, you can manually add these entries using the "Current Condo Listings" page or the meta box on the listing edit page.', 'maloney-listings'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function render_management_page() {
        if (!$this->is_developer()) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        if (isset($_POST['bulk_action']) && isset($_POST['listing_ids'])) {
            $this->handle_bulk_action();
        }
        // Removed: rebuild bed/bath index - bathrooms are now stored in Current Rental Availability entries
        
        $listings = $this->get_listings();
        ?>
        <div class="wrap">
            <h1><?php _e('Listings Management', 'maloney-listings'); ?></h1>
            
            <form method="post" action="">
                <div class="listings-management-header">
                    <select name="bulk_action" id="bulk_action">
                        <option value=""><?php _e('Bulk Actions', 'maloney-listings'); ?></option>
                        <option value="status_available"><?php _e('Set Status: Available', 'maloney-listings'); ?></option>
                        <option value="status_waitlist"><?php _e('Set Status: Waitlist', 'maloney-listings'); ?></option>
                        <option value="status_not_available"><?php _e('Set Status: Not Available', 'maloney-listings'); ?></option>
                        <option value="delete"><?php _e('Delete', 'maloney-listings'); ?></option>
                    </select>
                    <button type="submit" class="button action"><?php _e('Apply', 'maloney-listings'); ?></button>
                    <!-- Removed: Rebuild bed/bath index button - bathrooms are now stored in Current Rental Availability entries -->
                </div>
                
                <table class="fixed wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all" />
                            </td>
                            <th><?php _e('Title', 'maloney-listings'); ?></th>
                            <th><?php _e('Type', 'maloney-listings'); ?></th>
                            <th><?php _e('Status', 'maloney-listings'); ?></th>
                            <th><?php _e('Location', 'maloney-listings'); ?></th>
                            <th><?php _e('Bedrooms', 'maloney-listings'); ?></th>
                            <th><?php _e('Price', 'maloney-listings'); ?></th>
                            <th><?php _e('Actions', 'maloney-listings'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($listings)) : ?>
                            <?php foreach ($listings as $listing) : ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="listing_ids[]" value="<?php echo esc_attr($listing->ID); ?>" />
                                    </th>
                                    <td>
                                        <strong><a href="<?php echo get_edit_post_link($listing->ID); ?>"><?php echo esc_html($listing->post_title); ?></a></strong>
                                    </td>
                                    <td><?php echo $this->get_listing_type($listing->ID); ?></td>
                                    <td><?php echo $this->get_listing_status_badge($listing->ID); ?></td>
                                    <td><?php echo $this->get_listing_location($listing->ID); ?></td>
                                    <td><?php echo get_post_meta($listing->ID, '_listing_bedrooms', true) ?: '—'; ?></td>
                                    <td><?php echo $this->get_listing_price($listing->ID); ?></td>
                                    <td>
                                        <a href="<?php echo get_edit_post_link($listing->ID); ?>" class="button button-small"><?php _e('Edit', 'maloney-listings'); ?></a>
                                        <a href="<?php echo get_permalink($listing->ID); ?>" class="button button-small" target="_blank"><?php _e('View', 'maloney-listings'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="8"><?php _e('No listings found.', 'maloney-listings'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }
    
    public function render_vacancy_notifications_page() {
        if (!$this->is_developer()) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'vacancy_notifications';
        
        $notifications = $wpdb->get_results("
            SELECT n.*, p.post_title 
            FROM $table_name n
            LEFT JOIN {$wpdb->posts} p ON n.listing_id = p.ID
            ORDER BY n.created_at DESC
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Vacancy Notifications', 'maloney-listings'); ?></h1>
            
            <table class="fixed wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Listing', 'maloney-listings'); ?></th>
                        <th><?php _e('Email', 'maloney-listings'); ?></th>
                        <th><?php _e('Name', 'maloney-listings'); ?></th>
                        <th><?php _e('Phone', 'maloney-listings'); ?></th>
                        <th><?php _e('Status', 'maloney-listings'); ?></th>
                        <th><?php _e('Created', 'maloney-listings'); ?></th>
                        <th><?php _e('Notified', 'maloney-listings'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($notifications)) : ?>
                        <?php foreach ($notifications as $notification) : ?>
                            <tr>
                                <td>
                                    <?php if ($notification->post_title) : ?>
                                        <a href="<?php echo get_edit_post_link($notification->listing_id); ?>"><?php echo esc_html($notification->post_title); ?></a>
                                    <?php else : ?>
                                        <?php _e('Listing Deleted', 'maloney-listings'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($notification->email); ?></td>
                                <td><?php echo esc_html($notification->name ?: '—'); ?></td>
                                <td><?php echo esc_html($notification->phone ?: '—'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($notification->status); ?>">
                                        <?php echo esc_html(ucfirst($notification->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($notification->created_at)); ?></td>
                                <td>
                                    <?php echo $notification->notified_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($notification->notified_at)) : '—'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7"><?php _e('No notifications found.', 'maloney-listings'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function get_listings() {
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'post_status' => 'any',
        );
        
        $query = new WP_Query($args);
        return $query->posts;
    }
    
    private function handle_bulk_action() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.'));
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $listing_ids = array_map('intval', $_POST['listing_ids']);
        
        switch ($action) {
            case 'status_available':
                $this->set_listing_status($listing_ids, 'available');
                break;
            case 'status_waitlist':
                $this->set_listing_status($listing_ids, 'waitlist');
                break;
            case 'status_not_available':
                $this->set_listing_status($listing_ids, 'not-available');
                break;
            case 'delete':
                foreach ($listing_ids as $id) {
                    wp_delete_post($id, true);
                }
                break;
        }
    }
    
    private function set_listing_status($post_ids, $status_slug) {
        $term = get_term_by('slug', $status_slug, 'listing_status');
        if ($term) {
            foreach ($post_ids as $post_id) {
                wp_set_post_terms($post_id, array($term->term_id), 'listing_status');
            }
        }
    }
    
    private function get_listing_type($post_id) {
        $terms = get_the_terms($post_id, 'listing_type');
        return $terms && !is_wp_error($terms) ? $terms[0]->name : '—';
    }
    
    private function get_listing_status_badge($post_id) {
        $terms = get_the_terms($post_id, 'listing_status');
        if ($terms && !is_wp_error($terms)) {
            $status = $terms[0];
            $status_class = sanitize_html_class($status->slug);
            return '<span class="listing-status-badge' . esc_attr($status_class) . '">' . esc_html($status->name) . '</span>';
        }
        return '—';
    }
    
    private function get_listing_location($post_id) {
        $terms = get_the_terms($post_id, 'location');
        if ($terms && !is_wp_error($terms)) {
            $locations = array();
            foreach ($terms as $term) {
                $locations[] = $term->name;
            }
            return esc_html(implode(', ', $locations));
        }
        return '—';
    }
    
    private function get_listing_price($post_id) {
        $rent = get_post_meta($post_id, '_listing_rent_price', true);
        $purchase = get_post_meta($post_id, '_listing_purchase_price', true);
        
        if ($rent) {
            return '$' . number_format($rent) . '/mo';
        } elseif ($purchase) {
            return '$' . number_format($purchase);
        }
        return '—';
    }
    
    public function render_field_discovery_page() {
        if (!$this->is_developer()) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        Maloney_Listings_Field_Discovery::display_discovery_results();
    }
    
    public function render_migration_page() {
        if (!$this->is_developer()) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }
        
        // Handle migration if form submitted
        if (isset($_POST['run_migration']) && check_admin_referer('migrate_listings')) {
            if (!isset($_POST['source_post_types']) || empty($_POST['source_post_types'])) {
                echo '<div class="notice notice-error"><p>Please select at least one post type to migrate.</p></div>';
                include MALONEY_LISTINGS_PLUGIN_DIR . 'templates/admin-migration-form.php';
            } else {
                $migration = new Maloney_Listings_Migration();
                $results = $migration->run_migration();
                include MALONEY_LISTINGS_PLUGIN_DIR . 'templates/admin-migration-results.php';
            }
        } else {
            include MALONEY_LISTINGS_PLUGIN_DIR . 'templates/admin-migration-form.php';
        }
    }
    
    public function render_geocode_page() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }
        
        // Get listings without coordinates
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_listing_latitude',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => '_listing_latitude',
                    'value' => '',
                    'compare' => '=',
                ),
            ),
        );
        
        $query = new WP_Query($args);
        $listings_needing_geocode = $query->found_posts;
        $listings_needing_list = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $address = get_post_meta($post_id, '_listing_address', true);
                if (empty($address)) {
                    $address = get_post_meta($post_id, 'wpcf-address', true);
                }
                $city = get_post_meta($post_id, '_listing_city', true);
                if (empty($city)) {
                    $city = get_post_meta($post_id, 'wpcf-city', true);
                }
                $state = get_post_meta($post_id, '_listing_state', true);
                if (empty($state)) {
                    $state = get_post_meta($post_id, 'wpcf-state', true);
                }
                
                // Use ONLY the address field - no longer combines with city/town
                $address_string = !empty($address) ? trim($address) : '';
                if (empty($address_string)) {
                    $address_string = __('No address information', 'maloney-listings');
                }
                
                $listings_needing_list[] = array(
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'address' => $address_string,
                    'edit_link' => get_edit_post_link($post_id)
                );
            }
            wp_reset_postdata();
        }
        
        // Get listings with coordinates
        $args_with_coords = array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_listing_latitude',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key' => '_listing_longitude',
                    'compare' => 'EXISTS',
                ),
            ),
        );
        
        $query_with_coords = new WP_Query($args_with_coords);
        $listings_with_coords = $query_with_coords->found_posts;
        
        ?>
        <div class="wrap">
            <h1><?php _e('Geocode Listing Addresses', 'maloney-listings'); ?></h1>
            
            <div class="geocode-stats" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="margin-top: 0;"><?php _e('Status', 'maloney-listings'); ?></h2>
                        <p><strong><?php _e('Listings with coordinates:', 'maloney-listings'); ?></strong> <span id="stats-with-coords"><?php echo $listings_with_coords; ?></span></p>
                        <p><strong><?php _e('Listings needing geocoding:', 'maloney-listings'); ?></strong> <span id="stats-needing"><?php echo $listings_needing_geocode; ?></span></p>
                        <?php 
                        $total_listings = $listings_with_coords + $listings_needing_geocode;
                        $percentage = $total_listings > 0 ? round(($listings_with_coords / $total_listings) * 100) : 0;
                        ?>
                        <p><strong><?php _e('Total listings:', 'maloney-listings'); ?></strong> <?php echo $total_listings; ?> (<?php echo $percentage; ?>% geocoded)</p>
                    </div>
                    <div>
                        <button id="refresh-stats" class="button" style="margin-top: 10px;">
                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span> <?php _e('Refresh Stats', 'maloney-listings'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="geocode-info" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
                <h2><?php _e('How It Works', 'maloney-listings'); ?></h2>
                <p><?php _e('This tool will geocode all listings that have address information (City, State, Address fields) but are missing coordinates. The geocoding happens in the background via AJAX to avoid timeouts.', 'maloney-listings'); ?></p>
                <p><strong><?php _e('Note:', 'maloney-listings'); ?></strong> <?php _e('Geocoding uses the OpenStreetMap Nominatim API, which has rate limits. The process may take several minutes for many listings.', 'maloney-listings'); ?></p>
            </div>
            
            <?php if ($listings_needing_geocode > 0) : ?>
                <div class="geocode-listings-list" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
                    <h2><?php _e('Listings Needing Geocoding', 'maloney-listings'); ?></h2>
                    <p><?php printf(__('The following %d listings need geocoding:', 'maloney-listings'), $listings_needing_geocode); ?></p>
                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-top: 10px;">
                        <table class="fixed wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th style="width: 50px;"><?php _e('ID', 'maloney-listings'); ?></th>
                                    <th><?php _e('Title', 'maloney-listings'); ?></th>
                                    <th><?php _e('Address', 'maloney-listings'); ?></th>
                                    <th style="width: 100px;"><?php _e('Actions', 'maloney-listings'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($listings_needing_list)) : ?>
                                    <?php foreach ($listings_needing_list as $listing) : ?>
                                        <tr>
                                            <td><?php echo esc_html($listing['id']); ?></td>
                                            <td><strong><?php echo esc_html($listing['title']); ?></strong></td>
                                            <td><?php echo esc_html($listing['address']); ?></td>
                                            <td>
                                                <?php if ($listing['edit_link']) : ?>
                                                    <a href="<?php echo esc_url($listing['edit_link']); ?>" class="button button-small" target="_blank">
                                                        <?php _e('Edit', 'maloney-listings'); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="4"><?php _e('No listings found.', 'maloney-listings'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="geocode-actions" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
                    <button id="start-geocoding" class="button button-primary button-large">
                        <?php _e('Start Geocoding', 'maloney-listings'); ?>
                    </button>
                    <button id="stop-geocoding" class="button button-large" style="display: none;">
                        <?php _e('Stop', 'maloney-listings'); ?>
                    </button>
                    
                    <div id="geocode-progress" style="margin-top: 20px; display: none;">
                        <div style="background: #f0f0f0; border: 1px solid #ddd; height: 30px; position: relative; border-radius: 4px; overflow: hidden;">
                            <div id="geocode-progress-bar" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>
                            <div id="geocode-progress-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; color: #333; z-index: 1;"></div>
                        </div>
                        <div id="geocode-status" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa; border-radius: 4px;">
                            <p style="margin: 0;"><strong>Status:</strong> <span id="geocode-status-text">Ready to start...</span></p>
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;" id="geocode-details"></p>
                        </div>
                        <div id="geocode-errors" style="margin-top: 10px; display: none; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                            <p style="margin: 0;"><strong>⚠️ Errors:</strong></p>
                            <ul id="geocode-errors-list" style="margin: 5px 0 0 20px; font-size: 12px;"></ul>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <div class="notice notice-success">
                    <p><?php _e('All listings have been geocoded!', 'maloney-listings'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php
            // Always validate all existing coordinates first to catch any suspicious ones
            $geocoding = new Maloney_Listings_Geocoding();
            $suspicious_count = $geocoding->validate_existing_coordinates();
            $suspicious = $geocoding->get_suspicious_coordinates();
            ?>
            
            <?php if (!empty($suspicious)) : ?>
                <div class="geocode-suspicious" style="background: #fff; border: 1px solid #dc3232; padding: 20px; margin: 20px 0;">
                    <h2 style="color: #dc3232; margin-top: 0;">⚠️ Suspicious Coordinates (Outside Massachusetts)</h2>
                    <p><?php _e('The following listings have coordinates that appear to be outside Massachusetts bounds. Please review and correct them.', 'maloney-listings'); ?></p>
                    <p><strong><?php echo count($suspicious); ?></strong> <?php _e('listings with suspicious coordinates found.', 'maloney-listings'); ?></p>
                    
                    <table class="fixed wp-list-table widefat striped" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="width: 5%;">ID</th>
                                <th style="width: 30%;">Title</th>
                                <th style="width: 30%;">Address</th>
                                <th style="width: 15%;">Latitude</th>
                                <th style="width: 15%;">Longitude</th>
                                <th style="width: 5%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suspicious as $item) : ?>
                                <tr>
                                    <td><?php echo esc_html($item['id']); ?></td>
                                    <td><strong><?php echo esc_html($item['title']); ?></strong></td>
                                    <td><?php echo esc_html($item['address']); ?></td>
                                    <td><?php echo esc_html($item['lat']); ?></td>
                                    <td><?php echo esc_html($item['lng']); ?></td>
                                    <td>
                                        <?php if ($item['edit_url']) : ?>
                                            <a href="<?php echo esc_url($item['edit_url']); ?>" class="button button-small" target="_blank">
                                                <?php _e('Edit', 'maloney-listings'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p style="margin-top: 15px;">
                        <button id="validate-all-coordinates" class="button">
                            <?php _e('Re-validate All Coordinates', 'maloney-listings'); ?>
                        </button>
                    </p>
                </div>
            <?php else : ?>
                <div class="notice notice-success" style="margin-top: 20px;">
                    <p>✅ <?php _e('No suspicious coordinates found. All geocoded listings appear to be within Massachusetts bounds.', 'maloney-listings'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let isGeocoding = false;
            let geocodeQueue = [];
            let errorCount = 0;
            let errorMessages = [];
            
            // Refresh stats function
            function refreshStats() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_geocode_stats',
                        nonce: '<?php echo wp_create_nonce('geocode_stats_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#stats-with-coords').text(response.data.with_coords);
                            $('#stats-needing').text(response.data.needing);
                            const total = response.data.with_coords + response.data.needing;
                            const percentage = total > 0 ? Math.round((response.data.with_coords / total) * 100) : 0;
                            $('.geocode-stats p:last').html('<strong><?php _e('Total listings:', 'maloney-listings'); ?></strong> ' + total + ' (' + percentage + '% geocoded)');
                        }
                    }
                });
            }
            
            $('#refresh-stats').on('click', function() {
                const btn = $(this);
                btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="vertical-align: middle; animation: spin 1s linear infinite;"></span> Refreshing...');
                refreshStats();
                setTimeout(function() {
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> <?php _e('Refresh Stats', 'maloney-listings'); ?>');
                }, 1000);
            });
            
            $('#start-geocoding').on('click', function() {
                if (isGeocoding) return;
                
                isGeocoding = true;
                errorCount = 0;
                errorMessages = [];
                $('#geocode-errors').hide();
                $('#geocode-errors-list').empty();
                $('#start-geocoding').hide();
                $('#stop-geocoding').show();
                $('#geocode-progress').show();
                $('#geocode-status-text').text('Starting geocoding...');
                $('#geocode-details').text('');
                
                // Get first batch of listings to geocode
                geocodeNextBatch();
            });
            
            $('#stop-geocoding').on('click', function() {
                isGeocoding = false;
                $('#start-geocoding').show();
                $('#stop-geocoding').hide();
                $('#geocode-status-text').text('Geocoding stopped by user.');
                $('#geocode-details').text('');
                refreshStats();
            });
            
            function geocodeNextBatch() {
                if (!isGeocoding) return;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'batch_geocode_listings',
                        nonce: '<?php echo wp_create_nonce('batch_geocode_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            
                            if (data.processed > 0) {
                                const total = data.total_geocoded + data.total_needing;
                                const percentage = total > 0 ? Math.round((data.total_geocoded / total) * 100) : 0;
                                
                                // Update progress bar
                                $('#geocode-progress-bar').css('width', percentage + '%');
                                $('#geocode-progress-text').text(percentage + '%');
                                
                                // Update status text
                                if (data.has_more) {
                                    $('#geocode-status-text').text('Geocoding in progress...');
                                } else {
                                    $('#geocode-status-text').text('Geocoding complete!');
                                    $('#geocode-progress-bar').css('background', '#46b450');
                                }
                                
                                // Update details
                                const details = 'Processed: ' + data.processed + ' | ' +
                                              'Successfully geocoded: ' + data.geocoded + ' | ' +
                                              'Failed: ' + data.failed + ' | ' +
                                              'Total with coordinates: ' + data.total_geocoded + ' of ' + total;
                                $('#geocode-details').text(details);
                                
                                // Track errors
                                if (data.failed > 0) {
                                    errorCount += data.failed;
                                    if (data.error_details && data.error_details.length > 0) {
                                        errorMessages = errorMessages.concat(data.error_details);
                                        // Show last 10 errors
                                        const recentErrors = errorMessages.slice(-10);
                                        $('#geocode-errors-list').empty();
                                        recentErrors.forEach(function(err) {
                                            $('#geocode-errors-list').append('<li>' + err + '</li>');
                                        });
                                        $('#geocode-errors').show();
                                    }
                                }
                                
                                // Update stats display
                                $('#stats-with-coords').text(data.total_geocoded);
                                $('#stats-needing').text(data.total_needing);
                            }
                            
                            if (data.has_more) {
                                // Continue with next batch (delay to respect rate limits)
                                // Increased to 2 seconds between batches to avoid rate limiting issues
                                setTimeout(geocodeNextBatch, 2000); // 2 second delay between batches
                            } else {
                                // Done - ensure 100% is shown
                                isGeocoding = false;
                                $('#start-geocoding').show();
                                $('#stop-geocoding').hide();
                                $('#geocode-progress-bar').css('width', '100%');
                                $('#geocode-progress-text').text('100%');
                                $('#geocode-status-text').text('Geocoding complete!');
                                $('#geocode-progress-bar').css('background', '#46b450');
                                
                                if (errorCount > 0) {
                                    $('#geocode-details').html(
                                        'Completed with ' + errorCount + ' error(s). ' +
                                        '<a href="#" id="view-all-errors" style="color: #0073aa;">View all errors</a>'
                                    );
                                    $('#view-all-errors').on('click', function(e) {
                                        e.preventDefault();
                                        alert('Total errors: ' + errorCount + '\n\nRecent errors:\n' + errorMessages.slice(-20).join('\n'));
                                    });
                                }
                                
                                refreshStats();
                            }
                        } else {
                            $('#geocode-status-text').text('Error: ' + (response.data || 'Unknown error'));
                            $('#geocode-details').text('');
                            isGeocoding = false;
                            $('#start-geocoding').show();
                            $('#stop-geocoding').hide();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Geocoding AJAX error:', {xhr: xhr, status: status, error: error});
                        
                        // Don't stop completely - try to continue after a delay
                        // This handles temporary network issues or server timeouts
                        const errorMsg = error || 'Unknown error';
                        const statusMsg = status || 'unknown';
                        
                        $('#geocode-status-text').text('Temporary error occurred - retrying...');
                        $('#geocode-details').text('Error: ' + errorMsg + ' (Status: ' + statusMsg + ') - Will retry in 3 seconds');
                        
                        // Retry after 3 seconds instead of stopping
                        setTimeout(function() {
                            if (isGeocoding) {
                                geocodeNextBatch();
                            }
                        }, 3000);
                    },
                    timeout: 90000 // 90 second timeout for AJAX requests (increased for slow connections)
                });
            }
            
            // Validate all coordinates button
            $('#validate-all-coordinates').on('click', function() {
                const btn = $(this);
                btn.prop('disabled', true).text('<?php _e('Validating...', 'maloney-listings'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'validate_all_coordinates',
                        nonce: '<?php echo wp_create_nonce('validate_coordinates_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Validation complete. Found', 'maloney-listings'); ?> ' + response.data.count + ' <?php _e('suspicious coordinates.', 'maloney-listings'); ?>');
                            location.reload();
                        } else {
                            alert('<?php _e('Error validating coordinates.', 'maloney-listings'); ?>');
                        }
                        btn.prop('disabled', false).text('<?php _e('Re-validate All Coordinates', 'maloney-listings'); ?>');
                    },
                    error: function() {
                        alert('<?php _e('Error validating coordinates.', 'maloney-listings'); ?>');
                        btn.prop('disabled', false).text('<?php _e('Re-validate All Coordinates', 'maloney-listings'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render Extract Zip Codes page
     */
    public function render_extract_zip_codes_page() {
        if (!$this->is_developer()) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        
        $message = '';
        $message_type = 'info';
        
        // Handle form submission - Pattern extraction
        if (isset($_POST['extract_zip_codes']) && check_admin_referer('extract_zip_codes_action', 'extract_zip_codes_nonce')) {
            $force_update = isset($_POST['force_update']) && $_POST['force_update'] === '1';
            
            // Ensure field exists first
            $field_result = Maloney_Listings_Zip_Code_Extraction::ensure_zip_field_exists();
            if (is_wp_error($field_result)) {
                $message = __('Error: ' . $field_result->get_error_message(), 'maloney-listings');
                $message_type = 'error';
            } else {
                // Run batch extraction
                $results = Maloney_Listings_Zip_Code_Extraction::batch_extract_zip_codes($force_update);
                
                if ($results['success']) {
                    $message = sprintf(
                        __('Extraction complete! Processed %d listings, extracted %d zip codes, skipped %d (already had zip codes).', 'maloney-listings'),
                        $results['processed'],
                        $results['extracted'],
                        $results['skipped']
                    );
                    $message_type = 'success';
                } else {
                    $message = __('Error: ' . (isset($results['error']) ? $results['error'] : 'Unknown error'), 'maloney-listings');
                    $message_type = 'error';
                }
            }
        }
        
        // Enqueue scripts for geocoding zip codes
        add_action('admin_footer', array($this, 'add_geocode_zip_scripts'));
        
        // Check if field exists
        $field_exists = Maloney_Listings_Zip_Code_Extraction::field_exists();
        
        // Get listings without address or zip code
        $missing_listings = Maloney_Listings_Zip_Code_Extraction::get_listings_without_address_or_zip();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Extract Zip Codes from Addresses', 'maloney-listings'); ?></h1>
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php _e('Zip Code Extraction Tool', 'maloney-listings'); ?></h2>
                
                <p><?php _e('This tool extracts zip codes from the address field of all listings and populates the Zip Code field in Property Info.', 'maloney-listings'); ?></p>
                
                <h3><?php _e('How it works:', 'maloney-listings'); ?></h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('Scans the address field of each listing', 'maloney-listings'); ?></li>
                    <li><?php _e('Extracts zip codes using pattern matching (5 digits or 5+4 format)', 'maloney-listings'); ?></li>
                    <li><?php _e('Saves the zip code to the Zip Code field in Property Info', 'maloney-listings'); ?></li>
                    <li><?php _e('Skips listings that already have a zip code (unless "Force Update" is checked)', 'maloney-listings'); ?></li>
                </ul>
                
                <h3><?php _e('Field Status:', 'maloney-listings'); ?></h3>
                <?php if ($field_exists) : ?>
                    <p style="color: green;">
                        <strong>✓</strong> <?php _e('Zip Code field exists in Property Info group.', 'maloney-listings'); ?>
                    </p>
                <?php else : ?>
                    <p style="color: orange;">
                        <strong>⚠</strong> <?php _e('Zip Code field does not exist. It will be created automatically when you run the extraction.', 'maloney-listings'); ?>
                    </p>
                <?php endif; ?>
                
                <form method="post" action="">
                    <?php wp_nonce_field('extract_zip_codes_action', 'extract_zip_codes_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Options', 'maloney-listings'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="force_update" value="1" />
                                    <?php _e('Force update (re-extract zip codes even if they already exist)', 'maloney-listings'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('By default, listings that already have a zip code are skipped. Check this to re-extract all zip codes.', 'maloney-listings'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="extract_zip_codes" class="button button-primary" value="<?php _e('Extract Zip Codes', 'maloney-listings'); ?>" />
                    </p>
                </form>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php _e('Auto-Extraction', 'maloney-listings'); ?></h2>
                <p><?php _e('Zip codes are automatically extracted when you save a listing. This tool is useful for:', 'maloney-listings'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('Bulk extraction for existing listings', 'maloney-listings'); ?></li>
                    <li><?php _e('Re-extracting zip codes if address fields were updated', 'maloney-listings'); ?></li>
                    <li><?php _e('Initial setup after importing a database', 'maloney-listings'); ?></li>
                </ul>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php _e('Geocode & Extract Zip Codes (Recommended)', 'maloney-listings'); ?></h2>
                
                <p><?php _e('This tool uses the OpenStreetMap Nominatim API to geocode addresses and extract zip codes dynamically. It also updates coordinates for the map.', 'maloney-listings'); ?></p>
                
                <h3><?php _e('How it works:', 'maloney-listings'); ?></h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('Uses the address field (or builds from Property Name + City + State if address is empty)', 'maloney-listings'); ?></li>
                    <li><?php _e('Geocodes the address using OpenStreetMap Nominatim API', 'maloney-listings'); ?></li>
                    <li><?php _e('Extracts zip code from the geocoded result', 'maloney-listings'); ?></li>
                    <li><?php _e('Updates the zip code field and saves coordinates for the map', 'maloney-listings'); ?></li>
                    <li><?php _e('Processes in batches with rate limiting (1 request per second)', 'maloney-listings'); ?></li>
                </ul>
                
                <p><strong><?php _e('Note:', 'maloney-listings'); ?></strong> <?php _e('This method is more accurate than pattern matching and also geocodes addresses for the map. However, it requires an internet connection and may take longer due to API rate limits.', 'maloney-listings'); ?></p>
                
                <div id="geocode-zip-stats" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 4px;">
                    <p><strong><?php _e('Status:', 'maloney-listings'); ?></strong> <span id="geocode-zip-status-text"><?php _e('Ready to start...', 'maloney-listings'); ?></span></p>
                    <div id="geocode-zip-progress" style="margin-top: 10px; display: none;">
                        <div style="background: #ddd; height: 24px; border-radius: 4px; position: relative; overflow: hidden;">
                            <div id="geocode-zip-progress-bar" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>
                            <div id="geocode-zip-progress-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; color: #333; z-index: 1;"></div>
                        </div>
                        <div id="geocode-zip-details" style="margin-top: 10px; font-size: 12px; color: #666;"></div>
                    </div>
                    <div id="geocode-zip-successful" style="margin-top: 15px; display: none; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; max-height: 300px; overflow-y: auto;">
                        <strong><?php _e('Successfully Processed:', 'maloney-listings'); ?></strong> <span id="geocode-zip-successful-count">0</span>
                        <ul id="geocode-zip-successful-list" style="margin: 10px 0 0 20px; font-size: 12px; list-style: none; padding: 0;"></ul>
                    </div>
                    <div id="geocode-zip-errors" style="margin-top: 15px; display: none; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; max-height: 300px; overflow-y: auto;">
                        <strong><?php _e('Errors:', 'maloney-listings'); ?></strong> <span id="geocode-zip-errors-count">0</span>
                        <ul id="geocode-zip-errors-list" style="margin: 10px 0 0 20px; font-size: 12px; list-style: none; padding: 0;"></ul>
                    </div>
                </div>
                
                <p>
                    <button id="start-geocode-zip" class="button button-primary button-large">
                        <?php _e('Start Geocoding & Extracting Zip Codes', 'maloney-listings'); ?>
                    </button>
                    <button id="stop-geocode-zip" class="button button-large" style="display: none;">
                        <?php _e('Stop', 'maloney-listings'); ?>
                    </button>
                </p>
            </div>
            
            <?php if (!empty($missing_listings)) : ?>
                <div class="card" style="max-width: 100%; margin-top: 20px;">
                    <h2><?php _e('Listings Missing Address or Zip Code', 'maloney-listings'); ?> (<?php echo count($missing_listings); ?>)</h2>
                    <p><?php _e('The following listings are missing either a full address or a zip code. Click the listing name to edit and add the missing information.', 'maloney-listings'); ?></p>
                    
                    <table class="fixed wp-list-table widefat striped" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="width: 40%;"><?php _e('Listing Name', 'maloney-listings'); ?></th>
                                <th style="width: 30%;"><?php _e('Address', 'maloney-listings'); ?></th>
                                <th style="width: 15%;"><?php _e('City', 'maloney-listings'); ?></th>
                                <th style="width: 15%;"><?php _e('Zip Code', 'maloney-listings'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missing_listings as $listing) : ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url($listing['edit_link']); ?>" target="_blank">
                                                <?php echo esc_html($listing['title']); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if (empty($listing['address'])) : ?>
                                            <span style="color: #d63638; font-weight: bold;"><?php _e('Missing', 'maloney-listings'); ?></span>
                                        <?php else : ?>
                                            <?php echo esc_html($listing['address']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($listing['city']) ? esc_html($listing['city']) : '<span style="color: #999;">—</span>'; ?>
                                    </td>
                                    <td>
                                        <?php if (empty($listing['zip'])) : ?>
                                            <span style="color: #d63638; font-weight: bold;"><?php _e('Missing', 'maloney-listings'); ?></span>
                                        <?php else : ?>
                                            <?php echo esc_html($listing['zip']); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="card" style="max-width: 800px; margin-top: 20px;">
                    <h2><?php _e('All Listings Have Address and Zip Code', 'maloney-listings'); ?></h2>
                    <p style="color: green;">
                        <strong>✓</strong> <?php _e('Great! All listings have both an address and a zip code.', 'maloney-listings'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for batch geocoding and extracting zip codes
     */
    public function ajax_batch_geocode_zip_codes() {
        check_ajax_referer('geocode_zip_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $force_update = isset($_POST['force_update']) && $_POST['force_update'] === '1';
        
        // Ensure field exists
        $field_result = Maloney_Listings_Zip_Code_Extraction::ensure_zip_field_exists();
        if (is_wp_error($field_result)) {
            wp_send_json_error($field_result->get_error_message());
        }
        
        // Run batch geocoding and extraction
        $results = Maloney_Listings_Zip_Code_Extraction::batch_geocode_and_extract_zip($force_update, 20);
        
        wp_send_json_success($results);
    }
    
    /**
     * Add JavaScript for geocoding zip codes
     */
    public function add_geocode_zip_scripts() {
        $screen = get_current_screen();
        // Screen ID format: {post_type}_page_{menu_slug}
        if (!$screen || $screen->id !== 'listing_page_extract-zip-codes') {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            const geocodeZipNonce = '<?php echo wp_create_nonce('geocode_zip_nonce'); ?>';
            const geocodeStatsNonce = '<?php echo wp_create_nonce('geocode_stats_nonce'); ?>';
            let isGeocodingZip = false;
            let geocodeZipQueue = [];
            
            // Get initial stats
            function updateGeocodeZipStats() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_geocode_stats',
                        nonce: geocodeStatsNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            const total = response.data.with_coords + response.data.needing;
                            const percentage = total > 0 ? Math.round((response.data.with_coords / total) * 100) : 0;
                            // Update stats if needed
                        }
                    }
                });
            }
            
            $('#start-geocode-zip').on('click', function() {
                if (isGeocodingZip) return;
                
                isGeocodingZip = true;
                $('#geocode-zip-errors').hide();
                $('#geocode-zip-errors-list').empty();
                $('#geocode-zip-successful').hide();
                $('#geocode-zip-successful-list').empty();
                $('#start-geocode-zip').hide();
                $('#stop-geocode-zip').show();
                $('#geocode-zip-progress').show();
                $('#geocode-zip-status-text').text('Starting geocoding...');
                $('#geocode-zip-details').text('');
                
                geocodeZipNextBatch();
            });
            
            $('#stop-geocode-zip').on('click', function() {
                isGeocodingZip = false;
                $('#start-geocode-zip').show();
                $('#stop-geocode-zip').hide();
                $('#geocode-zip-status-text').text('Geocoding stopped by user.');
                $('#geocode-zip-details').text('');
            });
            
            function geocodeZipNextBatch() {
                if (!isGeocodingZip) return;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'batch_geocode_zip_codes',
                        nonce: geocodeZipNonce,
                        force_update: false
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            
                            // Calculate progress
                            // We need to get total count first
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'get_geocode_stats',
                                    nonce: geocodeStatsNonce
                                },
                                success: function(statsResponse) {
                                    if (statsResponse.success) {
                                        const total = statsResponse.data.with_coords + statsResponse.data.needing;
                                        const percentage = total > 0 ? Math.round((statsResponse.data.with_coords / total) * 100) : 0;
                                        
                                        $('#geocode-zip-progress-bar').css('width', percentage + '%');
                                        $('#geocode-zip-progress-text').text(percentage + '%');
                                    }
                                }
                            });
                            
                            // Update successful results
                            if (data.successful && data.successful.length > 0) {
                                data.successful.forEach(function(item) {
                                    let html = '<li style="padding: 5px 0; border-bottom: 1px solid rgba(0,0,0,0.1);">';
                                    html += '<strong><a href="post.php?post=' + item.id + '&action=edit" target="_blank">' + item.title + '</a></strong>';
                                    if (item.zip_code) {
                                        html += ' - Zip: <strong>' + item.zip_code + '</strong>';
                                    }
                                    if (item.coordinates) {
                                        html += ' - Coordinates: ' + item.coordinates;
                                    }
                                    html += '</li>';
                                    $('#geocode-zip-successful-list').append(html);
                                });
                                $('#geocode-zip-successful-count').text(data.successful.length);
                                $('#geocode-zip-successful').show();
                            }
                            
                            // Update errors
                            if (data.errors && data.errors.length > 0) {
                                data.errors.forEach(function(err) {
                                    let html = '<li style="padding: 5px 0; border-bottom: 1px solid rgba(0,0,0,0.1);">';
                                    html += '<strong><a href="post.php?post=' + err.id + '&action=edit" target="_blank">' + err.title + '</a></strong>';
                                    html += ' - <span style="color: #d63638;">' + err.error + '</span>';
                                    html += '</li>';
                                    $('#geocode-zip-errors-list').append(html);
                                });
                                $('#geocode-zip-errors-count').text(data.errors.length);
                                $('#geocode-zip-errors').show();
                            }
                            
                            if (data.has_more) {
                                $('#geocode-zip-status-text').text('Geocoding in progress...');
                                const details = 'Processed: ' + data.processed + ' | ' +
                                              'Zip codes extracted: ' + data.extracted + ' | ' +
                                              'Coordinates updated: ' + data.geocoded + ' | ' +
                                              'Skipped: ' + data.skipped + ' | ' +
                                              'Failed: ' + data.failed;
                                $('#geocode-zip-details').text(details);
                                
                                // Continue with next batch after a short delay
                                setTimeout(geocodeZipNextBatch, 500);
                            } else {
                                // Done
                                $('#geocode-zip-status-text').text('Geocoding complete!');
                                $('#geocode-zip-progress-bar').css('background', '#46b450');
                                const details = 'Processed: ' + data.processed + ' | ' +
                                              'Zip codes extracted: ' + data.extracted + ' | ' +
                                              'Coordinates updated: ' + data.geocoded + ' | ' +
                                              'Skipped: ' + data.skipped + ' | ' +
                                              'Failed: ' + data.failed;
                                $('#geocode-zip-details').text(details);
                                
                                isGeocodingZip = false;
                                $('#start-geocode-zip').show();
                                $('#stop-geocode-zip').hide();
                                
                                // Don't auto-reload, let user see the results
                            }
                        } else {
                            $('#geocode-zip-status-text').text('Error: ' + (response.data || 'Unknown error'));
                            isGeocodingZip = false;
                            $('#start-geocode-zip').show();
                            $('#stop-geocode-zip').hide();
                        }
                    },
                    error: function() {
                        $('#geocode-zip-status-text').text('Error: Failed to communicate with server.');
                        isGeocodingZip = false;
                        $('#start-geocode-zip').show();
                        $('#stop-geocode-zip').hide();
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler to validate all existing coordinates
     */
    public function ajax_validate_all_coordinates() {
        check_ajax_referer('validate_coordinates_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $geocoding = new Maloney_Listings_Geocoding();
        $count = $geocoding->validate_existing_coordinates();
        
        wp_send_json_success(array(
            'count' => $count,
        ));
    }
    
    /**
     * Add description under title in listings admin page
     */
    public function add_listings_page_description() {
        global $pagenow, $typenow;
        
        // Only show on main listings page (not child pages like add-current-availability, migrate-condo-listings, etc.)
        if ($pagenow === 'edit.php' && $typenow === 'listing' && empty($_GET['page'])) {
            // Check if there are listings needing geocoding
            $args = array(
                'post_type' => 'listing',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_listing_latitude',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => '_listing_latitude',
                        'value' => '',
                        'compare' => '=',
                    ),
                ),
            );
            
            $query = new WP_Query($args);
            $listings_needing_geocode = $query->found_posts;
            wp_reset_postdata();
            
            // Show description
            echo '<div class="notice notice-info" style="margin: 15px 0 0 0; padding: 12px 20px;">';
            echo '<p style="margin: 0; font-size: 14px;">' . __('Manage all your property listings here. Use the filters above to find specific listings by type or availability.', 'maloney-listings') . '</p>';
            echo '</div>';
            
            // Show geocoding notice if there are listings needing geocoding
            if ($listings_needing_geocode > 0) {
                $geocode_url = admin_url('edit.php?post_type=listing&page=geocode-addresses');
                echo '<div class="notice notice-warning" style="margin: 15px 0 0 0; padding: 12px 20px;">';
                echo '<p style="margin: 0; font-size: 14px;">';
                printf(
                    __('<strong>%d listing(s)</strong> still need geocoding. Go to <a href="%s">Geocode listing addresses</a> to ensure they appear correctly on the map.', 'maloney-listings'),
                    $listings_needing_geocode,
                    esc_url($geocode_url)
                );
                echo '</p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Remove Custom Fields meta box for listings
     */
    public function remove_custom_fields_meta_box() {
        remove_meta_box('postcustom', 'listing', 'normal');
    }
    
    /**
     * Add availability meta box to listing edit page (only for rental properties)
     */
    public function add_availability_meta_box() {
        global $post;
        
        // Only add meta box for rentals
        // Check if editing existing post
        if ($post && $post->ID) {
            $listing_types = wp_get_post_terms($post->ID, 'listing_type', array('fields' => 'slugs'));
            $is_rental = !empty($listing_types) && in_array('rental', $listing_types);
            
            // For new posts, check URL parameter or unit_type field
            if (!$is_rental && $post->post_status === 'auto-draft') {
                $unit_type = isset($_GET['unit_type']) ? sanitize_text_field($_GET['unit_type']) : '';
                if (empty($unit_type)) {
                    // Check if unit_type field exists and is set
                    $unit_type = get_post_meta($post->ID, '_listing_unit_type', true);
                }
                $is_rental = ($unit_type === 'rental');
            }
            
            // Only add meta box if it's a rental
            if ($is_rental) {
                add_meta_box(
                    'listing_availability',
                    __('Current Rental Availability', 'maloney-listings'),
                    array($this, 'render_availability_meta_box'),
                    'listing',
                    'normal',
                    'high'
                );
            }
        } else {
            // For new posts, check URL parameter
            $unit_type = isset($_GET['unit_type']) ? sanitize_text_field($_GET['unit_type']) : '';
            if ($unit_type === 'rental') {
                add_meta_box(
                    'listing_availability',
                    __('Current Rental Availability', 'maloney-listings'),
                    array($this, 'render_availability_meta_box'),
                    'listing',
                    'normal',
                    'high'
                );
            }
        }
    }
    
    /**
     * Render the availability meta box
     * Only shows for rental properties
     */
    public function render_availability_meta_box($post) {
        // Double-check if this is a rental property (meta box should only be added for rentals, but check again)
        $listing_types = wp_get_post_terms($post->ID, 'listing_type', array('fields' => 'slugs'));
        $is_rental = !empty($listing_types) && in_array('rental', $listing_types);
        
        // For new posts, also check unit_type
        if (!$is_rental && $post->post_status === 'auto-draft') {
            $unit_type = isset($_GET['unit_type']) ? sanitize_text_field($_GET['unit_type']) : '';
            if (empty($unit_type)) {
                $unit_type = get_post_meta($post->ID, '_listing_unit_type', true);
            }
            $is_rental = ($unit_type === 'rental');
        }
        
        if (!$is_rental) {
            echo '<p>' . __('This section is only available for rental properties.', 'maloney-listings') . '</p>';
            return;
        }
        
        // Get existing availability data
        $all_availability = array();
        if (class_exists('Maloney_Listings_Available_Units_Fields') && $post->ID) {
            $all_availability = Maloney_Listings_Available_Units_Fields::get_availability_data($post->ID);
        }
        
        // Get all unit size options for JavaScript
        $unit_size_options = array();
        if (function_exists('wpcf_admin_fields_get_fields')) {
            $fields = wpcf_admin_fields_get_fields();
            if (isset($fields['availability-bedrooms']) && isset($fields['availability-bedrooms']['data']['options']) && !empty($fields['availability-bedrooms']['data']['options'])) {
                foreach ($fields['availability-bedrooms']['data']['options'] as $key => $option) {
                    $value = is_array($option) && isset($option['value']) ? $option['value'] : (is_array($option) && isset($option['title']) ? $option['title'] : $key);
                    $label = is_array($option) && isset($option['title']) ? $option['title'] : (is_array($option) && isset($option['value']) ? $option['value'] : $key);
                    $unit_size_options[] = array('value' => $value, 'label' => $label);
                }
            }
        }
        // Fallback to default options if Toolset field not found or has no options
        if (empty($unit_size_options)) {
            $default_options = array('Studio', '1-Bedroom', '2-Bedroom', '3-Bedroom', '4-Bedroom', '4+ Bedroom', '5-Bedroom', '6-Bedroom');
            foreach ($default_options as $opt) {
                $unit_size_options[] = array('value' => $opt, 'label' => $opt);
            }
        }
        
        wp_nonce_field('save_availability_meta_box', 'availability_meta_box_nonce');
        ?>
        <div id="availability_meta_box_entries">
            <?php if (!empty($all_availability)) : ?>
                <?php foreach ($all_availability as $index => $entry) : ?>
                    <div class="availability-entry" style="border: 1px solid #ddd; margin: 10px 0; background: #f9f9f9;">
                        <h3 style="margin: 0; padding: 15px; background: #e5e5e5; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center;" class="entry-header">
                            <span style="display: flex; align-items: center; gap: 10px;">
                                <span class="entry-toggle" style="font-size: 12px; color: #666;">▼</span>
                                <?php _e('Entry', 'maloney-listings'); ?> #<?php echo ($index + 1); ?>
                            </span>
                            <button type="button" class="button remove-entry" style="margin: 0;"><?php _e('Remove', 'maloney-listings'); ?></button>
                        </h3>
                        <div class="entry-content" style="padding: 15px; display: block;">
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Unit Size', 'maloney-listings'); ?></label></th>
                                <td>
                                    <select name="availability[<?php echo $index; ?>][bedrooms]" required>
                                        <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                        <?php
                                        $options_found = false;
                                        $saved_value = isset($entry['bedrooms']) ? $entry['bedrooms'] : '';
                                        $found_saved_value = false;
                                        
                                        // Get all available unit size options from the Toolset field
                                        if (function_exists('wpcf_admin_fields_get_fields')) {
                                            $fields = wpcf_admin_fields_get_fields();
                                            if (isset($fields['availability-bedrooms']) && isset($fields['availability-bedrooms']['data']['options']) && !empty($fields['availability-bedrooms']['data']['options'])) {
                                                foreach ($fields['availability-bedrooms']['data']['options'] as $key => $option) {
                                                    $value = is_array($option) && isset($option['value']) ? $option['value'] : (is_array($option) && isset($option['title']) ? $option['title'] : $key);
                                                    $label = is_array($option) && isset($option['title']) ? $option['title'] : (is_array($option) && isset($option['value']) ? $option['value'] : $key);
                                                    
                                                    // Check if this is the saved value
                                                    if ($value === $saved_value || $label === $saved_value) {
                                                        $found_saved_value = true;
                                                    }
                                                    
                                                    ?>
                                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($entry['bedrooms'], $value); ?>><?php echo esc_html($label); ?></option>
                                                    <?php
                                                    $options_found = true;
                                                }
                                            }
                                        }
                                        
                                        // If saved value exists but wasn't found in options, add it
                                        if (!empty($saved_value) && !$found_saved_value) {
                                            ?>
                                            <option value="<?php echo esc_attr($saved_value); ?>" selected><?php echo esc_html($saved_value); ?></option>
                                            <?php
                                        }
                                        
                                        // Fallback to default options if Toolset field not found or has no options
                                        if (!$options_found) {
                                            $default_options = array('Studio', '1-Bedroom', '2-Bedroom', '3-Bedroom', '4-Bedroom', '4+ Bedroom', '5-Bedroom', '6-Bedroom');
                                            foreach ($default_options as $opt) {
                                                ?>
                                                <option value="<?php echo esc_attr($opt); ?>" <?php selected($entry['bedrooms'], $opt); ?>><?php echo esc_html($opt); ?></option>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Bathrooms', 'maloney-listings'); ?></label></th>
                                <td>
                                    <select name="availability[<?php echo $index; ?>][bathrooms]">
                                        <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                        <option value="1" <?php selected($entry['bathrooms'], '1'); ?>>1</option>
                                        <option value="1.5" <?php selected($entry['bathrooms'], '1.5'); ?>>1.5</option>
                                        <option value="2" <?php selected($entry['bathrooms'], '2'); ?>>2</option>
                                        <option value="2.5" <?php selected($entry['bathrooms'], '2.5'); ?>>2.5</option>
                                        <option value="3" <?php selected($entry['bathrooms'], '3'); ?>>3</option>
                                        <option value="3.5" <?php selected($entry['bathrooms'], '3.5'); ?>>3.5</option>
                                        <option value="4" <?php selected($entry['bathrooms'], '4'); ?>>4</option>
                                        <option value="4.5" <?php selected($entry['bathrooms'], '4.5'); ?>>4.5</option>
                                        <option value="5+" <?php selected($entry['bathrooms'], '5+'); ?>>5+</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Total Monthly Leasing Price', 'maloney-listings'); ?></label></th>
                                <td><input type="number" name="availability[<?php echo $index; ?>][rent]" value="<?php echo esc_attr($entry['rent']); ?>" step="0.01"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Minimum Income', 'maloney-listings'); ?></label></th>
                                <td><input type="number" name="availability[<?php echo $index; ?>][minimum_income]" value="<?php echo esc_attr($entry['minimum_income']); ?>" step="0.01"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Income Limit (AMI %)', 'maloney-listings'); ?></label></th>
                                <td>
                                    <select name="availability[<?php echo $index; ?>][income_limit]">
                                        <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                        <?php
                                                        $income_limit_terms = get_terms(array(
                                                            'taxonomy' => 'income_limit',
                                                            'hide_empty' => false,
                                                            'orderby' => 'name',
                                                            'order' => 'ASC',
                                                        ));
                                                        if (!is_wp_error($income_limit_terms) && !empty($income_limit_terms)) {
                                                            foreach ($income_limit_terms as $term) {
                                                                ?>
                                                                <option value="<?php echo esc_attr($term->name); ?>" <?php selected($entry['income_limit'], $term->name); ?>><?php echo esc_html($term->name); ?></option>
                                                                <?php
                                                            }
                                                        }
                                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <?php
                            // Concessions field (only show if enabled in settings)
                            $settings = Maloney_Listings_Settings::get_setting(null, array());
                            $enable_concessions = isset($settings['enable_concessions_filter']) ? $settings['enable_concessions_filter'] === '1' : false;
                            if ($enable_concessions) :
                                $concessions_terms = get_terms(array(
                                    'taxonomy' => 'concessions',
                                    'hide_empty' => false,
                                    'orderby' => 'name',
                                    'order' => 'ASC',
                                ));
                                if (!is_wp_error($concessions_terms) && !empty($concessions_terms)) :
                                    // Get existing concessions for this entry
                                    $entry_concessions = isset($entry['concessions']) ? (is_array($entry['concessions']) ? $entry['concessions'] : array($entry['concessions'])) : array();
                            ?>
                            <tr>
                                <th><label><?php _e('Concessions', 'maloney-listings'); ?></label></th>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <?php foreach ($concessions_terms as $term) : ?>
                                            <label style="display: flex; align-items: center; gap: 8px;">
                                                <input type="checkbox" name="availability[<?php echo $index; ?>][concessions][]" value="<?php echo esc_attr($term->term_id); ?>" <?php checked(in_array($term->term_id, $entry_concessions)); ?> />
                                                <?php echo esc_html($term->name); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php
                                endif;
                            endif;
                            ?>
                            <tr>
                                <th><label><?php _e('Type', 'maloney-listings'); ?></label></th>
                                <td>
                                    <select name="availability[<?php echo $index; ?>][type]">
                                        <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                        <option value="Lottery" <?php selected($entry['type'], 'Lottery'); ?>>Lottery</option>
                                        <option value="FCFS" <?php selected($entry['type'], 'FCFS'); ?>>FCFS</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Units Available', 'maloney-listings'); ?></label></th>
                                <td><input type="number" name="availability[<?php echo $index; ?>][units_available]" value="<?php echo esc_attr($entry['units_available']); ?>" min="0" required></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Accessible Units', 'maloney-listings'); ?></label></th>
                                <td><textarea name="availability[<?php echo $index; ?>][accessible_units]" rows="3" style="width: 100%;"><?php echo esc_textarea($entry['accessible_units']); ?></textarea></td>
                            </tr>
                        </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <p>
            <button type="button" id="add_availability_entry" class="button button-primary"><?php _e('+ Add Entry', 'maloney-listings'); ?></button>
            <a href="<?php echo admin_url('edit.php?post_type=listing&page=add-current-availability'); ?>" class="button" style="margin-left: 10px;"><?php _e('View All Availability', 'maloney-listings'); ?></a>
        </p>
        
        <script>
        jQuery(document).ready(function($) {
            // Unit size options from Toolset field
            var unitSizeOptions = <?php echo json_encode($unit_size_options); ?>;
            
            // Income limit terms from taxonomy
            var incomeLimitTerms = [
                <?php
                $income_limit_terms = get_terms(array(
                    'taxonomy' => 'income_limit',
                    'hide_empty' => false,
                    'orderby' => 'name',
                    'order' => 'ASC',
                ));
                if (!is_wp_error($income_limit_terms) && !empty($income_limit_terms)) {
                    $term_names = array();
                    foreach ($income_limit_terms as $term) {
                        $term_names[] = "'" . esc_js($term->name) . "'";
                    }
                    echo implode(', ', $term_names);
                }
                ?>
            ];
            
            // Concessions terms from taxonomy (only if enabled)
            var concessionsTerms = [];
            var enableConcessions = false;
            <?php
            $settings = Maloney_Listings_Settings::get_setting(null, array());
            $enable_concessions = isset($settings['enable_concessions_filter']) ? $settings['enable_concessions_filter'] === '1' : false;
            if ($enable_concessions) {
                $concessions_terms = get_terms(array(
                    'taxonomy' => 'concessions',
                    'hide_empty' => false,
                    'orderby' => 'name',
                    'order' => 'ASC',
                ));
                if (!is_wp_error($concessions_terms) && !empty($concessions_terms)) {
                    echo "enableConcessions = true;\n";
                    echo "concessionsTerms = [\n";
                    $term_data = array();
                    foreach ($concessions_terms as $term) {
                        $term_data[] = "{id: " . intval($term->term_id) . ", name: '" . esc_js($term->name) . "'}";
                    }
                    echo implode(",\n", $term_data);
                    echo "\n];\n";
                }
            }
            ?>
            
            var entryIndex = <?php echo !empty($all_availability) ? count($all_availability) : 0; ?>;
            
            // Collapsible entry functionality
            $(document).on('click', '#availability_meta_box_entries .entry-header', function(e) {
                if ($(e.target).hasClass('remove-entry') || $(e.target).closest('.remove-entry').length) {
                    return; // Don't toggle if clicking remove button
                }
                var $entry = $(this).closest('.availability-entry');
                var $content = $entry.find('.entry-content');
                var $toggle = $(this).find('.entry-toggle');
                
                if ($content.is(':visible')) {
                    $content.slideUp(200);
                    $toggle.text('▶');
                } else {
                    $content.slideDown(200);
                    $toggle.text('▼');
                }
            });
            
            $('#add_availability_entry').on('click', function() {
                var entryHtml = '<div class="availability-entry" style="border: 1px solid #ddd; margin: 10px 0; background: #f9f9f9;">' +
                    '<h3 style="margin: 0; padding: 15px; background: #e5e5e5; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center;" class="entry-header">' +
                    '<span style="display: flex; align-items: center; gap: 10px;">' +
                    '<span class="entry-toggle" style="font-size: 12px; color: #666;">▼</span>' +
                    '<?php _e('Entry', 'maloney-listings'); ?> #' + (entryIndex + 1) +
                    '</span>' +
                    '<button type="button" class="button remove-entry" style="margin: 0;"><?php _e('Remove', 'maloney-listings'); ?></button>' +
                    '</h3>' +
                    '<div class="entry-content" style="padding: 15px; display: block;">' +
                    '<table class="form-table">' +
                    '<tr><th><label><?php _e('Unit Size', 'maloney-listings'); ?></label></th>' +
                    '<td><select name="availability[' + entryIndex + '][bedrooms]" required>' +
                    '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                    (function() {
                        var options = '';
                        for (var i = 0; i < unitSizeOptions.length; i++) {
                            options += '<option value="' + unitSizeOptions[i].value + '">' + unitSizeOptions[i].label + '</option>';
                        }
                        return options;
                    })() +
                    '</select></td></tr>') +
                    '<tr><th><label><?php _e('Bathrooms', 'maloney-listings'); ?></label></th>' +
                    '<td><select name="availability[' + entryIndex + '][bathrooms]">' +
                    '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                    '<option value="1">1</option>' +
                    '<option value="1.5">1.5</option>' +
                    '<option value="2">2</option>' +
                    '<option value="2.5">2.5</option>' +
                    '<option value="3">3</option>' +
                    '<option value="3.5">3.5</option>' +
                    '<option value="4">4</option>' +
                    '<option value="4.5">4.5</option>' +
                    '<option value="5+">5+</option>' +
                    '</select></td></tr>' +
                    '<tr><th><label><?php _e('Total Monthly Leasing Price', 'maloney-listings'); ?></label></th>' +
                    '<td><input type="number" name="availability[' + entryIndex + '][rent]" step="0.01"></td></tr>' +
                    '<tr><th><label><?php _e('Minimum Income', 'maloney-listings'); ?></label></th>' +
                    '<td><input type="number" name="availability[' + entryIndex + '][minimum_income]" step="0.01"></td></tr>' +
                    '<tr><th><label><?php _e('Income Limit (AMI %)', 'maloney-listings'); ?></label></th>' +
                    '<td><select name="availability[' + entryIndex + '][income_limit]">' +
                    '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                    (function() {
                        var options = '';
                        for (var i = 0; i < incomeLimitTerms.length; i++) {
                            options += '<option value="' + incomeLimitTerms[i] + '">' + incomeLimitTerms[i] + '</option>';
                        }
                        return options;
                    })() +
                    '</select></td></tr>' +
                    (enableConcessions && concessionsTerms.length > 0 ? 
                        '<tr><th><label><?php _e('Concessions', 'maloney-listings'); ?></label></th>' +
                        '<td><div style="display: flex; flex-direction: column; gap: 8px;">' +
                        (function() {
                            var html = '';
                            for (var j = 0; j < concessionsTerms.length; j++) {
                                html += '<label style="display: flex; align-items: center; gap: 8px;">' +
                                        '<input type="checkbox" name="availability[' + entryIndex + '][concessions][]" value="' + concessionsTerms[j].id + '" />' +
                                        concessionsTerms[j].name +
                                        '</label>';
                            }
                            return html;
                        })() +
                        '</div></td></tr>' : '') +
                    '<tr><th><label><?php _e('Type', 'maloney-listings'); ?></label></th>' +
                    '<td><select name="availability[' + entryIndex + '][type]">' +
                    '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                    '<option value="Lottery">Lottery</option>' +
                    '<option value="FCFS">FCFS</option>' +
                    '</select></td></tr>' +
                    '<tr><th><label><?php _e('Units Available', 'maloney-listings'); ?></label></th>' +
                    '<td><input type="number" name="availability[' + entryIndex + '][units_available]" min="0" required></td></tr>' +
                    '<tr><th><label><?php _e('Accessible Units', 'maloney-listings'); ?></label></th>' +
                    '<td><textarea name="availability[' + entryIndex + '][accessible_units]" rows="3" style="width: 100%;"></textarea></td></tr>' +
                    '</table></div></div>';
                
                $('#availability_meta_box_entries').append(entryHtml);
                entryIndex++;
            });
            
            $(document).on('click', '#availability_meta_box_entries .remove-entry', function() {
                $(this).closest('.availability-entry').remove();
                // Renumber entries
                $('#availability_meta_box_entries .availability-entry').each(function(index) {
                    $(this).find('.entry-header span').first().html('<span class="entry-toggle" style="font-size: 12px; color: #666;">▼</span><?php _e('Entry', 'maloney-listings'); ?> #' + (index + 1));
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save availability data from meta box
     */
    public function save_availability_meta_box($post_id, $post) {
        // Check nonce
        if (!isset($_POST['availability_meta_box_nonce']) || !wp_verify_nonce($_POST['availability_meta_box_nonce'], 'save_availability_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Only save for rental properties
        $listing_types = wp_get_post_terms($post_id, 'listing_type', array('fields' => 'slugs'));
        if (empty($listing_types) || !in_array('rental', $listing_types)) {
            return;
        }
        
        // Get availability data from form
        $availability = isset($_POST['availability']) ? $_POST['availability'] : array();
        
        // Clear existing repetitive field values
        delete_post_meta($post_id, 'wpcf-availability-property');
        delete_post_meta($post_id, 'wpcf-availability-town');
        delete_post_meta($post_id, 'wpcf-availability-bedrooms');
        delete_post_meta($post_id, 'wpcf-availability-bathrooms');
        delete_post_meta($post_id, 'wpcf-availability-rent');
        delete_post_meta($post_id, 'wpcf-availability-minimum-income');
        delete_post_meta($post_id, 'wpcf-availability-income-limit');
        delete_post_meta($post_id, 'wpcf-availability-concessions');
        delete_post_meta($post_id, 'wpcf-availability-concessions-count');
        delete_post_meta($post_id, 'wpcf-availability-type');
        delete_post_meta($post_id, 'wpcf-availability-units-available');
        delete_post_meta($post_id, 'wpcf-availability-accessible-units');
        delete_post_meta($post_id, 'wpcf-availability-view-apply');
        
        // Get property data for auto-fill
        $property_town = get_post_meta($post_id, 'wpcf-city', true);
        if (empty($property_town)) {
            $property_town = get_post_meta($post_id, '_listing_city', true);
        }
        $property_link = get_permalink($post_id);
        
        $total_available = 0;
        
        // Save each entry
        foreach ($availability as $entry) {
            $bedrooms = isset($entry['bedrooms']) ? trim($entry['bedrooms']) : '';
            $units_available = isset($entry['units_available']) ? intval($entry['units_available']) : 0;
            
            if (empty($bedrooms) || $units_available <= 0) {
                continue; // Skip invalid entries
            }
            
            $bathrooms = isset($entry['bathrooms']) ? sanitize_text_field($entry['bathrooms']) : '';
            $rent = isset($entry['rent']) ? sanitize_text_field($entry['rent']) : '';
            $minimum_income = isset($entry['minimum_income']) ? sanitize_text_field($entry['minimum_income']) : '';
            $income_limit = isset($entry['income_limit']) ? sanitize_text_field($entry['income_limit']) : '';
            $type = isset($entry['type']) ? sanitize_text_field($entry['type']) : '';
            $accessible_units = isset($entry['accessible_units']) ? sanitize_textarea_field($entry['accessible_units']) : '';
            
            // Clean up rent and income (remove $ and commas)
            $rent_clean = preg_replace('/[^0-9.]/', '', $rent);
            $minimum_income_clean = preg_replace('/[^0-9.]/', '', $minimum_income);
            
            // Save as repetitive fields
            add_post_meta($post_id, 'wpcf-availability-property', $post_id);
            add_post_meta($post_id, 'wpcf-availability-town', $property_town);
            add_post_meta($post_id, 'wpcf-availability-bedrooms', $bedrooms);
            if (!empty($bathrooms)) {
                add_post_meta($post_id, 'wpcf-availability-bathrooms', $bathrooms);
            }
            add_post_meta($post_id, 'wpcf-availability-rent', $rent_clean);
            add_post_meta($post_id, 'wpcf-availability-minimum-income', $minimum_income_clean);
            add_post_meta($post_id, 'wpcf-availability-income-limit', $income_limit);
            // Save concessions (multiple selections) - save all for this entry before moving to next entry
            // Store count first, then the concession IDs
            $concession_count = 0;
            if (!empty($entry['concessions']) && is_array($entry['concessions'])) {
                $concession_count = count($entry['concessions']);
                foreach ($entry['concessions'] as $concession_id) {
                    add_post_meta($post_id, 'wpcf-availability-concessions', intval($concession_id));
                }
            }
            // Store count marker to help with retrieval
            if ($concession_count > 0) {
                add_post_meta($post_id, 'wpcf-availability-concessions-count', $concession_count);
            }
            add_post_meta($post_id, 'wpcf-availability-type', $type);
            add_post_meta($post_id, 'wpcf-availability-units-available', $units_available);
            add_post_meta($post_id, 'wpcf-availability-accessible-units', $accessible_units);
            add_post_meta($post_id, 'wpcf-availability-view-apply', esc_url_raw($property_link));
            
            $total_available += $units_available;
        }
        
        // Update total available (for backward compatibility)
        update_post_meta($post_id, 'wpcf-total-available-units', $total_available);
        update_post_meta($post_id, '_listing_total_available_units', $total_available);
    }
    
    /**
     * Add condo listings meta box to listing edit page
     * Only shows for condo properties
     */
    public function add_condo_listings_meta_box() {
        global $post;
        
        // Only add meta box for condos
        // Check if editing existing post
        if ($post && $post->ID) {
            $listing_types = wp_get_post_terms($post->ID, 'listing_type', array('fields' => 'slugs'));
            $is_condo = false;
            if (!empty($listing_types)) {
                foreach ($listing_types as $type_slug) {
                    if (stripos($type_slug, 'condo') !== false || stripos($type_slug, 'condominium') !== false) {
                        $is_condo = true;
                        break;
                    }
                }
            }
            
            // For new posts, check URL parameter or unit_type field
            if (!$is_condo && $post->post_status === 'auto-draft') {
                $unit_type = isset($_GET['unit_type']) ? sanitize_text_field($_GET['unit_type']) : '';
                if (empty($unit_type)) {
                    // Check if unit_type field exists and is set
                    $unit_type = get_post_meta($post->ID, '_listing_unit_type', true);
                }
                $is_condo = ($unit_type === 'condo');
            }
            
            // Only add meta box if it's a condo
            if ($is_condo) {
                add_meta_box(
                    'listing_condo_listings',
                    __('Current Condo Listings', 'maloney-listings'),
                    array($this, 'render_condo_listings_meta_box'),
                    'listing',
                    'normal',
                    'high'
                );
            }
        } else {
            // For new posts, check URL parameter
            $unit_type = isset($_GET['unit_type']) ? sanitize_text_field($_GET['unit_type']) : '';
            if ($unit_type === 'condo') {
                add_meta_box(
                    'listing_condo_listings',
                    __('Current Condo Listings', 'maloney-listings'),
                    array($this, 'render_condo_listings_meta_box'),
                    'listing',
                    'normal',
                    'high'
                );
            }
        }
    }
    
    /**
     * Render the condo listings meta box
     * Only shows for condo properties
     */
    public function render_condo_listings_meta_box($post) {
        // Double-check if this is a condo property
        $listing_types = wp_get_post_terms($post->ID, 'listing_type', array('fields' => 'slugs'));
        $is_condo = false;
        if (!empty($listing_types)) {
            foreach ($listing_types as $type_slug) {
                if (stripos($type_slug, 'condo') !== false || stripos($type_slug, 'condominium') !== false) {
                    $is_condo = true;
                    break;
                }
            }
        }
        
        // For new posts, also check unit_type
        if (!$is_condo && $post->post_status === 'auto-draft') {
            $unit_type = isset($_GET['unit_type']) ? sanitize_text_field($_GET['unit_type']) : '';
            if (empty($unit_type)) {
                $unit_type = get_post_meta($post->ID, '_listing_unit_type', true);
            }
            $is_condo = ($unit_type === 'condo');
        }
        
        if (!$is_condo) {
            echo '<p>' . __('This meta box is only available for condo/condominium listings.', 'maloney-listings') . '</p>';
            return;
        }
        
        // Get existing condo listings data
        $all_condo_listings = array();
        if (class_exists('Maloney_Listings_Condo_Listings_Fields')) {
            $all_condo_listings = Maloney_Listings_Condo_Listings_Fields::get_condo_listings_data($post->ID);
        }
        
        // Get property data for auto-fill
        $property_town = get_post_meta($post->ID, 'wpcf-city', true);
        if (empty($property_town)) {
            $property_town = get_post_meta($post->ID, '_listing_city', true);
        }
        $property_link = get_permalink($post->ID);
        
        wp_nonce_field('save_condo_listings_meta_box', 'condo_listings_meta_box_nonce');
        ?>
        <div id="condo_listings_meta_box">
            <p class="description"><?php _e('Add current condo listings for this property. Each entry represents a unit type with available units.', 'maloney-listings'); ?></p>
            <p>
                <a href="<?php echo admin_url('edit.php?post_type=listing&page=add-current-condo-listings'); ?>" class="button" style="margin-left: 10px;"><?php _e('View All Condo Listings', 'maloney-listings'); ?></a>
            </p>
            
            <div id="condo_listings_meta_box_entries">
                <?php if (!empty($all_condo_listings)) : ?>
                    <?php foreach ($all_condo_listings as $index => $entry) : ?>
                        <div class="condo-listing-entry" style="border: 1px solid #ddd; margin: 10px 0; background: #f9f9f9;">
                            <h3 style="margin: 0; padding: 15px; background: #e5e5e5; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center;" class="entry-header">
                                <span style="display: flex; align-items: center; gap: 10px;">
                                    <span class="entry-toggle" style="font-size: 12px; color: #666;">▼</span>
                                    <?php _e('Entry', 'maloney-listings'); ?> #<?php echo ($index + 1); ?>
                                </span>
                                <button type="button" class="button remove-entry" style="margin: 0;"><?php _e('Remove', 'maloney-listings'); ?></button>
                            </h3>
                            <div class="entry-content" style="padding: 15px; display: block;">
                                <table class="form-table">
                                    <tr>
                                        <th><label><?php _e('Town', 'maloney-listings'); ?></label></th>
                                        <td><input type="text" name="condo_listings[<?php echo $index; ?>][town]" value="<?php echo esc_attr($entry['town']); ?>" placeholder="City | Neighborhood" style="width: 100%;"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Unit Size', 'maloney-listings'); ?></label></th>
                                        <td>
                                            <select name="condo_listings[<?php echo $index; ?>][bedrooms]" required>
                                                <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                                <?php
                                                $options_found = false;
                                                $saved_value = isset($entry['bedrooms']) ? $entry['bedrooms'] : '';
                                                $found_saved_value = false;
                                                
                                                // Get all available unit size options from the Toolset field
                                                if (function_exists('wpcf_admin_fields_get_fields')) {
                                                    $fields = wpcf_admin_fields_get_fields();
                                                    if (isset($fields['condo-listings-bedrooms']) && isset($fields['condo-listings-bedrooms']['data']['options']) && !empty($fields['condo-listings-bedrooms']['data']['options'])) {
                                                        foreach ($fields['condo-listings-bedrooms']['data']['options'] as $key => $option) {
                                                            $value = is_array($option) && isset($option['value']) ? $option['value'] : (is_array($option) && isset($option['title']) ? $option['title'] : $key);
                                                            $label = is_array($option) && isset($option['title']) ? $option['title'] : (is_array($option) && isset($option['value']) ? $option['value'] : $key);
                                                            
                                                            if ($value === $saved_value || $label === $saved_value) {
                                                                $found_saved_value = true;
                                                            }
                                                            
                                                            ?>
                                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($entry['bedrooms'], $value); ?>><?php echo esc_html($label); ?></option>
                                                            <?php
                                                            $options_found = true;
                                                        }
                                                    }
                                                }
                                                
                                                // If saved value exists but wasn't found in options, add it
                                                if (!empty($saved_value) && !$found_saved_value) {
                                                    ?>
                                                    <option value="<?php echo esc_attr($saved_value); ?>" selected><?php echo esc_html($saved_value); ?></option>
                                                    <?php
                                                }
                                                
                                                // Fallback to default options if Toolset field not found or has no options
                                                if (!$options_found) {
                                                    $default_options = array('Studio', '1-Bedroom', '2-Bedroom', '3-Bedroom', '4-Bedroom', '4+ Bedroom', '5-Bedroom', '6-Bedroom');
                                                    foreach ($default_options as $opt) {
                                                        ?>
                                                        <option value="<?php echo esc_attr($opt); ?>" <?php selected($entry['bedrooms'], $opt); ?>><?php echo esc_html($opt); ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Bathrooms', 'maloney-listings'); ?></label></th>
                                        <td>
                                            <select name="condo_listings[<?php echo $index; ?>][bathrooms]">
                                                <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                                <option value="1" <?php selected($entry['bathrooms'], '1'); ?>>1</option>
                                                <option value="1.5" <?php selected($entry['bathrooms'], '1.5'); ?>>1.5</option>
                                                <option value="2" <?php selected($entry['bathrooms'], '2'); ?>>2</option>
                                                <option value="2.5" <?php selected($entry['bathrooms'], '2.5'); ?>>2.5</option>
                                                <option value="3" <?php selected($entry['bathrooms'], '3'); ?>>3</option>
                                                <option value="3.5" <?php selected($entry['bathrooms'], '3.5'); ?>>3.5</option>
                                                <option value="4" <?php selected($entry['bathrooms'], '4'); ?>>4</option>
                                                <option value="4.5" <?php selected($entry['bathrooms'], '4.5'); ?>>4.5</option>
                                                <option value="5+" <?php selected($entry['bathrooms'], '5+'); ?>>5+</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Price', 'maloney-listings'); ?></label></th>
                                        <td><input type="number" name="condo_listings[<?php echo $index; ?>][price]" value="<?php echo esc_attr($entry['price']); ?>" step="0.01"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Income Limit (AMI %)', 'maloney-listings'); ?></label></th>
                                        <td>
                                            <input type="text" name="condo_listings[<?php echo $index; ?>][income_limit]" value="<?php echo esc_attr($entry['income_limit']); ?>" placeholder="e.g., 80% or 80% (Minimum) - 100% (Maximum)" style="width: 100%;">
                                            <p class="description"><?php _e('Enter income limit as percentage (e.g., "80%") or range (e.g., "80% (Minimum) - 100% (Maximum)")', 'maloney-listings'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Type', 'maloney-listings'); ?></label></th>
                                        <td>
                                            <select name="condo_listings[<?php echo $index; ?>][type]">
                                                <option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>
                                                <option value="Lottery" <?php selected($entry['type'], 'Lottery'); ?>>Lottery</option>
                                                <option value="FCFS" <?php selected($entry['type'], 'FCFS'); ?>>FCFS</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Units Available', 'maloney-listings'); ?></label></th>
                                        <td><input type="number" name="condo_listings[<?php echo $index; ?>][units_available]" value="<?php echo esc_attr($entry['units_available']); ?>" min="0" required></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Accessible Units', 'maloney-listings'); ?></label></th>
                                        <td><textarea name="condo_listings[<?php echo $index; ?>][accessible_units]" rows="3" style="width: 100%;"><?php echo esc_textarea($entry['accessible_units']); ?></textarea></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <p>
                <button type="button" id="add_condo_listing_entry" class="button button-primary"><?php _e('+ Add Entry', 'maloney-listings'); ?></button>
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Get unit size options for JavaScript
            var unitSizeOptions = [
                <?php
                $unit_size_options_js = array();
                if (function_exists('wpcf_admin_fields_get_fields')) {
                    $fields_js = wpcf_admin_fields_get_fields();
                    if (isset($fields_js['condo-listings-bedrooms']) && isset($fields_js['condo-listings-bedrooms']['data']['options']) && !empty($fields_js['condo-listings-bedrooms']['data']['options'])) {
                        foreach ($fields_js['condo-listings-bedrooms']['data']['options'] as $key => $option) {
                            $value = is_array($option) && isset($option['value']) ? $option['value'] : (is_array($option) && isset($option['title']) ? $option['title'] : $key);
                            $label = is_array($option) && isset($option['title']) ? $option['title'] : (is_array($option) && isset($option['value']) ? $option['value'] : $key);
                            $unit_size_options_js[] = array('value' => $value, 'label' => $label);
                        }
                    }
                }
                // Fallback to default options if Toolset field not found or has no options
                if (empty($unit_size_options_js)) {
                    $default_options = array('Studio', '1-Bedroom', '2-Bedroom', '3-Bedroom', '4-Bedroom', '4+ Bedroom', '5-Bedroom', '6-Bedroom');
                    foreach ($default_options as $opt) {
                        $unit_size_options_js[] = array('value' => $opt, 'label' => $opt);
                    }
                }
                foreach ($unit_size_options_js as $opt) {
                    echo '{value: "' . esc_js($opt['value']) . '", label: "' . esc_js($opt['label']) . '"},';
                }
                ?>
            ];
            
            var entryIndex = <?php echo !empty($all_condo_listings) ? count($all_condo_listings) : 0; ?>;
            
            // Collapsible entry functionality
            $(document).on('click', '#condo_listings_meta_box_entries .entry-header', function(e) {
                if ($(e.target).hasClass('remove-entry') || $(e.target).closest('.remove-entry').length) {
                    return; // Don't toggle if clicking remove button
                }
                var $entry = $(this).closest('.condo-listing-entry');
                var $content = $entry.find('.entry-content');
                var $toggle = $(this).find('.entry-toggle');
                
                if ($content.is(':visible')) {
                    $content.slideUp(200);
                    $toggle.text('▶');
                } else {
                    $content.slideDown(200);
                    $toggle.text('▼');
                }
            });
            
            $('#add_condo_listing_entry').on('click', function() {
                var entryHtml = '<div class="condo-listing-entry" style="border: 1px solid #ddd; margin: 10px 0; background: #f9f9f9;">' +
                    '<h3 style="margin: 0; padding: 15px; background: #e5e5e5; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center;" class="entry-header">' +
                    '<span style="display: flex; align-items: center; gap: 10px;">' +
                    '<span class="entry-toggle" style="font-size: 12px; color: #666;">▼</span>' +
                    '<?php _e('Entry', 'maloney-listings'); ?> #' + (entryIndex + 1) +
                    '</span>' +
                    '<button type="button" class="button remove-entry" style="margin: 0;"><?php _e('Remove', 'maloney-listings'); ?></button>' +
                    '</h3>' +
                    '<div class="entry-content" style="padding: 15px; display: block;">' +
                    '<table class="form-table">' +
                    '<tr><th><label><?php _e('Town', 'maloney-listings'); ?></label></th>' +
                    '<td><input type="text" name="condo_listings[' + entryIndex + '][town]" placeholder="City | Neighborhood" style="width: 100%;"></td></tr>' +
                    '<tr><th><label><?php _e('Unit Size', 'maloney-listings'); ?></label></th>' +
                    '<td><select name="condo_listings[' + entryIndex + '][bedrooms]" required>' +
                    '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                    (function() {
                        var options = '';
                        for (var i = 0; i < unitSizeOptions.length; i++) {
                            options += '<option value="' + unitSizeOptions[i].value + '">' + unitSizeOptions[i].label + '</option>';
                        }
                        return options;
                    })() +
                    '</select></td></tr>' +
                    '<tr><th><label><?php _e('Bathrooms', 'maloney-listings'); ?></label></th>' +
                    '<td><select name="condo_listings[' + entryIndex + '][bathrooms]">' +
                    '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                    '<option value="1">1</option>' +
                    '<option value="1.5">1.5</option>' +
                    '<option value="2">2</option>' +
                    '<option value="2.5">2.5</option>' +
                    '<option value="3">3</option>' +
                    '<option value="3.5">3.5</option>' +
                    '<option value="4">4</option>' +
                    '<option value="4.5">4.5</option>' +
                    '<option value="5+">5+</option>' +
                    '</select></td></tr>' +
                    '<tr><th><label><?php _e('Price', 'maloney-listings'); ?></label></th>' +
                    '<td><input type="number" name="condo_listings[' + entryIndex + '][price]" step="0.01"></td></tr>' +
                    '<tr><th><label><?php _e('Income Limit (AMI %)', 'maloney-listings'); ?></label></th>' +
                    '<td><input type="text" name="condo_listings[' + entryIndex + '][income_limit]" placeholder="e.g., 80% or 80% (Minimum) - 100% (Maximum)" style="width: 100%;"></td></tr>' +
                    '<tr><th><label><?php _e('Type', 'maloney-listings'); ?></label></th>' +
                    '<td><select name="condo_listings[' + entryIndex + '][type]">' +
                    '<option value=""><?php _e('-- Select --', 'maloney-listings'); ?></option>' +
                    '<option value="Lottery">Lottery</option>' +
                    '<option value="FCFS">FCFS</option>' +
                    '</select></td></tr>' +
                    '<tr><th><label><?php _e('Units Available', 'maloney-listings'); ?></label></th>' +
                    '<td><input type="number" name="condo_listings[' + entryIndex + '][units_available]" min="0" required></td></tr>' +
                    '<tr><th><label><?php _e('Accessible Units', 'maloney-listings'); ?></label></th>' +
                    '<td><textarea name="condo_listings[' + entryIndex + '][accessible_units]" rows="3" style="width: 100%;"></textarea></td></tr>' +
                    '</table></div></div>';
                $('#condo_listings_meta_box_entries').append(entryHtml);
                entryIndex++;
            });
            
            // Remove entry button
            $(document).on('click', '.remove-entry', function() {
                $(this).closest('.condo-listing-entry').remove();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save condo listings data from meta box
     */
    public function save_condo_listings_meta_box($post_id, $post) {
        // Check nonce
        if (!isset($_POST['condo_listings_meta_box_nonce']) || !wp_verify_nonce($_POST['condo_listings_meta_box_nonce'], 'save_condo_listings_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Only save for condo properties
        $listing_types = wp_get_post_terms($post_id, 'listing_type', array('fields' => 'slugs'));
        $is_condo = false;
        if (!empty($listing_types)) {
            foreach ($listing_types as $type_slug) {
                if (stripos($type_slug, 'condo') !== false || stripos($type_slug, 'condominium') !== false) {
                    $is_condo = true;
                    break;
                }
            }
        }
        
        if (!$is_condo) {
            return;
        }
        
        // Get condo listings data from form
        $condo_listings = isset($_POST['condo_listings']) ? $_POST['condo_listings'] : array();
        
        // Clear existing repetitive field values
        delete_post_meta($post_id, 'wpcf-condo-listings-property');
        delete_post_meta($post_id, 'wpcf-condo-listings-town');
        delete_post_meta($post_id, 'wpcf-condo-listings-bedrooms');
        delete_post_meta($post_id, 'wpcf-condo-listings-bathrooms');
        delete_post_meta($post_id, 'wpcf-condo-listings-price');
        delete_post_meta($post_id, 'wpcf-condo-listings-income-limit');
        delete_post_meta($post_id, 'wpcf-condo-listings-type');
        delete_post_meta($post_id, 'wpcf-condo-listings-units-available');
        delete_post_meta($post_id, 'wpcf-condo-listings-accessible-units');
        delete_post_meta($post_id, 'wpcf-condo-listings-view-apply');
        
        // Get property data for auto-fill
        $property_town = get_post_meta($post_id, 'wpcf-city', true);
        if (empty($property_town)) {
            $property_town = get_post_meta($post_id, '_listing_city', true);
        }
        $property_link = get_permalink($post_id);
        
        $total_available = 0;
        
        // Save each entry
        foreach ($condo_listings as $entry) {
            $bedrooms = isset($entry['bedrooms']) ? trim($entry['bedrooms']) : '';
            $units_available = isset($entry['units_available']) ? intval($entry['units_available']) : 0;
            
            if (empty($bedrooms) || $units_available <= 0) {
                continue; // Skip invalid entries
            }
            
            $town = isset($entry['town']) ? sanitize_text_field($entry['town']) : $property_town;
            $bathrooms = isset($entry['bathrooms']) ? sanitize_text_field($entry['bathrooms']) : '';
            $price = isset($entry['price']) ? sanitize_text_field($entry['price']) : '';
            $income_limit = isset($entry['income_limit']) ? sanitize_text_field($entry['income_limit']) : '';
            $type = isset($entry['type']) ? sanitize_text_field($entry['type']) : '';
            $accessible_units = isset($entry['accessible_units']) ? sanitize_textarea_field($entry['accessible_units']) : '';
            
            // Clean up price (remove $ and commas)
            $price_clean = preg_replace('/[^0-9.]/', '', $price);
            
            // Save as repetitive fields
            add_post_meta($post_id, 'wpcf-condo-listings-property', $post_id);
            add_post_meta($post_id, 'wpcf-condo-listings-town', $town);
            add_post_meta($post_id, 'wpcf-condo-listings-bedrooms', $bedrooms);
            if (!empty($bathrooms)) {
                add_post_meta($post_id, 'wpcf-condo-listings-bathrooms', $bathrooms);
            }
            add_post_meta($post_id, 'wpcf-condo-listings-price', $price_clean);
            add_post_meta($post_id, 'wpcf-condo-listings-income-limit', $income_limit);
            add_post_meta($post_id, 'wpcf-condo-listings-type', $type);
            add_post_meta($post_id, 'wpcf-condo-listings-units-available', $units_available);
            add_post_meta($post_id, 'wpcf-condo-listings-accessible-units', $accessible_units);
            add_post_meta($post_id, 'wpcf-condo-listings-view-apply', esc_url_raw($property_link));
            
            $total_available += $units_available;
        }
        
        // Update total available (for backward compatibility)
        update_post_meta($post_id, 'wpcf-total-available-condo-units', $total_available);
        update_post_meta($post_id, '_listing_total_available_condo_units', $total_available);
    }
    
    /**
     * Render Template Blocks management page
     */
    public function render_template_blocks_page() {
        if (!$this->is_developer()) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        
        $message = '';
        $message_type = 'info';
        
        // Handle replace blocks form submission
        if (isset($_POST['replace_blocks']) && check_admin_referer('replace_blocks_action', 'replace_blocks_nonce')) {
            $replace_action = isset($_POST['replace_action']) ? sanitize_text_field($_POST['replace_action']) : 'rental_templates';
            $old_block_pattern = isset($_POST['old_block_pattern']) ? sanitize_text_field($_POST['old_block_pattern']) : '';
            $replace_shortcode = isset($_POST['replace_shortcode']) ? sanitize_text_field($_POST['replace_shortcode']) : '[maloney_listing_availability]';
            $replace_template_id = isset($_POST['replace_template_id']) ? sanitize_text_field($_POST['replace_template_id']) : '';
            
            if (empty($old_block_pattern)) {
                $message = __('Please enter a block pattern to replace.', 'maloney-listings');
                $message_type = 'error';
            } elseif ($replace_action === 'single_template' && empty($replace_template_id)) {
                $message = __('Please select a template.', 'maloney-listings');
                $message_type = 'error';
            } else {
                if (!class_exists('Maloney_Listings_Toolset_Template_Blocks')) {
                    $message = __('Template Blocks class not found.', 'maloney-listings');
                    $message_type = 'error';
                } else {
                    if ($replace_action === 'rental_templates') {
                        // Get all templates for listing post type that are rentals
                        global $WPV_settings;
                        if (!isset($WPV_settings) && class_exists('WPV_Settings')) {
                            $WPV_settings = WPV_Settings::get_instance();
                        }
                        
                        $template_ids = array();
                        $conditions_key = 'views_template_conditions_for_listing';
                        if (isset($WPV_settings[$conditions_key]) && is_array($WPV_settings[$conditions_key])) {
                            foreach ($WPV_settings[$conditions_key] as $condition) {
                                // Check if this template is for rentals
                                if (isset($condition['taxonomy']) && $condition['taxonomy'] === 'listing_type') {
                                    if (isset($condition['terms']) && is_array($condition['terms'])) {
                                        foreach ($condition['terms'] as $term) {
                                            if (stripos($term, 'rental') !== false && isset($condition['template_id'])) {
                                                $template_ids[] = $condition['template_id'];
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Also check default template if it's for rentals
                        $default_key = 'views_template_for_listing';
                        if (isset($WPV_settings[$default_key]) && $WPV_settings[$default_key] > 0) {
                            // We'll include it but the replace function will check content
                            $template_ids[] = $WPV_settings[$default_key];
                        }
                        
                        $template_ids = array_unique($template_ids);
                        
                        if (empty($template_ids)) {
                            $message = __('No rental templates found. Please check your Toolset template settings.', 'maloney-listings');
                            $message_type = 'error';
                        } else {
                            $results = array();
                            $success_count = 0;
                            $error_count = 0;
                            $skipped_count = 0;
                            
                            foreach ($template_ids as $tid) {
                                $result = Maloney_Listings_Toolset_Template_Blocks::replace_block(
                                    $tid,
                                    $old_block_pattern,
                                    'core/shortcode',
                                    array(),
                                    $replace_shortcode
                                );
                                
                                if (is_wp_error($result)) {
                                    // If block not found, that's OK - just skip this template
                                    if ($result->get_error_code() === 'block_not_found') {
                                        // Skip templates that don't have the block - not an error
                                        $skipped_count++;
                                        continue;
                                    }
                                    // Other errors are real errors
                                    $error_count++;
                                    $results[$tid] = $result; // Store WP_Error object
                                } else {
                                    $success_count++;
                                }
                            }
                            
                            // Build message
                            if ($success_count > 0) {
                                $message = sprintf(__('Successfully replaced blocks in %d template(s).', 'maloney-listings'), $success_count);
                                if ($skipped_count > 0) {
                                    $message .= ' ' . sprintf(__('%d template(s) skipped (no matching blocks).', 'maloney-listings'), $skipped_count);
                                }
                                if ($error_count > 0) {
                                    $message .= ' ' . sprintf(__('%d error(s) occurred.', 'maloney-listings'), $error_count);
                                }
                                $message_type = 'success';
                            } else {
                                // No successes
                                if ($skipped_count > 0 && $error_count === 0) {
                                    $message = sprintf(__('Checked %d template(s), but no matching blocks were found in any of them.', 'maloney-listings'), $skipped_count);
                                    $message .= ' ' . __('Make sure the block pattern matches the content. Try using "vacancy-table" as the pattern.', 'maloney-listings');
                                } elseif ($error_count > 0) {
                                    $error_details = array();
                                    foreach ($results as $tid => $result) {
                                        if (is_wp_error($result)) {
                                            $error_details[] = sprintf(__('Template %d: %s', 'maloney-listings'), $tid, $result->get_error_message());
                                        }
                                    }
                                    $message = __('No blocks were replaced.', 'maloney-listings');
                                    if (!empty($error_details)) {
                                        $message .= ' ' . implode('; ', array_slice($error_details, 0, 3));
                                    }
                                } else {
                                    $message = __('No templates were processed.', 'maloney-listings');
                                }
                                $message_type = 'error';
                            }
                        }
                    } else {
                        // Single template
                        $result = Maloney_Listings_Toolset_Template_Blocks::replace_block(
                            $replace_template_id,
                            $old_block_pattern,
                            'core/shortcode',
                            array(),
                            $replace_shortcode
                        );
                        
                        if (is_wp_error($result)) {
                            $message = $result->get_error_message();
                            // Add helpful suggestion
                            if (stripos($result->get_error_message(), 'not found') !== false) {
                                $message .= ' ' . __('Try using "vacancy-table" or "toolset-blocks/fields-and-text" as the pattern. You can also view blocks in the template using the debug section below.', 'maloney-listings');
                            }
                            $message_type = 'error';
                        } else {
                            $message = __('Block replaced successfully!', 'maloney-listings');
                            $message_type = 'success';
                        }
                    }
                }
            }
        }
        
        // Handle form submission
        if (isset($_POST['insert_block']) && check_admin_referer('template_blocks_action', 'template_blocks_nonce')) {
            $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '';
            $block_type = isset($_POST['block_type']) ? sanitize_text_field($_POST['block_type']) : 'shortcode';
            $block_name = isset($_POST['block_name']) ? sanitize_text_field($_POST['block_name']) : '';
            $shortcode = isset($_POST['shortcode']) ? sanitize_text_field($_POST['shortcode']) : '';
            $position = isset($_POST['position']) ? sanitize_text_field($_POST['position']) : 'append';
            $anchor_block = isset($_POST['anchor_block']) ? sanitize_text_field($_POST['anchor_block']) : '';
            
            if (empty($template_id)) {
                $message = __('Please select a template.', 'maloney-listings');
                $message_type = 'error';
            } elseif ($block_type === 'shortcode' && empty($shortcode)) {
                $message = __('Please enter a shortcode.', 'maloney-listings');
                $message_type = 'error';
            } elseif ($block_type === 'custom' && empty($block_name)) {
                $message = __('Please enter a block name.', 'maloney-listings');
                $message_type = 'error';
            } else {
                if (!class_exists('Maloney_Listings_Toolset_Template_Blocks')) {
                    $message = __('Template Blocks class not found.', 'maloney-listings');
                    $message_type = 'error';
                } else {
                    if ($block_type === 'shortcode') {
                        // For shortcode blocks, we need to manually create the block with the shortcode content
                        $template = Maloney_Listings_Toolset_Template_Blocks::get_template($template_id);
                        if ($template) {
                            $blocks = parse_blocks($template->post_content);
                            $shortcode_block = array(
                                'blockName' => 'core/shortcode',
                                'attrs' => array(),
                                'innerContent' => array($shortcode),
                                'innerBlocks' => array(),
                            );
                            
                            // Insert based on position
                            if ($position === 'append') {
                                $blocks[] = $shortcode_block;
                            } elseif ($position === 'prepend') {
                                array_unshift($blocks, $shortcode_block);
                            } elseif ($position === 'before' && !empty($anchor_block)) {
                                // Determine if we should search by content pattern
                                $search_by_content = (stripos($anchor_block, 'neighborhood') !== false || stripos($anchor_block, 'availability') !== false || stripos($anchor_block, 'vacancy') !== false);
                                $index = Maloney_Listings_Toolset_Template_Blocks::find_block_index($blocks, $anchor_block, $search_by_content);
                                if ($index !== false) {
                                    array_splice($blocks, $index, 0, array($shortcode_block));
                                } else {
                                    $blocks[] = $shortcode_block;
                                }
                            } elseif ($position === 'after' && !empty($anchor_block)) {
                                // Determine if we should search by content pattern
                                $search_by_content = (stripos($anchor_block, 'neighborhood') !== false || stripos($anchor_block, 'availability') !== false || stripos($anchor_block, 'vacancy') !== false);
                                $index = Maloney_Listings_Toolset_Template_Blocks::find_block_index($blocks, $anchor_block, $search_by_content);
                                if ($index !== false) {
                                    array_splice($blocks, $index + 1, 0, array($shortcode_block));
                                } else {
                                    $blocks[] = $shortcode_block;
                                }
                            }
                            
                            $new_content = serialize_blocks($blocks);
                            $result = wp_update_post(array(
                                'ID' => $template_id,
                                'post_content' => $new_content,
                            ), true);
                            
                            if (is_wp_error($result)) {
                                $result = $result;
                            } else {
                                $result = true;
                            }
                        } else {
                            $result = new WP_Error('template_not_found', __('Template not found.', 'maloney-listings'));
                        }
                        } else {
                            // For shortcode blocks, pass shortcode in attributes
                            $block_attrs = array();
                            if ($block_type === 'shortcode' && !empty($shortcode)) {
                                $block_attrs['shortcode'] = $shortcode;
                            }
                            
                            // Determine if we should search by content (if anchor_block looks like a content pattern)
                            $search_by_content = false;
                            if (!empty($anchor_block) && (stripos($anchor_block, 'neighborhood') !== false || stripos($anchor_block, 'availability') !== false || stripos($anchor_block, 'vacancy') !== false)) {
                                $search_by_content = true;
                            }
                            
                            $result = Maloney_Listings_Toolset_Template_Blocks::insert_block(
                                $template_id,
                                $block_name,
                                $block_attrs,
                                $position,
                                $anchor_block,
                                $search_by_content
                            );
                        }
                    
                    if (is_wp_error($result)) {
                        $message = $result->get_error_message();
                        $message_type = 'error';
                    } else {
                        $message = __('Block inserted successfully!', 'maloney-listings');
                        $message_type = 'success';
                    }
                }
            }
        }
        
        // Get templates using WP_Query
        $templates = array();
        $templates_query = new WP_Query(array(
            'post_type' => 'view-template',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        
        if ($templates_query->have_posts()) {
            while ($templates_query->have_posts()) {
                $templates_query->the_post();
                $templates[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                );
            }
            wp_reset_postdata();
        }
        
        // Handle auto-assign field groups
        $assign_message = '';
        $assign_message_type = 'info';
        if (isset($_GET['ml_groups_assigned']) && isset($_GET['ml_groups_found'])) {
            $assigned = intval($_GET['ml_groups_assigned']);
            $found = intval($_GET['ml_groups_found']);
            if ($assigned > 0) {
                $assign_message = sprintf(__('Successfully assigned %d field group(s) to the listing post type. Found %d matching group(s).', 'maloney-listings'), $assigned, $found);
                $assign_message_type = 'success';
            } else {
                $assign_message = sprintf(__('Found %d matching field group(s), but they were already assigned to the listing post type.', 'maloney-listings'), $found);
                $assign_message_type = 'info';
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Manage Template Blocks', 'maloney-listings'); ?></h1>
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($assign_message) : ?>
                <div class="notice notice-<?php echo esc_attr($assign_message_type); ?> is-dismissible">
                    <p><?php echo esc_html($assign_message); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php _e('Auto-Assign Toolset Field Groups', 'maloney-listings'); ?></h2>
                <p><?php _e('Automatically assign common Toolset field groups to the "listing" post type. This will find and assign:', 'maloney-listings'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('Property Info', 'maloney-listings'); ?></li>
                    <li><?php _e('Condo Lotteries', 'maloney-listings'); ?></li>
                    <li><?php _e('Condominiums', 'maloney-listings'); ?></li>
                    <li><?php _e('Rental Lotteries', 'maloney-listings'); ?></li>
                    <li><?php _e('Rental Properties', 'maloney-listings'); ?></li>
                    <li><?php _e('Current Rental Availability', 'maloney-listings'); ?></li>
                    <li><?php _e('Current Condo Listings', 'maloney-listings'); ?></li>
                </ul>
                <p><strong><?php _e('Note:', 'maloney-listings'); ?></strong> <?php _e('This tool finds field groups by name, so it works across different databases. Groups that are already assigned will be skipped.', 'maloney-listings'); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('auto_assign_field_groups_action', 'auto_assign_field_groups_nonce'); ?>
                    <p class="submit">
                        <input type="submit" name="auto_assign_field_groups" class="button button-primary" value="<?php _e('Auto-Assign Field Groups', 'maloney-listings'); ?>" />
                    </p>
                </form>
                <?php
                // Handle auto-assign field groups form submission
                if (isset($_POST['auto_assign_field_groups']) && check_admin_referer('auto_assign_field_groups_action', 'auto_assign_field_groups_nonce')) {
                    if (class_exists('Maloney_Listings_Custom_Fields')) {
                        $custom_fields = new Maloney_Listings_Custom_Fields();
                        $assign_results = $custom_fields->auto_assign_field_groups();
                        if (!empty($assign_results) && isset($assign_results['assigned'])) {
                            $assigned = intval($assign_results['assigned']);
                            $found = intval($assign_results['found']);
                            if ($assigned > 0) {
                                echo '<div class="notice notice-success" style="margin-top: 20px;">';
                                echo '<p><strong>' . __('Success:', 'maloney-listings') . '</strong> ' . sprintf(__('Successfully assigned %d field group(s) to the listing post type. Found %d matching group(s).', 'maloney-listings'), $assigned, $found) . '</p>';
                                echo '</div>';
                            } else {
                                echo '<div class="notice notice-info" style="margin-top: 20px;">';
                                echo '<p><strong>' . __('Info:', 'maloney-listings') . '</strong> ' . sprintf(__('Found %d matching field group(s), but they were already assigned to the listing post type.', 'maloney-listings'), $found) . '</p>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="notice notice-info" style="margin-top: 20px;"><p>' . __('No field groups were found or assigned.', 'maloney-listings') . '</p></div>';
                        }
                    } else {
                        echo '<div class="notice notice-error" style="margin-top: 20px;"><p>' . __('Custom Fields class not found.', 'maloney-listings') . '</p></div>';
                    }
                }
                ?>
            </div>
            
            <div class="card">
                <h2><?php _e('Insert Block into Template', 'maloney-listings'); ?></h2>
                <p><?php _e('This tool allows you to programmatically insert blocks into Toolset Content Templates without manually editing them.', 'maloney-listings'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('template_blocks_action', 'template_blocks_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="template_id"><?php _e('Template', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <select name="template_id" id="template_id" required style="width: 100%; max-width: 400px;">
                                    <option value=""><?php _e('-- Select Template --', 'maloney-listings'); ?></option>
                                    <?php foreach ($templates as $template) : ?>
                                        <option value="<?php echo esc_attr($template['id']); ?>">
                                            <?php echo esc_html($template['title']); ?> (ID: <?php echo esc_html($template['id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Select the Toolset Content Template to modify.', 'maloney-listings'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="block_type"><?php _e('Block Type', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <select name="block_type" id="block_type" style="width: 100%; max-width: 400px;">
                                    <option value="shortcode"><?php _e('Shortcode Block', 'maloney-listings'); ?></option>
                                    <option value="custom"><?php _e('Custom Block', 'maloney-listings'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr id="shortcode_row">
                            <th scope="row">
                                <label for="shortcode"><?php _e('Shortcode', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="shortcode" id="shortcode" class="regular-text" placeholder="[maloney_listing_availability]" />
                                <p class="description"><?php _e('Enter the shortcode to insert (e.g., [maloney_listing_availability]).', 'maloney-listings'); ?></p>
                            </td>
                        </tr>
                        
                        <tr id="block_name_row" style="display: none;">
                            <th scope="row">
                                <label for="block_name"><?php _e('Block Name', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="block_name" id="block_name" class="regular-text" placeholder="maloney-listings/availability-block" />
                                <p class="description"><?php _e('Enter the block name (e.g., maloney-listings/availability-block).', 'maloney-listings'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="position"><?php _e('Position', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <select name="position" id="position" style="width: 100%; max-width: 400px;">
                                    <option value="append"><?php _e('Append (at end)', 'maloney-listings'); ?></option>
                                    <option value="prepend"><?php _e('Prepend (at beginning)', 'maloney-listings'); ?></option>
                                    <option value="before"><?php _e('Before anchor block', 'maloney-listings'); ?></option>
                                    <option value="after"><?php _e('After anchor block', 'maloney-listings'); ?></option>
                                </select>
                                <p class="description"><?php _e('Where to insert the block in the template.', 'maloney-listings'); ?></p>
                            </td>
                        </tr>
                        
                        <tr id="anchor_row" style="display: none;">
                            <th scope="row">
                                <label for="anchor_block"><?php _e('Anchor Block Name', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="anchor_block" id="anchor_block" class="regular-text" placeholder="core/paragraph" />
                                <p class="description"><?php _e('Block name to insert before/after (e.g., core/paragraph, core/heading).', 'maloney-listings'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="insert_block" class="button button-primary" value="<?php _e('Insert Block', 'maloney-listings'); ?>" />
                    </p>
                </form>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Replace Existing Blocks', 'maloney-listings'); ?></h2>
                <p><?php _e('Replace existing blocks (like "Current Rental Availability") with your custom blocks across all rental templates.', 'maloney-listings'); ?></p>
                
                <form method="post" action="" style="margin-top: 20px;">
                    <?php wp_nonce_field('replace_blocks_action', 'replace_blocks_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label><?php _e('Action', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="radio" name="replace_action" value="rental_templates" checked />
                                    <?php _e('Replace in all Rental templates', 'maloney-listings'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="replace_action" value="single_template" />
                                    <?php _e('Replace in single template', 'maloney-listings'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr id="replace_template_row" style="display: none;">
                            <th scope="row">
                                <label for="replace_template_id"><?php _e('Template', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <select name="replace_template_id" id="replace_template_id" style="width: 100%; max-width: 400px;">
                                    <option value=""><?php _e('-- Select Template --', 'maloney-listings'); ?></option>
                                    <?php foreach ($templates as $template) : ?>
                                        <option value="<?php echo esc_attr($template['id']); ?>">
                                            <?php echo esc_html($template['title']); ?> (ID: <?php echo esc_html($template['id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="old_block_pattern"><?php _e('Block to Replace', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="old_block_pattern" id="old_block_pattern" class="regular-text" placeholder="vacancy-table or toolset-blocks/fields-and-text" value="vacancy-table" />
                                <p class="description"><?php _e('Block name (e.g., toolset-blocks/fields-and-text, core/shortcode) or content pattern (e.g., "vacancy-table", "Current Rental Availability").', 'maloney-listings'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="replace_shortcode"><?php _e('New Shortcode', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="replace_shortcode" id="replace_shortcode" class="regular-text" value="[maloney_listing_availability]" />
                                <p class="description"><?php _e('Shortcode to replace with (e.g., [maloney_listing_availability]).', 'maloney-listings'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="replace_blocks" class="button button-primary" value="<?php _e('Replace Blocks', 'maloney-listings'); ?>" />
                    </p>
                </form>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Debug: View Blocks in Template', 'maloney-listings'); ?></h2>
                <p><?php _e('Use this to see what blocks are in a template to help identify the correct pattern to use for replacement.', 'maloney-listings'); ?></p>
                
                <form method="post" action="" style="margin-top: 20px;">
                    <?php wp_nonce_field('view_blocks_action', 'view_blocks_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="debug_template_id"><?php _e('Template', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <select name="debug_template_id" id="debug_template_id" style="width: 100%; max-width: 400px;">
                                    <option value=""><?php _e('-- Select Template --', 'maloney-listings'); ?></option>
                                    <?php foreach ($templates as $template) : ?>
                                        <option value="<?php echo esc_attr($template['id']); ?>">
                                            <?php echo esc_html($template['title']); ?> (ID: <?php echo esc_html($template['id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="view_blocks" class="button" value="<?php _e('View Blocks', 'maloney-listings'); ?>" />
                    </p>
                </form>
                
                <?php
                // Handle view blocks form submission
                if (isset($_POST['view_blocks']) && check_admin_referer('view_blocks_action', 'view_blocks_nonce')) {
                    $debug_template_id = isset($_POST['debug_template_id']) ? sanitize_text_field($_POST['debug_template_id']) : '';
                    if (!empty($debug_template_id) && class_exists('Maloney_Listings_Toolset_Template_Blocks')) {
                        $block_list = Maloney_Listings_Toolset_Template_Blocks::list_blocks($debug_template_id);
                        if (!is_wp_error($block_list) && !empty($block_list)) {
                            ?>
                            <h3><?php _e('Blocks Found:', 'maloney-listings'); ?></h3>
                            <table class="widefat striped" style="margin-top: 15px;">
                                <thead>
                                    <tr>
                                        <th><?php _e('Block Name', 'maloney-listings'); ?></th>
                                        <th><?php _e('Content Preview', 'maloney-listings'); ?></th>
                                        <th><?php _e('Depth', 'maloney-listings'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($block_list as $block_info) : ?>
                                        <tr>
                                            <td><code><?php echo esc_html($block_info['blockName']); ?></code></td>
                                            <td><code style="font-size: 11px; word-break: break-all;"><?php echo esc_html(substr($block_info['content'], 0, 200)); ?><?php echo strlen($block_info['content']) > 200 ? '...' : ''; ?></code></td>
                                            <td><?php echo esc_html($block_info['depth']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php
                        } else {
                            echo '<p>' . __('No blocks found or error occurred.', 'maloney-listings') . '</p>';
                        }
                    }
                }
                ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Replace Ninja Table 3596 (Current Condo Listings)', 'maloney-listings'); ?></h2>
                <p><?php _e('Replace all instances of Ninja Table 3596 (Current Condo Listings) with the new <code>[maloney_listing_condo_listings]</code> shortcode. The shortcode will automatically show listings for the current property on single listing pages.', 'maloney-listings'); ?></p>
                
                <form method="post" action="" style="margin-top: 20px;">
                    <?php wp_nonce_field('replace_ninja_table_3596_action', 'replace_ninja_table_3596_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label><?php _e('Action', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="radio" name="ninja_table_3596_action" value="dry_run" checked />
                                    <?php _e('Dry Run (Preview only - no changes)', 'maloney-listings'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="ninja_table_3596_action" value="replace" />
                                    <?php _e('Replace in all Listing templates', 'maloney-listings'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="description">
                        <?php _e('This will find and replace <code>[types field=\'current-condo-listings-table\'][/types]</code> with <code>[maloney_listing_condo_listings]</code> in all Toolset Content Templates assigned to the Listing post type.', 'maloney-listings'); ?>
                    </p>
                    
                    <p class="submit">
                        <input type="submit" name="replace_ninja_table_3596" class="button button-primary" value="<?php _e('Execute', 'maloney-listings'); ?>" />
                    </p>
                </form>
                
                <?php
                if (isset($_POST['replace_ninja_table_3596']) && check_admin_referer('replace_ninja_table_3596_action', 'replace_ninja_table_3596_nonce')) {
                    if (class_exists('Maloney_Listings_Toolset_Template_Blocks')) {
                        $action = isset($_POST['ninja_table_3596_action']) ? sanitize_text_field($_POST['ninja_table_3596_action']) : 'dry_run';
                        $dry_run = ($action === 'dry_run');
                        
                        $results = Maloney_Listings_Toolset_Template_Blocks::replace_ninja_table_3596('listing', $dry_run);
                        
                        if (!empty($results)) {
                            ?>
                            <div class="notice <?php echo $dry_run ? 'notice-info' : 'notice-success'; ?>" style="margin-top: 20px;">
                                <h3><?php echo $dry_run ? __('Dry Run Results:', 'maloney-listings') : __('Replacement Results:', 'maloney-listings'); ?></h3>
                                <table class="widefat striped" style="margin-top: 10px;">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Template ID', 'maloney-listings'); ?></th>
                                            <th><?php _e('Template Title', 'maloney-listings'); ?></th>
                                            <?php if ($dry_run) : ?>
                                                <th><?php _e('Pattern Found', 'maloney-listings'); ?></th>
                                            <?php else : ?>
                                                <th><?php _e('Status', 'maloney-listings'); ?></th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $found_count = 0;
                                        $replaced_count = 0;
                                        foreach ($results as $template_id => $result) : 
                                            $template = get_post($template_id);
                                            $template_title = $template ? $template->post_title : __('Unknown', 'maloney-listings');
                                            
                                            if ($dry_run) {
                                                if ($result['success'] && $result['found']) {
                                                    $found_count++;
                                                }
                                            } else {
                                                if ($result['success'] && $result['replaced']) {
                                                    $replaced_count++;
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo esc_html($template_id); ?></td>
                                                <td><?php echo esc_html($template_title); ?></td>
                                                <td>
                                                    <?php if ($dry_run) : ?>
                                                        <?php if ($result['success'] && $result['found']) : ?>
                                                            <span style="color: #00a32a; font-weight: bold;"><?php _e('Found', 'maloney-listings'); ?></span>
                                                        <?php elseif ($result['success']) : ?>
                                                            <span style="color: #d63638;"><?php _e('Not Found', 'maloney-listings'); ?></span>
                                                        <?php else : ?>
                                                            <span style="color: #d63638;"><?php echo esc_html($result['error']); ?></span>
                                                        <?php endif; ?>
                                                    <?php else : ?>
                                                        <?php if ($result['success'] && $result['replaced']) : ?>
                                                            <span style="color: #00a32a; font-weight: bold;"><?php _e('Replaced', 'maloney-listings'); ?></span>
                                                        <?php elseif ($result['success']) : ?>
                                                            <span style="color: #d63638;"><?php _e('Not Found', 'maloney-listings'); ?></span>
                                                        <?php else : ?>
                                                            <span style="color: #d63638;"><?php echo esc_html($result['error']); ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p style="margin-top: 15px;">
                                    <?php if ($dry_run) : ?>
                                        <strong><?php printf(__('Found pattern in %d template(s).', 'maloney-listings'), $found_count); ?></strong>
                                        <?php if ($found_count > 0) : ?>
                                            <?php _e('Run again with "Replace" selected to apply the changes.', 'maloney-listings'); ?>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <strong><?php printf(__('Replaced in %d template(s).', 'maloney-listings'), $replaced_count); ?></strong>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php
                        }
                    }
                }
                ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Insert Map After Neighborhood', 'maloney-listings'); ?></h2>
                <p><?php _e('Automatically insert the listing map block after the neighborhood block in all Toolset Content Templates.', 'maloney-listings'); ?></p>
                
                <form method="post" action="" style="margin-top: 20px;">
                    <?php wp_nonce_field('insert_map_action', 'insert_map_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label><?php _e('Template Scope', 'maloney-listings'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="radio" name="map_template_scope" value="assigned" checked />
                                    <?php _e('Only templates assigned to Listing post type', 'maloney-listings'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="map_template_scope" value="all" />
                                    <?php _e('All Toolset Content Templates', 'maloney-listings'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="description">
                        <?php _e('This will add the map shortcode [maloney_listing_map] after any block containing "neighborhood" in the selected templates.', 'maloney-listings'); ?>
                    </p>
                    
                    <p class="description" style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                        <strong><?php _e('Template Scope Options:', 'maloney-listings'); ?></strong><br>
                        <strong><?php _e('Only templates assigned to Listing post type:', 'maloney-listings'); ?></strong> <?php _e('This option will only process Content Templates that are currently assigned to the "Listing" post type in Toolset settings. This includes default templates and conditional templates (e.g., templates assigned based on listing type taxonomy). This is the safer option and recommended for most cases.', 'maloney-listings'); ?><br><br>
                        <strong><?php _e('All Toolset Content Templates:', 'maloney-listings'); ?></strong> <?php _e('This option will process ALL published Toolset Content Templates in your site, regardless of which post type they are assigned to. Use this option if you want to add the map to templates that might be used for other post types or if you have templates that are not currently assigned but you want to update them anyway.', 'maloney-listings'); ?>
                    </p>
                    
                    <p class="submit">
                        <input type="submit" name="insert_map_after_neighborhood" class="button button-primary" value="<?php _e('Insert Map After Neighborhood', 'maloney-listings'); ?>" />
                    </p>
                </form>
                
                <?php
                // Handle insert map after neighborhood form submission
                if (isset($_POST['insert_map_after_neighborhood']) && check_admin_referer('insert_map_action', 'insert_map_nonce')) {
                    if (class_exists('Maloney_Listings_Toolset_Template_Blocks')) {
                        $template_scope = isset($_POST['map_template_scope']) ? sanitize_text_field($_POST['map_template_scope']) : 'assigned';
                        $all_templates = ($template_scope === 'all');
                        $results = Maloney_Listings_Toolset_Template_Blocks::insert_map_after_neighborhood($all_templates);
                        
                        if (!empty($results)) {
                            ?>
                            <h3 style="margin-top: 20px;"><?php _e('Results:', 'maloney-listings'); ?></h3>
                            <table class="widefat striped" style="margin-top: 15px;">
                                <thead>
                                    <tr>
                                        <th><?php _e('Template ID', 'maloney-listings'); ?></th>
                                        <th><?php _e('Status', 'maloney-listings'); ?></th>
                                        <th><?php _e('Message', 'maloney-listings'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $template_id => $result) : 
                                        $template = get_post($template_id);
                                        $template_title = $template ? $template->post_title : 'Template ' . $template_id;
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($template_title); ?> (<?php echo esc_html($template_id); ?>)</td>
                                            <td>
                                                <?php if (is_wp_error($result)) : ?>
                                                    <span style="color: #d63638;"><?php _e('Error', 'maloney-listings'); ?></span>
                                                <?php else : ?>
                                                    <span style="color: #00a32a;"><?php _e('Success', 'maloney-listings'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if (is_wp_error($result)) {
                                                    echo esc_html($result->get_error_message());
                                                } else {
                                                    echo esc_html(__('Map block inserted successfully.', 'maloney-listings'));
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php
                        }
                    }
                }
                ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Usage Examples', 'maloney-listings'); ?></h2>
                <h3><?php _e('Using PHP Code', 'maloney-listings'); ?></h3>
                <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;"><code>// Insert shortcode block
Maloney_Listings_Toolset_Template_Blocks::insert_block(
    13317, // Template ID
    'core/shortcode',
    array(),
    'append'
);

// Replace existing "Current Rental Availability" block
Maloney_Listings_Toolset_Template_Blocks::replace_block(
    13317,
    'core/shortcode', // Old block name
    'core/shortcode', // New block name
    array(), // Attributes
    '[maloney_listing_availability]' // New shortcode
);

// Replace in ALL rental templates
Maloney_Listings_Toolset_Template_Blocks::replace_block_for_post_type(
    'listing',
    'core/shortcode', // Old block (will match blocks containing "rental availability")
    'core/shortcode', // New block
    array(),
    '[maloney_listing_availability]'
);

// Insert custom block
Maloney_Listings_Toolset_Template_Blocks::insert_block(
    13317,
    'maloney-listings/availability-block',
    array('showTitle' => true),
    'after',
    'core/paragraph'
);

// Check if block exists
if (Maloney_Listings_Toolset_Template_Blocks::block_exists(13317, 'core/shortcode')) {
    // Block already exists
}

// Remove a block
Maloney_Listings_Toolset_Template_Blocks::remove_block(13317, 'core/shortcode');</code></pre>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#block_type').on('change', function() {
                    if ($(this).val() === 'shortcode') {
                        $('#shortcode_row').show();
                        $('#block_name_row').hide();
                    } else {
                        $('#shortcode_row').hide();
                        $('#block_name_row').show();
                    }
                });
                
                $('#position').on('change', function() {
                    if ($(this).val() === 'before' || $(this).val() === 'after') {
                        $('#anchor_row').show();
                    } else {
                        $('#anchor_row').hide();
                    }
                });
                
                $('input[name="replace_action"]').on('change', function() {
                    if ($(this).val() === 'single_template') {
                        $('#replace_template_row').show();
                    } else {
                        $('#replace_template_row').hide();
                    }
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * Fix Toolset meta data issues on listing posts
     */
    public function render_fix_toolset_meta_page() {
        if (!$this->is_developer()) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        
        $message = '';
        $message_type = 'info';
        $fixed_count = 0;
        
        // Handle fix action
        if (isset($_POST['fix_toolset_meta']) && check_admin_referer('fix_toolset_meta_action', 'fix_toolset_meta_nonce')) {
            global $wpdb;
            
            // Find all posts (including field group posts) with problematic Toolset meta
            $problematic_meta_keys = array(
                '_wp_types_group_post_types',
                '_wp_types_group_terms',
                '_wp_types_group_templates',
                '_wpcf_group_post_types',
            );
            
            foreach ($problematic_meta_keys as $meta_key) {
                // Get all meta entries for this key
                $meta_entries = $wpdb->get_results($wpdb->prepare(
                    "SELECT pm.meta_id, pm.post_id, pm.meta_value, p.post_type
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE pm.meta_key = %s",
                    $meta_key
                ));
                
                foreach ($meta_entries as $meta) {
                    // Check if value is an array (serialized) or if it's stored incorrectly
                    $value = maybe_unserialize($meta->meta_value);
                    
                    if (is_array($value)) {
                        // For listing posts: delete the meta (shouldn't exist there)
                        if ($meta->post_type === 'listing') {
                            delete_post_meta($meta->post_id, $meta_key, $meta->meta_value);
                            $fixed_count++;
                        } 
                        // For field group posts: convert array to comma-separated string
                        // This is the critical fix - field groups need this meta as a string
                        else {
                            // Convert array to comma-separated string format that Toolset expects
                            // Format: ",post_type1,post_type2," (with leading and trailing commas)
                            // Filter out empty values and ensure we have valid strings
                            $filtered = array_filter($value, function($v) {
                                return !empty($v) && is_string($v);
                            });
                            
                            if (!empty($filtered)) {
                                $string_value = ',' . implode(',', $filtered) . ',';
                            } else {
                                // Empty array - use 'all' as default
                                $string_value = 'all';
                            }
                            
                            update_post_meta($meta->post_id, $meta_key, $string_value);
                            $fixed_count++;
                        }
                    }
                }
            }
            
            if ($fixed_count > 0) {
                $message = sprintf(
                    __('Fixed %d problematic Toolset meta entries on listing posts. These meta keys should not exist on listing posts and have been removed.', 'maloney-listings'),
                    $fixed_count
                );
                $message_type = 'success';
            } else {
                $message = __('No problematic Toolset meta entries found. All listing posts are clean.', 'maloney-listings');
                $message_type = 'info';
            }
        }
        
        // Check for problematic meta (on ALL post types, not just listings)
        global $wpdb;
        $problematic_meta_keys = array(
            '_wp_types_group_post_types',
            '_wp_types_group_terms',
            '_wp_types_group_templates',
            '_wpcf_group_post_types',
        );
        
        $problematic_count = 0;
        $problematic_list = array();
        
        foreach ($problematic_meta_keys as $meta_key) {
            $meta_entries = $wpdb->get_results($wpdb->prepare(
                "SELECT pm.meta_id, pm.post_id, p.post_title, p.post_type, pm.meta_value 
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = %s
                LIMIT 100",
                $meta_key
            ));
            
            foreach ($meta_entries as $meta) {
                $value = maybe_unserialize($meta->meta_value);
                if (is_array($value)) {
                    $problematic_count++;
                    $problematic_list[] = array(
                        'post_id' => $meta->post_id,
                        'title' => $meta->post_title,
                        'post_type' => $meta->post_type,
                        'meta_key' => $meta_key,
                        'action' => $meta->post_type === 'listing' ? 'Delete' : 'Convert to string',
                    );
                }
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Fix Toolset Meta Data', 'maloney-listings'); ?></h1>
            
            <?php if ($message) : ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php _e('About This Tool', 'maloney-listings'); ?></h2>
                <p><?php _e('This tool fixes a critical issue where Toolset field group assignment meta keys are stored as arrays instead of strings. This causes fatal errors when Toolset tries to read them.', 'maloney-listings'); ?></p>
                <p><strong><?php _e('The problematic meta keys are:', 'maloney-listings'); ?></strong></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><code>_wp_types_group_post_types</code></li>
                    <li><code>_wp_types_group_terms</code></li>
                    <li><code>_wp_types_group_templates</code></li>
                    <li><code>_wpcf_group_post_types</code></li>
                </ul>
                <p><?php _e('This tool will:', 'maloney-listings'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('Delete these meta keys from listing posts (they should not exist there)', 'maloney-listings'); ?></li>
                    <li><?php _e('Convert arrays to comma-separated strings on field group posts (Toolset requires string format)', 'maloney-listings'); ?></li>
                </ul>
                <p><strong style="color: #dc3232;"><?php _e('This fix is critical - it will resolve fatal errors when editing posts or creating new pages.', 'maloney-listings'); ?></strong></p>
            </div>
            
            <?php if ($problematic_count > 0) : ?>
                <div class="card" style="margin-top: 20px; border-left: 4px solid #dc3232;">
                    <h2><?php _e('Problematic Meta Found', 'maloney-listings'); ?></h2>
                    <p><strong><?php printf(__('Found %d listing posts with problematic Toolset meta entries.', 'maloney-listings'), $problematic_count); ?></strong></p>
                    
                    <?php if (!empty($problematic_list)) : ?>
                        <table class="widefat striped" style="margin-top: 15px;">
                            <thead>
                                <tr>
                                    <th><?php _e('Post ID', 'maloney-listings'); ?></th>
                                    <th><?php _e('Post Type', 'maloney-listings'); ?></th>
                                    <th><?php _e('Title', 'maloney-listings'); ?></th>
                                    <th><?php _e('Problematic Meta Key', 'maloney-listings'); ?></th>
                                    <th><?php _e('Action', 'maloney-listings'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($problematic_list as $item) : ?>
                                    <tr>
                                        <td><?php echo esc_html($item['post_id']); ?></td>
                                        <td><code><?php echo esc_html($item['post_type']); ?></code></td>
                                        <td>
                                            <?php if ($item['post_type'] !== 'wp-types-group') : ?>
                                                <a href="<?php echo get_edit_post_link($item['post_id']); ?>" target="_blank">
                                                    <?php echo esc_html($item['title']); ?>
                                                </a>
                                            <?php else : ?>
                                                <?php echo esc_html($item['title']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?php echo esc_html($item['meta_key']); ?></code></td>
                                        <td><strong><?php echo esc_html($item['action']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <form method="post" action="" style="margin-top: 20px;">
                        <?php wp_nonce_field('fix_toolset_meta_action', 'fix_toolset_meta_nonce'); ?>
                        <p>
                            <button type="submit" name="fix_toolset_meta" class="button button-primary button-large">
                                <?php _e('Fix All Problematic Meta Entries', 'maloney-listings'); ?>
                            </button>
                        </p>
                        <p class="description"><?php _e('This will remove all problematic Toolset meta entries from listing posts. This is safe and will not affect your field groups or field data.', 'maloney-listings'); ?></p>
                    </form>
                </div>
            <?php else : ?>
                <div class="card" style="margin-top: 20px; border-left: 4px solid #46b450;">
                    <h2><?php _e('No Issues Found', 'maloney-listings'); ?></h2>
                    <p><?php _e('All listing posts are clean. No problematic Toolset meta entries were found.', 'maloney-listings'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Preview units that cannot be imported (without running full migration)
     */
    private function preview_not_importable_units($migration_instance) {
        // Use reflection to access private methods
        $reflection = new ReflectionClass($migration_instance);
        $get_data_method = $reflection->getMethod('get_ninja_table_data');
        $get_data_method->setAccessible(true);
        $group_data_method = $reflection->getMethod('group_data_by_property');
        $group_data_method->setAccessible(true);
        $find_listing_method = $reflection->getMethod('find_listing_by_name');
        $find_listing_method->setAccessible(true);
        
        // Get data from Ninja Table
        $data = $get_data_method->invoke($migration_instance);
        
        if (empty($data)) {
            return array();
        }
        
        // Group by property
        $grouped = $group_data_method->invoke($migration_instance, $data);
        
        $not_importable = array();
        
        // Check each property
        foreach ($grouped as $property_name => $rows_data) {
            $listing = $find_listing_method->invoke($migration_instance, $property_name);
            
            if (!$listing) {
                // Property not found - add all units
                foreach ($rows_data as $row) {
                    $not_importable[] = array(
                        'property' => $property_name,
                        'bedrooms' => isset($row['bedrooms']) ? $row['bedrooms'] : '',
                        'units_available' => isset($row['units_available']) ? $row['units_available'] : '',
                        'rent' => isset($row['rent']) ? $row['rent'] : '',
                        'reason' => 'Property not found in listings',
                    );
                }
            } else {
                // Check if it's a rental
                $listing_types = wp_get_post_terms($listing->ID, 'listing_type', array('fields' => 'slugs'));
                if (empty($listing_types) || !in_array('rental', $listing_types)) {
                    // Not a rental - add all units
                    foreach ($rows_data as $row) {
                        $not_importable[] = array(
                            'property' => $property_name,
                            'bedrooms' => isset($row['bedrooms']) ? $row['bedrooms'] : '',
                            'units_available' => isset($row['units_available']) ? $row['units_available'] : '',
                            'rent' => isset($row['rent']) ? $row['rent'] : '',
                            'reason' => 'Property is not a rental listing',
                        );
                    }
                }
            }
        }
        
        return $not_importable;
    }
}
