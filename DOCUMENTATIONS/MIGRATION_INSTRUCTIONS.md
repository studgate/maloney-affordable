# Migration Instructions

## Step-by-Step Migration Process

### Step 1: Discover Existing Fields

1. Go to WordPress Admin → Listings → Field Discovery
2. This will show you:
   - What post types exist (condominium, rental, etc.)
   - How many posts exist for each type
   - What custom fields exist for each post type
   - What taxonomies are used

**Review the results** and note down:
- Exact post type names
- Field names you want to preserve
- Fields that are specific to condos vs rentals

### Step 2: Update Field Mapping

Edit: `wp-content/plugins/maloney-listings/includes/class-migration.php`

Find the `get_field_mapping()` method and update it to match your actual field names:

```php
private function get_field_mapping() {
    return array(
        // Map your existing field names to new field names
        'your_old_bedrooms_field' => '_listing_bedrooms',
        'your_old_rent_field' => '_listing_rent_price',
        // ... etc
    );
}
```

### Step 3: Update Source Post Types

In the same file, update `$source_post_types` array if your post types have different names:

```php
private $source_post_types = array('condominium', 'condo', 'rental', 'rental-property');
```

### Step 4: Backup Your Database

**IMPORTANT:** Always backup before migration!

### Step 5: Run Migration

1. Go to WordPress Admin → Listings → Migrate Listings
2. Review the field discovery results
3. Check the source post types you want to migrate
4. Click "Run Migration"
5. Review the results

### Step 6: Test Migration

1. Go to Listings → All Listings
2. Check that posts were created
3. Verify fields were migrated correctly
4. Check that listing types were set correctly
5. Test a few listings to ensure data is intact

### Step 7: Set Up Redirects (Optional)

If you want old URLs to redirect to new ones, add this to your `.htaccess` or use a redirect plugin:

```apache
# Redirect old condo URLs
RedirectMatch 301 ^/condos-for-sale/(.*)$ /listing/$1

# Redirect old rental URLs  
RedirectMatch 301 ^/rental-properties/(.*)$ /listing/$1
```

### Step 8: Create Unified Frontend Page

1. Create a new page in WordPress
2. Title: "Listings" or "Property Listings"
3. Set the page template to "Listings Page"
4. Publish the page
5. The page will automatically show all listings with filters

### Step 9: Update Navigation

Update your site navigation to point to the new `/listings/` page instead of the old pages.

## Conditional Fields Setup

After migration, when editing listings:

1. **Select Listing Type** (Condo or Rental) in the taxonomy box
2. **Fields will automatically show/hide:**
   - Condo selected → Shows "Purchase Price" field
   - Rental selected → Shows "Monthly Rent" field
   - Common fields (bedrooms, bathrooms, etc.) always show

## Adding New Conditional Fields

To add more conditional fields:

1. Edit `class-custom-fields.php`
2. Add fields with appropriate classes:
   - `condo-field` - Shows only for condos
   - `rental-field` - Shows only for rentals
   - No class - Shows for both

Example:
```php
<tr class="conditional-field condo-field">
    <th>Unit Type</th>
    <td><input type="text" name="listing_unit_type" /></td>
</tr>
```

## Troubleshooting

### Migration didn't work
- Check field names match exactly
- Verify post types exist
- Check WordPress error logs
- Review migration results for specific errors

### Fields not showing/hiding
- Check browser console for JavaScript errors
- Verify listing type taxonomy is set correctly
- Clear browser cache

### Old URLs not working
- Set up redirects (see Step 7)
- Or update internal links to point to new URLs

