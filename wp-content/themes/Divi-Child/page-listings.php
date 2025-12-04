<?php
/**
 * Template Name: Listings Page
 * Unified listings page template
 */

get_header(); ?>

<?php
// Query listings
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$listings_query = new WP_Query(array(
    'post_type' => 'listing',
    'posts_per_page' => 12,
    'paged' => $paged,
    'post_status' => 'publish',
));

// Load the listing archive template
if (file_exists(MALONEY_LISTINGS_PLUGIN_DIR . 'templates/archive-listing.php')) {
    global $wp_query;
    $original_query = $wp_query;
    $wp_query = $listings_query;
    include MALONEY_LISTINGS_PLUGIN_DIR . 'templates/archive-listing.php';
    $wp_query = $original_query;
    wp_reset_postdata();
} else {
    echo '<p>Listing template not found. Please activate the Maloney Listings plugin.</p>';
}
?>

<?php get_footer(); ?>

