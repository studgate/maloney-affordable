<?php
/**
 * Migration Form Template
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}

$discovery = Maloney_Listings_Field_Discovery::discover_fields();
?>

<div class="wrap">
    <h1><?php _e('Migrate Existing Condos and Rental Properties', 'maloney-listings'); ?></h1>
    
    <div class="notice notice-warning">
        <p><strong>⚠️ Important:</strong> Before running migration, please:</p>
        <ol>
            <li>Backup your database</li>
            <li>Review the field discovery results below</li>
            <li>Update the field mapping in <code>class-migration.php</code> if needed</li>
        </ol>
    </div>
    
    <h2>Field Discovery Results</h2>
    <?php if (!empty($discovery['post_types'])) : ?>
        <?php foreach ($discovery['post_types'] as $post_type => $data) : ?>
            <div class="post-type-info">
                <h3><?php echo esc_html($post_type); ?></h3>
                <p><strong>Total Posts:</strong> <?php echo ($data['count']->publish ?? 0) + ($data['count']->draft ?? 0); ?></p>
                
                <?php if (!empty($data['fields'])) : ?>
                    <p><strong>Fields Found:</strong> <?php echo count($data['fields']); ?></p>
                    <details>
                        <summary>View Fields</summary>
                        <ul>
                            <?php foreach (array_keys($data['fields']) as $field) : ?>
                                <li><code><?php echo esc_html($field); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php else : ?>
        <p>No existing post types found. Please check the field discovery script configuration.</p>
    <?php endif; ?>
    
    <h2>Migration Options</h2>
    <form method="post" action="">
        <?php wp_nonce_field('migrate_listings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="source_post_types"><?php _e('Source Post Types', 'maloney-listings'); ?></label>
                </th>
                <td>
                    <?php
                    $source_types = array('condominiums', 'rental-properties');
                    foreach ($source_types as $type) :
                        $exists = post_type_exists($type);
                        ?>
                        <label>
                            <input type="checkbox" name="source_post_types[]" value="<?php echo esc_attr($type); ?>" 
                                   <?php checked($exists, true); ?> <?php disabled(!$exists); ?> />
                            <?php echo esc_html($type); ?>
                            <?php if ($exists) : ?>
                                <span class="description">(<?php echo wp_count_posts($type)->publish; ?> published)</span>
                            <?php else : ?>
                                <span class="description">(not found)</span>
                            <?php endif; ?>
                        </label><br>
                    <?php endforeach; ?>
                    <p class="description">Only post types that exist in your system will be migrated.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="run_migration" class="button button-primary" 
                   value="<?php _e('Run Migration', 'maloney-listings'); ?>" 
                   onclick="return confirm('Are you sure you want to run the migration? Make sure you have a database backup!');" />
        </p>
    </form>
</div>

