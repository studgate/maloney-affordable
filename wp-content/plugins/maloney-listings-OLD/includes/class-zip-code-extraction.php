<?php
/**
 * Zip Code Extraction and Management
 * 
 * Extracts zip codes from address fields and populates the zip-code field
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

class Maloney_Listings_Zip_Code_Extraction {
    
    /**
     * Field slug (will create wpcf-zip-code meta key)
     */
    const FIELD_SLUG = 'zip-code';
    
    public function __construct() {
        // Auto-extract zip code when listing is saved
        add_action('save_post_listing', array($this, 'auto_extract_zip_on_save'), 10, 2);
        
        // DISABLED: Ensure field exists - field should already exist in Toolset
        // add_action('admin_init', array($this, 'ensure_zip_field_exists'), 20);
    }
    
    /**
     * Extract zip code from address string
     * 
     * Handles various formats:
     * - "123 Main St, Boston, MA 02101"
     * - "123 Main St, Boston, MA 02101-1234"
     * - "123 Main St, Boston, MA, 02101"
     * - "123 Main St, 02101"
     * - "123 Main St" (no zip)
     * 
     * @param string $address Address string
     * @return string|false Extracted zip code or false if not found
     */
    public static function extract_zip_from_address($address) {
        if (empty($address) || !is_string($address)) {
            return false;
        }
        
        // Clean up the address
        $address = trim($address);
        
        // Pattern 1: 5 digits or 5+4 format at the end (most common)
        // Matches: "MA 02101", "MA 02101-1234", ", 02101", " 02101"
        if (preg_match('/\b(\d{5}(?:-\d{4})?)\s*$/', $address, $matches)) {
            return $matches[1];
        }
        
        // Pattern 2: 5 digits or 5+4 format anywhere in the string
        // This catches cases where zip might be in the middle or start
        if (preg_match('/\b(\d{5}(?:-\d{4})?)\b/', $address, $matches)) {
            // Validate it's likely a zip code (not a street number or other number)
            $zip = $matches[1];
            // If it's at the start, it might be a street number, so check context
            $pos = strpos($address, $zip);
            if ($pos > 0) {
                // Not at the start, likely a zip code
                return $zip;
            } elseif ($pos === 0 && strlen($address) <= 10) {
                // At the start but address is short, might be just a zip
                return $zip;
            }
        }
        
        return false;
    }
    
    /**
     * Check if zip-code field exists in Toolset
     * 
     * @return bool True if field exists
     */
    public static function field_exists() {
        if (!function_exists('wpcf_admin_fields_get_fields')) {
            return false;
        }
        
        $fields = wpcf_admin_fields_get_fields();
        return isset($fields[self::FIELD_SLUG]);
    }
    
    /**
     * Ensure zip-code field exists in Property Info group
     * Creates it if it doesn't exist
     * 
     * @return bool|WP_Error True on success, false on failure, WP_Error if Toolset not active
     */
    public static function ensure_zip_field_exists() {
        // Check if field already exists
        if (self::field_exists()) {
            return true;
        }
        
        // Check if Toolset is active
        if (!function_exists('wpcf_admin_fields_save_field')) {
            return new WP_Error('toolset_inactive', 'Toolset Types plugin is not active.');
        }
        
        // Create the field
        $field = array(
            'name' => 'Zip Code',
            'slug' => self::FIELD_SLUG,
            'type' => 'textfield',
            'description' => 'Zip code extracted from address field',
            'data' => array(
                'repetitive' => 0,
                'conditional_display' => array(
                    'relation' => 'AND',
                    'conditions' => array(),
                ),
            ),
        );
        
        $result = wpcf_admin_fields_save_field($field);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Try to add field to Property Info group
        self::add_field_to_property_info_group();
        
        return true;
    }
    
    /**
     * Add zip-code field to Property Info field group
     * Positions it right after the state field
     * 
     * @return bool True on success, false on failure
     */
    private static function add_field_to_property_info_group() {
        if (!function_exists('wpcf_admin_fields_get_groups')) {
            return false;
        }
        
        // Find Property Info group
        $groups = wpcf_admin_fields_get_groups();
        $property_info_group = null;
        $group_id = null;
        
        foreach ($groups as $group) {
            $group_name_lower = strtolower($group['name']);
            if (strpos($group_name_lower, 'property') !== false && 
                strpos($group_name_lower, 'info') !== false) {
                $property_info_group = $group;
                $group_id = isset($group['id']) ? $group['id'] : null;
                break;
            }
        }
        
        if (!$property_info_group || !$group_id) {
            return false;
        }
        
        // Get existing fields in the group from meta (this preserves order)
        $group_fields_meta = get_post_meta($group_id, '_wp_types_group_fields', true);
        $group_fields = array();
        
        if (!empty($group_fields_meta)) {
            // Toolset stores fields as comma-separated string: ",field1,field2,field3,"
            if (is_string($group_fields_meta)) {
                $group_fields = array_filter(array_map('trim', explode(',', trim($group_fields_meta, ','))));
            } elseif (is_array($group_fields_meta)) {
                $group_fields = array_filter($group_fields_meta);
            }
        }
        
        // Check if zip-code field is already in group
        if (in_array(self::FIELD_SLUG, $group_fields)) {
            return true; // Already added
        }
        
        // Find state field position (could be "state" or "state-1")
        $state_field_slug = null;
        $state_field_index = false;
        
        // Check for "state-1" first (more common in Property Info group)
        if (in_array('state-1', $group_fields)) {
            $state_field_slug = 'state-1';
            $state_field_index = array_search('state-1', $group_fields);
        } elseif (in_array('state', $group_fields)) {
            $state_field_slug = 'state';
            $state_field_index = array_search('state', $group_fields);
        }
        
        // Insert zip-code right after state field
        if ($state_field_index !== false) {
            // Insert after state field
            array_splice($group_fields, $state_field_index + 1, 0, self::FIELD_SLUG);
        } else {
            // State field not found, just append to end
            $group_fields[] = self::FIELD_SLUG;
        }
        
        // Save updated field order (as comma-separated string with leading/trailing commas)
        $fields_string = ',' . implode(',', $group_fields) . ',';
        update_post_meta($group_id, '_wp_types_group_fields', $fields_string);
        
        return true;
    }
    
    /**
     * Extract and save zip code for a listing
     * 
     * @param int $post_id Post ID
     * @param bool $force_update Force update even if zip code already exists
     * @return string|false Extracted zip code or false
     */
    public static function extract_and_save_zip($post_id, $force_update = false) {
        // Check if field exists
        if (!self::field_exists()) {
            // Try to create it
            $result = self::ensure_zip_field_exists();
            if (is_wp_error($result)) {
                return false;
            }
        }
        
        // Check if zip code already exists (unless forcing update)
        // Check for empty strings and whitespace-only values
        if (!$force_update) {
            $existing_zip = get_post_meta($post_id, 'wpcf-' . self::FIELD_SLUG, true);
            // Also check alternative meta keys
            if (empty($existing_zip)) {
                $existing_zip = get_post_meta($post_id, 'wpcf-zip', true);
            }
            if (empty($existing_zip)) {
                $existing_zip = get_post_meta($post_id, '_listing_zip', true);
            }
            // Check if zip exists and is not empty/whitespace
            if (!empty($existing_zip) && trim($existing_zip) !== '') {
                return $existing_zip;
            }
        }
        
        // Get address from various sources
        $address = get_post_meta($post_id, 'wpcf-address', true);
        if (empty($address)) {
            $address = get_post_meta($post_id, '_listing_address', true);
        }
        
        // If still empty, try building from parts
        if (empty($address)) {
            $address_parts = array();
            $street = get_post_meta($post_id, 'wpcf-address', true);
            if (empty($street)) {
                $street = get_post_meta($post_id, '_listing_address', true);
            }
            if (!empty($street)) {
                $address_parts[] = $street;
            }
            
            $city = get_post_meta($post_id, 'wpcf-city', true);
            if (empty($city)) {
                $city = get_post_meta($post_id, '_listing_city', true);
            }
            if (!empty($city)) {
                $address_parts[] = $city;
            }
            
            $state = get_post_meta($post_id, 'wpcf-state-1', true);
            if (empty($state)) {
                $state = get_post_meta($post_id, '_listing_state', true);
            }
            if (!empty($state)) {
                $address_parts[] = $state;
            }
            
            $address = !empty($address_parts) ? implode(', ', $address_parts) : '';
        }
        
        // Extract zip code
        $zip_code = self::extract_zip_from_address($address);
        
        if ($zip_code) {
            // Save to both meta key formats for compatibility
            update_post_meta($post_id, 'wpcf-' . self::FIELD_SLUG, $zip_code);
            update_post_meta($post_id, '_listing_zip', $zip_code);
            return $zip_code;
        }
        
        return false;
    }
    
    /**
     * Auto-extract zip code when listing is saved
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function auto_extract_zip_on_save($post_id, $post) {
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only process listings
        if ($post->post_type !== 'listing') {
            return;
        }
        
        // Check if field exists, create if needed
        if (!self::field_exists()) {
            self::ensure_zip_field_exists();
        }
        
        // Extract and save zip code (only if not already set)
        self::extract_and_save_zip($post_id, false);
    }
    
    /**
     * Batch extract zip codes for all listings
     * 
     * @param bool $force_update Force update even if zip code exists
     * @return array Results with counts
     */
    public static function batch_extract_zip_codes($force_update = false) {
        // Ensure field exists
        if (!self::field_exists()) {
            $result = self::ensure_zip_field_exists();
            if (is_wp_error($result)) {
                return array(
                    'success' => false,
                    'error' => $result->get_error_message(),
                    'processed' => 0,
                    'extracted' => 0,
                    'skipped' => 0,
                );
            }
        }
        
        // Get all listings
        $listings = get_posts(array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ));
        
        $processed = 0;
        $extracted = 0;
        $skipped = 0;
        $errors = array();
        
        foreach ($listings as $listing) {
            $processed++;
            
            // Check if zip already exists (unless forcing update)
            // Check for empty strings and whitespace-only values
            if (!$force_update) {
                $existing_zip = get_post_meta($listing->ID, 'wpcf-' . self::FIELD_SLUG, true);
                // Also check alternative meta keys
                if (empty($existing_zip)) {
                    $existing_zip = get_post_meta($listing->ID, 'wpcf-zip', true);
                }
                if (empty($existing_zip)) {
                    $existing_zip = get_post_meta($listing->ID, '_listing_zip', true);
                }
                // Check if zip exists and is not empty/whitespace
                if (!empty($existing_zip) && trim($existing_zip) !== '') {
                    $skipped++;
                    continue;
                }
            }
            
            // Extract zip code
            $zip_code = self::extract_and_save_zip($listing->ID, $force_update);
            
            if ($zip_code) {
                $extracted++;
            } else {
                $skipped++;
            }
        }
        
        return array(
            'success' => true,
            'processed' => $processed,
            'extracted' => $extracted,
            'skipped' => $skipped,
            'errors' => $errors,
        );
    }
    
    /**
     * Get listings without full address and no zip code
     * 
     * @return array Array of listing objects with missing address/zip
     */
    public static function get_listings_without_address_or_zip() {
        // Get all listings
        $listings = get_posts(array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ));
        
        $missing_listings = array();
        
        foreach ($listings as $listing) {
            // Check for address
            $address = get_post_meta($listing->ID, 'wpcf-address', true);
            if (empty($address)) {
                $address = get_post_meta($listing->ID, '_listing_address', true);
            }
            
            // Check for zip code (all formats)
            $zip = get_post_meta($listing->ID, 'wpcf-zip-code', true);
            if (empty($zip)) {
                $zip = get_post_meta($listing->ID, 'wpcf-zip', true);
            }
            if (empty($zip)) {
                $zip = get_post_meta($listing->ID, '_listing_zip', true);
            }
            
            // If no address OR no zip code, add to list
            if (empty($address) || empty($zip)) {
                // Get city for display
                $city = get_post_meta($listing->ID, 'wpcf-city', true);
                if (empty($city)) {
                    $city = get_post_meta($listing->ID, '_listing_city', true);
                }
                
                $missing_listings[] = array(
                    'id' => $listing->ID,
                    'title' => $listing->post_title,
                    'address' => $address,
                    'zip' => $zip,
                    'city' => $city,
                    'edit_link' => get_edit_post_link($listing->ID),
                );
            }
        }
        
        return $missing_listings;
    }
    
    /**
     * Get zip code from address using Nominatim geocoding API
     * 
     * @param string $address Address string to geocode
     * @return string|false Zip code or false on failure
     */
    public static function get_zip_from_geocoding($address) {
        if (empty($address)) {
            return false;
        }
        
        // Add Massachusetts context if not present
        $search_query = $address;
        if (stripos($address, 'massachusetts') === false && 
            stripos($address, 'ma') === false && 
            stripos($address, 'usa') === false) {
            $search_query = $address . ', Massachusetts, USA';
        }
        
        // Use Nominatim API to geocode
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query(array(
            'q' => $search_query,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'us',
            'addressdetails' => 1,
        ));
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Maloney Affordable Listings',
            ),
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !is_array($data) || empty($data[0])) {
            return false;
        }
        
        $result = $data[0];
        $address_parts = isset($result['address']) ? $result['address'] : array();
        
        // Try to get zip code from address parts
        if (isset($address_parts['postcode'])) {
            return $address_parts['postcode'];
        }
        
        // Sometimes it's in different fields
        if (isset($address_parts['postal_code'])) {
            return $address_parts['postal_code'];
        }
        
        return false;
    }
    
    /**
     * Get zip code and geocode for a listing using Nominatim API
     * 
     * @param int $post_id Post ID
     * @param bool $force_update Force update even if zip code exists
     * @return array Result with zip_code, latitude, longitude, and success status
     */
    public static function geocode_and_extract_zip($post_id, $force_update = false) {
        // Check if zip code already exists (unless forcing update)
        if (!$force_update) {
            $existing_zip = get_post_meta($post_id, 'wpcf-zip-code', true);
            if (empty($existing_zip)) {
                $existing_zip = get_post_meta($post_id, 'wpcf-zip', true);
            }
            if (empty($existing_zip)) {
                $existing_zip = get_post_meta($post_id, '_listing_zip', true);
            }
            if (!empty($existing_zip)) {
                // Return existing zip and coordinates if they exist
                $lat = get_post_meta($post_id, '_listing_latitude', true);
                $lng = get_post_meta($post_id, '_listing_longitude', true);
                return array(
                    'success' => true,
                    'zip_code' => $existing_zip,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'skipped' => true,
                );
            }
        }
        
        // Build address from fields
        // First try the full address field
        $original_address = get_post_meta($post_id, 'wpcf-address', true);
        $address_meta_key = 'wpcf-address';
        if (empty($original_address)) {
            $original_address = get_post_meta($post_id, '_listing_address', true);
            $address_meta_key = '_listing_address';
        }
        
        // Check if address is already complete (has city and state, or ends with USA, or has zip code)
        $is_full_address = false;
        if (!empty($original_address)) {
            $trimmed = trim($original_address);
            
            // Check if it ends with USA
            $is_full_address = (stripos($trimmed, ', USA') !== false) || 
                              (stripos($trimmed, ' USA') !== false && stripos($trimmed, ' USA') === strlen($trimmed) - 4);
            
            // If not, check if it already contains city and state information
            if (!$is_full_address) {
                // Get city and state to check if they're already in the address
                $city = get_post_meta($post_id, 'wpcf-city', true);
                if (empty($city)) {
                    $city = get_post_meta($post_id, '_listing_city', true);
                }
                // Extract city name if it has "|" format
                $city_name = $city;
                if (!empty($city) && strpos($city, '|') !== false) {
                    $city_name = trim(explode('|', $city)[0]);
                }
                
                $state = get_post_meta($post_id, 'wpcf-state-1', true);
                if (empty($state)) {
                    $state = get_post_meta($post_id, '_listing_state', true);
                }
                if (empty($state)) {
                    $state = 'MA';
                }
                
                // Check if address contains state (MA, Massachusetts) or zip code (5 digits)
                $has_state = stripos($trimmed, $state) !== false || 
                            stripos($trimmed, 'Massachusetts') !== false ||
                            preg_match('/\bMA\b/i', $trimmed);
                $has_zip = preg_match('/\b\d{5}(-\d{4})?\b/', $trimmed);
                
                // Check if address contains city name (check both full city field and extracted name)
                $has_city = false;
                if (!empty($city_name)) {
                    $has_city = stripos($trimmed, $city_name) !== false;
                }
                // Also check the full city field if it's different
                if (!$has_city && !empty($city) && $city !== $city_name) {
                    // Check if any part of the city field is in the address
                    $city_parts = explode('|', $city);
                    foreach ($city_parts as $part) {
                        $part = trim($part);
                        if (!empty($part) && stripos($trimmed, $part) !== false) {
                            $has_city = true;
                            break;
                        }
                    }
                }
                
                // If address has both state and (city or zip), consider it complete
                if ($has_state && ($has_city || $has_zip)) {
                    $is_full_address = true;
                }
            }
        }
        
        $address = $original_address;
        
        // If address is already complete, use it as-is for geocoding (don't combine with city/state)
        // Otherwise, build from address parts or city + state
        if (!$is_full_address) {
            if (empty($address)) {
                // No address field - build from city and state
                $address_parts = array();
                
                // Get city
                $city = get_post_meta($post_id, 'wpcf-city', true);
                if (empty($city)) {
                    $city = get_post_meta($post_id, '_listing_city', true);
                }
                // Extract city name if it has "|" format
                if (!empty($city) && strpos($city, '|') !== false) {
                    $city = trim(explode('|', $city)[0]);
                }
                if (!empty($city)) {
                    $address_parts[] = $city;
                }
                
                // Get state
                $state = get_post_meta($post_id, 'wpcf-state-1', true);
                if (empty($state)) {
                    $state = get_post_meta($post_id, '_listing_state', true);
                }
                if (empty($state)) {
                    $state = 'MA'; // Default to Massachusetts
                }
                $address_parts[] = $state;
                
                $address = !empty($address_parts) ? implode(', ', $address_parts) : '';
            } else {
                // Address exists but is not complete - add city and state if needed
                // Get city and state
                $city = get_post_meta($post_id, 'wpcf-city', true);
                if (empty($city)) {
                    $city = get_post_meta($post_id, '_listing_city', true);
                }
                // Extract city name if it has "|" format
                $city_name = $city;
                if (!empty($city) && strpos($city, '|') !== false) {
                    $city_name = trim(explode('|', $city)[0]);
                }
                
                $state = get_post_meta($post_id, 'wpcf-state-1', true);
                if (empty($state)) {
                    $state = get_post_meta($post_id, '_listing_state', true);
                }
                if (empty($state)) {
                    $state = 'MA';
                }
                
                // Check if city is already in address (check both full city and extracted name)
                $city_in_address = false;
                if (!empty($city_name)) {
                    $city_in_address = stripos($address, $city_name) !== false;
                }
                if (!$city_in_address && !empty($city) && $city !== $city_name) {
                    $city_parts = explode('|', $city);
                    foreach ($city_parts as $part) {
                        $part = trim($part);
                        if (!empty($part) && stripos($address, $part) !== false) {
                            $city_in_address = true;
                            break;
                        }
                    }
                }
                
                // Check if state is already in address
                $state_in_address = stripos($address, $state) !== false || 
                                   stripos($address, 'Massachusetts') !== false ||
                                   preg_match('/\bMA\b/i', $address);
                
                // Build address parts
                $address_parts = array($address);
                
                // Add city if not already in address
                if (!empty($city_name) && !$city_in_address) {
                    $address_parts[] = $city_name;
                }
                
                // Add state if not already in address
                if (!$state_in_address) {
                    $address_parts[] = $state;
                }
                
                $address = implode(', ', $address_parts);
            }
        }
        
        if (empty($address)) {
            return array(
                'success' => false,
                'error' => 'No address found',
            );
        }
        
        // Add Massachusetts context if not present
        $search_query = $address;
        if (stripos($address, 'massachusetts') === false && 
            stripos($address, 'ma') === false && 
            stripos($address, 'usa') === false) {
            $search_query = $address . ', Massachusetts, USA';
        }
        
        // Use Nominatim API to geocode
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query(array(
            'q' => $search_query,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'us',
            'addressdetails' => 1,
        ));
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Maloney Affordable Listings',
            ),
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !is_array($data) || empty($data[0])) {
            return array(
                'success' => false,
                'error' => 'No results from geocoding API',
            );
        }
        
        $result = $data[0];
        $address_parts = isset($result['address']) ? $result['address'] : array();
        
        // Extract zip code
        $zip_code = false;
        if (isset($address_parts['postcode'])) {
            $zip_code = $address_parts['postcode'];
        } elseif (isset($address_parts['postal_code'])) {
            $zip_code = $address_parts['postal_code'];
        }
        
        // Get coordinates
        $latitude = isset($result['lat']) ? floatval($result['lat']) : false;
        $longitude = isset($result['lon']) ? floatval($result['lon']) : false;
        
        // Build standardized address from geocoding result
        $geocoded_address = isset($result['display_name']) ? $result['display_name'] : '';
        // Extract just the street address part (before first comma usually)
        $street_address = '';
        if (!empty($address_parts)) {
            $street_parts = array();
            if (isset($address_parts['house_number'])) {
                $street_parts[] = $address_parts['house_number'];
            }
            if (isset($address_parts['road'])) {
                $street_parts[] = $address_parts['road'];
            } elseif (isset($address_parts['street'])) {
                $street_parts[] = $address_parts['street'];
            }
            $street_address = implode(' ', $street_parts);
        }
        
        // Get city and state from geocoding result or use existing
        $geocoded_city = '';
        if (isset($address_parts['city'])) {
            $geocoded_city = $address_parts['city'];
        } elseif (isset($address_parts['town'])) {
            $geocoded_city = $address_parts['town'];
        } elseif (isset($address_parts['village'])) {
            $geocoded_city = $address_parts['village'];
        } elseif (isset($address_parts['municipality'])) {
            $geocoded_city = $address_parts['municipality'];
        }
        
        $geocoded_state = '';
        if (isset($address_parts['state'])) {
            $geocoded_state = $address_parts['state'];
        }
        
        // Save zip code if found
        if ($zip_code) {
            update_post_meta($post_id, 'wpcf-zip-code', $zip_code);
            update_post_meta($post_id, '_listing_zip', $zip_code);
            
            // Update address field with zip code
            if (!empty($address_meta_key) && !empty($original_address)) {
                // Check if zip code is already in the address
                if (strpos($original_address, $zip_code) === false) {
                    $updated_address = '';
                    
                    if ($is_full_address) {
                        // Address is already complete - just add zip code if missing
                        // Check if address has state but no zip - insert zip after state
                        if (preg_match('/\b(MA|Massachusetts)\b/i', $original_address)) {
                            // Address has state - add zip after state
                            if (preg_match('/\b(MA|Massachusetts)\b\s*$/i', $original_address)) {
                                // State is at the end - add zip after it
                                $updated_address = preg_replace('/\b(MA|Massachusetts)\b\s*$/i', '$1 ' . $zip_code, $original_address);
                            } elseif (preg_match('/\b(MA|Massachusetts)\b\s*,/i', $original_address)) {
                                // State is followed by comma - add zip before comma
                                $updated_address = preg_replace('/\b(MA|Massachusetts)\b\s*,/i', '$1 ' . $zip_code . ',', $original_address);
                            } else {
                                // State exists but format is unclear - try to add zip after state
                                $updated_address = preg_replace('/\b(MA|Massachusetts)\b/i', '$1 ' . $zip_code, $original_address, 1);
                            }
                        } elseif (preg_match('/\b\d{5}(-\d{4})?\b/', $original_address)) {
                            // Address already has a zip code (different one) - replace it
                            $updated_address = preg_replace('/\b\d{5}(-\d{4})?\b/', $zip_code, $original_address);
                        } else {
                            // Address ends with USA - add zip before USA
                            if (stripos($original_address, ', USA') !== false) {
                                $updated_address = str_ireplace(', USA', ', ' . $zip_code . ', USA', $original_address);
                            } elseif (preg_match('/\bUSA\s*$/i', $original_address)) {
                                $updated_address = preg_replace('/\s+USA\s*$/i', ' ' . $zip_code . ', USA', $original_address);
                            } else {
                                // Just append zip at the end
                                $updated_address = $original_address . ' ' . $zip_code;
                            }
                        }
                    } else {
                        // Address is incomplete - check what's missing and add only what's needed
                        $trimmed = trim($original_address);
                        
                        // Check if address already has state
                        $has_state = preg_match('/\b(MA|Massachusetts)\b/i', $trimmed);
                        // Check if address already has a city (look for common patterns)
                        $has_city = false;
                        // Check if address already has zip (different one)
                        $has_existing_zip = preg_match('/\b\d{5}(-\d{4})?\b/', $trimmed);
                        
                        if ($has_state && !$has_existing_zip) {
                            // Address has state but no zip - just add zip after state
                            if (preg_match('/\b(MA|Massachusetts)\b\s*$/i', $trimmed)) {
                                $updated_address = preg_replace('/\b(MA|Massachusetts)\b\s*$/i', '$1 ' . $zip_code, $trimmed);
                            } elseif (preg_match('/\b(MA|Massachusetts)\b\s*,/i', $trimmed)) {
                                $updated_address = preg_replace('/\b(MA|Massachusetts)\b\s*,/i', '$1 ' . $zip_code . ',', $trimmed);
                            } else {
                                $updated_address = preg_replace('/\b(MA|Massachusetts)\b/i', '$1 ' . $zip_code, $trimmed, 1);
                            }
                        } elseif ($has_existing_zip) {
                            // Address has a different zip - replace it
                            $updated_address = preg_replace('/\b\d{5}(-\d{4})?\b/', $zip_code, $trimmed);
                        } else {
                            // Address is truly incomplete - build from scratch but be careful not to duplicate
                            // Extract street address from original (everything before the last comma, or everything if no comma)
                            $final_street = $original_address;
                            if (strpos($original_address, ',') !== false) {
                                // Address has commas - extract street part (before first comma usually)
                                $parts = explode(',', $original_address);
                                $final_street = trim($parts[0]);
                            }
                            
                            // Only add city if it's not already in the street address
                            $city = '';
                            if (!empty($geocoded_city)) {
                                $city = $geocoded_city;
                            } else {
                                $city = get_post_meta($post_id, 'wpcf-city', true);
                                if (empty($city)) {
                                    $city = get_post_meta($post_id, '_listing_city', true);
                                }
                                // Extract city name if it has "|" format
                                if (!empty($city) && strpos($city, '|') !== false) {
                                    $city = trim(explode('|', $city)[0]);
                                }
                            }
                            
                            // Check if city is already in the street address
                            $city_in_street = false;
                            if (!empty($city)) {
                                $city_in_street = stripos($final_street, $city) !== false;
                            }
                            
                            // Prefer geocoded state, then existing state field
                            $state = '';
                            if (!empty($geocoded_state)) {
                                $state = $geocoded_state;
                            } else {
                                $state = get_post_meta($post_id, 'wpcf-state-1', true);
                                if (empty($state)) {
                                    $state = get_post_meta($post_id, '_listing_state', true);
                                }
                            }
                            if (empty($state)) {
                                $state = 'MA';
                            }
                            
                            // Build address: Street, City (if not already in street), State Zip
                            $address_components = array();
                            if (!empty($final_street)) {
                                $address_components[] = $final_street;
                            }
                            if (!empty($city) && !$city_in_street) {
                                $address_components[] = $city;
                            }
                            if (!empty($state)) {
                                $address_components[] = $state . ' ' . $zip_code;
                            } else {
                                $address_components[] = $zip_code;
                            }
                            
                            $updated_address = implode(', ', $address_components);
                        }
                    }
                    
                    // Save updated address if we built one
                    if (!empty($updated_address)) {
                        update_post_meta($post_id, $address_meta_key, $updated_address);
                    }
                }
            }
        }
        
        // Save coordinates if found
        if ($latitude && $longitude) {
            update_post_meta($post_id, '_listing_latitude', $latitude);
            update_post_meta($post_id, '_listing_longitude', $longitude);
            
            // Validate coordinates are in Massachusetts
            if ($latitude < 41.0 || $latitude > 43.0 || $longitude < -73.5 || $longitude > -69.9) {
                update_post_meta($post_id, '_listing_geocode_suspicious', '1');
            } else {
                delete_post_meta($post_id, '_listing_geocode_suspicious');
            }
        }
        
        return array(
            'success' => true,
            'zip_code' => $zip_code,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address_used' => $search_query,
        );
    }
    
    /**
     * Batch geocode and extract zip codes for all listings
     * 
     * @param bool $force_update Force update even if zip code exists
     * @param int $batch_size Number of listings to process per batch
     * @return array Results with counts
     */
    public static function batch_geocode_and_extract_zip($force_update = false, $batch_size = 20) {
        // Ensure field exists
        if (!self::field_exists()) {
            $result = self::ensure_zip_field_exists();
            if (is_wp_error($result)) {
                return array(
                    'success' => false,
                    'error' => $result->get_error_message(),
                    'processed' => 0,
                    'extracted' => 0,
                    'geocoded' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                );
            }
        }
        
        // Get listings without zip codes (or all if forcing update)
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => $batch_size,
            'post_status' => 'any',
            'orderby' => 'ID',
            'order' => 'ASC',
        );
        
        if (!$force_update) {
            // Only get listings without zip codes
            $args['meta_query'] = array(
                'relation' => 'AND',
                array(
                    'relation' => 'OR',
                    array(
                        'key' => 'wpcf-zip-code',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => 'wpcf-zip-code',
                        'value' => '',
                        'compare' => '=',
                    ),
                ),
            );
        }
        
        $query = new WP_Query($args);
        
        $processed = 0;
        $extracted = 0;
        $geocoded = 0;
        $skipped = 0;
        $failed = 0;
        $errors = array();
        $successful = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $post_title = get_the_title($post_id);
                $processed++;
                
                // Geocode and extract zip
                $result = self::geocode_and_extract_zip($post_id, $force_update);
                
                if ($result['success']) {
                    if (isset($result['skipped']) && $result['skipped']) {
                        $skipped++;
                    } else {
                        $success_entry = array(
                            'title' => $post_title,
                            'id' => $post_id,
                        );
                        
                        if ($result['zip_code']) {
                            $extracted++;
                            $success_entry['zip_code'] = $result['zip_code'];
                        }
                        if ($result['latitude'] && $result['longitude']) {
                            $geocoded++;
                            $success_entry['coordinates'] = $result['latitude'] . ', ' . $result['longitude'];
                        }
                        if (isset($result['address_used'])) {
                            $success_entry['address_used'] = $result['address_used'];
                        }
                        
                        $successful[] = $success_entry;
                    }
                } else {
                    $failed++;
                    $errors[] = array(
                        'title' => $post_title,
                        'id' => $post_id,
                        'error' => isset($result['error']) ? $result['error'] : 'Unknown error',
                    );
                }
                
                // Rate limit: 1 request per second for Nominatim
                if ($processed < $query->post_count) {
                    usleep(1000000); // 1 second delay
                }
            }
            wp_reset_postdata();
        }
        
        return array(
            'success' => true,
            'processed' => $processed,
            'extracted' => $extracted,
            'geocoded' => $geocoded,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
            'successful' => $successful,
            'has_more' => $query->found_posts > $batch_size,
        );
    }
}

