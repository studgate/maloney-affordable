<?php
/**
 * Archive Listing Content (no header/footer - theme handles layout)
 * 
 *  Developer: Ralph Francois
 *  Company: Responsab LLC.
 */
?>

<h1 class="entry-title"><?php post_type_archive_title(); ?></h1>

<!-- Filters Section -->
<div class="listing-filters">
    <div class="listing-filter-group">
        <label for="filter_listing_type"><?php _e('Property Type', 'maloney-listings'); ?></label>
        <select id="filter_listing_type" name="listing_type">
            <option value=""><?php _e('All Types', 'maloney-listings'); ?></option>
            <?php
            $types = get_terms(array('taxonomy' => 'listing_type', 'hide_empty' => false));
            foreach ($types as $type) :
                ?>
                <option value="<?php echo esc_attr($type->slug); ?>"><?php echo esc_html($type->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="listing-filter-group">
        <label for="filter_status"><?php _e('Status', 'maloney-listings'); ?></label>
        <select id="filter_status" name="status">
            <option value=""><?php _e('All Statuses', 'maloney-listings'); ?></option>
            <?php
            $statuses = get_terms(array('taxonomy' => 'listing_status', 'hide_empty' => false));
            foreach ($statuses as $status) :
                ?>
                <option value="<?php echo esc_attr($status->slug); ?>"><?php echo esc_html($status->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="listing-filter-group">
        <label for="filter_location"><?php _e('Location', 'maloney-listings'); ?></label>
        <select id="filter_location" name="location">
            <option value=""><?php _e('All Locations', 'maloney-listings'); ?></option>
            <?php
            $locations = get_terms(array('taxonomy' => 'location', 'hide_empty' => false));
            foreach ($locations as $location) :
                ?>
                <option value="<?php echo esc_attr($location->term_id); ?>"><?php echo esc_html($location->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="listing-filter-group">
        <label for="filter_bedrooms"><?php _e('Bedrooms', 'maloney-listings'); ?></label>
        <select id="filter_bedrooms" name="bedrooms">
            <option value=""><?php _e('Any', 'maloney-listings'); ?></option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4+</option>
        </select>
    </div>
    
    <button type="button" id="toggle_advanced_filters" class="button"><?php _e('Advanced Filters', 'maloney-listings'); ?></button>
    
    <div class="advanced-filters" id="advanced_filters">
        <div class="listing-filter-group">
            <label for="filter_price_min"><?php _e('Price Range', 'maloney-listings'); ?></label>
            <div class="price-range">
                <input type="number" id="filter_price_min" name="price_min" placeholder="Min" />
                <span>to</span>
                <input type="number" id="filter_price_max" name="price_max" placeholder="Max" />
            </div>
        </div>
        
        <div class="listing-filter-group">
            <label for="filter_income_level"><?php _e('Income Level', 'maloney-listings'); ?></label>
            <input type="number" id="filter_income_level" name="income_level" placeholder="Annual Income" />
        </div>
        
        <div class="listing-filter-group">
            <label><?php _e('Amenities', 'maloney-listings'); ?></label>
            <div class="amenities-checkboxes">
                <?php
                $amenities = get_terms(array('taxonomy' => 'amenities', 'hide_empty' => false));
                foreach ($amenities as $amenity) :
                    ?>
                    <label>
                        <input type="checkbox" name="amenities[]" value="<?php echo esc_attr($amenity->term_id); ?>" />
                        <?php echo esc_html($amenity->name); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <button type="button" id="apply_filters" class="button button-primary"><?php _e('Apply Filters', 'maloney-listings'); ?></button>
    <button type="button" id="clear_filters" class="button"><?php _e('Clear Filters', 'maloney-listings'); ?></button>
</div>

<!-- View Toggle -->
<div class="view-toggle">
    <button type="button" id="toggle-card-view" class="active"><?php _e('Card View', 'maloney-listings'); ?></button>
    <button type="button" id="toggle-map-view"><?php _e('Map View', 'maloney-listings'); ?></button>
</div>

<!-- Results Area -->
<div id="listings-results">
    <div id="listings-grid" class="listings-grid">
        <?php
        if (have_posts()) :
            while (have_posts()) : the_post();
                include MALONEY_LISTINGS_PLUGIN_DIR . 'templates/listing-card.php';
            endwhile;
        else :
            ?>
            <div class="no-listings-found">
                <p><?php _e('No listings found.', 'maloney-listings'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="listings-map" style="display: none;"></div>
    
    <!-- Pagination -->
    <div class="listings-pagination">
        <?php
        global $wp_query;
        echo paginate_links(array(
            'total' => $wp_query->max_num_pages,
            'prev_text' => __('&laquo; Previous', 'maloney-listings'),
            'next_text' => __('Next &raquo;', 'maloney-listings'),
        ));
        ?>
    </div>
</div>

