<?php
/**
 * Vacancy Notification System
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Vacancy_Notifications {
    
    public function __construct() {
        add_action('save_post_listing', array($this, 'check_status_change'), 10, 3);
        add_action('wp_ajax_submit_vacancy_notification', array($this, 'submit_notification'));
        add_action('wp_ajax_nopriv_submit_vacancy_notification', array($this, 'submit_notification'));
    }
    
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vacancy_notifications';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            listing_id BIGINT(20) UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            notified_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX listing_id (listing_id),
            INDEX email (email),
            INDEX status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function check_status_change($post_id, $post, $update) {
        if ($post->post_type !== 'listing' || !$update) {
            return;
        }
        
        // Check if status changed to "Available"
        $status_terms = wp_get_post_terms($post_id, 'listing_status');
        if ($status_terms && !is_wp_error($status_terms)) {
            $current_status = $status_terms[0]->slug;
            
            if ($current_status === 'available') {
                $this->notify_subscribers($post_id);
            }
        }
    }
    
    private function notify_subscribers($listing_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vacancy_notifications';
        
        $subscribers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE listing_id = %d AND status = 'pending'",
            $listing_id
        ));
        
        if (empty($subscribers)) {
            return;
        }
        
        $listing = get_post($listing_id);
        $listing_url = get_permalink($listing_id);
        
        foreach ($subscribers as $subscriber) {
            $subject = 'Listing Now Available: ' . $listing->post_title;
            $message = $this->get_notification_email_body($listing, $subscriber, $listing_url);
            
            $sent = wp_mail(
                $subscriber->email,
                $subject,
                $message,
                array('Content-Type: text/html; charset=UTF-8')
            );
            
            if ($sent) {
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'notified',
                        'notified_at' => current_time('mysql'),
                    ),
                    array('id' => $subscriber->id)
                );
            }
        }
    }
    
    private function get_notification_email_body($listing, $subscriber, $listing_url) {
        $name = !empty($subscriber->name) ? $subscriber->name : 'there';
        
        $message = '<html><body>';
        $message .= '<h2>Listing Now Available!</h2>';
        $message .= '<p>Hello ' . esc_html($name) . ',</p>';
        $message .= '<p>A listing you requested to be notified about is now available:</p>';
        $message .= '<h3>' . esc_html($listing->post_title) . '</h3>';
        $message .= '<p><a href="' . esc_url($listing_url) . '">View Listing Details</a></p>';
        $message .= '<p>Best regards,<br>Maloney Affordable</p>';
        $message .= '</body></html>';
        
        return $message;
    }
    
    public function submit_notification() {
        check_ajax_referer('maloney_listings_nonce', 'nonce');
        
        $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        
        if (!$listing_id || !$email) {
            wp_send_json_error('Listing ID and email are required');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'vacancy_notifications';
        
        // Check if already subscribed
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE listing_id = %d AND email = %s",
            $listing_id,
            $email
        ));
        
        if ($existing) {
            wp_send_json_error('You are already subscribed to notifications for this listing');
        }
        
        // Insert notification
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'listing_id' => $listing_id,
                'email' => $email,
                'name' => $name,
                'phone' => $phone,
                'status' => 'pending',
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($inserted) {
            wp_send_json_success('You will be notified when this listing becomes available');
        } else {
            wp_send_json_error('Failed to submit notification');
        }
    }
}

