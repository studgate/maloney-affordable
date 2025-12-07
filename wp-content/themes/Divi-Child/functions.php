<?php
/**
 * Divi Child Theme Functions
 * Maloney Affordable - Listing System
 */

// Define plugin directory constant if not already defined (for page-listings.php template)
if (!defined('MALONEY_LISTINGS_PLUGIN_DIR')) {
    // Try to find the plugin directory
    $plugin_path = WP_PLUGIN_DIR . '/maloney-listings';
    if (file_exists($plugin_path)) {
        define('MALONEY_LISTINGS_PLUGIN_DIR', trailingslashit($plugin_path));
    }
}

// Enqueue parent theme styles
function divi_child_enqueue_styles() {
    $parent_style = 'divi-style';
    
    wp_enqueue_style($parent_style, get_template_directory_uri() . '/style.css');
    wp_enqueue_style('divi-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array($parent_style),
        wp_get_theme()->get('Version')
    );
}
add_action('wp_enqueue_scripts', 'divi_child_enqueue_styles');

