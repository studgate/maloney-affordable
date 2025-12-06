<?php
/**
 * Migration Results Template
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Migration Results', 'maloney-listings'); ?></h1>
    
    <?php if (isset($results)) : ?>
        <div class="notice notice-success">
            <h2>Migration Complete!</h2>
            <ul>
                <li><strong>Successfully Migrated:</strong> <?php echo $results['migrated']; ?> listings</li>
                <?php if (isset($results['skipped']) && $results['skipped'] > 0) : ?>
                    <li><strong>Skipped (Already Migrated):</strong> <?php echo $results['skipped']; ?> listings</li>
                <?php endif; ?>
                <li><strong>Failed:</strong> <?php echo $results['failed']; ?> listings</li>
            </ul>
        </div>
        
        <?php 
        // Separate skipped items from actual errors
        $skipped_errors = array();
        $actual_errors = array();
        if (!empty($results['errors'])) {
            foreach ($results['errors'] as $error) {
                if (stripos($error, 'Skipped') !== false) {
                    $skipped_errors[] = $error;
                } else {
                    $actual_errors[] = $error;
                }
            }
        }
        ?>
        
        <?php if (!empty($skipped_errors)) : ?>
            <div class="notice notice-info" style="max-height: 400px; overflow-y: auto;">
                <h3><?php _e('Skipped Items (Already Migrated):', 'maloney-listings'); ?></h3>
                <p><?php _e('These items were skipped because they were already migrated previously. This prevents duplicate listings.', 'maloney-listings'); ?></p>
                <ul style="margin-left: 20px;">
                    <?php foreach ($skipped_errors as $error) : ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($actual_errors)) : ?>
            <div class="notice notice-error">
                <h3><?php _e('Errors:', 'maloney-listings'); ?></h3>
                <ul>
                    <?php foreach ($actual_errors as $error) : ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($results['migrated'] > 0) : ?>
            <div class="notice notice-warning">
                <p><strong><?php _e('Important:', 'maloney-listings'); ?></strong> <?php _e('Geocoding was disabled during migration to prevent system slowdown. Please use the "Geocode Addresses" tool to geocode the newly migrated listings.', 'maloney-listings'); ?></p>
            </div>
        <?php endif; ?>
        
        <p>
            <a href="<?php echo admin_url('edit.php?post_type=listing'); ?>" class="button button-primary">
                <?php _e('View All Listings', 'maloney-listings'); ?>
            </a>
        </p>
    <?php endif; ?>
</div>

