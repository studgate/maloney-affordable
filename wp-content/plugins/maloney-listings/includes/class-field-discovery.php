<?php
/**
 * Field Discovery Tool
 * Run this to see what fields exist for existing post types
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Maloney_Listings_Field_Discovery {
    
    public static function discover_fields() {
        $discovery = array(
            'post_types' => array(),
            'fields' => array(),
        );
        
        // Check for common post type slugs
        $possible_types = array('condominiums', 'condominium', 'condo', 'rental-properties', 'rental-property', 'rental', 'property');
        
        foreach ($possible_types as $post_type) {
            if (post_type_exists($post_type)) {
                $posts = get_posts(array(
                    'post_type' => $post_type,
                    'posts_per_page' => 10,
                    'post_status' => 'any',
                ));
                
                if (!empty($posts)) {
                    $discovery['post_types'][$post_type] = array(
                        'count' => wp_count_posts($post_type),
                        'sample_posts' => array(),
                    );
                    
                    // Get fields from first post
                    $sample_post = $posts[0];
                    $all_meta = get_post_meta($sample_post->ID);
                    
                    $discovery['post_types'][$post_type]['fields'] = array();
                    
                    foreach ($all_meta as $key => $values) {
                        // Skip hidden fields (starting with _)
                        if (strpos($key, '_') === 0 && strpos($key, '_listing_') !== 0) {
                            continue;
                        }
                        
                        $value = maybe_unserialize($values[0]);
                        $discovery['post_types'][$post_type]['fields'][$key] = array(
                            'type' => gettype($value),
                            'sample_value' => is_array($value) ? 'Array' : substr($value, 0, 50),
                        );
                    }
                    
                    // Get taxonomies
                    $taxonomies = get_object_taxonomies($post_type);
                    $discovery['post_types'][$post_type]['taxonomies'] = array();
                    
                    foreach ($taxonomies as $taxonomy) {
                        $terms = get_the_terms($sample_post->ID, $taxonomy);
                        if ($terms && !is_wp_error($terms)) {
                            $discovery['post_types'][$post_type]['taxonomies'][$taxonomy] = array_map(function($term) {
                                return $term->name;
                            }, $terms);
                        }
                    }
                }
            }
        }
        
        return $discovery;
    }
    
    public static function display_discovery_results() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }
        
        $discovery = self::discover_fields();
        
        ?>
        <div class="wrap">
            <h1>Field Discovery Results</h1>
            <p>This shows what post types and fields currently exist in your system.</p>
            
            <?php if (empty($discovery['post_types'])) : ?>
                <div class="notice notice-info">
                    <p>No existing post types found. Looking for: condominium, condo, rental, rental-property, property</p>
                    <p>If your post types have different names, please update the discovery script.</p>
                </div>
            <?php else : ?>
                <?php foreach ($discovery['post_types'] as $post_type => $data) : ?>
                    <div class="post-type-discovery">
                        <h2>Post Type: <?php echo esc_html($post_type); ?></h2>
                        <p><strong>Total Posts:</strong> 
                            Published: <?php echo $data['count']->publish ?? 0; ?>, 
                            Draft: <?php echo $data['count']->draft ?? 0; ?>
                        </p>
                        
                        <?php if (!empty($data['taxonomies'])) : ?>
                            <h3>Taxonomies:</h3>
                            <ul>
                                <?php foreach ($data['taxonomies'] as $taxonomy => $terms) : ?>
                                    <li><strong><?php echo esc_html($taxonomy); ?>:</strong> <?php echo esc_html(implode(', ', $terms)); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <?php if (!empty($data['fields'])) : ?>
                            <h3>Custom Fields:</h3>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>Field Name</th>
                                        <th>Type</th>
                                        <th>Sample Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['fields'] as $field_name => $field_data) : ?>
                                        <tr>
                                            <td><code><?php echo esc_html($field_name); ?></code></td>
                                            <td><?php echo esc_html($field_data['type']); ?></td>
                                            <td><?php echo esc_html($field_data['sample_value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p>No custom fields found for this post type.</p>
                        <?php endif; ?>
                    </div>
                    <hr>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <p><strong>Next Step:</strong> Use this information to create the migration script that will map these fields to the new unified listing system.</p>
        </div>
        <?php
    }
}

