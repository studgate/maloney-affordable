# Quick Setup Guide - Fresh Database Snapshot

After pulling a fresh database snapshot from the live site, follow these steps to get listings working:

## Important: Migration

**If you have existing `condominiums` or `rental-properties` post types that need to be migrated to the unified `listing` post type:**

1. Go to: **Listings → Migrate Listings**
2. Select the source post types you want to migrate
3. Click **"Run Migration"**
4. This will:
   - Create new `listing` posts from old post types
   - Migrate all fields, content, images, and metadata
   - Set the correct `listing_type` taxonomy
   - Preserve all Toolset custom fields

## 1. Activate Required Plugins

Ensure these plugins are active:
- ✅ **Maloney Affordable Listings** (this plugin)
- ✅ **Toolset Types** (for custom fields)
- ✅ **Toolset Views** (if using views)

## 2. Assign Toolset Field Groups to "Listing" Post Type

**Option A: Use the Admin Tool (Fastest)**
1. Go to: **Listings → Assign Field Groups**
2. Check these field groups:
   - ✅ **Property Info** (applies to both)
   - ✅ **Condo Lotteries** (for condos)
   - ✅ **Condominiums** (for condos)
   - ✅ **Rental Lotteries** (for rentals)
   - ✅ **Rental Properties** (for rentals)
3. Click **"Assign Selected Groups"**

**Option B: Manual Assignment**
1. Go to: **Toolset → Custom Fields Group**
2. Edit each group and add "Listing" to Post Types:
   - Property Info
   - Condo Lotteries
   - Condominiums
   - Rental Lotteries
   - Rental Properties

## 3. Remove Taxonomy Dependencies from Field Groups

**IMPORTANT:** The field groups should NOT have taxonomy dependencies set in Toolset. Our PHP filter handles conditional display.

1. Go to: **Toolset → Custom Fields Group**
2. Edit each group (Condo Lotteries, Condominiums, Rental Lotteries, Rental Properties)
3. In the "Where to display this group" section, **remove** any "Taxonomies: Listing Type" conditions
4. Keep only the "Post Types: Listing" assignment

## 4. Flush Rewrite Rules

1. Go to: **Settings → Permalinks**
2. Click **"Save Changes"** (no need to change anything)
3. This ensures custom permalinks (`/rental-properties/{slug}` and `/condominiums/{slug}`) work

## 5. Verify Listings Exist

1. Go to: **Listings → All Listings**
2. Verify listings are present
3. Check that they have the `listing_type` taxonomy assigned (Condo or Rental)

## 6. Test Adding a New Listing

1. Go to: **Listings → Add New**
2. You should see a modal asking to select "Condo" or "Rental"
3. Select one and verify:
   - ✅ Correct field groups appear (Condo groups for Condo, Rental groups for Rental)
   - ✅ Property Info always shows
   - ✅ Unit Type dropdown is set correctly

## 7. Verify Frontend

1. Visit the listings archive page (usually `/listing/` or wherever you have the listings view)
2. Check that:
   - ✅ Map loads and shows markers
   - ✅ Filters work
   - ✅ Listing cards display correctly
   - ✅ Links use correct permalinks (`/rental-properties/{slug}` or `/condominiums/{slug}`)

## 8. Geocode Listings (If Needed)

If listings don't show on the map:

1. Go to: **Listings → Geocode Addresses**
2. Click **"Start Geocoding"**
3. Wait for the process to complete
4. Refresh the listings page and verify markers appear

## Quick Checklist

- [ ] Plugins activated
- [ ] Field groups assigned to "Listing" post type
- [ ] Taxonomy dependencies removed from field groups
- [ ] Rewrite rules flushed
- [ ] Listings exist and have `listing_type` taxonomy
- [ ] Can add new listing with correct field groups showing
- [ ] Frontend listings page works
- [ ] Map shows markers (if geocoded)

## Troubleshooting

**Field groups not showing:**
- Verify groups are assigned to "Listing" post type
- Check that taxonomy dependencies are removed
- Clear browser cache

**Permalinks not working:**
- Flush rewrite rules (Settings → Permalinks → Save)
- Check `.htaccess` file is writable

**Map not showing:**
- Run geocoding tool (Listings → Geocode Addresses)
- Check browser console for JavaScript errors
- Verify listings have addresses in the address field

