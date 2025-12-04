# Field Migration Complete ✅

## All Fields Mapped and Ready for Migration

I've updated the migration script with all your custom fields from both post types. Here's what's been configured:

### Common Fields (Both Post Types)
These fields will be migrated from both `condominiums` and `rental-properties`:

- **Basic Property Info:**
  - `wpcf-property-name` → `_listing_property_name`
  - `wpcf-city` → `_listing_city`
  - `wpcf-state-1` → `_listing_state`
  - `wpcf-address` → `_listing_address`
  - `wpcf-telephone` → `_listing_telephone`
  - `wpcf-email` → `_listing_email`

- **Property Details:**
  - `wpcf-main-marketing-text` → `_listing_main_marketing_text`
  - `wpcf-extra-top-level-info` → `_listing_extra_top_level_info`
  - `wpcf-features` → `_listing_features`
  - `wpcf-amenities-photo` → `_listing_amenities_photo`
  - `wpcf-neighborhood` → `_listing_neighborhood`
  - `wpcf-eligibility` → `_listing_eligibility`
  - `wpcf-maximum-asset-limits` → `_listing_maximum_asset_limits`
  - `wpcf-additional-content` → `_listing_additional_content`
  - `wpcf-faq` → `_listing_faq`
  - `wpcf-property-photo` → `_listing_property_photo`

- **Unit Info:**
  - `wpcf-unit-sizes` → `_listing_unit_sizes` (Array field - preserved)
  - `wpcf-income-limits` → `_listing_income_limits`

- **Application Info:**
  - `wpcf-application-period-starts` → `_listing_application_period_starts`
  - `wpcf-application-distribution-ends` → `_listing_application_distribution_ends`
  - `wpcf-application-period-ends` → `_listing_application_period_ends`
  - `wpcf-application-info` → `_listing_application_info`
  - `wpcf-online-application-url` → `_listing_online_application_url`
  - `wpcf-lottery-process` → `_listing_lottery_process`

- **Other:**
  - `om_disable_all_campaigns` → `_listing_om_disable_all_campaigns`

### Rental-Specific Fields
These fields will ONLY be migrated from `rental-properties`:

- `wpcf-status` → `_listing_rental_status`
- `wpcf-vacancy-table` → `_listing_vacancy_table` (Ninja Tables shortcode)

### Condo-Specific Fields
These fields will ONLY be migrated from `condominiums`:

- `wpcf-condo-status` → `_listing_condo_status`
- `wpcf-current-condo-listings-table` → `_listing_current_condo_listings_table` (Ninja Tables shortcode)
- `wpcf-form-url` → `_listing_form_url`

## Admin Interface Updates

All these fields are now available in the WordPress admin when editing listings:

1. **Listing Details** - Basic info (bedrooms, bathrooms, etc.)
2. **Location Information** - Address, coordinates, city, state
3. **Property Information** - Name, marketing text, features, FAQ, etc.
4. **Application Information** - Application periods, lottery info, URLs
5. **Pricing & Income** - Rent/purchase prices, income limits

### Conditional Fields

When you select "Condo" or "Rental" as the listing type:
- **Condo selected:** Shows condo-specific fields (condo status, condo listings table, form URL)
- **Rental selected:** Shows rental-specific fields (rental status, vacancy table)
- Fields automatically show/hide based on selection

## Next Steps

1. **Backup your database** (IMPORTANT!)

2. **Run Field Discovery:**
   - Go to: Listings → Field Discovery
   - Verify all fields are detected

3. **Run Migration:**
   - Go to: Listings → Migrate Listings
   - Select `condominiums` and/or `rental-properties`
   - Click "Run Migration"

4. **Verify Migration:**
   - Check a few migrated listings
   - Verify all fields were copied correctly
   - Test conditional fields (select Condo vs Rental)

5. **Update Frontend Templates:**
   - The frontend templates can now access all these fields
   - Use `get_post_meta($post_id, '_listing_property_name', true)` etc.

## Field Access in Templates

In your templates, access fields like this:

```php
// Property info
$property_name = get_post_meta($post_id, '_listing_property_name', true);
$city = get_post_meta($post_id, '_listing_city', true);
$address = get_post_meta($post_id, '_listing_address', true);

// Application info
$application_url = get_post_meta($post_id, '_listing_online_application_url', true);
$lottery_process = get_post_meta($post_id, '_listing_lottery_process', true);

// Type-specific (check listing type first)
$listing_type = wp_get_post_terms($post_id, 'listing_type');
if ($listing_type && $listing_type[0]->slug === 'rental') {
    $vacancy_table = get_post_meta($post_id, '_listing_vacancy_table', true);
    echo do_shortcode($vacancy_table); // Render Ninja Tables shortcode
}
```

## Notes

- **Array fields** (like `wpcf-unit-sizes`) are preserved as arrays
- **Ninja Tables shortcodes** are preserved - use `do_shortcode()` to render them
- **Timestamps** for application periods are preserved as-is (Unix timestamps)
- **All wpcf- fields** are preserved even if not explicitly mapped
- **Featured images** are migrated automatically
- **Taxonomies** are migrated if they match existing taxonomies

## Ready to Migrate!

Everything is configured and ready. Just backup your database and run the migration!

