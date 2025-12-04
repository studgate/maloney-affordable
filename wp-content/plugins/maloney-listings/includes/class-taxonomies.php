<?php
/**
 * Register Taxonomies
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Taxonomies {
    
    public function __construct() {
        add_action('init', array($this, 'register_taxonomies'));
    }
    
    public function register_taxonomies() {
        $this->register_listing_type_taxonomy();
        $this->register_listing_status_taxonomy();
        $this->register_amenities_taxonomy();
        $this->register_income_limit_taxonomy();
        $this->register_concessions_taxonomy();
        $this->register_property_accessibility_taxonomy();
    }
    
    private function register_listing_type_taxonomy() {
        $labels = array(
            'name'              => _x('Listing Types', 'taxonomy general name', 'maloney-listings'),
            'singular_name'     => _x('Listing Type', 'taxonomy singular name', 'maloney-listings'),
            'search_items'      => __('Search Listing Types', 'maloney-listings'),
            'all_items'         => __('All Listing Types', 'maloney-listings'),
            'parent_item'       => __('Parent Listing Type', 'maloney-listings'),
            'parent_item_colon' => __('Parent Listing Type:', 'maloney-listings'),
            'edit_item'         => __('Edit Listing Type', 'maloney-listings'),
            'update_item'       => __('Update Listing Type', 'maloney-listings'),
            'add_new_item'      => __('Add New Listing Type', 'maloney-listings'),
            'new_item_name'     => __('New Listing Type Name', 'maloney-listings'),
            'menu_name'         => __('Listing Types', 'maloney-listings'),
        );
        
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'listing-type'),
            'show_in_rest'      => true,
        );
        
        register_taxonomy('listing_type', array('listing'), $args);
        
        // Register default terms
        $this->register_default_listing_types();
    }
    
    private function register_listing_status_taxonomy() {
        $labels = array(
            'name'              => _x('Listing Status', 'taxonomy general name', 'maloney-listings'),
            'singular_name'     => _x('Listing Status', 'taxonomy singular name', 'maloney-listings'),
            'search_items'      => __('Search Statuses', 'maloney-listings'),
            'all_items'         => __('All Statuses', 'maloney-listings'),
            'edit_item'         => __('Edit Status', 'maloney-listings'),
            'update_item'       => __('Update Status', 'maloney-listings'),
            'add_new_item'      => __('Add New Status', 'maloney-listings'),
            'new_item_name'     => __('New Status Name', 'maloney-listings'),
            'menu_name'         => __('Listing Status', 'maloney-listings'),
        );
        
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'listing-status'),
            'show_in_rest'      => true,
        );
        
        register_taxonomy('listing_status', array('listing'), $args);
        
        // Register default statuses
        $this->register_default_listing_statuses();
    }
    
    // Location taxonomy removed (not used). Town filter remains via city meta.
    
    private function register_amenities_taxonomy() {
        $labels = array(
            'name'              => _x('Amenities', 'taxonomy general name', 'maloney-listings'),
            'singular_name'     => _x('Amenity', 'taxonomy singular name', 'maloney-listings'),
            'search_items'      => __('Search Amenities', 'maloney-listings'),
            'all_items'         => __('All Amenities', 'maloney-listings'),
            'edit_item'         => __('Edit Amenity', 'maloney-listings'),
            'update_item'       => __('Update Amenity', 'maloney-listings'),
            'add_new_item'      => __('Add New Amenity', 'maloney-listings'),
            'new_item_name'     => __('New Amenity Name', 'maloney-listings'),
            'menu_name'         => __('Amenities', 'maloney-listings'),
        );
        
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'amenity'),
            'show_in_rest'      => true,
        );
        
        register_taxonomy('amenities', array('listing'), $args);
        
        // Register default amenities
        $this->register_default_amenities();
    }
    
    private function register_default_amenities() {
        $amenities = array(
            'Air Conditioning',
            'Dishwasher',
            'Gym',
            'Laundry Facilities',
            'Parking',
            'Pool',
        );
        
        foreach ($amenities as $amenity) {
            if (!term_exists($amenity, 'amenities')) {
                wp_insert_term($amenity, 'amenities');
            }
        }
    }
    
    private function register_income_limit_taxonomy() {
        $labels = array(
            'name'              => _x('Income Limits', 'taxonomy general name', 'maloney-listings'),
            'singular_name'     => _x('Income Limit', 'taxonomy singular name', 'maloney-listings'),
            'search_items'      => __('Search Income Limits', 'maloney-listings'),
            'all_items'         => __('All Income Limits', 'maloney-listings'),
            'edit_item'         => __('Edit Income Limit', 'maloney-listings'),
            'update_item'       => __('Update Income Limit', 'maloney-listings'),
            'add_new_item'      => __('Add New Income Limit', 'maloney-listings'),
            'new_item_name'     => __('New Income Limit Name', 'maloney-listings'),
            'menu_name'         => __('Income Limits', 'maloney-listings'),
        );
        
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'income-limit'),
            'show_in_rest'      => true,
        );
        
        register_taxonomy('income_limit', array('listing'), $args);
        
        // Register default income limits
        $this->register_default_income_limits();
    }
    
    private function register_default_income_limits() {
        $income_limits = array('50%', '60%', '70%', '80%', '90%', '100%', '110%', '120%', '150%');
        
        foreach ($income_limits as $limit) {
            if (!term_exists($limit, 'income_limit')) {
                wp_insert_term($limit, 'income_limit');
            }
        }
    }
    
    private function register_default_listing_types() {
        $types = array('Condo', 'Rental');
        
        foreach ($types as $type) {
            if (!term_exists($type, 'listing_type')) {
                wp_insert_term($type, 'listing_type');
            }
        }
    }
    
    private function register_default_listing_statuses() {
        $statuses = array(
            'Available' => array('description' => 'Property is currently available'),
            'Waitlist' => array('description' => 'Property has a waitlist'),
            'Not Available' => array('description' => 'Property is not available'),
            'Waitlist Open' => array('description' => 'Waitlist is open'),
            'Waitlist Short' => array('description' => 'Short waitlist'),
            'Waitlist Closed' => array('description' => 'Waitlist is closed'),
            'Waitlist Unknown' => array('description' => 'Waitlist status unknown'),
        );
        
        foreach ($statuses as $status => $args) {
            if (!term_exists($status, 'listing_status')) {
                wp_insert_term($status, 'listing_status', $args);
            }
        }
    }
    
    private function register_concessions_taxonomy() {
        $labels = array(
            'name'              => _x('Concessions', 'taxonomy general name', 'maloney-listings'),
            'singular_name'     => _x('Concession', 'taxonomy singular name', 'maloney-listings'),
            'search_items'      => __('Search Concessions', 'maloney-listings'),
            'all_items'         => __('All Concessions', 'maloney-listings'),
            'edit_item'         => __('Edit Concession', 'maloney-listings'),
            'update_item'       => __('Update Concession', 'maloney-listings'),
            'add_new_item'      => __('Add New Concession', 'maloney-listings'),
            'new_item_name'     => __('New Concession Name', 'maloney-listings'),
            'menu_name'         => __('Concessions', 'maloney-listings'),
        );
        
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'concession'),
            'show_in_rest'      => true,
        );
        
        register_taxonomy('concessions', array('listing'), $args);
        
        // Register default concessions
        $this->register_default_concessions();
    }
    
    private function register_default_concessions() {
        $concessions = array('1 Month free');
        
        foreach ($concessions as $concession) {
            if (!term_exists($concession, 'concessions')) {
                wp_insert_term($concession, 'concessions');
            }
        }
    }
    
    private function register_property_accessibility_taxonomy() {
        $labels = array(
            'name'              => _x('Property Accessibility', 'taxonomy general name', 'maloney-listings'),
            'singular_name'     => _x('Property Accessibility', 'taxonomy singular name', 'maloney-listings'),
            'search_items'      => __('Search Property Accessibility', 'maloney-listings'),
            'all_items'         => __('All Property Accessibility', 'maloney-listings'),
            'edit_item'         => __('Edit Property Accessibility', 'maloney-listings'),
            'update_item'       => __('Update Property Accessibility', 'maloney-listings'),
            'add_new_item'      => __('Add New Property Accessibility', 'maloney-listings'),
            'new_item_name'     => __('New Property Accessibility Name', 'maloney-listings'),
            'menu_name'         => __('Property Accessibility', 'maloney-listings'),
        );
        
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'property-accessibility'),
            'show_in_rest'      => true,
        );
        
        register_taxonomy('property_accessibility', array('listing'), $args);
        
        // Register default property accessibility terms
        $this->register_default_property_accessibility();
    }
    
    private function register_default_property_accessibility() {
        $accessibility_terms = array(
            'Elevator',
            'Step-Free Entrance',
            'Wheelchair Access',
            'Roll-in Showers',
        );
        
        foreach ($accessibility_terms as $term) {
            if (!term_exists($term, 'property_accessibility')) {
                wp_insert_term($term, 'property_accessibility');
            }
        }
    }
}
