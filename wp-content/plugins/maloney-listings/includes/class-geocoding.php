<?php
/**
 * Geocoding Functionality
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Geocoding {
    
    public function __construct() {
        add_action('wp_ajax_geocode_address', array($this, 'geocode_address'));
        add_action('wp_ajax_batch_geocode_listings', array($this, 'batch_geocode_listings'));
        add_action('wp_ajax_get_geocode_stats', array($this, 'get_geocode_stats'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Auto-geocode when listing is saved and address fields exist
        // Use priority 99 to run after Toolset saves its fields
        add_action('save_post_listing', array($this, 'auto_geocode_on_save'), 99);
        
        // Also hook into Toolset's save action if available
        if (function_exists('wpcf_admin_post_save')) {
            add_action('types_save_post', array($this, 'auto_geocode_on_save'), 20);
        }

        // Admin meta box with geocode controls
        add_action('add_meta_boxes', array($this, 'add_geocode_meta_box'));
        add_action('save_post_listing', array($this, 'save_geocode_meta_box'), 10, 3);

        // Cron queue for bulk geocoding
        add_action('ml_geocode_cron', array($this, 'process_cron_queue'));
    }
    
    /**
     * Get current geocoding statistics
     */
    public function get_geocode_stats() {
        check_ajax_referer('geocode_stats_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
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
            'fields' => 'ids',
        );
        $query = new WP_Query($args);
        $needing = $query->found_posts;
        
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
            'fields' => 'ids',
        );
        $query_with_coords = new WP_Query($args_with_coords);
        $with_coords = $query_with_coords->found_posts;
        
        wp_send_json_success(array(
            'with_coords' => $with_coords,
            'needing' => $needing,
        ));
    }
    
    /**
     * Batch geocode listings via AJAX
     */
    public function batch_geocode_listings() {
        check_ajax_referer('batch_geocode_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Process listings per request - optimized batch size for WP Engine
        // Reduced batch size to avoid timeouts, but process faster with better optimization
        $batch_size = 5; // Very small batches to avoid WP Engine timeouts and rate limiting
        
        // Get listings without coordinates
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => $batch_size,
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
        $processed = 0;
        $geocoded = 0;
        $failed = 0;
        $error_details = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $post_title = get_the_title($post_id);
                $processed++;
                
                // Skip if already has coordinates (optimization)
                $existing_lat = get_post_meta($post_id, '_listing_latitude', true);
                $existing_lng = get_post_meta($post_id, '_listing_longitude', true);
                if (!empty($existing_lat) && !empty($existing_lng)) {
                    // Already geocoded, skip
                    continue;
                }
                
                $address = $this->build_address_from_fields($post_id);
                if ($address) {
                    // Check cache first (much faster than API call)
                    $cached_coords = $this->get_cached_geocode($address);
                    $api_call_made = false;
                    
                    if ($cached_coords) {
                        // Use cached coordinates
                        $coordinates = $cached_coords;
                    } else {
                        // Make API call
                        $coordinates = $this->geocode_with_nominatim($address);
                        $api_call_made = true;
                        
                        // Handle retry flag (rate limiting or timeout)
                        if (is_array($coordinates) && isset($coordinates['retry'])) {
                            // Rate limited or timeout - mark as failed for this batch but will retry later
                            // Add a longer delay to respect rate limits
                            if (isset($coordinates['rate_limited'])) {
                                sleep(2); // Wait 2 seconds if rate limited (reduced from 3)
                            }
                            // Mark as failed so it can be retried in next batch
                            // Don't use continue - let it fall through to failed handling
                            $failed++;
                            $error_details[] = $post_title . ': Rate limited or timeout - will retry in next batch. Address: "' . $address . '"';
                            // Skip saving coordinates and continue to next listing
                            continue;
                        }
                        
                        // Cache successful results
                        if ($coordinates && isset($coordinates['latitude']) && isset($coordinates['longitude'])) {
                            $this->cache_geocode($address, $coordinates);
                        }
                    }
                    
                    if ($coordinates && isset($coordinates['latitude']) && isset($coordinates['longitude'])) {
                        update_post_meta($post_id, '_listing_latitude', $coordinates['latitude']);
                        update_post_meta($post_id, '_listing_longitude', $coordinates['longitude']);
                        
                        // Store the address that was geocoded for change detection
                        update_post_meta($post_id, '_listing_geocoded_address', $address);
                        
                        // Flag suspicious coordinates
                        if (isset($coordinates['suspicious']) && $coordinates['suspicious']) {
                            update_post_meta($post_id, '_listing_geocode_suspicious', '1');
                        } else {
                            delete_post_meta($post_id, '_listing_geocode_suspicious');
                        }
                        
                        $geocoded++;
                    } else {
                        $failed++;
                        $error_details[] = $post_title . ': Could not geocode address "' . $address . '"';
                    }
                    
                    // Only delay if we made an actual API call (not from cache)
                    // Nominatim requires at least 1 second between requests
                    // Use 1.5 seconds to be safer and avoid rate limiting
                    if ($api_call_made && $processed < $query->post_count) {
                        usleep(1500000); // 1.5 second delay between API calls (safer for rate limits)
                    }
                } else {
                    $failed++;
                    $error_details[] = $post_title . ': No address information found';
                }
            }
            wp_reset_postdata();
        }
        
        // Get totals for progress - recalculate after processing
        $total_args = array(
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
            'fields' => 'ids',
        );
        $total_query = new WP_Query($total_args);
        $total_needing = $total_query->found_posts;
        
        $total_with_coords_args = array(
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
            'fields' => 'ids',
        );
        $total_with_coords_query = new WP_Query($total_with_coords_args);
        $total_geocoded = $total_with_coords_query->found_posts;
        
        wp_send_json_success(array(
            'processed' => $processed,
            'geocoded' => $geocoded,
            'failed' => $failed,
            'has_more' => $total_needing > 0, // Check if there are still listings needing geocoding
            'total_needing' => $total_needing,
            'total_geocoded' => $total_geocoded,
            'error_details' => $error_details,
        ));
    }
    
    /**
     * Auto-geocode listing address when saved
     * Works for both NEW listings and EDITED listings
     * Always geocodes on save to ensure coordinates are up-to-date if address changes
     * Will geocode even if coordinates are currently empty
     */
    public function auto_geocode_on_save($post_id) {
        // Verify this is a listing post type
        $post_type = get_post_type($post_id);
        if ($post_type !== 'listing') {
            return;
        }
        
        // Skip geocoding during migration to prevent system slowdown
        if (class_exists('Maloney_Listings_Migration') && Maloney_Listings_Migration::is_migrating()) {
            return;
        }
        
        // Don't run on autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Don't run on revision
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Build address from fields
        // First check POST data (for new addresses being saved or updated)
        // Toolset fields are in $_POST['wpcf'] array
        $address = '';
        if (isset($_POST['wpcf']['address']) && !empty($_POST['wpcf']['address'])) {
            $address = sanitize_text_field($_POST['wpcf']['address']);
        } elseif (isset($_POST['wpcf-address']) && !empty($_POST['wpcf-address'])) {
            $address = sanitize_text_field($_POST['wpcf-address']);
        } elseif (isset($_POST['_listing_address']) && !empty($_POST['_listing_address'])) {
            $address = sanitize_text_field($_POST['_listing_address']);
        }
        
        // If not in POST, check saved meta (for existing listings being edited)
        if (empty($address)) {
            $address = $this->build_address_from_fields($post_id);
        }
        
        if (empty($address) || trim($address) === '') {
            // No address available, can't geocode
            return;
        }
        
        // Check if address has changed by comparing with existing coordinates
        // If coordinates exist and address hasn't changed, skip geocoding to speed up saves
        $existing_lat = get_post_meta($post_id, '_listing_latitude', true);
        $existing_lng = get_post_meta($post_id, '_listing_longitude', true);
        $existing_address = get_post_meta($post_id, '_listing_geocoded_address', true);
        
        // If coordinates exist and address hasn't changed, skip geocoding
        if (!empty($existing_lat) && !empty($existing_lng) && $existing_address === $address) {
            // Address hasn't changed, coordinates are still valid - skip geocoding
            return;
        }
        
        // Always geocode on save (works for both new and edited listings)
        // This will geocode even if coordinates are currently empty
        // Check cache first to avoid unnecessary API calls
        $coordinates = $this->get_cached_geocode($address);
        
        if (!$coordinates) {
            // Not in cache, make API call
            $coordinates = $this->geocode_with_nominatim($address);
            
            // Cache successful results
            if ($coordinates && isset($coordinates['latitude']) && isset($coordinates['longitude'])) {
                $this->cache_geocode($address, $coordinates);
            }
        }
        
        // Save coordinates (will overwrite existing ones if address changed)
        if ($coordinates && isset($coordinates['latitude']) && isset($coordinates['longitude'])) {
            update_post_meta($post_id, '_listing_latitude', $coordinates['latitude']);
            update_post_meta($post_id, '_listing_longitude', $coordinates['longitude']);
            // Store the address that was geocoded so we can detect changes
            update_post_meta($post_id, '_listing_geocoded_address', $address);
            
            // Flag suspicious coordinates
            if (isset($coordinates['suspicious']) && $coordinates['suspicious']) {
                update_post_meta($post_id, '_listing_geocode_suspicious', '1');
            } else {
                delete_post_meta($post_id, '_listing_geocode_suspicious');
            }
        }
    }
    
    /**
     * Build geocoding address string
     * Combines address field with city/state/zip if address is incomplete
     */
    private function build_address_from_fields($post_id) {
        // Get address field
        $address = get_post_meta($post_id, 'wpcf-address', true);
        if (empty($address)) {
            $address = get_post_meta($post_id, '_listing_address', true);
        }
        
        // Clean up duplicate city/state if present
        $address = $this->clean_duplicate_address($address);
        
        if (empty($address)) {
            return '';
        }
        
        $address = trim($address);
        
        // Check if address already has city/state/zip
        $has_city_state = (
            preg_match('/,\s*[A-Z]{2}\s+\d{5}/', $address) || // Has "MA 02101" pattern
            preg_match('/,\s*Massachusetts/i', $address) ||
            preg_match('/,\s*MA\s*,/i', $address) ||
            preg_match('/,\s*\d{5}/', $address) // Has zip code
        );
        
        // If address is incomplete (missing city/state/zip), try to add them
        if (!$has_city_state) {
            $city = get_post_meta($post_id, 'wpcf-city', true);
            if (empty($city)) {
                $city = get_post_meta($post_id, '_listing_city', true);
            }
            
            $state = get_post_meta($post_id, 'wpcf-state', true);
            if (empty($state)) {
                $state = get_post_meta($post_id, '_listing_state', true);
            }
            if (empty($state)) {
                $state = 'MA'; // Default to Massachusetts
            }
            
            $zip = get_post_meta($post_id, 'wpcf-zip-code', true);
            if (empty($zip)) {
                $zip = get_post_meta($post_id, '_listing_zip_code', true);
            }
            
            // Build complete address
            $parts = array($address);
            if (!empty($city)) {
                $parts[] = $city;
            }
            if (!empty($state)) {
                $parts[] = $state;
            }
            if (!empty($zip)) {
                $parts[] = $zip;
            }
            
            $address = implode(', ', $parts);
        }
        
        return $address;
    }
    
    /**
     * Clean up duplicate city/state information in address
     * Removes patterns like "Address, City, State, USA, City, State"
     * 
     * @param string $address Original address
     * @return string Cleaned address
     */
    private function clean_duplicate_address($address) {
        if (empty($address)) {
            return $address;
        }
        
        $address = trim($address);
        
        // Pattern: Look for duplicate city/state at the end
        // Example: "1000 Presidents Way, Dedham, MA, USA, Dedham, MA"
        // Should become: "1000 Presidents Way, Dedham, MA, USA"
        
        // Split by comma
        $parts = array_map('trim', explode(',', $address));
        
        if (count($parts) <= 3) {
            // Not enough parts to have duplicates
            return $address;
        }
        
        // Look for patterns where the last 2-3 parts repeat earlier parts
        // Common pattern: Street, City, State, [USA], City, State
        $last_parts = array_slice($parts, -2); // Last 2 parts
        $second_last_parts = array_slice($parts, -3, 2); // 2 parts before last
        
        // Check if last 2 parts match any earlier 2 consecutive parts
        for ($i = 0; $i < count($parts) - 3; $i++) {
            $check_parts = array_slice($parts, $i, 2);
            
            // Normalize for comparison (remove "USA", case-insensitive)
            $check_normalized = array_map(function($p) {
                return strtolower(trim(str_replace('USA', '', $p)));
            }, $check_parts);
            
            $last_normalized = array_map(function($p) {
                return strtolower(trim(str_replace('USA', '', $p)));
            }, $last_parts);
            
            if ($check_normalized === $last_normalized) {
                // Found duplicate - remove last 2 parts
                $cleaned = array_slice($parts, 0, -2);
                return implode(', ', $cleaned);
            }
        }
        
        // Also check for single duplicate at the end (e.g., "Address, City, State, City")
        if (count($parts) >= 3) {
            $last_part = strtolower(trim($parts[count($parts) - 1]));
            // Check if last part matches any earlier part (excluding USA)
            for ($i = 1; $i < count($parts) - 1; $i++) {
                $check_part = strtolower(trim(str_replace('USA', '', $parts[$i])));
                if ($check_part === $last_part && $check_part !== 'usa') {
                    // Found duplicate - remove last part
                    $cleaned = array_slice($parts, 0, -1);
                    return implode(', ', $cleaned);
                }
            }
        }
        
        return $address;
    }
    
    public function enqueue_admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        
        global $post_type;
        if ('listing' !== $post_type) {
            return;
        }
        
        wp_enqueue_script('maloney-listings-geocode', MALONEY_LISTINGS_PLUGIN_URL . 'assets/js/admin-geocode.js', array('jquery'), MALONEY_LISTINGS_VERSION, true);
        wp_localize_script('maloney-listings-geocode', 'maloneyGeocode', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('geocode_nonce'),
        ));
    }

    public function add_geocode_meta_box() {
        add_meta_box(
            'listing_geocode_box',
            __('Geocode Address', 'maloney-listings'),
            array($this, 'render_geocode_meta_box'),
            'listing',
            'side',
            'default'
        );
    }

    public function render_geocode_meta_box($post) {
        wp_nonce_field('listing_geocode_box', 'listing_geocode_nonce');
        
        // Get address - use ONLY the address field, don't combine with city/state
        $address_field = get_post_meta($post->ID, 'wpcf-address', true);
        if (empty($address_field)) {
            $address_field = get_post_meta($post->ID, '_listing_address', true);
        }
        
        // Clean up duplicate city/state if present (e.g., "1000 Presidents Way, Dedham, MA, USA, Dedham, MA")
        $address_field = $this->clean_duplicate_address($address_field);
        
        $lat = get_post_meta($post->ID, '_listing_latitude', true);
        $lng = get_post_meta($post->ID, '_listing_longitude', true);
        ?>
        <p><small><?php _e('Specify the full address then click Geocode. Values save when you update the post.', 'maloney-listings'); ?></small></p>
        <p><label><?php _e('Address', 'maloney-listings'); ?><br>
            <input type="text" id="listing_address" name="listing_address" value="<?php echo esc_attr($address_field); ?>" style="width:100%" />
        </label></p>
        <p><label><?php _e('Latitude', 'maloney-listings'); ?><br>
            <input type="text" id="listing_latitude" name="listing_latitude" value="<?php echo esc_attr($lat); ?>" style="width:100%" />
        </label></p>
        <p><label><?php _e('Longitude', 'maloney-listings'); ?><br>
            <input type="text" id="listing_longitude" name="listing_longitude" value="<?php echo esc_attr($lng); ?>" style="width:100%" />
        </label></p>
        <p>
            <button type="button" class="button" id="geocode_address"><?php _e('Geocode Address', 'maloney-listings'); ?></button>
        </p>
        <?php
    }

    public function save_geocode_meta_box($post_id, $post, $update) {
        if (!isset($_POST['listing_geocode_nonce']) || !wp_verify_nonce($_POST['listing_geocode_nonce'], 'listing_geocode_box')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        // Save latitude and longitude
        if (isset($_POST['listing_latitude'])) {
            update_post_meta($post_id, '_listing_latitude', sanitize_text_field($_POST['listing_latitude']));
        }
        if (isset($_POST['listing_longitude'])) {
            update_post_meta($post_id, '_listing_longitude', sanitize_text_field($_POST['listing_longitude']));
        }
        
        // If address was provided in the geocode box, clean it and update the address field
        if (isset($_POST['listing_address']) && !empty($_POST['listing_address'])) {
            $address = sanitize_text_field($_POST['listing_address']);
            $address = $this->clean_duplicate_address($address);
            
            // Update the address field (prefer wpcf-address, fallback to _listing_address)
            $existing_address = get_post_meta($post_id, 'wpcf-address', true);
            if (!empty($existing_address)) {
                update_post_meta($post_id, 'wpcf-address', $address);
            } else {
                update_post_meta($post_id, '_listing_address', $address);
            }
            
            // Also update the geocoded address meta
            update_post_meta($post_id, '_listing_geocoded_address', $address);
        }
    }
    
    public function geocode_address() {
        check_ajax_referer('geocode_nonce', 'nonce');
        
        if (empty($_POST['address'])) {
            wp_send_json_error('Address is required');
        }
        
        $address = sanitize_text_field($_POST['address']);
        $coordinates = $this->geocode_with_nominatim($address);
        
        if ($coordinates) {
            wp_send_json_success($coordinates);
        } else {
            wp_send_json_error('Could not geocode address');
        }
    }
    
    /**
     * Get cached geocoding result for an address
     * Uses WordPress transients for fast lookups
     */
    private function get_cached_geocode($address) {
        // Normalize address for cache key (lowercase, trim whitespace)
        $cache_key = 'ml_geocode_' . md5(strtolower(trim($address)));
        
        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }
        
        return false;
    }
    
    /**
     * Cache geocoding result for an address
     * Stores for 30 days (addresses don't change often)
     */
    private function cache_geocode($address, $coordinates) {
        // Normalize address for cache key
        $cache_key = 'ml_geocode_' . md5(strtolower(trim($address)));
        
        // Cache for 30 days (2592000 seconds)
        set_transient($cache_key, $coordinates, 30 * DAY_IN_SECONDS);
    }
    
    private function geocode_with_nominatim($address) {
        // Clean and normalize the address
        $address = trim($address);
        
        // Remove trailing "USA" or "United States" as Nominatim prefers without it
        $address = preg_replace('/,\s*(USA|United States|US)$/i', '', $address);
        $address = trim($address);
        
        // Use OpenStreetMap Nominatim API
        // Add location context to avoid international results (e.g., "Sewell St" in London instead of Boston)
        $address_with_context = $address;
        
        // If address doesn't already contain state/country context, add Massachusetts, USA
        if (stripos($address, 'massachusetts') === false && 
            stripos($address, 'ma') === false && 
            stripos($address, 'usa') === false && 
            stripos($address, 'united states') === false) {
            $address_with_context = $address . ', Massachusetts, USA';
        }
        
        // URL encode the address properly
        $url = 'https://nominatim.openstreetmap.org/search';
        $params = array(
            'q' => $address_with_context,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'us', // Restrict to United States
            'addressdetails' => 1,
        );
        
        $url = add_query_arg($params, $url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 20, // Increased timeout for slow responses and WP Engine
            'headers' => array(
                'User-Agent' => 'Maloney Affordable Listings WordPress Plugin (https://www.maloneyaffordable.com)',
                'Referer' => home_url(),
            ),
            'sslverify' => false, // Disable SSL verification for WP Engine compatibility
            'redirection' => 5,
            'httpversion' => '1.1', // Use HTTP/1.1 for better compatibility
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('Geocoding error for "' . $address . '" (original: "' . $address_with_context . '"): ' . $error_message);
            
            // Check if it's a timeout or connection error - these might be recoverable
            if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'connect') !== false) {
                // Return a special flag to indicate retry might help
                return array('retry' => true, 'error' => $error_message);
            }
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Geocoding HTTP error ' . $response_code . ' for: ' . $address . ' (original: "' . $address_with_context . '")');
            
            // Handle rate limiting (429 Too Many Requests)
            if ($response_code === 429) {
                error_log('Nominatim rate limit hit - need to slow down requests for: ' . $address);
                return array('retry' => true, 'rate_limited' => true, 'error' => 'Rate limited');
            }
            
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log('Geocoding empty response for: ' . $address . ' (original: "' . $address_with_context . '")');
            return false;
        }
        
        $data = json_decode($body, true);
        
        // Log if no results found
        if (empty($data) || !is_array($data)) {
            error_log('Geocoding no results (empty or invalid JSON) for: ' . $address . ' (original: "' . $address_with_context . '")');
            return false;
        }
        
        if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
            // Verify the result is in the US (preferably Massachusetts)
            $result = $data[0];
            $country = isset($result['address']['country_code']) ? strtolower($result['address']['country_code']) : '';
            
            // Only accept US results
            if ($country !== 'us') {
                error_log('Geocoding result not in US for: ' . $address . ' (original: "' . $address_with_context . '") (got: ' . $country . ')');
                return false;
            }
            
            $lat = floatval($result['lat']);
            $lng = floatval($result['lon']);
            
            // Log successful geocoding for debugging
            error_log('Geocoding success for: ' . $address . ' (original: "' . $address_with_context . '") -> Lat: ' . $lat . ', Lng: ' . $lng);
            
            // Validate coordinates are within Massachusetts bounds
            // Massachusetts approximate bounds: 41.2° N to 42.9° N, 69.9° W to 73.5° W
            $is_valid = $this->validate_massachusetts_coordinates($lat, $lng);
            
            if (!$is_valid) {
                error_log('Geocoding coordinates outside Massachusetts bounds for: ' . $address . ' (Lat: ' . $lat . ', Lng: ' . $lng . ')');
            }
            
            return array(
                'latitude' => $lat,
                'longitude' => $lng,
                'suspicious' => !$is_valid, // Flag if outside Massachusetts bounds
            );
        }
        
        error_log('Geocoding no results (empty array) for: ' . $address . ' (original: "' . $address_with_context . '")');
        return false;
    }

    /* ---------------------------- Bulk Cron Queue ---------------------------- */

    public static function start_cron_queue() {
        update_option('ml_geocode_queue_active', 1);
        if (!wp_next_scheduled('ml_geocode_cron')) {
            wp_schedule_single_event(time() + 5, 'ml_geocode_cron');
        }
    }

    public static function stop_cron_queue() {
        delete_option('ml_geocode_queue_active');
    }

    public function process_cron_queue() {
        if (!get_option('ml_geocode_queue_active')) return;
        $batch = 50;
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => $batch,
            'post_status' => 'publish',
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'OR',
                array('key' => '_listing_latitude', 'compare' => 'NOT EXISTS'),
                array('key' => '_listing_latitude', 'value' => '', 'compare' => '=')
            ),
        );
        $posts = get_posts($args);
        foreach ($posts as $pid) {
            $addr = $this->build_address_from_fields($pid);
            if (!$addr) continue;
            $coords = $this->geocode_with_nominatim($addr);
            if ($coords) {
                update_post_meta($pid, '_listing_latitude', $coords['latitude']);
                update_post_meta($pid, '_listing_longitude', $coords['longitude']);
            }
        }
        // Reschedule if more remain
        $remaining = new WP_Query(array(
            'post_type' => 'listing', 'posts_per_page' => 1, 'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'OR',
                array('key' => '_listing_latitude', 'compare' => 'NOT EXISTS'),
                array('key' => '_listing_latitude', 'value' => '', 'compare' => '=')
            ),
        ));
        if ($remaining->found_posts > 0 && get_option('ml_geocode_queue_active')) {
            wp_schedule_single_event(time() + 15, 'ml_geocode_cron');
        } else {
            self::stop_cron_queue();
        }
    }
    
    /**
     * Validate coordinates are within Massachusetts bounds
     * Massachusetts approximate bounds: 41.2° N to 42.9° N, 69.9° W to 73.5° W
     * Note: Longitude is NEGATIVE for Massachusetts (west of prime meridian)
     * 
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return bool True if within Massachusetts bounds
     */
    private function validate_massachusetts_coordinates($lat, $lng) {
        // Massachusetts bounds (with small buffer)
        // Latitude: 41.0° N to 43.0° N
        $min_lat = 41.0;
        $max_lat = 43.0;
        
        // Longitude: -73.5° W to -69.9° W (NEGATIVE values, west of prime meridian)
        // More negative = further west, less negative = further east
        $min_lng = -73.8; // Westernmost (more negative)
        $max_lng = -69.5; // Easternmost (less negative)
        
        // Validate latitude first
        if ($lat < $min_lat || $lat > $max_lat) {
            return false;
        }
        
        // Validate longitude (must be negative and within bounds)
        if ($lng > 0) {
            // Positive longitude means east of prime meridian (Europe, Asia, etc.) - definitely wrong
            return false;
        }
        
        // Check if within Massachusetts longitude bounds
        // Since both are negative, we need to check: min_lng <= lng <= max_lng
        // But min_lng is more negative, so: -73.8 <= lng <= -69.5
        if ($lng < $min_lng || $lng > $max_lng) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check existing coordinates and flag suspicious ones
     */
    public function validate_existing_coordinates() {
        $args = array(
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
        
        $query = new WP_Query($args);
        $suspicious_count = 0;
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $lat = floatval(get_post_meta($post_id, '_listing_latitude', true));
                $lng = floatval(get_post_meta($post_id, '_listing_longitude', true));
                
                if ($lat && $lng) {
                    $is_valid = $this->validate_massachusetts_coordinates($lat, $lng);
                    if (!$is_valid) {
                        update_post_meta($post_id, '_listing_geocode_suspicious', '1');
                        $suspicious_count++;
                    } else {
                        delete_post_meta($post_id, '_listing_geocode_suspicious');
                    }
                }
            }
            wp_reset_postdata();
        }
        
        return $suspicious_count;
    }
    
    /**
     * Get listings with suspicious coordinates
     */
    public function get_suspicious_coordinates() {
        $args = array(
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
                array(
                    'key' => '_listing_geocode_suspicious',
                    'value' => '1',
                    'compare' => '=',
                ),
            ),
        );
        
        $query = new WP_Query($args);
        $suspicious = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $lat = floatval(get_post_meta($post_id, '_listing_latitude', true));
                $lng = floatval(get_post_meta($post_id, '_listing_longitude', true));
                $address = $this->build_address_from_fields($post_id);
                
                $suspicious[] = array(
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'address' => $address,
                    'lat' => $lat,
                    'lng' => $lng,
                    'edit_url' => get_edit_post_link($post_id),
                );
            }
            wp_reset_postdata();
        }
        
        return $suspicious;
    }
    
    /**
     * Public method to geocode address (used by frontend class)
     */
    public function geocode_address_private($address) {
        return $this->geocode_with_nominatim($address);
    }
}
