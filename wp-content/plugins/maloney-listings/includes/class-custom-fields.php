<?php
/**
 * Custom Fields Management
 * 
 * Handles the Unit Type field and conditional display of Toolset field groups
 * based on listing type (Condo or Rental).
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

class Maloney_Listings_Custom_Fields {
    
    public function __construct() {
        // Normalize Toolset field group meta to prevent fatal errors
        // This intercepts get_post_meta calls and converts arrays to strings
        add_filter('get_post_metadata', array($this, 'normalize_toolset_group_meta'), 10, 4);
        // Keep admin columns
        add_filter('manage_listing_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_listing_posts_custom_column', array($this, 'render_admin_columns'), 10, 2);
        // Make status column sortable
        add_filter('manage_edit-listing_sortable_columns', array($this, 'make_status_column_sortable'));
        add_action('pre_get_posts', array($this, 'handle_status_column_sorting'));
        
        // Add unit_type field that syncs with listing_type taxonomy
        add_action('add_meta_boxes', array($this, 'add_unit_type_meta_box'));
        add_action('save_post_listing', array($this, 'save_unit_type'), 5); // Priority 5 to run before other save hooks
        
        // Show error message if validation failed
        add_action('admin_notices', array($this, 'show_unit_type_error'));
        
        // Clear Toolset template cache when key fields change so conditions are re-evaluated
        add_action('save_post_listing', array($this, 'clear_toolset_template_cache'), 20);

        // DISABLED: Auto-assign field groups - users should use Toolset's native interface
        // add_action('admin_init', array($this, 'auto_assign_field_groups'), 1);
        
        // Filter Toolset field groups server-side based on listing type
        // Priority 1 ensures this runs before Toolset's own conditional logic
        add_filter('toolset_show_field_group_for_post', array($this, 'filter_toolset_field_group_display'), 1, 5);
        
        // Ensure taxonomy is set early so Toolset can see it when checking field groups
        add_action('load-post.php', array($this, 'ensure_taxonomy_for_new_posts'), 1);
        add_action('load-post-new.php', array($this, 'ensure_taxonomy_for_new_posts'), 1);
        add_action('admin_init', array($this, 'ensure_taxonomy_for_new_posts'), 1);
        
            // Intercept "Add New Listing" to ask for unit type
            add_action('admin_init', array($this, 'intercept_new_listing'));
            add_action('admin_footer', array($this, 'add_listing_type_selection_modal'));
        }
        
        /**
         * Normalize Toolset field group meta to prevent fatal errors
         * Converts arrays to comma-separated strings when Toolset tries to read them
         * 
         * This filter intercepts get_post_meta calls and normalizes the value before Toolset reads it
         * 
         * The issue: Some field group posts have _wp_types_group_post_types stored as serialized arrays
         * (e.g., 'a:1:{i:0;s:7:"listing";}') instead of comma-separated strings (e.g., ',listing,')
         * When Toolset tries to trim() the array, it causes a fatal error.
         */
        public function normalize_toolset_group_meta($value, $post_id, $meta_key, $single) {
            // Only handle Toolset field group assignment meta keys
            $toolset_meta_keys = array(
                '_wp_types_group_post_types',
                '_wp_types_group_terms',
                '_wp_types_group_templates',
                '_wpcf_group_post_types',
            );
            
            if (!in_array($meta_key, $toolset_meta_keys)) {
                return $value; // Not our concern, return original value (null means let WordPress fetch it)
            }
            
            // Use static cache to avoid repeated database queries for the same post/meta
            static $checked_posts = array();
            $cache_key = $post_id . '_' . $meta_key;
            
            // If we've already checked and fixed this post/meta, return the value as-is
            if (isset($checked_posts[$cache_key])) {
                return $value;
            }
            
            // Check if value is already cached and is an array (WordPress might have unserialized it)
            if ($value !== null && is_array($value)) {
                // Same normalization logic
                $filtered = array_filter($value, function($v) {
                    return !empty($v) && is_string($v);
                });
                
                if (!empty($filtered)) {
                    $normalized = ',' . implode(',', $filtered) . ',';
                } else {
                    $normalized = 'all';
                }
                
                // Fix in database (use direct SQL to avoid recursion)
                global $wpdb;
                $wpdb->update(
                    $wpdb->postmeta,
                    array('meta_value' => $normalized),
                    array(
                        'post_id' => $post_id,
                        'meta_key' => $meta_key
                    ),
                    array('%s'),
                    array('%d', '%s')
                );
                
                wp_cache_delete($post_id, 'post_meta');
                $checked_posts[$cache_key] = true; // Mark as checked
                
                return $single ? $normalized : array($normalized);
            }
            
            // Check the raw database value to catch serialized arrays
            global $wpdb;
            $raw_meta_value = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
                $post_id,
                $meta_key
            ));
            
            if ($raw_meta_value === null) {
                $checked_posts[$cache_key] = true; // Mark as checked (no meta found)
                return $value; // No meta found, return null to let WordPress handle it
            }
            
            // Check if the raw value is a serialized array
            $unserialized = maybe_unserialize($raw_meta_value);
            
            if (is_array($unserialized)) {
                // Convert array to comma-separated string format that Toolset expects
                // Format: ",value1,value2," (with leading and trailing commas)
                // Filter out empty values and ensure we have valid strings
                $filtered = array_filter($unserialized, function($v) {
                    return !empty($v) && is_string($v);
                });
                
                if (!empty($filtered)) {
                    $normalized = ',' . implode(',', $filtered) . ',';
                } else {
                    // Empty array - use 'all' as default
                    $normalized = 'all';
                }
                
                // Fix it in the database for future reads (use direct SQL to avoid recursion)
                $wpdb->update(
                    $wpdb->postmeta,
                    array('meta_value' => $normalized),
                    array(
                        'post_id' => $post_id,
                        'meta_key' => $meta_key
                    ),
                    array('%s'),
                    array('%d', '%s')
                );
                
                // Clear any caches
                wp_cache_delete($post_id, 'post_meta');
                $checked_posts[$cache_key] = true; // Mark as checked
                
                // Return the normalized value
                return $single ? $normalized : array($normalized);
            }
            
            // Value is already a string (correct format), mark as checked and return as-is
            $checked_posts[$cache_key] = true;
            return $value;
        }
    
    /**
     * Auto-assign known Toolset field groups to listing post type
     * 
     * Ensures that Condo Lotteries, Condominiums, Rental Lotteries, and Rental Properties
     * field groups are assigned to the 'listing' post type so they appear in the editor.
     */
    public function auto_assign_field_groups() {
        // Known Toolset field group IDs
        // 361 = Condo Lotteries, 166 = Condominiums, 11 = Rental Lotteries, 183 = Rental Properties
        $group_ids = array(361, 166, 11, 183);
        
        if (class_exists('Toolset_Field_Group_Post_Factory')) {
            $factory = Toolset_Field_Group_Post_Factory::get_instance();
            foreach ($group_ids as $group_id) {
                try {
                    $group = $factory->load($group_id);
                    if ($group) {
                        $assigned_types = $group->get_assigned_to_types();
                        if (!in_array('listing', $assigned_types)) {
                            if (method_exists($group, 'assign_to_type')) {
                                $group->assign_to_type('listing');
                            } elseif (method_exists($group, 'set_assigned_to_types')) {
                                $assigned_types[] = 'listing';
                                $group->set_assigned_to_types($assigned_types);
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Ignore errors
                }
            }
        }
    }
    
    /**
     * Ensure listing_type taxonomy is set for new posts before Toolset checks field groups
     * 
     * When creating a new listing with unit_type in the URL, this ensures the taxonomy
     * term is set on the post object so Toolset's conditional display can work correctly.
     */
    public function ensure_taxonomy_for_new_posts() {
        global $pagenow, $post;
        
        // Get post ID from various sources
        $post_id = 0;
        if (isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
        } elseif ($post && isset($post->ID)) {
            $post_id = $post->ID;
        }
        
        if (!$post_id) {
            return;
        }
        
        // Get post object
        if (!$post || $post->ID != $post_id) {
            $post = get_post($post_id);
        }
        
        if (!$post || $post->post_type !== 'listing') {
            return;
        }
        
        // Check URL parameter for new posts
        if (isset($_GET['unit_type']) && in_array($_GET['unit_type'], array('condo', 'rental'))) {
            $unit_type = sanitize_text_field($_GET['unit_type']);
            
            // Set the taxonomy term on the post
            $term = get_term_by('slug', $unit_type, 'listing_type');
            if ($term && !is_wp_error($term)) {
                $result = wp_set_object_terms($post_id, array($term->term_id), 'listing_type', false);
                if (!is_wp_error($result)) {
                    // Clear caches to ensure fresh data
                    clean_post_cache($post_id);
                    wp_cache_delete($post_id, 'post_meta');
                    // Refresh post object
                    $post = get_post($post_id);
                    // Update global post object if it exists
                    global $wp_query;
                    if ($wp_query && isset($wp_query->post) && $wp_query->post->ID == $post_id) {
                        $wp_query->post = $post;
                    }
                }
            }
        }
    }
    
    
    /**
     * Clear Toolset template cache when key fields change
     * This ensures Toolset re-evaluates conditional templates based on current field values
     */
    public function clear_toolset_template_cache($post_id) {
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Clear the _views_template meta so Toolset re-evaluates conditions
        // Toolset will automatically select the correct template based on current field values
        delete_post_meta($post_id, '_views_template');
    }
    
    /**
     * Filter Toolset field group display based on listing_type taxonomy
     * 
     * Controls which field groups are shown based on whether the listing is a Condo or Rental.
     * Uses Toolset's filter: toolset_show_field_group_for_post
     * 
     * @param bool $show Whether to show the group (from Toolset)
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param string $group_slug Field group slug
     * @param int $group_id Field group ID
     * @return bool Whether to show the group
     */
    public function filter_toolset_field_group_display($show, $post_id, $post, $group_slug, $group_id) {
        // Only filter for listing post type
        if (!$post || $post->post_type !== 'listing') {
            return $show;
        }
        
        // Hide "Current Availability", "Current Rental Availability", and "Current Condo Listings" Toolset field groups - we use custom meta boxes instead
        // Check by group slug, ID, or name
        $hide_group = false;
        
        // Check by slug
        if ($group_slug === 'current-rental-availability' || 
            $group_slug === 'current-condo-listings' || 
            $group_slug === 'current-availability') {
            $hide_group = true;
        }
        
        // Check by known ID from database
        // Current Rental Availability (post ID 12542)
        // Current Condo Listings - add known ID if available
        // Current Availability - add known ID if available
        if ($group_id == 12542) {
            $hide_group = true;
        }
        
        // Check by group name/title using post object
        if (!$hide_group && $group_id > 0) {
            $group_post = get_post($group_id);
            if ($group_post) {
                $title_lower = strtolower($group_post->post_title);
                // Hide "Current Availability", "Current Rental Availability", or "Current Condo Listings"
                if (stripos($title_lower, 'current') !== false) {
                    // Check for "Current Availability" (without Rental/Condo - this is the old name)
                    if (stripos($title_lower, 'availability') !== false && 
                        stripos($title_lower, 'rental') === false && 
                        stripos($title_lower, 'condo') === false) {
                        $hide_group = true;
                    }
                    // Check for "Current Rental Availability"
                    if (stripos($title_lower, 'rental') !== false && stripos($title_lower, 'availability') !== false) {
                        $hide_group = true;
                    }
                    // Check for "Current Condo Listings"
                    if (stripos($title_lower, 'condo') !== false && stripos($title_lower, 'listing') !== false) {
                        $hide_group = true;
                    }
                }
            }
        }
        
        // Also check using Toolset API if available (requires domain parameter)
        if (!$hide_group && function_exists('toolset_get_field_group')) {
            try {
                // toolset_get_field_group requires 2 arguments: $group_id and $domain
                $group_obj = toolset_get_field_group($group_id, 'posts');
                if ($group_obj) {
                    $name = strtolower($group_obj->get_display_name());
                    // Hide "Current Availability", "Current Rental Availability", or "Current Condo Listings"
                    if (stripos($name, 'current') !== false) {
                        // Check for "Current Availability" (without Rental/Condo - this is the old name)
                        if (stripos($name, 'availability') !== false && 
                            stripos($name, 'rental') === false && 
                            stripos($name, 'condo') === false) {
                            $hide_group = true;
                        }
                        // Check for "Current Rental Availability"
                        if (stripos($name, 'rental') !== false && stripos($name, 'availability') !== false) {
                            $hide_group = true;
                        }
                        // Check for "Current Condo Listings"
                        if (stripos($name, 'condo') !== false && stripos($name, 'listing') !== false) {
                            $hide_group = true;
                        }
                    }
                }
            } catch (Exception $e) {
                // Ignore errors
            }
        }
        
        if ($hide_group) {
            // Hide this Toolset field group - we use the custom meta box instead
            return false;
        }
        
        // Known Toolset field group IDs
        $condo_groups = array(361, 166); // Condo Lotteries (361), Condominiums (166)
        $rental_groups = array(11, 183); // Rental Lotteries (11), Rental Properties (183)
        
        // Get listing type - check multiple sources
        $listing_type = '';
        
        // First, check URL parameter (most reliable for new posts)
        if (isset($_GET['unit_type'])) {
            $listing_type = strtolower(sanitize_text_field($_GET['unit_type']));
        }
        
        // Then check taxonomy if post exists
        if (empty($listing_type) && $post_id) {
            $terms = wp_get_post_terms($post_id, 'listing_type', array('fields' => 'slugs'));
            if (!empty($terms) && !is_wp_error($terms)) {
                $listing_type = strtolower($terms[0]);
            }
        }
        
        // If no type selected, hide all conditional groups
        if (empty($listing_type)) {
            if (in_array($group_id, $condo_groups) || in_array($group_id, $rental_groups)) {
                return false; // Hide condo and rental groups
            }
            return $show; // Show other groups (like Property Info)
        }
        
        // Filter based on listing type
        if ($listing_type === 'condo') {
            // Hide rental groups
            if (in_array($group_id, $rental_groups)) {
                return false;
            }
            // Show condo groups
            if (in_array($group_id, $condo_groups)) {
                return true;
            }
        } elseif ($listing_type === 'rental') {
            // Hide condo groups
            if (in_array($group_id, $condo_groups)) {
                return false;
            }
            // Show rental groups
            if (in_array($group_id, $rental_groups)) {
                return true;
            }
        }
        
        // For all other groups (like Property Info), return as-is
        return $show;
    }
    
    public function enqueue_admin_scripts($hook) {
        // Only on post edit screens for listing post type
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        global $post;
        if (!$post || $post->post_type !== 'listing') {
            return;
        }
        
        // Enqueue JavaScript to sync Unit Type dropdown with taxonomy and refresh field groups
        wp_add_inline_script('jquery', "
        jQuery(document).ready(function($) {
            /**
             * Sync Unit Type dropdown with Listing Type taxonomy
             * When dropdown changes, updates taxonomy checkbox and reloads page to refresh field groups
             */
            $('#listing_unit_type').on('change', function() {
                var type = $(this).val();
                if (!type) return;
                
                // Update taxonomy checkbox to match selected unit type
                $('#listing_typechecklist input').each(function() {
                    var label = $(this).closest('label').text().toLowerCase();
                    if ((type === 'condo' && (label.includes('condo') || label.includes('condominium'))) ||
                        (type === 'rental' && label.includes('rental'))) {
                        $('#listing_typechecklist input').prop('checked', false);
                        $(this).prop('checked', true).trigger('change');
                    }
                });
                
                // Reload page with unit_type parameter to trigger PHP filter
                // This ensures field groups are shown/hidden based on selected type
                var url = new URL(window.location);
                url.searchParams.set('unit_type', type);
                window.location.href = url.toString();
            });
            
            /**
             * Auto-set Unit Type dropdown and taxonomy from URL parameter on page load
             * This handles the case when a new listing is created with unit_type in URL
             */
            var urlParams = new URLSearchParams(window.location.search);
            var unitType = urlParams.get('unit_type');
            if (unitType && $('#listing_unit_type').length) {
                $('#listing_unit_type').val(unitType);
                // Also check the corresponding taxonomy checkbox
                $('#listing_typechecklist input').each(function() {
                    var label = $(this).closest('label').text().toLowerCase();
                    if ((unitType === 'condo' && (label.includes('condo') || label.includes('condominium'))) ||
                        (unitType === 'rental' && label.includes('rental'))) {
                        $('#listing_typechecklist input').prop('checked', false);
                        $(this).prop('checked', true);
                    }
                });
            }
        });
        ");
    }
    
    /**
     * Intercept "Add New Listing" to prompt for unit type selection
     * 
     * When user clicks "Add New Listing", shows a modal to select Condo or Rental.
     * Once selected, creates a draft post with the taxonomy set and redirects to edit screen.
     */
    public function intercept_new_listing() {
        global $pagenow;
        
        if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'listing') {
            // If a unit_type was selected from the modal, create a draft first,
            // assign taxonomy + meta, then redirect to the edit screen.
            if (isset($_GET['unit_type']) && in_array($_GET['unit_type'], array('condo','rental'), true)) {
                // Safety: prevent duplicate creation loops
                if (!isset($_GET['ml_created'])) {
                    $unit_type = sanitize_text_field($_GET['unit_type']);
                    $post_id = wp_insert_post(array(
                        'post_type'   => 'listing',
                        'post_status' => 'draft',
                        'post_title'  => '',
                    ));
                    if (!is_wp_error($post_id) && $post_id) {
                        // Assign listing_type taxonomy term to the new post
                        $term = get_term_by('slug', $unit_type, 'listing_type');
                        if ($term && !is_wp_error($term)) {
                            wp_set_object_terms($post_id, array($term->term_id), 'listing_type', false);
                        }
                        // Save unit type as post meta for quick access
                        update_post_meta($post_id, '_listing_unit_type', $unit_type);
                        // Clear caches to ensure fresh data
                        clean_post_cache($post_id);
                        // Redirect to edit screen with unit_type param so scripts can detect it
                        wp_safe_redirect(add_query_arg(array(
                            'post'      => $post_id,
                            'action'    => 'edit',
                            'unit_type' => $unit_type, // Pass unit_type so scripts can use it
                        ), admin_url('post.php')));
                        exit;
                    }
                }
                // If something failed, fall through to modal behavior
            }
            // No unit_type provided yet: the modal will be rendered in admin_footer
        }
    }
    
    /**
     * Add modal dialog for selecting unit type when adding new listing
     * 
     * Displays a modal with buttons to select "Condo" or "Rental" when user
     * clicks "Add New Listing" without a unit_type parameter in the URL.
     */
    public function add_listing_type_selection_modal() {
        global $pagenow;
        
        if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'listing') {
            if (!isset($_GET['unit_type']) || empty($_GET['unit_type'])) {
                ?>
                <div id="unit-type-selection-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; display: flex; align-items: center; justify-content: center;">
                    <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; text-align: center;">
                        <h2 style="margin-top: 0;"><?php _e('Select Unit Type', 'maloney-listings'); ?></h2>
                        <p><?php _e('Please select the type of unit you want to add:', 'maloney-listings'); ?></p>
                        <div style="display: flex; gap: 20px; justify-content: center; margin: 30px 0;">
                            <a href="<?php echo esc_url(add_query_arg('unit_type', 'condo', admin_url('post-new.php?post_type=listing'))); ?>" 
                               style="display: inline-block; padding: 20px 40px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; font-size: 18px; font-weight: bold;">
                                <?php _e('Condo', 'maloney-listings'); ?>
                            </a>
                            <a href="<?php echo esc_url(add_query_arg('unit_type', 'rental', admin_url('post-new.php?post_type=listing'))); ?>" 
                               style="display: inline-block; padding: 20px 40px; background: #d63638; color: white; text-decoration: none; border-radius: 4px; font-size: 18px; font-weight: bold;">
                                <?php _e('Rental', 'maloney-listings'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Display error notice if unit type validation failed
     */
    public function show_unit_type_error() {
        if (isset($_GET['unit_type_error']) && $_GET['unit_type_error'] == '1') {
            echo '<div class="notice notice-error"><p>' . __('<strong>Error:</strong> Unit Type is required. Please select Condo or Rental before saving.', 'maloney-listings') . '</p></div>';
        }
    }

    /**
     * Add Unit Type meta box to listing edit screen
     * 
     * This meta box provides a dropdown to select Condo or Rental, which syncs
     * with the listing_type taxonomy and controls which Toolset field groups are displayed.
     */
    public function add_unit_type_meta_box() {
        add_meta_box(
            'listing_unit_type',
            __('Unit Type', 'maloney-listings'),
            array($this, 'render_unit_type_meta_box'),
            'listing',
            'side',
            'high'
        );
    }
    
    /**
     * Render the Unit Type meta box
     * 
     * Displays a dropdown to select Condo or Rental. The selection syncs with
     * the listing_type taxonomy and triggers a page reload to show/hide appropriate field groups.
     * 
     * @param WP_Post $post The current post object
     */
    public function render_unit_type_meta_box($post) {
        wp_nonce_field('listing_unit_type', 'listing_unit_type_nonce');
        
        // Get current listing type from taxonomy
        $listing_type_terms = wp_get_post_terms($post->ID, 'listing_type');
        $current_type = !empty($listing_type_terms) ? strtolower($listing_type_terms[0]->slug) : '';
        
        // Get unit_type from meta (for backwards compatibility)
        $unit_type = get_post_meta($post->ID, '_listing_unit_type', true);
        if (!$unit_type && $current_type) {
            $unit_type = $current_type;
        }
        
        // Check URL parameter for new listings
        if (empty($unit_type) && isset($_GET['unit_type'])) {
            $unit_type = sanitize_text_field($_GET['unit_type']);
        }
        
        ?>
        <p>
            <label for="listing_unit_type">
                <strong><?php _e('Unit Type', 'maloney-listings'); ?></strong>
            </label>
        </p>
        <p>
            <select id="listing_unit_type" name="listing_unit_type" required style="width: 100%;">
                <option value=""><?php _e('Select Type (Required)', 'maloney-listings'); ?></option>
                <option value="condo" <?php selected($unit_type, 'condo'); ?>><?php _e('Condo', 'maloney-listings'); ?></option>
                <option value="rental" <?php selected($unit_type, 'rental'); ?>><?php _e('Rental', 'maloney-listings'); ?></option>
            </select>
        </p>
        <p class="description">
            <strong><?php _e('Required:', 'maloney-listings'); ?></strong> <?php _e('You must select a unit type. This will hide irrelevant fields (Condo fields hide for Rentals, Rental fields hide for Condos).', 'maloney-listings'); ?>
        </p>
        <script>
        jQuery(document).ready(function($) {
            // Get unit_type from URL parameter or existing value
            var unitTypeFromUrl = '';
            try {
                var params = new URLSearchParams(window.location.search);
                unitTypeFromUrl = params.get('unit_type') || '';
            } catch(e) {}
            
            var currentUnitType = $('#listing_unit_type').val() || unitTypeFromUrl || '<?php echo esc_js($unit_type); ?>';
            
            // Auto-select from URL parameter or existing value
            if (currentUnitType && (currentUnitType === 'condo' || currentUnitType === 'rental')) {
                $('#listing_unit_type').val(currentUnitType);
                
                // Also check the taxonomy checkbox - do this multiple times to ensure it sticks
                function checkTaxonomy() {
                    var taxInputs = $('#listing_typechecklist input[type="checkbox"], #listing_typechecklist input[type="radio"]');
                    var checked = false;
                    
                    taxInputs.each(function() {
                        var $input = $(this);
                        var label = $input.closest('label').text().toLowerCase();
                        var shouldCheck = false;
                        
                        if (currentUnitType === 'condo' && (label.includes('condo') || label.includes('condominium'))) {
                            shouldCheck = true;
                        } else if (currentUnitType === 'rental' && label.includes('rental')) {
                            shouldCheck = true;
                        }
                        
                        if (shouldCheck) {
                            // Uncheck all first
                            taxInputs.prop('checked', false);
                            // Check the matching one
                            $input.prop('checked', true);
                            // Trigger multiple events to ensure Toolset sees it
                            $input.trigger('change').trigger('click').trigger('change');
                            checked = true;
                            return false; // break
                        }
                    });
                    
                    return checked;
                }
                
                // Try multiple times with delays to ensure taxonomy checkbox is set
                // This is necessary because Toolset may load the taxonomy checklist asynchronously
                setTimeout(checkTaxonomy, 100);
                setTimeout(checkTaxonomy, 500);
                setTimeout(checkTaxonomy, 1000);
            }
            
            // Prevent form submission if unit type is not selected
            $('#post').on('submit', function(e) {
                var unitType = $('#listing_unit_type').val();
                if (!unitType) {
                    e.preventDefault();
                    alert('<?php echo esc_js(__('Please select a Unit Type (Condo or Rental) before saving.', 'maloney-listings')); ?>');
                    $('#listing_unit_type').focus();
                    return false;
                }
            });
            
            // Sync unit_type with listing_type taxonomy
            $('#listing_unit_type').on('change', function() {
                var unitType = $(this).val();
                if (!unitType) return;
                
                // Find the listing_type taxonomy checkboxes/radios
                var taxInputs = $('#listing_typechecklist input[type="checkbox"], #listing_typechecklist input[type="radio"]');
                
                taxInputs.each(function() {
                    var $input = $(this);
                    var label = $input.closest('label').text().toLowerCase();
                    var shouldCheck = false;
                    
                    if (unitType === 'condo' && (label.includes('condo') || label.includes('condominium'))) {
                        shouldCheck = true;
                    } else if (unitType === 'rental' && label.includes('rental')) {
                        shouldCheck = true;
                    }
                    
                    if (shouldCheck) {
                        // Uncheck all first
                        taxInputs.prop('checked', false);
                        // Check the matching one
                        $input.prop('checked', true).trigger('change').trigger('click').trigger('change');
                        return false; // break
                    }
                });
                
                // Reload page to trigger PHP filter and refresh field groups
                var url = new URL(window.location);
                url.searchParams.set('unit_type', unitType);
                window.location.href = url.toString();
            });
            
            // Also sync when taxonomy changes
            $(document).on('change', '#listing_typechecklist input[type="checkbox"], #listing_typechecklist input[type="radio"]', function() {
                if ($(this).prop('checked')) {
                    var label = $(this).closest('label').text().toLowerCase();
                    if (label.includes('condo') || label.includes('condominium')) {
                        $('#listing_unit_type').val('condo').trigger('change');
                    } else if (label.includes('rental')) {
                        $('#listing_unit_type').val('rental').trigger('change');
                    }
                    
                    // Reload page to trigger PHP filter and refresh field groups
                    var url = new URL(window.location);
                    url.searchParams.set('unit_type', $('#listing_unit_type').val());
                    window.location.href = url.toString();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save Unit Type field and sync with listing_type taxonomy
     * 
     * Validates that a unit type is selected, saves it as post meta, and syncs
     * it with the listing_type taxonomy term.
     * 
     * @param int $post_id The post ID being saved
     */
    public function save_unit_type($post_id) {
        // Verify nonce for security
        if (!isset($_POST['listing_unit_type_nonce']) || !wp_verify_nonce($_POST['listing_unit_type_nonce'], 'listing_unit_type')) {
            return;
        }
        
        // Skip autosaves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Validate: Unit type is required
        if (empty($_POST['listing_unit_type'])) {
            add_filter('redirect_post_location', array($this, 'add_unit_type_error_query'), 10, 2);
            wp_die(__('Error: Unit Type is required. Please select Condo or Rental.', 'maloney-listings'), __('Unit Type Required', 'maloney-listings'), array('back_link' => true));
            return;
        }
        
        // Save unit_type as post meta
        $unit_type = sanitize_text_field($_POST['listing_unit_type']);
        update_post_meta($post_id, '_listing_unit_type', $unit_type);
        
        // Sync with listing_type taxonomy
        $term = get_term_by('slug', $unit_type, 'listing_type');
        if ($term) {
            wp_set_post_terms($post_id, array($term->term_id), 'listing_type');
        }
    }
    
    /**
     * Add error query parameter to redirect URL when unit type validation fails
     * 
     * @param string $location The redirect URL
     * @param int $post_id The post ID
     * @return string Modified redirect URL
     */
    public function add_unit_type_error_query($location, $post_id) {
        return add_query_arg('unit_type_error', '1', $location);
    }
    
    /**
     * Add custom columns to the listings admin list table
     * 
     * @param array $columns Existing columns
     * @return array Modified columns array
     */
    public function add_admin_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['listing_type'] = __('Type', 'maloney-listings');
        $new_columns['listing_status'] = __('Status', 'maloney-listings');
        $new_columns['income_limits'] = __('Income Limits', 'maloney-listings');
        // Location taxonomy removed; keep columns minimal
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Make status column sortable
     * 
     * @param array $columns Existing sortable columns
     * @return array Modified columns array
     */
    public function make_status_column_sortable($columns) {
        $columns['listing_status'] = 'listing_status';
        return $columns;
    }
    
    /**
     * Handle sorting by status column
     * 
     * @param WP_Query $query The query object
     */
    public function handle_status_column_sorting($query) {
        global $pagenow, $wpdb;
        
        if (!is_admin() || $pagenow !== 'edit.php') {
            return;
        }
        
        if (!isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'listing') {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        if ($orderby === 'listing_status') {
            $order = $query->get('order') ? $query->get('order') : 'ASC';
            
            // Use a custom orderby that handles both rental and condo status fields
            // We'll join both meta tables and use COALESCE to get the first non-null value
            add_filter('posts_join', array($this, 'add_status_sort_join'));
            add_filter('posts_orderby', array($this, 'add_status_sort_orderby'), 10, 1);
            
            // Store order for use in orderby filter
            $this->status_sort_order = $order;
        }
    }
    
    /**
     * Add JOIN clauses for status sorting
     * 
     * @param string $join The JOIN clause
     * @return string Modified JOIN clause
     */
    public function add_status_sort_join($join) {
        global $wpdb;
        
        // Check if joins already exist to avoid duplicates
        if (strpos($join, 'mt1') === false) {
            $join .= " LEFT JOIN {$wpdb->postmeta} AS mt1 ON ({$wpdb->posts}.ID = mt1.post_id AND mt1.meta_key = 'wpcf-status')";
        }
        if (strpos($join, 'mt2') === false) {
            $join .= " LEFT JOIN {$wpdb->postmeta} AS mt2 ON ({$wpdb->posts}.ID = mt2.post_id AND mt2.meta_key = 'wpcf-condo-status')";
        }
        
        // Remove filter after use
        remove_filter('posts_join', array($this, 'add_status_sort_join'));
        
        return $join;
    }
    
    /**
     * Add ORDER BY clause for status sorting
     * 
     * @param string $orderby The ORDER BY clause
     * @return string Modified ORDER BY clause
     */
    public function add_status_sort_orderby($orderby) {
        $order = isset($this->status_sort_order) ? $this->status_sort_order : 'ASC';
        
        // Use COALESCE to get the first non-null status value, then sort by it
        $orderby = "COALESCE(mt1.meta_value, mt2.meta_value, '') " . $order;
        
        // Remove filter after use
        remove_filter('posts_orderby', array($this, 'add_status_sort_orderby'), 10);
        unset($this->status_sort_order);
        
        return $orderby;
    }
    
    /**
     * Render content for custom admin columns
     * 
     * @param string $column The column name
     * @param int $post_id The post ID
     */
    public function render_admin_columns($column, $post_id) {
        switch ($column) {
            case 'listing_type':
                $terms = get_the_terms($post_id, 'listing_type');
                if ($terms && !is_wp_error($terms)) {
                    $types = array();
                    foreach ($terms as $term) {
                        $type_class = strtolower($term->slug);
                        $types[] = '<span class="listing-type-badge' . esc_attr($type_class) . '">' . esc_html($term->name) . '</span>';
                    }
                    echo implode(' ', $types);
                } else {
                    echo '—';
                }
                break;
                
            case 'listing_status':
                // Get listing type to determine which status field to use
                $listing_type_terms = get_the_terms($post_id, 'listing_type');
                $is_condo = false;
                $is_rental = false;
                
                if ($listing_type_terms && !is_wp_error($listing_type_terms)) {
                    foreach ($listing_type_terms as $term) {
                        $type_slug = strtolower($term->slug);
                        if (strpos($type_slug, 'condo') !== false || strpos($type_slug, 'condominium') !== false) {
                            $is_condo = true;
                            break;
                        } elseif (strpos($type_slug, 'rental') !== false) {
                            $is_rental = true;
                            break;
                        }
                    }
                }
                
                // Get status value based on listing type
                $status_value = '';
                if ($is_condo) {
                    // Check condo status fields
                    $status_value = get_post_meta($post_id, 'wpcf-condo-status', true);
                    if (empty($status_value)) {
                        $status_value = get_post_meta($post_id, '_listing_condo_status', true);
                    }
                } elseif ($is_rental) {
                    // Check rental status fields
                    $status_value = get_post_meta($post_id, 'wpcf-status', true);
                    if (empty($status_value)) {
                        $status_value = get_post_meta($post_id, '_listing_rental_status', true);
                    }
                }
                
                // Map status values to display format
                if (!empty($status_value) || $status_value === '0' || $status_value === 0) {
                    $status_display = self::map_status_display($status_value, $is_condo);
                    echo '<span class="listing-status-badge">' . esc_html($status_display) . '</span>';
                } else {
                    echo '—';
                }
                break;
                
            case 'income_limits':
                // Get income limits from meta fields
                $income_limits = get_post_meta($post_id, 'wpcf-income-limits', true);
                if (empty($income_limits) && $income_limits !== '0' && $income_limits !== 0) {
                    $income_limits = get_post_meta($post_id, '_listing_income_limits', true);
                }
                
                if (!empty($income_limits) || $income_limits === '0' || $income_limits === 0) {
                    $income_display = self::map_income_limits_display($income_limits);
                    echo esc_html($income_display);
                } else {
                    echo '—';
                }
                break;
                
            // no location column anymore
        }
    }
    
    /**
     * Map status value to display label for frontend
     * Frontend-specific mapping (e.g., "FCFS Condo Sales" → "For Sale")
     * 
     * @param string|int $status_value The raw status value from meta field (numeric: 1, 2, 3, etc.)
     * @param bool $is_condo Whether this is a condo listing
     * @return string Display-friendly status text for frontend
     */
    public static function map_status_display_frontend($status_value, $is_condo = false) {
        $status = self::map_status_display($status_value, $is_condo);
        
        // Frontend-specific mappings
        if ($is_condo && $status === 'FCFS Condo Sales') {
            return 'For Sale';
        }
        
        return $status;
    }
    
    /**
     * Map status values to display format
     * 
     * Maps Toolset radio field numeric values (1, 2, 3, etc.) to their display labels
     * Based on Toolset field configuration:
     * - Rentals: 1=Active Rental, 2=Open Lottery, 3=Closed Lottery, 4=Inactive Rental, 5=Custom Lottery, 6=Upcoming Lottery
     * - Condos: 1=FCFS Condo Sales, 2=Active Condo Lottery, 3=Closed Condo Lottery, 4=Inactive Condo Property, 5=Upcoming Condo
     * 
     * @param string|int $status_value The raw status value from meta field (numeric: 1, 2, 3, etc.)
     * @param bool $is_condo Whether this is a condo listing
     * @return string Display-friendly status text
     */
    public static function map_status_display($status_value, $is_condo = false) {
        if (empty($status_value) && $status_value !== '0' && $status_value !== 0) {
            return '';
        }
        
        // Convert to integer for numeric comparison
        $status_int = intval($status_value);
        
        if ($is_condo) {
            // Condo status mapping based on Toolset field configuration
            // wpcf-condo-status radio field values:
            $condo_status_map = array(
                1 => 'FCFS Condo Sales',
                2 => 'Active Condo Lottery',
                3 => 'Closed Condo Lottery',
                4 => 'Inactive Condo Property',
                5 => 'Upcoming Condo',
            );
            
            if (isset($condo_status_map[$status_int])) {
                return $condo_status_map[$status_int];
            }
            
            // Fallback: if value is not numeric or not in map, try string matching
            $status_lower = strtolower(trim($status_value));
            if (stripos($status_lower, 'fcfs') !== false || (stripos($status_lower, 'first come') !== false && stripos($status_lower, 'sale') !== false)) {
                return 'FCFS Condo Sales';
            } elseif (stripos($status_lower, 'active') !== false && stripos($status_lower, 'lottery') !== false) {
                return 'Active Condo Lottery';
            } elseif (stripos($status_lower, 'closed') !== false && stripos($status_lower, 'lottery') !== false) {
                return 'Closed Condo Lottery';
            } elseif (stripos($status_lower, 'inactive') !== false) {
                return 'Inactive Condo Property';
            } elseif (stripos($status_lower, 'upcoming') !== false) {
                return 'Upcoming Condo';
            } elseif (stripos($status_lower, 'lottery') !== false) {
                return 'Active Condo Lottery'; // Default to active if just "lottery"
            }
            
            // Last resort: return as-is with proper capitalization
            return ucwords(strtolower($status_value));
        } else {
            // Rental status mapping based on Toolset field configuration
            // wpcf-status radio field values:
            // 1=Active Rental → Display: "Active Rental"
            // 2=Open Lottery → Display: "Open Lottery"
            // 3=Closed Lottery → Display: "Closed Lottery"
            // 4=Inactive Rental → Display: "Inactive Rental"
            // 5=Custom Lottery → Display: "Custom Lottery"
            // 6=Upcoming Lottery → Display: "Upcoming Lottery"
            $rental_status_map = array(
                1 => 'Active Rental',              // Active Rental (First Come, First Served)
                2 => 'Open Lottery',               // Open Lottery
                3 => 'Closed Lottery',             // Closed Lottery
                4 => 'Inactive Rental',            // Inactive Rental
                5 => 'Custom Lottery',             // Custom Lottery
                6 => 'Upcoming Lottery',           // Upcoming Lottery
            );
            
            if (isset($rental_status_map[$status_int])) {
                return $rental_status_map[$status_int];
            }
            
            // Fallback: if value is not numeric or not in map, try string matching
            $status_lower = strtolower(trim($status_value));
            if (stripos($status_lower, 'active') !== false && stripos($status_lower, 'rental') !== false) {
                return 'Active Rental';
            } elseif (stripos($status_lower, 'open') !== false && stripos($status_lower, 'lottery') !== false) {
                return 'Open Lottery';
            } elseif (stripos($status_lower, 'upcoming') !== false && stripos($status_lower, 'lottery') !== false) {
                return 'Upcoming Lottery';
            } elseif (stripos($status_lower, 'custom') !== false && stripos($status_lower, 'lottery') !== false) {
                return 'Custom Lottery';
            } elseif (stripos($status_lower, 'closed') !== false && stripos($status_lower, 'lottery') !== false) {
                return 'Closed Lottery';
            } elseif (stripos($status_lower, 'inactive') !== false && stripos($status_lower, 'rental') !== false) {
                return 'Inactive Rental';
            } elseif (stripos($status_lower, 'first come') !== false || stripos($status_lower, 'fcfs') !== false) {
                return 'Active Rental';
            } elseif (stripos($status_lower, 'lottery') !== false && stripos($status_lower, 'closed') === false && stripos($status_lower, 'open') === false && stripos($status_lower, 'custom') === false && stripos($status_lower, 'upcoming') === false) {
                return 'Open Lottery'; // Default lottery to Open Lottery
            } elseif (stripos($status_lower, 'waitlist') !== false || stripos($status_lower, 'waiting') !== false) {
                return 'Waiting List';
            }
            
            // Last resort: return as-is with proper capitalization
            return ucwords(strtolower($status_value));
        }
    }
    
    /**
     * Map income limits numeric values to display labels
     * 
     * Maps Toolset radio field numeric values to their display labels
     * wpcf-income-limits radio field values:
     * 1 = "Boston Inclusionary"
     * 2 = "HUD Boston-Camb.-Quincy"
     * 3 = "Custom Income Limits"
     * 
     * @param string|int $income_value The raw income limits value from meta field (numeric: 1, 2, 3)
     * @return string Display-friendly income limits text
     */
    public static function map_income_limits_display($income_value) {
        if (empty($income_value) && $income_value !== '0' && $income_value !== 0) {
            return '';
        }
        
        // Convert to integer for numeric comparison
        $income_int = intval($income_value);
        
        $income_limits_map = array(
            1 => 'Boston Inclusionary',
            2 => 'HUD Boston-Camb.-Quincy',
            3 => 'Custom Income Limits',
        );
        
        if (isset($income_limits_map[$income_int])) {
            return $income_limits_map[$income_int];
        }
        
        // Fallback: if value is not numeric or not in map, return as-is
        return $income_value;
    }
}
