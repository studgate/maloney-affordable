<?php
/**
 * Toolset Types Integration
 * Helps assign existing field groups to the new 'listing' post type
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Toolset_Integration {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_page'));
        // Hook into admin_init to redirect before any output is sent
        add_action('admin_init', array($this, 'maybe_redirect_assign_groups'));
    }
    
    /**
     * Check if current user is the developer
     */
    private function is_developer() {
        $current_user = wp_get_current_user();
        
        // Developer usernames
        $developer_usernames = array('ralph', 'responseab-oct25');
        
        // Developer emails
        $developer_emails = array('ralph@responsab.com', 'ralph@responseab.com');
        
        if (in_array($current_user->user_login, $developer_usernames, true)) {
            return true;
        }
        
        if (in_array($current_user->user_email, $developer_emails, true)) {
            return true;
        }
        
        return false;
    }
    
    public function add_admin_page() {
        // Only show to developer
        if (!$this->is_developer()) {
            return;
        }
        
        add_submenu_page(
            'edit.php?post_type=listing',
            __('Assign Toolset Field Groups', 'maloney-listings'),
            __('Assign Field Groups', 'maloney-listings'),
            'manage_options',
            'assign-toolset-groups',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Redirect before any output is sent (using load-{hook} action)
     */
    public function maybe_redirect_assign_groups() {
        // Check if we're on the assign-toolset-groups page
        if (isset($_GET['page']) && $_GET['page'] === 'assign-toolset-groups' && isset($_GET['post_type']) && $_GET['post_type'] === 'listing') {
            // Check if user is developer
            if (!$this->is_developer()) {
                wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
            }
            
            // Redirect users directly to Toolset's native assignment page
            // This avoids any potential issues with Toolset's internal metadata handling
            wp_safe_redirect(admin_url('admin.php?page=types-custom-fields'));
            exit;
        }
    }
    
    public function render_admin_page() {
        // This should not be reached if redirect works, but keep as fallback
        // Check if user is developer
        if (!$this->is_developer()) {
            wp_die(__('You do not have permission to access this page.', 'maloney-listings'));
        }
        
        // Redirect users directly to Toolset's native assignment page
        // This avoids any potential issues with Toolset's internal metadata handling
        wp_safe_redirect(admin_url('admin.php?page=types-custom-fields'));
        exit;
    }
}
