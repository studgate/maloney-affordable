# Setup Questions Answered

## 1. Are all customizations in the plugin?

**YES, almost everything is in the plugin.** However, there are a few theme files:

### Theme Files (Divi-Child):
- **`page-listings.php`** - Just a wrapper that includes the plugin's `archive-listing.php` template
- **`style.css`** - Contains some basic CSS that may be overridden by plugin CSS

**Recommendation:** The theme files are minimal. The plugin's `frontend.css` should handle all styling. You could remove the listing-related CSS from the theme's `style.css` if you want everything in the plugin.

### Plugin Files:
- All templates (`archive-listing.php`, `single-listing.php`, `listing-card.php`)
- All JavaScript (`frontend.js`, `admin.js`)
- All CSS (`frontend.css`, `admin.css`)
- All functionality (post types, taxonomies, AJAX, geocoding, etc.)

## 2. Migration - Is it still needed?

**YES, if you have existing `condominiums` or `rental-properties` post types.**

The migration tool is still available at **Listings → Migrate Listings**. It will:
- Convert old post types to the unified `listing` post type
- Migrate all fields, content, images, and metadata
- Set the correct `listing_type` taxonomy
- Preserve all Toolset custom fields

**After a fresh DB pull:** If the live site already has `listing` posts, you don't need to migrate. If it still has the old post types, run the migration.

## 3. What can we remove?

### ✅ Removed (Development/Debug Tools):
- **Debug Listing** page - Removed
- **Diagnostics** page - Removed  
- **Index Health** page - Removed
- **Direct JSON dump** handler - Removed

### ❓ Vacancy Notifications - Should we keep it?

**Vacancy Notifications** allows users to sign up to be notified when a listing becomes available. It:
- Creates a database table to store email notifications
- Shows a form on single listing pages
- Sends emails when listing status changes to "Available"

**Recommendation:** 
- **Keep it** if you want users to be notified when properties become available
- **Remove it** if you don't need this functionality

If you want to remove it, we need to:
1. Comment out the initialization in `maloney-listings.php`
2. Remove the admin page
3. Remove the frontend form from templates
4. Remove the database table creation

## 4. What fields should we add?

### ✅ Already Supported:
- **Bathrooms** - Already fully supported in filters, cards, and templates
- **Zip Code** - Already exists in templates (`wpcf-zip`, `_listing_zip`), but not in filters

### 📝 Recommended Additions:

1. **Zip Code Filter** - Add to search/filter options
   - Currently zip code is displayed but not filterable
   - Could add to "Search location" autocomplete or as separate filter

2. **Additional Useful Fields** (if not already in Toolset):
   - Square footage (`wpcf-square-feet`, `_listing_square_feet`)
   - Year built (`wpcf-year-built`, `_listing_year_built`)
   - Pet policy (`wpcf-pet-policy`, `_listing_pet_policy`)
   - Laundry (`wpcf-laundry`, `_listing_laundry`)

## Summary

- **Customizations:** 99% in plugin, minimal theme dependencies
- **Migration:** Still needed if old post types exist
- **Removed:** Debug, Diagnostics, Index Health pages
- **Vacancy Notifications:** Keep if needed, can remove if not
- **Fields:** Bathrooms already supported, Zip Code exists but not filterable

