<?php
/**
 * Available Units Fields Setup
 * Creates a repeating field group for Current Rental Availability
 * Each row contains: property, town, bedrooms, rent, minimum_income, income_limit, type, units_available, accessible_units, view_apply
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

class Maloney_Listings_Available_Units_Fields {
    
    /**
     * Field definitions for the repeating availability entries
     */
    private $fields = array(
        'availability-property' => array(
            'name' => 'Property',
            'type' => 'post',
            'description' => 'Property name (auto-filled from selected property)',
            'post_type' => 'listing',
        ),
        'availability-town' => array(
            'name' => 'Town',
            'type' => 'textfield',
            'description' => 'City/Town (auto-filled from property)',
        ),
        'availability-bedrooms' => array(
            'name' => 'Unit Size',
            'type' => 'select',
            'description' => 'Unit Size (Studio, 1-Bedroom, 2-Bedroom, etc.)',
            'options' => array(
                'Studio' => 'Studio',
                '1-Bedroom' => '1-Bedroom',
                '2-Bedroom' => '2-Bedroom',
                '3-Bedroom' => '3-Bedroom',
                '4-Bedroom' => '4-Bedroom',
                '4+ Bedroom' => '4+ Bedroom',
                '5-Bedroom' => '5-Bedroom',
                '6-Bedroom' => '6-Bedroom',
            ),
        ),
        'availability-bathrooms' => array(
            'name' => 'Bathrooms',
            'type' => 'select',
            'description' => 'Number of bathrooms',
            'options' => array(
                '1' => '1',
                '1.5' => '1.5',
                '2' => '2',
                '2.5' => '2.5',
                '3' => '3',
                '3.5' => '3.5',
                '4' => '4',
                '4.5' => '4.5',
                '5+' => '5+',
            ),
        ),
        'availability-rent' => array(
            'name' => 'Total Monthly Leasing Price',
            'type' => 'numeric',
            'description' => 'Monthly rent price',
        ),
        'availability-minimum-income' => array(
            'name' => 'Minimum Income',
            'type' => 'numeric',
            'description' => 'Minimum income required',
        ),
        'availability-income-limit' => array(
            'name' => 'Income Limit (AMI %)',
            'type' => 'select',
            'description' => 'Income limit as percentage of AMI',
            'options' => array(), // Will be populated from income_limit taxonomy
        ),
        'availability-type' => array(
            'name' => 'Type',
            'type' => 'select',
            'description' => 'Lottery or First Come First Serve',
            'options' => array(
                'Lottery' => 'Lottery',
                'FCFS' => 'FCFS',
            ),
        ),
        'availability-units-available' => array(
            'name' => 'Units Available',
            'type' => 'numeric',
            'description' => 'Number of units available',
        ),
        'availability-accessible-units' => array(
            'name' => 'Accessible Units',
            'type' => 'textarea',
            'description' => 'Description of accessible units',
        ),
        'availability-view-apply' => array(
            'name' => 'Learn More Link',
            'type' => 'url',
            'description' => 'Link to property page (auto-filled from property)',
        ),
    );
    
    /**
     * Check if a field exists
     */
    private function field_exists($field_slug) {
        if (!function_exists('wpcf_admin_fields_get_fields')) {
            return false;
        }
        
        $fields = wpcf_admin_fields_get_fields();
        return isset($fields[$field_slug]);
    }
    
    /**
     * Create a Toolset field
     */
    private function create_field($field_slug, $field_data) {
        if (!function_exists('wpcf_admin_fields_save_field')) {
            return false;
        }
        
        $field = array(
            'name' => $field_data['name'],
            'slug' => $field_slug,
            'type' => $field_data['type'],
            'description' => $field_data['description'],
            'data' => array(
                'repetitive' => 1, // Make all fields repetitive so they repeat together
                'conditional_display' => array(
                    'relation' => 'AND',
                    'conditions' => array(),
                ),
            ),
        );
        
        // Add field-specific data
        if ($field_data['type'] === 'numeric') {
            $field['data']['validate'] = array(
                'number' => array(
                    'active' => 1,
                    'message' => 'Please enter a valid number',
                ),
            );
        } elseif ($field_data['type'] === 'select' && isset($field_data['options'])) {
            $field['data']['options'] = array();
            // Special handling for income_limit field - pull from taxonomy
            if ($field_slug === 'availability-income-limit') {
                $income_limit_terms = get_terms(array(
                    'taxonomy' => 'income_limit',
                    'hide_empty' => false,
                ));
                if (!is_wp_error($income_limit_terms) && !empty($income_limit_terms)) {
                    foreach ($income_limit_terms as $term) {
                        $field['data']['options'][$term->name] = array(
                            'title' => $term->name,
                            'value' => $term->name,
                        );
                    }
                } else {
                    // Fallback to default options if taxonomy is empty
                    $default_options = array('50%', '60%', '70%', '80%', '90%', '100%', '110%', '120%', '150%');
                    foreach ($default_options as $option) {
                        $field['data']['options'][$option] = array(
                            'title' => $option,
                            'value' => $option,
                        );
                    }
                }
            } else {
                // Regular select field with predefined options
                foreach ($field_data['options'] as $value => $label) {
                    $field['data']['options'][$value] = array(
                        'title' => $label,
                        'value' => $value,
                    );
                }
            }
        } elseif ($field_data['type'] === 'post' && isset($field_data['post_type'])) {
            $field['data']['post_type'] = $field_data['post_type'];
            $field['data']['post_reference_type'] = 'post';
        } elseif ($field_data['type'] === 'textarea') {
            $field['data']['rows'] = 3;
        }
        
        $result = wpcf_admin_fields_save_field($field);
        
        if (is_wp_error($result)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get or create the "Current Rental Availability" field group
     */
    private function get_or_create_availability_group() {
        if (!function_exists('wpcf_admin_fields_get_groups')) {
            return null;
        }
        
        // Try to find existing "Current Rental Availability" group
        $groups = wpcf_admin_fields_get_groups();
        foreach ($groups as $group) {
            if (stripos($group['name'], 'current') !== false && 
                (stripos($group['name'], 'rental') !== false || stripos($group['name'], 'availability') !== false)) {
                return $group;
            }
        }
        
        // Create new group
        if (function_exists('wpcf_admin_fields_save_group')) {
            $group_data = array(
                'name' => 'Current Rental Availability',
                'description' => 'Repeating field group for managing available rental units',
            );
            $group_id = wpcf_admin_fields_save_group($group_data);
            
            if ($group_id) {
                // Assign to listing post type (must be comma-separated string, not array)
                update_post_meta($group_id, '_wp_types_group_post_types', ',listing,');
                
                return array(
                    'id' => $group_id,
                    'name' => 'Current Rental Availability',
                );
            }
        }
        
        return null;
    }
    
    /**
     * Add fields to field group
     */
    private function add_fields_to_group($group_id, $field_slugs) {
        // Get existing fields in group
        $existing_fields = get_post_meta($group_id, '_wp_types_group_fields', true);
        $existing_slugs = array();
        if (!empty($existing_fields)) {
            if (is_string($existing_fields)) {
                $existing_slugs = array_filter(array_map('trim', explode(',', trim($existing_fields, ','))));
            } elseif (is_array($existing_fields)) {
                $existing_slugs = $existing_fields;
            }
        }
        
        // Merge with new fields
        $all_fields = array_unique(array_merge($existing_slugs, $field_slugs));
        
        // Save group fields (as comma-separated string with leading/trailing commas)
        update_post_meta($group_id, '_wp_types_group_fields', ',' . implode(',', $all_fields) . ',');
        
        return true;
    }
    
    /**
     * Ensure field group is assigned to listing post type
     */
    private function ensure_group_assigned_to_listing($group_id) {
        // Get existing post types from meta
        $existing_types = get_post_meta($group_id, '_wp_types_group_post_types', true);
        
        if (empty($existing_types)) {
            $existing_types = array();
        } elseif (is_string($existing_types)) {
            $existing_types = array_filter(array_map('trim', explode(',', $existing_types)));
        }
        
        // Add listing if not already assigned
        if (!in_array('listing', $existing_types)) {
            $existing_types[] = 'listing';
            // Convert to comma-separated string format (Toolset requires string, not array)
            $types_string = ',' . implode(',', array_filter($existing_types)) . ',';
            update_post_meta($group_id, '_wp_types_group_post_types', $types_string);
        }
        
        return true;
    }
    
    /**
     * Create all available units fields
     */
    public function create_fields() {
        if (!function_exists('wpcf_admin_fields_save_field')) {
            return array(
                'success' => false,
                'message' => 'Toolset Types plugin is not active. Please activate Toolset Types to create fields.',
                'created' => 0,
            );
        }
        
        $created = 0;
        $errors = array();
        
        // Create all fields
        foreach ($this->fields as $field_slug => $field_data) {
            if (!$this->field_exists($field_slug)) {
                if ($this->create_field($field_slug, $field_data)) {
                    $created++;
                } else {
                    $errors[] = "Failed to create field: {$field_data['name']}";
                }
            }
        }
        
        // Get or create the field group
        $group = $this->get_or_create_availability_group();
        if ($group && isset($group['id'])) {
            $field_slugs = array_keys($this->fields);
            $this->add_fields_to_group($group['id'], $field_slugs);
            $this->ensure_group_assigned_to_listing($group['id']);
        } else {
            $errors[] = 'Could not create or find "Current Rental Availability" field group. Fields were created but not assigned to a group.';
        }
        
        return array(
            'success' => true,
            'created' => $created,
            'errors' => $errors,
        );
    }
    
    /**
     * Check if all fields exist
     */
    public function fields_exist() {
        foreach ($this->fields as $field_slug => $field_data) {
            if (!$this->field_exists($field_slug)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get availability data for a listing
     * Returns array of availability entries
     */
    public static function get_availability_data($post_id) {
        $availability = array();
        
        // Get all repetitive field values
        $property_ids = get_post_meta($post_id, 'wpcf-availability-property', false);
        $towns = get_post_meta($post_id, 'wpcf-availability-town', false);
        $bedrooms = get_post_meta($post_id, 'wpcf-availability-bedrooms', false);
        $bathrooms = get_post_meta($post_id, 'wpcf-availability-bathrooms', false);
        $rents = get_post_meta($post_id, 'wpcf-availability-rent', false);
        $min_incomes = get_post_meta($post_id, 'wpcf-availability-minimum-income', false);
        $income_limits = get_post_meta($post_id, 'wpcf-availability-income-limit', false);
        $concessions_all = get_post_meta($post_id, 'wpcf-availability-concessions', false); // All concessions for all entries
        $concessions_counts = get_post_meta($post_id, 'wpcf-availability-concessions-count', false); // Count of concessions per entry
        $types = get_post_meta($post_id, 'wpcf-availability-type', false);
        $units_available = get_post_meta($post_id, 'wpcf-availability-units-available', false);
        $accessible_units = get_post_meta($post_id, 'wpcf-availability-accessible-units', false);
        $view_apply_links = get_post_meta($post_id, 'wpcf-availability-view-apply', false);
        
        // Get the maximum count to iterate
        $max_count = max(
            count($property_ids),
            count($towns),
            count($bedrooms),
            count($bathrooms),
            count($rents),
            count($min_incomes),
            count($income_limits),
            count($types),
            count($units_available),
            count($accessible_units),
            count($view_apply_links)
        );
        
        // Group concessions by entry index using count markers
        // Concessions are saved sequentially: all concessions for entry 0, then all for entry 1, etc.
        // We use count markers to know how many concessions belong to each entry
        $concessions_by_entry = array();
        $concession_pointer = 0;
        
        // Match concessions to entries using count markers
        for ($i = 0; $i < $max_count; $i++) {
            $concessions_by_entry[$i] = array();
            // Get count for this entry
            $count = isset($concessions_counts[$i]) ? intval($concessions_counts[$i]) : 0;
            // Get concessions for this entry based on count
            if ($count > 0 && $concession_pointer < count($concessions_all)) {
                for ($j = 0; $j < $count && $concession_pointer < count($concessions_all); $j++) {
                    $concessions_by_entry[$i][] = $concessions_all[$concession_pointer];
                    $concession_pointer++;
                }
            }
        }
        
        // Build array of availability entries
        for ($i = 0; $i < $max_count; $i++) {
            $property_id = isset($property_ids[$i]) ? $property_ids[$i] : $post_id; // Fallback to post_id if not set
            $property_name = '';
            if ($property_id) {
                $property_post = get_post($property_id);
                $property_name = $property_post ? $property_post->post_title : '';
            }
            
            // Get concessions for this entry
            $entry_concessions = isset($concessions_by_entry[$i]) ? $concessions_by_entry[$i] : array();
            
            $availability[] = array(
                'property_id' => $property_id,
                'property' => $property_name,
                'town' => isset($towns[$i]) ? $towns[$i] : '',
                'bedrooms' => isset($bedrooms[$i]) ? $bedrooms[$i] : '',
                'bathrooms' => isset($bathrooms[$i]) ? $bathrooms[$i] : '',
                'rent' => isset($rents[$i]) ? $rents[$i] : '',
                'minimum_income' => isset($min_incomes[$i]) ? $min_incomes[$i] : '',
                'income_limit' => isset($income_limits[$i]) ? $income_limits[$i] : '',
                'concessions' => $entry_concessions, // Array of concession term IDs
                'type' => isset($types[$i]) ? $types[$i] : '',
                'units_available' => isset($units_available[$i]) ? $units_available[$i] : '',
                'accessible_units' => isset($accessible_units[$i]) ? $accessible_units[$i] : '',
                'view_apply' => isset($view_apply_links[$i]) ? $view_apply_links[$i] : '',
            );
        }
        
        return $availability;
    }
    
    /**
     * Get all availability entries from all rental properties
     * Returns array of all entries with property info
     */
    public static function get_all_availability_entries() {
        global $wpdb;
        
        // Get all post IDs that have availability data
        $post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
            'wpcf-availability-bedrooms'
        ));
        
        $all_entries = array();
        
        foreach ($post_ids as $post_id) {
            $entries = self::get_availability_data($post_id);
            foreach ($entries as $entry) {
                $entry['source_post_id'] = $post_id; // Track which post this came from
                $all_entries[] = $entry;
            }
        }
        
        return $all_entries;
    }
    
    /**
     * Format availability data for display
     * Returns string like "2 Studio, 3 One Bedroom, 1 Two Bedroom available"
     */
    public static function format_availability_display($post_id) {
        $availability = self::get_availability_data($post_id);
        
        if (empty($availability)) {
            return '';
        }
        
        // Group by unit size and sum units
        $units_by_size = array();
        foreach ($availability as $entry) {
            if (!empty($entry['bedrooms']) && !empty($entry['units_available']) && intval($entry['units_available']) > 0) {
                $size = $entry['bedrooms'];
                if (!isset($units_by_size[$size])) {
                    $units_by_size[$size] = 0;
                }
                $units_by_size[$size] += intval($entry['units_available']);
            }
        }
        
        if (empty($units_by_size)) {
            return '';
        }
        
        $parts = array();
        foreach ($units_by_size as $size => $count) {
            $parts[] = $count . ' ' . $size;
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Get total available units count
     */
    public static function get_total_available($post_id) {
        $availability = self::get_availability_data($post_id);
        
        $total = 0;
        foreach ($availability as $entry) {
            if (!empty($entry['units_available'])) {
                // Extract number from text like "1 (ADA-M Unit)" or "2"
                $units_text = $entry['units_available'];
                if (preg_match('/(\d+)/', $units_text, $matches)) {
                    $total += intval($matches[1]);
                } else {
                    $total += intval($units_text);
                }
            }
        }
        
        return $total;
    }
    
    /**
     * Parse availability data into grouped format for filtering
     * Returns array like: [['unit_type' => 'Studio', 'count' => 2], ['unit_type' => '1-Bedroom', 'count' => 3]]
     */
    public static function parse_availability_data($post_id) {
        $availability = self::get_availability_data($post_id);
        
        if (empty($availability)) {
            return array();
        }
        
        // Group by unit type and sum counts
        $grouped = array();
        foreach ($availability as $entry) {
            if (!empty($entry['bedrooms']) && !empty($entry['units_available'])) {
                $unit_type = $entry['bedrooms'];
                $units_text = $entry['units_available'];
                
                // Extract number from text like "1 (ADA-M Unit)" or "2"
                $count = 0;
                if (preg_match('/(\d+)/', $units_text, $matches)) {
                    $count = intval($matches[1]);
                } else {
                    $count = intval($units_text);
                }
                
                if ($count > 0) {
                    if (!isset($grouped[$unit_type])) {
                        $grouped[$unit_type] = array(
                            'unit_type' => $unit_type,
                            'count' => 0,
                            'accessible' => array(),
                        );
                    }
                    $grouped[$unit_type]['count'] += $count;
                    
                    // Collect accessible units info
                    if (!empty($entry['accessible_units']) && $entry['accessible_units'] !== '0') {
                        if (!in_array($entry['accessible_units'], $grouped[$unit_type]['accessible'])) {
                            $grouped[$unit_type]['accessible'][] = $entry['accessible_units'];
                        }
                    }
                }
            }
        }
        
        return array_values($grouped);
    }
}
