<?php
/**
 * Register Custom Post Types
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Post_Types {
    
    public function __construct() {
        add_action('init', array($this, 'register_listing_post_type'));
        add_action('init', array($this, 'set_block_editor_mode'), 20);
        add_action('init', array($this, 'add_listing_rewrite_rules'), 20);
        
        // Force classic editor for listing and legacy post types
        add_filter('use_block_editor_for_post_type', array($this, 'force_classic_editor'), 10, 2);
        add_filter('use_block_editor_for_post', array($this, 'force_classic_editor_for_post'), 10, 2);
        
        // Custom permalink structure based on listing type
        add_filter('post_type_link', array($this, 'custom_listing_permalink'), 10, 2);
    }
    
    public function register_listing_post_type() {
        $labels = array(
            'name'                  => _x('Listings', 'Post Type General Name', 'maloney-listings'),
            'singular_name'         => _x('Listing', 'Post Type Singular Name', 'maloney-listings'),
            'menu_name'             => __('Listings', 'maloney-listings'),
            'name_admin_bar'        => __('Listing', 'maloney-listings'),
            'archives'              => __('Listing Archives', 'maloney-listings'),
            'attributes'            => __('Listing Attributes', 'maloney-listings'),
            'parent_item_colon'     => __('Parent Listing:', 'maloney-listings'),
            'all_items'             => __('All Listings', 'maloney-listings'),
            'add_new_item'          => __('Add New Listing', 'maloney-listings'),
            'add_new'               => __('Add New', 'maloney-listings'),
            'new_item'              => __('New Listing', 'maloney-listings'),
            'edit_item'             => __('Edit Listing', 'maloney-listings'),
            'update_item'           => __('Update Listing', 'maloney-listings'),
            'view_item'             => __('View Listing', 'maloney-listings'),
            'view_items'            => __('View Listings', 'maloney-listings'),
            'search_items'          => __('Search Listing', 'maloney-listings'),
            'not_found'             => __('Not found', 'maloney-listings'),
            'not_found_in_trash'    => __('Not found in Trash', 'maloney-listings'),
            'featured_image'        => __('Listing Image', 'maloney-listings'),
            'set_featured_image'    => __('Set listing image', 'maloney-listings'),
            'remove_featured_image' => __('Remove listing image', 'maloney-listings'),
            'use_featured_image'    => __('Use as listing image', 'maloney-listings'),
            'insert_into_item'      => __('Insert into listing', 'maloney-listings'),
            'uploaded_to_this_item' => __('Uploaded to this listing', 'maloney-listings'),
            'items_list'            => __('Listings list', 'maloney-listings'),
            'items_list_navigation' => __('Listings list navigation', 'maloney-listings'),
            'filter_items_list'     => __('Filter listings list', 'maloney-listings'),
        );
        
        $args = array(
            'label'                 => __('Listing', 'maloney-listings'),
            'description'           => __('Property listings for condos and rentals', 'maloney-listings'),
            'labels'                => $labels,
            'supports'              => array('title', 'thumbnail', 'excerpt', 'custom-fields'),
            'taxonomies'            => array('listing_type', 'listing_status', 'amenities', 'income_limit', 'concessions', 'property_accessibility'),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 20,
            'menu_icon'             => 'dashicons-building',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
            'rewrite'               => array('slug' => 'listings', 'with_front' => false),
        );
        
        register_post_type('listing', $args);
    }
    
    /**
     * Set block editor mode for listing post type
     * Works with Toolset Types if available
     */
    public function set_block_editor_mode() {
        // If Toolset Types is managing this post type, set editor mode via their API
        if (class_exists('Toolset_Post_Type_Repository')) {
            $post_type_repo = Toolset_Post_Type_Repository::get_instance();
            $post_type = $post_type_repo->get('listing');
            
            if ($post_type && method_exists($post_type, 'set_editor_mode')) {
                try {
                    // Use Toolset's EditorMode constant
                    if (class_exists('\OTGS\Toolset\Common\PostType\EditorMode')) {
                        $post_type->set_editor_mode(\OTGS\Toolset\Common\PostType\EditorMode::BLOCK);
                    }
                } catch (Exception $e) {
                    // Post type might not be managed by Types, that's okay
                }
            }
        }
        
        // Ensure classic editor by disabling REST editor for listing
        global $wp_post_types;
        if (isset($wp_post_types['listing'])) {
            $wp_post_types['listing']->show_in_rest = false;
        }
    }
    
    /**
     * Force classic editor
     */
    public function force_classic_editor($use_block_editor, $post_type) {
        if (in_array($post_type, array('listing','condominiums','rental-properties'), true)) {
            return false; // Classic editor
        }
        return $use_block_editor;
    }
    
    /**
     * Force classic editor for listing posts
     */
    public function force_classic_editor_for_post($use_block_editor, $post) {
        if ($post && in_array($post->post_type, array('listing','condominiums','rental-properties'), true)) {
            return false;
        }
        return $use_block_editor;
    }
    
    /**
     * Add rewrite rules for custom permalink structure
     */
    public function add_listing_rewrite_rules() {
        // Add rewrite rules for rental-properties and condominiums
        add_rewrite_rule(
            '^rental-properties/([^/]+)/?$',
            'index.php?post_type=listing&name=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^condominiums/([^/]+)/?$',
            'index.php?post_type=listing&name=$matches[1]',
            'top'
        );
    }
    
    /**
     * Custom permalink structure for listings based on type
     * Rentals: /rental-properties/{slug}
     * Condos: /condominiums/{slug}
     */
    public function custom_listing_permalink($post_link, $post) {
        if ($post->post_type !== 'listing') {
            return $post_link;
        }
        
        // Get listing type
        $listing_types = get_the_terms($post->ID, 'listing_type');
        
        if ($listing_types && !is_wp_error($listing_types)) {
            $type_slug = strtolower($listing_types[0]->slug);
            
            // Determine the base slug based on listing type
            if ($type_slug === 'rental' || $type_slug === 'rental-properties') {
                $base_slug = 'rental-properties';
            } elseif ($type_slug === 'condo' || $type_slug === 'condominium' || $type_slug === 'condominiums') {
                $base_slug = 'condominiums';
            } else {
                // Default to listing if type doesn't match
                return $post_link;
            }
            
            // Build new permalink
            $post_link = home_url('/' . $base_slug . '/' . $post->post_name . '/');
        }
        
        return $post_link;
    }
}
