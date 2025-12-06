<?php
/**
 * Condo Listings Migration
 * Migrates condo listings data from Ninja Table 3596 to listing custom fields
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

class Maloney_Listings_Condo_Listings_Migration {
    
    private $ninja_table_id = 3596;
    private $results = array(
        'processed' => 0,
        'updated' => 0,
        'not_found' => 0,
        'errors' => array(),
        'not_imported_units' => array(), // Store units that couldn't be imported
    );
    
    /**
     * Get data from Ninja Table
     */
    private function get_ninja_table_data() {
        // Try to get from cache first
        $cache_data = get_post_meta($this->ninja_table_id, '_ninja_table_cache_object', true);
        if (!empty($cache_data) && is_array($cache_data)) {
            return $cache_data;
        }
        
        // Fallback: get from database
        global $wpdb;
        $table = $wpdb->prefix . 'ninja_table_items';
        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $this->results['errors'][] = 'Ninja Tables database table not found.';
            return array();
        }
        
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT value FROM {$table} WHERE table_id = %d",
            $this->ninja_table_id
        ));
        
        $data = array();
        foreach ($rows as $row) {
            $values = json_decode($row->value, true);
            if (is_array($values)) {
                $data[] = $values;
            }
        }
        
        return $data;
    }
    
    /**
     * Normalize unit type from Ninja Table to standard format
     */
    private function normalize_unit_type($bedrooms) {
        $bedrooms = trim($bedrooms);
        
        // Map various formats to standard types
        $mapping = array(
            'studio' => 'Studio',
            '0-bedroom' => 'Studio',
            '0 br' => 'Studio',
            '1-bedroom' => '1-Bedroom',
            '1 br' => '1-Bedroom',
            '1 bedroom' => '1-Bedroom',
            '2-bedroom' => '2-Bedroom',
            '2 br' => '2-Bedroom',
            '2 bedroom' => '2-Bedroom',
            '3-bedroom' => '3-Bedroom',
            '3 br' => '3-Bedroom',
            '3 bedroom' => '3-Bedroom',
            '4-bedroom' => '4+ Bedroom',
            '4+ bedroom' => '4+ Bedroom',
            '4+ br' => '4+ Bedroom',
        );
        
        $bedrooms_lower = strtolower($bedrooms);
        foreach ($mapping as $key => $value) {
            if (stripos($bedrooms_lower, $key) !== false) {
                return $value;
            }
        }
        
        // Check for exact matches
        if (stripos($bedrooms_lower, 'studio') !== false) {
            return 'Studio';
        }
        if (preg_match('/\b1\s*[-]?\s*bed/i', $bedrooms)) {
            return '1-Bedroom';
        }
        if (preg_match('/\b2\s*[-]?\s*bed/i', $bedrooms)) {
            return '2-Bedroom';
        }
        if (preg_match('/\b3\s*[-]?\s*bed/i', $bedrooms)) {
            return '3-Bedroom';
        }
        if (preg_match('/\b4\+?\s*[-]?\s*bed/i', $bedrooms)) {
            return '4+ Bedroom';
        }
        
        // Return original if no match
        return $bedrooms;
    }
    
    /**
     * Ensure unit size option exists in the field
     * Adds the option to the field if it doesn't exist
     */
    private function ensure_unit_size_option_exists($unit_type) {
        if (!function_exists('wpcf_admin_fields_get_fields') || !function_exists('wpcf_admin_fields_save_field')) {
            return false;
        }
        
        $fields = wpcf_admin_fields_get_fields();
        $field_slug = 'condo-listings-bedrooms';
        
        if (!isset($fields[$field_slug])) {
            // Field doesn't exist, can't add option
            return false;
        }
        
        $field = $fields[$field_slug];
        
        // Check if option already exists
        if (isset($field['data']['options']) && is_array($field['data']['options'])) {
            foreach ($field['data']['options'] as $key => $option) {
                if (is_array($option) && isset($option['value']) && $option['value'] === $unit_type) {
                    // Option already exists
                    return true;
                } elseif (is_string($option) && $option === $unit_type) {
                    // Option already exists (old format)
                    return true;
                } elseif ($key === $unit_type) {
                    // Option already exists (key is the value)
                    return true;
                }
            }
        } else {
            $field['data']['options'] = array();
        }
        
        // Add the new option
        $field['data']['options'][$unit_type] = array(
            'title' => $unit_type,
            'value' => $unit_type,
        );
        
        // Save the updated field
        $result = wpcf_admin_fields_save_field($field);
        
        return !is_wp_error($result);
    }
    
    /**
     * Extract number from units_available field
     */
    private function extract_units_count($units_available) {
        if (empty($units_available)) {
            return 0;
        }
        
        // Handle cases like "1 (ADA-M Unit)" - extract just the number
        if (preg_match('/(\d+)/', $units_available, $matches)) {
            return intval($matches[1]);
        }
        
        return intval($units_available);
    }
    
    /**
     * Group data by property - keep all rows as separate entries
     */
    private function group_data_by_property($data) {
        $grouped = array();
        
        foreach ($data as $row) {
            $property = isset($row['property']) ? trim($row['property']) : '';
            
            if (empty($property)) {
                continue;
            }
            
            if (!isset($grouped[$property])) {
                $grouped[$property] = array();
            }
            
            // Store each row as a separate entry (don't aggregate)
            $grouped[$property][] = $row;
        }
        
        return $grouped;
    }
    
    /**
     * Find listing by property name with improved fuzzy matching
     */
    private function find_listing_by_name($property_name) {
        $property_name = trim($property_name);
        $property_name_lower = strtolower($property_name);
        
        // Normalize common variations
        $normalized = $this->normalize_property_name($property_name);
        
        // Try exact match first
        $posts = get_posts(array(
            'post_type' => 'listing',
            'title' => $property_name,
            'posts_per_page' => 1,
            'post_status' => 'any',
        ));
        
        if (!empty($posts)) {
            return $posts[0];
        }
        
        // Try normalized match
        if ($normalized !== $property_name) {
            $posts = get_posts(array(
                'post_type' => 'listing',
                'title' => $normalized,
                'posts_per_page' => 1,
                'post_status' => 'any',
            ));
            
            if (!empty($posts)) {
                return $posts[0];
            }
        }
        
        // Try search with partial match
        $posts = get_posts(array(
            'post_type' => 'listing',
            's' => $property_name,
            'posts_per_page' => 20,
            'post_status' => 'any',
        ));
        
        if (!empty($posts)) {
            $best_match = null;
            $best_score = 0;
            
            foreach ($posts as $post) {
                $post_title_lower = strtolower($post->post_title);
                
                // Exact match
                if ($post_title_lower === $property_name_lower) {
                    return $post;
                }
                
                // Check if property name contains post title or vice versa
                if (stripos($post_title_lower, $property_name_lower) !== false || 
                    stripos($property_name_lower, $post_title_lower) !== false) {
                    // Calculate similarity score
                    similar_text($property_name_lower, $post_title_lower, $score);
                    if ($score > $best_score) {
                        $best_score = $score;
                        $best_match = $post;
                    }
                }
            }
            
            if ($best_match && $best_score > 60) {
                return $best_match;
            }
            
            // Return first result if no good match found
            return $posts[0];
        }
        
        return null;
    }
    
    /**
     * Normalize property name for better matching
     */
    private function normalize_property_name($name) {
        $name = trim($name);
        
        // Common variations
        $variations = array(
            'gordons wood' => 'gordon\'s wood condominium',
            'gordons woods' => 'gordon\'s wood condominium',
            'gordon\'s wood' => 'gordon\'s wood condominium',
        );
        
        $name_lower = strtolower($name);
        foreach ($variations as $key => $value) {
            if (stripos($name_lower, $key) !== false) {
                return $value;
            }
        }
        
        return $name;
    }
    
    /**
     * Update listing with condo listings data
     * Uses repetitive fields structure - one entry per row
     */
    private function update_listing_condo_listings($listing_id, $rows_data) {
        // Clear existing repetitive field values
        delete_post_meta($listing_id, 'wpcf-condo-listings-property');
        delete_post_meta($listing_id, 'wpcf-condo-listings-town');
        delete_post_meta($listing_id, 'wpcf-condo-listings-bedrooms');
        delete_post_meta($listing_id, 'wpcf-condo-listings-bathrooms');
        delete_post_meta($listing_id, 'wpcf-condo-listings-price');
        delete_post_meta($listing_id, 'wpcf-condo-listings-income-limit');
        delete_post_meta($listing_id, 'wpcf-condo-listings-type');
        delete_post_meta($listing_id, 'wpcf-condo-listings-units-available');
        delete_post_meta($listing_id, 'wpcf-condo-listings-accessible-units');
        delete_post_meta($listing_id, 'wpcf-condo-listings-view-apply');
        
        // Get property data for auto-fill
        $property = get_post($listing_id);
        $property_town = get_post_meta($listing_id, 'wpcf-city', true);
        if (empty($property_town)) {
            $property_town = get_post_meta($listing_id, '_listing_city', true);
        }
        $property_link = get_permalink($listing_id);
        
        $total_available = 0;
        
        // Create one entry per row
        foreach ($rows_data as $row) {
            $bedrooms = isset($row['bedrooms']) ? trim($row['bedrooms']) : '';
            $units_available = isset($row['units_available']) ? $row['units_available'] : '0';
            $accessible_units = isset($row['accessible_units']) ? trim($row['accessible_units']) : '';
            $price = isset($row['price']) ? trim($row['price']) : '';
            $income_limit = isset($row['income_limit']) ? trim($row['income_limit']) : '';
            $type = isset($row['type']) ? trim($row['type']) : '';
            $view_apply = isset($row['view_apply']) ? trim($row['view_apply']) : '';
            $town = isset($row['town']) ? trim($row['town']) : $property_town;
            
            // Skip if no bedrooms or units
            if (empty($bedrooms)) {
                continue;
            }
            
            $unit_type = $this->normalize_unit_type($bedrooms);
            $units_count = $this->extract_units_count($units_available);
            
            if ($units_count <= 0) {
                continue;
            }
            
            // Ensure the unit type option exists in the field
            $this->ensure_unit_size_option_exists($unit_type);
            
            // Keep the full units_available text (e.g., "1 (ADA-M Unit)" or "1 (55+ Age-Restricted Unit)")
            $units_available_text = trim($units_available);
            
            // Clean up price (remove $ and commas)
            $price_clean = preg_replace('/[^0-9.]/', '', $price);
            
            // Use view_apply from row or generate from property
            $view_apply_url = !empty($view_apply) ? trim($view_apply) : $property_link;
            
            // Save as repetitive fields
            add_post_meta($listing_id, 'wpcf-condo-listings-property', $listing_id);
            add_post_meta($listing_id, 'wpcf-condo-listings-town', $town); // Keep full "City | Neighborhood" format
            add_post_meta($listing_id, 'wpcf-condo-listings-bedrooms', $unit_type);
            add_post_meta($listing_id, 'wpcf-condo-listings-price', $price_clean);
            add_post_meta($listing_id, 'wpcf-condo-listings-income-limit', $income_limit);
            add_post_meta($listing_id, 'wpcf-condo-listings-type', $type);
            add_post_meta($listing_id, 'wpcf-condo-listings-units-available', $units_available_text); // Save full text, not just number
            add_post_meta($listing_id, 'wpcf-condo-listings-accessible-units', $accessible_units);
            add_post_meta($listing_id, 'wpcf-condo-listings-view-apply', esc_url_raw($view_apply_url));
            
            $total_available += $units_count;
        }
        
        // Update total available (for backward compatibility)
        update_post_meta($listing_id, 'wpcf-total-available-condo-units', $total_available);
        update_post_meta($listing_id, '_listing_total_available_condo_units', $total_available);
        
        return true;
    }
    
    /**
     * Run migration
     */
    public function run_migration() {
        $this->results = array(
            'processed' => 0,
            'updated' => 0,
            'not_found' => 0,
            'errors' => array(),
            'not_imported_units' => array(),
        );
        
        // Get data from Ninja Table
        $data = $this->get_ninja_table_data();
        
        if (empty($data)) {
            $this->results['errors'][] = 'No data found in Ninja Table.';
            return $this->results;
        }
        
        // Group by property
        $grouped = $this->group_data_by_property($data);
        
        // Update each listing
        foreach ($grouped as $property_name => $rows_data) {
            $this->results['processed']++;
            
            $listing = $this->find_listing_by_name($property_name);
            
            if (!$listing) {
                $this->results['not_found']++;
                $this->results['errors'][] = "Listing not found for property: {$property_name}";
                // Add all units for this property to not_imported_units
                foreach ($rows_data as $row) {
                    $this->results['not_imported_units'][] = array(
                        'property' => $property_name,
                        'property_found' => '',
                        'bedrooms' => isset($row['bedrooms']) ? $row['bedrooms'] : '',
                        'units_available' => isset($row['units_available']) ? $row['units_available'] : '',
                        'price' => isset($row['price']) ? $row['price'] : '',
                        'town' => isset($row['town']) ? $row['town'] : '',
                        'reason' => 'Property not found in listings',
                    );
                }
                continue;
            }
            
            // Only update condo properties
            $listing_types = wp_get_post_terms($listing->ID, 'listing_type', array('fields' => 'slugs'));
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
                // Add all units for this property to not_imported_units (not a condo)
                foreach ($rows_data as $row) {
                    $this->results['not_imported_units'][] = array(
                        'property' => $property_name,
                        'property_found' => $listing->post_title . ' (ID: ' . $listing->ID . ')',
                        'bedrooms' => isset($row['bedrooms']) ? $row['bedrooms'] : '',
                        'units_available' => isset($row['units_available']) ? $row['units_available'] : '',
                        'price' => isset($row['price']) ? $row['price'] : '',
                        'town' => isset($row['town']) ? $row['town'] : '',
                        'reason' => 'Property is not a condo listing',
                    );
                }
                continue; // Skip non-condo listings
            }
            
            // Update condo listings (pass all rows for this property)
            if ($this->update_listing_condo_listings(
                $listing->ID,
                $rows_data
            )) {
                $this->results['updated']++;
            } else {
                $this->results['errors'][] = "Failed to update listing: {$property_name} (ID: {$listing->ID})";
                // Add all units for this property to not_imported_units
                foreach ($rows_data as $row) {
                    $this->results['not_imported_units'][] = array(
                        'property' => $property_name,
                        'property_found' => $listing->post_title . ' (ID: ' . $listing->ID . ')',
                        'bedrooms' => isset($row['bedrooms']) ? $row['bedrooms'] : '',
                        'units_available' => isset($row['units_available']) ? $row['units_available'] : '',
                        'price' => isset($row['price']) ? $row['price'] : '',
                        'town' => isset($row['town']) ? $row['town'] : '',
                        'reason' => 'Failed to update listing',
                    );
                }
            }
        }
        
        return $this->results;
    }
    
    /**
     * Get migration results
     */
    public function get_results() {
        return $this->results;
    }
}

