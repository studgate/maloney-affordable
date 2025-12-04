# Fresh Database Setup Checklist

**Developer:** Responsab LLC  
**Website:** https://www.responsab.com

Use this checklist after importing a fresh database snapshot to get the listings system fully operational.

---

## ✅ Step 1: Install & Activate Plugin

- [ ] Activate **Toolset Types** plugin (required for custom fields)
  - **Important:** All Toolset field groups and custom fields should already exist in your database
  - The plugin does NOT create or modify Toolset field groups or fields
- [ ] Activate **Maloney Affordable Listings** plugin
  - The plugin will create post types and taxonomies automatically
  - The plugin will NOT create or modify any Toolset field groups or fields

---

## ✅ Step 2: Assign Toolset Field Groups to Listing Post Type

**Important:** All Toolset field groups and custom fields must already exist in your database. The plugin does NOT create them.

- [ ] Go to **Listings → Assign Field Groups** (this redirects to Toolset's native interface)
  - OR go directly to **Toolset → Custom Fields**
- [ ] For each field group you want to use with listings:
  - [ ] Click on the field group name
  - [ ] Under "Post Types", check the box for **"listing"**
  - [ ] Click **"Save"**

**Common field groups to assign:**
- [ ] **Property Info** (applies to all listings)
  - **Note:** The **Zip Code** field will be automatically created when you run "Extract Zip Codes" (Step 6)
  - It will be positioned right after the State field automatically
- [ ] **Condominiums** (condos only)
- [ ] **Condo Lotteries** (condos only)
- [ ] **Rental Properties** (rentals only)
- [ ] **Rental Lotteries** (rentals only)
- [ ] **Current Rental Availability** (rentals only, if exists)

**Note:** The plugin will NOT create or modify these field groups. They must already exist in your Toolset installation.

---

## ✅ Step 3: Migrate Existing Listings

- [ ] Go to **Listings → Migrate Existing Condos and Rental Properties**
- [ ] Review migration options
- [ ] Click **"Start Migration"**
- [ ] Wait for migration to complete
- [ ] Verify listings appear in **Listings → All Listings**

---

## ✅ Step 4: Migrate Available Units (If Applicable)

**Only if you have availability data in Ninja Tables (Table ID 790):**

- [ ] Go to **Listings → Migrate Available Units**
- [ ] Click **"Start Migration"**
- [ ] Wait for migration to complete
- [ ] Verify availability data in **Listings → Current Availability**

---

## ✅ Step 5: Geocode Addresses

**Critical for map functionality:**

- [ ] Go to **Listings → Geocode Addresses**
- [ ] Review geocoding status (how many need geocoding)
- [ ] Click **"Start Geocoding"** to batch geocode all listings
- [ ] Wait for geocoding to complete (may take several minutes)
- [ ] Re-run if needed until "Listings needing geocoding" shows 0
- [ ] Verify geocoded listings appear on the map

**Note:** Listings without geocoded addresses will NOT appear on the map.

---

## ✅ Step 6: Extract Zip Codes (If Needed)

**Only if listings have addresses but no zip codes:**

- [ ] Go to **Listings → Extract Zip Codes**
- [ ] Review list of listings without zip codes
- [ ] Click **"Geocode & Extract Zip Codes"** (recommended - uses geocoding API)
  - OR click **"Extract Zip Codes from Addresses"** (simple pattern matching)
- [ ] Wait for processing to complete
- [ ] Review successful extractions and any errors
- [ ] Verify extracted zip codes in listing edit pages
- [ ] **Note:** This automatically creates the Zip Code field in Property Info group (positioned after State field)

---

## ✅ Step 7: Create Listings Page

**The plugin does not automatically create a listings page. Choose one option:**

### Option A: Use Archive Page (Easiest)
- [ ] Visit `/listings/` to verify the archive page works
- [ ] No additional setup needed

### Option B: Create Custom Page with Shortcode
- [ ] Go to **Pages → Add New**
- [ ] Title: "Listings" (or your preferred title)
- [ ] Add shortcode: `[maloney_listings_view]`
- [ ] Publish the page
- [ ] Add to navigation menu

### Option C: Create Page with Gutenberg Block
- [ ] Go to **Pages → Add New**
- [ ] Title: "Listings" (or your preferred title)
- [ ] Add "Listings View" block
- [ ] Configure block settings
- [ ] Publish the page
- [ ] Add to navigation menu

## ✅ Step 8: Setup Toolset Templates (If Using Custom Templates)

**Only if you have existing Toolset Views templates:**

- [ ] Go to **Listings → Setup Toolset Templates**
- [ ] Click **"Migrate Templates"**
- [ ] Verify templates are migrated correctly
- [ ] Check that templates have proper conditions set

---

## ✅ Step 9: Configure Plugin Settings

- [ ] Go to **Listings → Settings**
- [ ] Configure the following:
  - [ ] **Enable "Search this area"** - Toggle map area search (default: enabled)
  - [ ] **Rental Badge Color** - Set color (default: rgba(232, 105, 98, 1))
  - [ ] **Condo Badge Color** - Set color (default: rgba(228, 199, 128, 1))
  - [ ] **Enable Directions Button** - Show/hide on individual listing maps (default: enabled)
  - [ ] **Enable Street View Button** - Show/hide on individual listing maps (default: enabled)
  - [ ] **"Just Listed" Period** - Set period (1, 3, 7, or 14 days)
  - [ ] **Enable Bathrooms Filter** - Show/hide bathrooms filter (default: disabled)
  - [ ] **Enable Income Limits Filter** - Show/hide income limits filter (default: disabled)
- [ ] Click **"Save Settings"**

---

## ✅ Step 10: Flush Permalinks

- [ ] Go to **Settings → Permalinks**
- [ ] Click **"Save Changes"** (no need to change anything)
- [ ] This ensures all custom post type URLs work correctly

---

## ✅ Step 11: Test the System

### Test Listings Page:
- [ ] Visit the listings archive page (`/listings/` or your custom listings page)
- [ ] Verify map displays with markers
- [ ] Test filters (Type, Beds & Baths, Availability, etc.)
- [ ] Verify filter results update correctly
- [ ] Test "Search location" autocomplete
- [ ] Test mobile view toggle (if applicable)
- [ ] Verify pagination works

### Test Individual Listings:
- [ ] Click on a listing from the map or list
- [ ] Verify all fields display correctly
- [ ] Check that map loads properly
- [ ] Test "Directions" button (if enabled)
- [ ] Test "Street View" button (if enabled)
- [ ] Test "Back to Results" link
- [ ] Verify availability table displays (for rentals)

### Test Admin Functions:
- [ ] Go to **Listings → Add New**
- [ ] Test adding a new **Condo** listing
  - [ ] Verify correct field groups appear
  - [ ] Fill in required fields
  - [ ] Save and verify
- [ ] Test adding a new **Rental** listing
  - [ ] Verify correct field groups appear
  - [ ] Fill in required fields
  - [ ] Add availability data
  - [ ] Save and verify
- [ ] Test geocoding from individual listing edit page
- [ ] Test editing existing listings

---

## ✅ Step 11: Verify Everything Works

- [ ] All listings appear on the map
- [ ] Filters work correctly
- [ ] Search location works
- [ ] Individual listing pages display correctly
- [ ] Availability data shows for rentals
- [ ] Admin functions work (add/edit listings)
- [ ] No JavaScript errors in browser console
- [ ] No PHP errors in WordPress debug log

---

## Troubleshooting

If something doesn't work:

1. **Fields not showing?**
   - Ensure Toolset Types is activated
   - **Verify field groups exist:** Check that your Toolset field groups are present in **Toolset → Custom Fields**
   - **Assign field groups:** Make sure field groups are assigned to the `listing` post type:
     - Go to **Listings → Assign Field Groups** (or **Toolset → Custom Fields**)
     - For each field group, ensure "listing" is checked under "Post Types"
   - **Note:** The plugin does NOT create field groups. They must already exist in your database.

2. **Map not loading?**
   - Ensure listings are geocoded (Step 6)
   - Check browser console for errors
   - Verify Leaflet.js is loading

3. **Filters not working?**
   - Clear WordPress cache
   - Check browser console for errors
   - Verify taxonomies have terms assigned

4. **Templates not working?**
   - Run template migration (Step 8)
   - Verify templates have proper conditions
   - Check Toolset Views is activated

---

## Quick Reference

- **Listings Archive:** `/listings/` (or your custom page)
- **Admin Menu:** Listings (in WordPress admin sidebar)
- **Settings:** Listings → Settings
- **Geocode:** Listings → Geocode Addresses
- **Migrate:** Listings → Migrate Existing Condos and Rental Properties
- **Availability:** Listings → Current Availability

---

**For detailed documentation, see:**
- [`SETUP_GUIDE.md`](SETUP_GUIDE.md) - Complete setup guide
- [`SHORTCODES.md`](SHORTCODES.md) - Available shortcodes

---

**Support:** Responsab LLC - https://www.responsab.com

