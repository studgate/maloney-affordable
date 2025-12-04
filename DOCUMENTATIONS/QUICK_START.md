# Quick Start Guide

## Your Post Types

- `condominiums` - Your existing condo post type
- `rental-properties` - Your existing rental post type

## Step 1: Discover Your Fields

1. Go to: **WordPress Admin → Listings → Field Discovery**
2. Review the results to see:
   - How many posts exist for each type
   - What custom fields are used
   - What taxonomies are attached

## Step 2: Update Field Mapping (if needed)

The migration script has default field mappings, but you may need to customize them based on your actual field names.

Edit: `wp-content/plugins/maloney-listings/includes/class-migration.php`

Find the `get_field_mapping()` method (around line 200) and update it:

```php
private function get_field_mapping() {
    return array(
        // Map your Toolset Types field names to new field names
        // Example: 'wpcf-bedrooms' => '_listing_bedrooms',

        // Common fields (adjust based on your actual field names)
        'bedrooms' => '_listing_bedrooms',
        'wpcf-bedrooms' => '_listing_bedrooms',
        'bathrooms' => '_listing_bathrooms',
        'wpcf-bathrooms' => '_listing_bathrooms',
        // ... etc
    );
}
```

**Note:** Toolset Types fields typically have the `wpcf-` prefix. Check your Field Discovery results to see exact field names.

## Step 3: Backup Database

**IMPORTANT:** Always backup before migration!

## Step 4: Run Migration

1. Go to: **WordPress Admin → Listings → Migrate Listings**
2. You'll see checkboxes for:
   - `condominiums` (with count of published posts)
   - `rental-properties` (with count of published posts)
3. Select the post types you want to migrate
4. Click **"Run Migration"**
5. Review the results

## Step 5: Test Migration

1. Go to: **Listings → All Listings**
2. Verify your posts were migrated
3. Check a few listings to ensure:
   - Data was copied correctly
   - Listing type is set correctly (Condo or Rental)
   - Fields are populated
   - Images are attached

## Step 6: Create Frontend Page

1. Go to: **Pages → Add New**
2. Title: "Listings" (or "Property Listings")
3. In the Page Attributes box (right sidebar), select template: **"Listings Page"**
4. Publish the page
5. Visit the page to see all your listings with filters

## Step 7: Update Navigation

Update your site menu to point to the new listings page instead of the old `/condos-for-sale/` and `/rental-properties/` pages.

## Step 8: Set Up Redirects (Optional)

If you want old URLs to redirect to new ones:

1. Install a redirect plugin (like "Redirection")
2. Or add to `.htaccess`:

   ```apache
   # Redirect old condo URLs
   RedirectMatch 301 ^/condos-for-sale/(.*)$ /listings/$1

   # Redirect old rental URLs
   RedirectMatch 301 ^/rental-properties/(.*)$ /listings/$1
   ```

## Troubleshooting

### Migration shows "0 posts found"

- Check that post types are spelled correctly: `condominiums` and `rental-properties`
- Verify posts exist in WordPress Admin → Posts → [Your Post Type]

### Fields not migrated

- Check Field Discovery to see exact field names
- Update field mapping in `class-migration.php`
- Toolset Types fields usually have `wpcf-` prefix

### Conditional fields not working

- Make sure you've selected the listing type (Condo or Rental) in the taxonomy box
- Check browser console for JavaScript errors
- Clear browser cache

## Next Steps

After migration is complete:

- Test creating new listings
- Test conditional fields (select Condo vs Rental)
- Test frontend filters
- Test map view
- Test vacancy notifications
