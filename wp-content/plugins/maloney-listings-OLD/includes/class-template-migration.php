<?php
/**
 * Template Migration for Toolset Content Templates
 * 
 * Migrates conditional templates from old post types (condominiums, rental-properties)
 * to the new unified listing post type with proper taxonomy conditions
 * 
 * Developer: Ralph Francois
 * Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Template_Migration {
    
    /**
     * Migrate conditional templates to listing post type
     */
    public static function migrate_conditional_templates() {
        global $WPV_settings;
        
        if (!class_exists('WPV_Settings')) {
            return false;
        }
        
        if (!isset($WPV_settings)) {
            $WPV_settings = WPV_Settings::get_instance();
        }
        
        $conditions_key = 'views_template_conditions_for_listing';
        $listing_conditions = array();
        
        // Get rental-properties conditions
        $rental_conditions_key = 'views_template_conditions_for_rental-properties';
        $rental_conditions = isset($WPV_settings[$rental_conditions_key]) ? $WPV_settings[$rental_conditions_key] : array();
        
        // Get condominiums conditions
        $condo_conditions_key = 'views_template_conditions_for_condominiums';
        $condo_conditions = isset($WPV_settings[$condo_conditions_key]) ? $WPV_settings[$condo_conditions_key] : array();
        
        $priority = 0;
        
        // Migrate rental conditions - add listing_type = Rental condition
        foreach ($rental_conditions as $key => $condition) {
            if (!isset($condition['conditions']) || !isset($condition['content_template_id'])) {
                continue;
            }
            
            $new_condition = $condition;
            
            // Add listing_type taxonomy condition
            $listing_type_condition = array(
                'firstArgument' => array(
                    'source' => array('value' => 'taxonomy'),
                    'value' => array(
                        'label' => 'Listing Type',
                        'value' => 'listing_type',
                        'type' => 'taxonomy'
                    )
                ),
                'operator' => array(
                    'label' => '=',
                    'value' => 'eq'
                ),
                'secondArgument' => array(
                    'source' => array('value' => 'taxonomy-value'),
                    'value' => array(
                        'value' => 'rental',
                        'label' => 'Rental'
                    )
                )
            );
            
            // Add the listing_type condition to the beginning of conditions array
            if (!isset($new_condition['conditions']['conditions']) || !is_array($new_condition['conditions']['conditions'])) {
                $new_condition['conditions']['conditions'] = array();
            }
            
            array_unshift($new_condition['conditions']['conditions'], $listing_type_condition);
            
            // Update parsed conditions
            $parsed = isset($new_condition['parsed_conditions']) ? $new_condition['parsed_conditions'] : '';
            $new_condition['parsed_conditions'] = "  ( ( 'listing_type' eq 'rental' ) AND " . trim($parsed) . " ) ";
            
            // Update priority and timestamp
            $new_condition['priority'] = $priority++;
            $new_condition['updated_at'] = time();
            
            $listing_conditions[] = $new_condition;
        }
        
        // Migrate condo conditions - add listing_type = Condo condition
        foreach ($condo_conditions as $key => $condition) {
            if (!isset($condition['conditions']) || !isset($condition['content_template_id'])) {
                continue;
            }
            
            $new_condition = $condition;
            
            // Add listing_type taxonomy condition
            $listing_type_condition = array(
                'firstArgument' => array(
                    'source' => array('value' => 'taxonomy'),
                    'value' => array(
                        'label' => 'Listing Type',
                        'value' => 'listing_type',
                        'type' => 'taxonomy'
                    )
                ),
                'operator' => array(
                    'label' => '=',
                    'value' => 'eq'
                ),
                'secondArgument' => array(
                    'source' => array('value' => 'taxonomy-value'),
                    'value' => array(
                        'value' => 'condo',
                        'label' => 'Condo'
                    )
                )
            );
            
            // Add the listing_type condition to the beginning of conditions array
            if (!isset($new_condition['conditions']['conditions']) || !is_array($new_condition['conditions']['conditions'])) {
                $new_condition['conditions']['conditions'] = array();
            }
            
            array_unshift($new_condition['conditions']['conditions'], $listing_type_condition);
            
            // Update parsed conditions
            $parsed = isset($new_condition['parsed_conditions']) ? $new_condition['parsed_conditions'] : '';
            $new_condition['parsed_conditions'] = "  ( ( 'listing_type' eq 'condo' ) AND " . trim($parsed) . " ) ";
            
            // Update priority and timestamp
            $new_condition['priority'] = $priority++;
            $new_condition['updated_at'] = time();
            
            $listing_conditions[] = $new_condition;
        }
        
        // Convert to indexed array format that Toolset expects (numeric keys starting from 0)
        $formatted_conditions = array();
        $index = 0;
        foreach ($listing_conditions as $condition) {
            $formatted_conditions[$index] = $condition;
            $index++;
        }
        
        // Save to WPV settings
        $WPV_settings[$conditions_key] = $formatted_conditions;
        $WPV_settings->save();
        
        return true;
    }
    
    /**
     * Setup default template for listing post type if not set
     */
    public static function setup_default_template() {
        global $WPV_settings;
        
        if (!class_exists('WPV_Settings')) {
            return false;
        }
        
        if (!isset($WPV_settings)) {
            $WPV_settings = WPV_Settings::get_instance();
        }
        
        $default_key = 'views_template_for_listing';
        
        // Check if default template is already set
        if (isset($WPV_settings[$default_key]) && $WPV_settings[$default_key] > 0) {
            return $WPV_settings[$default_key];
        }
        
        // Try to find the existing "Template for Listings" (ID: 13317)
        $default_template_id = 13317;
        $template = get_post($default_template_id);
        
        if ($template && $template->post_type === 'view-template') {
            $WPV_settings[$default_key] = $default_template_id;
            $WPV_settings->save();
            return $default_template_id;
        }
        
        return false;
    }
}

