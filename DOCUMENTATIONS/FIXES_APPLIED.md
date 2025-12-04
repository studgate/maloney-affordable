# Fixes Applied - Step by Step Guide

## 1. ✅ Migration Fixed - Now Migrates ALL Fields

**Problem:** Fields were empty after migration.

**Solution:** 
- Updated migration to use direct database queries to get ALL post meta
- Uses Toolset Types API (`wpcf_admin_fields_get_fields()`) to get all field definitions
- Migrates ALL `wpcf-` prefixed fields (preserves them as-is)
- Migrates ALL attachments/photos and updates their parent relationships
- Handles arrays, files, images, checkboxes properly

**To Test:**
1. Go to: **Listings → Field Discovery** - Verify all fields are detected
2. Go to: **Listings → Migrate Listings** - Run migration
3. Check a migrated listing - All fields should be populated

## 2. ✅ Block Editor Fixed

**Problem:** Listings were using classic editor instead of block editor.

**Solution:**
- Added `force_block_editor()` filter to force block editor for listing post type
- Added `force_block_editor_for_post()` filter for individual posts
- Ensures `show_in_rest` is true

**To Test:**
1. Go to: **Listings → Add New**
2. You should see the Gutenberg block editor (not classic editor)
3. Toolset Types field groups will appear below the editor

## 3. ✅ Property Type Filter Changed to Dropdown

**Problem:** Filter was buttons, should be dropdown and wasn't working.

**Solution:**
- Changed from buttons to `<select>` dropdown
- Updated JavaScript to read from dropdown
- Fixed filter logic

**To Test:**
1. Go to: **/listing/** (listings archive page)
2. Property Type filter should be a dropdown
3. Selecting a type and clicking "Apply Filters" should work

## 4. ✅ Map View Shows All Locations with Pins

**Problem:** Map should show all listings with pins based on addresses.

**Solution:**
- Map initializes with all listings from `window.maloneyListingsData`
- Creates markers for all listings with lat/lng coordinates
- Map auto-fits to show all markers
- Updates markers when filters are applied
- Uses marker clustering for many pins

**To Test:**
1. Go to: **/listing/**
2. Click "Map View"
3. You should see all listings with pins on the map
4. Apply filters - map should update to show filtered listings

## 5. ✅ Conditional Fields - Hide Based on Type

**Problem:** Need to hide condo fields when editing rentals, hide rental fields when editing condos.

**Solution:**
- Updated `conditional-fields.js` to detect listing type
- Hides entire Toolset Types field groups based on title
- Works with both taxonomy selection and unit_type dropdown

**To Test:**
1. Go to: **Listings → Add New**
2. Select "Condo" from Unit Type dropdown
3. Rental Properties and Rental Lotteries field groups should hide
4. Select "Rental" - Condominiums and Condo Lotteries should hide

## 6. ✅ Home Page "Units" Option Added

**Problem:** Need to add third option for combined listings.

**Solution:**
- Created `[maloney_listings_home_option]` shortcode
- Can be used in Divi Builder or page content

**Usage:**
```
[maloney_listings_home_option type="units" title="ALL UNITS" description="Search all available affordable housing units" button_text="Search All Units"]
```

**To Add:**
1. Edit your home page (in Divi Builder or page editor)
2. Add a third card using the shortcode above
3. Or use Divi modules with the same styling

## 7. ✅ Toolset View Shortcode Created

**Problem:** Need a View similar to "Condos-For-Sale" that can be inserted into pages with filters and map.

**Solution:**
- Created `[maloney_listings_view]` shortcode
- Includes filters, map view toggle, and listings grid
- Can be inserted into any page

**Usage:**
```
[maloney_listings_view type="units"]  <!-- Shows all -->
[maloney_listings_view type="condo"]  <!-- Shows only condos -->
[maloney_listings_view type="rental"] <!-- Shows only rentals -->
```

**To Use:**
1. Create or edit a page
2. Add the shortcode: `[maloney_listings_view type="units"]`
3. The page will show filters, map toggle, and listings

## Next Steps

1. **Run Migration Again:**
   - Go to: **Listings → Migrate Listings**
   - Select post types and run
   - Check that all fields are now populated

2. **Set Up Field Groups:**
   - Go to: **Listings → Assign Field Groups**
   - Assign all relevant field groups (Property Info, Rental Properties, Condominiums, etc.)

3. **Set Up Conditional Display in Toolset Types:**
   - Go to: **Toolset → Custom Fields Group**
   - Edit "Rental Properties" group
   - Set conditional display: Show when `listing_type` = "Rental"
   - Edit "Condominiums" group
   - Set conditional display: Show when `listing_type` = "Condo"

4. **Add "Units" Option to Home Page:**
   - Edit home page
   - Add: `[maloney_listings_home_option type="units"]`

5. **Create Listings Page with View:**
   - Create a new page or edit existing
   - Add: `[maloney_listings_view type="units"]`
   - This will show filters and map

## Troubleshooting

### Migration Still Shows Empty Fields
- Check Field Discovery to see what fields exist
- Verify Toolset Types is active
- Check that old posts have data in wp_postmeta table

### Block Editor Not Showing
- Clear browser cache
- Check that `show_in_rest` is true for listing post type
- Deactivate/reactivate plugin

### Conditional Fields Not Hiding
- Check that field group names contain "rental" or "condo"
- Verify JavaScript is loaded (check browser console)
- Make sure Unit Type is selected before saving

