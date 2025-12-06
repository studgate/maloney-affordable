<?php
if (!current_user_can('manage_options')) { wp_die('No permission'); }
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
?>
<div class="wrap">
    <h1>Debug Listing</h1>
    <form method="get">
        <input type="hidden" name="post_type" value="listing" />
        <input type="hidden" name="page" value="debug-listing" />
        <p>
            <label>Listing ID: <input type="number" name="post_id" value="<?php echo esc_attr($post_id); ?>" /></label>
            <button class="button">Load</button>
        </p>
    </form>
    <?php if ($post_id): ?>
        <?php $post = get_post($post_id); if (!$post) { echo '<p>Post not found.</p>'; return; } ?>
        <h2><?php echo esc_html($post->post_title); ?> (#<?php echo $post_id; ?>)</h2>
        <h3>Taxonomies</h3>
        <ul>
            <?php foreach (array('listing_type','listing_status','location','amenities') as $tax): $terms = get_the_terms($post_id, $tax); ?>
                <li><strong><?php echo esc_html($tax); ?>:</strong> <?php echo $terms && !is_wp_error($terms) ? esc_html(implode(', ', wp_list_pluck($terms,'name'))) : '—'; ?></li>
            <?php endforeach; ?>
        </ul>
        <h3>Core Fields</h3>
        <ul>
            <?php foreach (array('_listing_bedrooms','_listing_bathrooms','_listing_rent_price','_listing_purchase_price','_listing_latitude','_listing_longitude','_listing_address','_listing_city','_listing_state','_listing_unit_sizes','_listing_lottery_process','_listing_rental_status','_listing_condo_status') as $k): ?>
                <li><code><?php echo esc_html($k); ?></code>: <?php $v = get_post_meta($post_id,$k,true); echo is_array($v)?'<pre>'.esc_html(print_r($v,true)).'</pre>':esc_html((string)$v); ?></li>
            <?php endforeach; ?>
        </ul>
        <h3>Toolset (wpcf-*) Fields</h3>
        <ul>
            <?php $all = get_post_meta($post_id); ksort($all); foreach ($all as $key=>$vals) { if (strpos($key,'wpcf-')!==0) continue; $val = maybe_unserialize($vals[0]); ?>
                <li><code><?php echo esc_html($key); ?></code>: <?php echo is_array($val)?'<pre>'.esc_html(print_r($val,true)).'</pre>':esc_html(substr((string)$val,0,400)); ?></li>
            <?php } ?>
        </ul>
        <h3>Ninja Tables</h3>
        <ul>
            <?php foreach (array('wpcf-vacancy-table','_listing_vacancy_table','wpcf-current-condo-listings-table','_listing_current_condo_listings_table') as $k): $v = get_post_meta($post_id,$k,true); if(!$v) continue; ?>
                <li><code><?php echo esc_html($k); ?></code>: <?php echo esc_html($v); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

