# Database Import Setup Guide - Step by Step

**Developer:** Responsab LLC  
**Website:** https://www.responsab.com

This guide walks you through setting up the Maloney Affordable Listings system after importing a fresh database snapshot.

---

## Prerequisites

Before starting, ensure you have:
- ✅ WordPress installed and running
- ✅ Database imported successfully
- ✅ Access to WordPress admin panel

---

## Step 1: Activate Required Plugins

1. Go to **Plugins → Installed Plugins**
2. Activate these plugins (in this order):
   - ✅ **Toolset Types** (required for custom fields)
   - ✅ **Maloney Affordable Listings** (main plugin)
   - ✅ **Toolset Views** (optional, only if using custom templates)

**Note:** If Toolset Types is not active, the plugin will show a warning. Activate it first, then refresh the page.

---

## Step 2: Update Site URLs (If Changed)

If your site URL changed after importing the database:

1. Go to **Settings → General**
2. Update:
   - **WordPress Address (URL)**
   - **Site Address (URL)**
3. Click **Save Changes**

---

## Step 3: Flush Permalinks

This ensures all custom post type URLs work correctly:

1. Go to **Settings → Permalinks**
2. Click **Save Changes** (no need to change anything)
3. This flushes rewrite rules and registers all custom post types

---

## Step 4: Assign Toolset Field Groups to "Listing" Post Type

The plugin needs Toolset field groups assigned to the `listing` post type.

### Option A: Use the Admin Tool (Recommended)

1. Go to **Listings → Assign Field Groups**
2. Check these field groups:
   - ✅ **Property Info** (applies to both condos and rentals)
   - ✅ **Condo Lotteries** (for condos only)
   - ✅ **Condominiums** (for condos only)
   - ✅ **Rental Lotteries** (for rentals only)
   - ✅ **Rental Properties** (for rentals only)
   - ✅ **Current Rental Availability** (for rentals only, if exists)
3. Click **"Assign Selected Groups"**
4. Wait for confirmation message

### Option B: Manual Assignment

1. Go to **Toolset → Custom Fields Group**
2. For each group, click **Edit**:
   - **Property Info** → Under "Post Types", add **"Listing"**
   - **Condo Lotteries** → Under "Post Types", add **"Listing"**
   - **Condominiums** → Under "Post Types", add **"Listing"**
   - **Rental Lotteries** → Under "Post Types", add **"Listing"**
   - **Rental Properties** → Under "Post Types", add **"Listing"**
3. Click **Save** for each group

**Important:** Do NOT set taxonomy dependencies in Toolset. The plugin handles conditional display via PHP filters.

---

## Step 5: Remove Taxonomy Dependencies from Field Groups

The plugin uses PHP filters for conditional display, not Toolset taxonomy dependencies.

1. Go to **Toolset → Custom Fields Group**
2. Edit each group (Condo Lotteries, Condominiums, Rental Lotteries, Rental Properties)
3. In the **"Where to display this group"** section:
   - **Remove** any "Taxonomies: Listing Type" conditions
   - **Keep only** "Post Types: Listing" assignment
4. Click **Save** for each group

---

## Step 6: Verify Toolset Fields Are Created

The plugin automatically creates repetitive fields for "Current Rental Availability" when you visit the admin area.

1. Go to **Listings → Add New**
2. You should see field groups in the editor
3. If fields are missing:
   - Visit any admin page (fields are created on `admin_init`)
   - Check that Toolset Types is active
   - Go to **Toolset → Custom Fields** and verify fields exist

---

## Step 7: Migrate Existing Listings (If Needed)

If your database has old `condominiums` or `rental-properties` post types that need to be migrated:

1. Go to **Listings → Migrate Existing Condos and Rental Properties**
2. Review the post type counts
3. Select the post types you want to migrate:
   - ✅ Condominiums
   - ✅ Rental Properties
4. Click **"Run Migration"**
5. Wait for completion and review results

**Note:** This is a one-time migration. After migration, all new listings should use the `listing` post type.

---

## Step 8: Migrate Available Units Data (If Needed)

If you have available units data in Ninja Tables (Table ID 790) that needs to be migrated:

1. Go to **Listings → Migrate Available Units**
2. Review the migration preview
3. Click **"Start Migration"**
4. Wait for completion

**Note:** After migration, manage availability through **Listings → Current Availability**.

---

## Step 9: Rebuild Bedrooms/Bathrooms Index

This ensures filters work correctly by rebuilding the `_listing_bedrooms` and `_listing_bathrooms` meta fields.

1. Go to **Listings → Index Health**
2. Click **"Run Dry Run"** to see what needs to be rebuilt
3. Click **"Rebuild Now"** to backfill missing data
4. Wait for completion

---

## Step 10: Geocode Addresses

To enable map functionality, geocode all listing addresses:

1. Go to **Listings → Geocode Addresses**
2. Review the count of listings needing geocoding
3. Click **"Start Geocoding"**
4. The tool processes in batches (to respect API rate limits)
5. Repeat until the "needing geocode" count reaches **0**

**Note:** 
- Geocoding uses OpenStreetMap Nominatim API (free, but rate-limited)
- You can also geocode individual listings from the listing edit page
- For production, consider using a paid geocoding service

---

## Step 11: Migrate Toolset Templates (Optional)

If you have existing Toolset Views templates for `condominiums` or `rental-properties`:

1. Go to **Listings → Setup Toolset Templates**
2. Review the templates that will be migrated
3. Click **"Migrate Templates"**
4. This creates conditional templates for the `listing` post type

**Note:** This is only needed if you have existing templates. New templates can be created directly for the `listing` post type.

---

## Step 11b: Replace Template Blocks (If Needed)

If your templates contain old Toolset blocks (like `[types field='vacancy-table'][/types]`) that need to be replaced with the new shortcode blocks:

1. Go to **Listings → Template Blocks**
2. Scroll to **"Replace Existing Blocks"** section
3. Configure the replacement:
   - **Template:** Select "All Templates" or a specific template
   - **Block to Replace:** Enter the pattern to find (e.g., `vacancy-table` or `toolset-blocks/fields-and-text`)
   - **New Block Type:** Select `core/shortcode`
   - **New Shortcode:** Enter the replacement shortcode (e.g., `[maloney_listing_availability]`)
4. Click **"Replace Blocks"**
5. Review the results - it will show how many templates were updated

**Common Replacements:**
- Replace `vacancy-table` field with `[maloney_listing_availability]` shortcode
- Replace old Toolset availability blocks with the new custom shortcode

**Debugging:**
- Use the **"Debug Template Blocks"** section to view all blocks in a template
- Enter a template ID and click **"List Blocks"** to see what blocks exist

**Note:** This is typically needed if you're migrating from old Toolset field blocks to the new plugin shortcodes.

---

## Step 12: Configure Plugin Settings

Configure plugin behavior:

1. Go to **Listings → Settings**
2. Configure:
   - **Enable "Search this area" feature** - Show/hide the map area search button
   - **Rental Badge Color** - Color for rental property badges (default: `rgba(232, 105, 98, 1)`)
   - **Condo Badge Color** - Color for condo badges (default: `rgba(228, 199, 128, 1)`)
   - **Enable Directions Button** - Show/hide directions button on single listing maps
   - **Enable Street View Button** - Show/hide street view button on single listing maps
   - **"Just Listed" Period** - Days to consider a listing "just listed" (1, 3, 7, or 14 days)
   - **Enable Bathrooms Filter** - Show/hide bathrooms filter on listings page
3. Click **Save Settings**

---

## Step 13: Create/Verify Listings Page

Ensure you have a page with the listings shortcode:

1. Go to **Pages → Add New** (or edit existing listings page)
2. Add the shortcode: `[maloney_listings_view]`
3. Publish/Update the page
4. Note the page slug (e.g., `/listing/`)

**Alternative:** The plugin may have already created this page. Check **Pages → All Pages** for a page named "Listings".

---

## Step 14: Test the System

Verify everything is working:

### Test 1: View Listings Page
1. Visit your listings page (e.g., `/listing/`)
2. Verify:
   - ✅ Listings are displayed
   - ✅ Map is visible with markers
   - ✅ Filters are working
   - ✅ Search location works

### Test 2: Test Filters
1. On the listings page, test each filter:
   - ✅ Bedrooms filter
   - ✅ Type filter (Condo/Rental)
   - ✅ Availability filter
   - ✅ Location search
   - ✅ Available Units filter
2. Verify results update correctly

### Test 3: Test Single Listing Page
1. Click on a listing card
2. Verify:
   - ✅ Single listing page loads
   - ✅ Map is visible with marker
   - ✅ Directions button works (if enabled)
   - ✅ Street View button works (if enabled)
   - ✅ All fields are displayed correctly

### Test 4: Test Adding New Listing
1. Go to **Listings → Add New**
2. Click **"Add New Listing"**
3. Select **"Condo"** or **"Rental"** in the modal
4. Verify:
   - ✅ Correct field groups appear based on selection
   - ✅ Unit Type field is set correctly
   - ✅ You can save and publish the listing

### Test 5: Test Current Availability (Rentals Only)
1. Go to **Listings → Current Availability**
2. Verify:
   - ✅ Table displays rental properties
   - ✅ You can add new availability entries
   - ✅ You can edit existing entries

---

## Step 15: Clear Cache (If Using Caching Plugin)

If you're using a caching plugin (WP Super Cache, W3 Total Cache, etc.):

1. Clear all cache
2. Clear browser cache
3. Test the listings page again

---

## Troubleshooting

### Fields Not Showing in Editor

**Problem:** Field groups don't appear when editing a listing.

**Solutions:**
1. Verify Toolset Types is active
2. Go to **Listings → Assign Field Groups** and reassign groups
3. Visit any admin page (fields are created on `admin_init`)
4. Check **Toolset → Custom Fields** to verify fields exist
5. Clear browser cache

### Map Not Loading

**Problem:** Map doesn't show on listings page or single listing page.

**Solutions:**
1. Verify listings have geocoded addresses:
   - Go to **Listings → Geocode Addresses**
   - Check if any listings need geocoding
2. Check browser console for JavaScript errors
3. Verify Leaflet.js is loading (check Network tab)
4. Check that listings have valid latitude/longitude coordinates

### Filters Not Working

**Problem:** Filters don't update results.

**Solutions:**
1. Clear WordPress cache
2. Go to **Listings → Index Health** and rebuild indexes
3. Verify taxonomies have terms assigned
4. Check browser console for JavaScript errors
5. Verify AJAX is working (check Network tab when filtering)

### Permalinks Not Working

**Problem:** Listing URLs return 404 errors.

**Solutions:**
1. Go to **Settings → Permalinks**
2. Click **Save Changes** (flushes rewrite rules)
3. Verify permalink structure is set (not "Plain")
4. Check `.htaccess` file permissions

### Toolset Warning Message

**Problem:** Plugin shows warning that Toolset Types is required.

**Solutions:**
1. Verify Toolset Types plugin is installed and activated
2. Refresh the admin page
3. Check that Toolset Types is in the correct directory:
   - `/wp-content/plugins/types/` or
   - `/wp-content/plugins/toolset-types/`

---

## Quick Checklist

Use this checklist to ensure you've completed all steps:

- [ ] Step 1: Activated Toolset Types and Maloney Listings plugins
- [ ] Step 2: Updated site URLs (if changed)
- [ ] Step 3: Flushed permalinks
- [ ] Step 4: Assigned Toolset field groups to "Listing" post type
- [ ] Step 5: Removed taxonomy dependencies from field groups
- [ ] Step 6: Verified Toolset fields are created
- [ ] Step 7: Migrated existing listings (if needed)
- [ ] Step 8: Migrated available units data (if needed)
- [ ] Step 9: Rebuilt bedrooms/bathrooms index
- [ ] Step 10: Geocoded all addresses
- [ ] Step 11: Migrated Toolset templates (if needed)
- [ ] Step 11b: Replaced template blocks (if needed)
- [ ] Step 12: Configured plugin settings
- [ ] Step 13: Created/verified listings page
- [ ] Step 14: Tested all functionality
- [ ] Step 15: Cleared cache

---

## Support

If you encounter issues not covered in this guide:

- **Developer:** Responsab LLC
- **Website:** https://www.responsab.com

---

## Additional Resources

- **Shortcodes Documentation:** See `SHORTCODES.md` for available shortcodes
- **General Setup Guide:** See `SETUP_GUIDE.md` for general plugin information

---

**Last Updated:** 2025-01-XX  
**Plugin Version:** 1.0.0

