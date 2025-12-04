# Maloney Affordable Listings Plugin - Complete Setup Guide

**Developer:** Ralph Francois  
**Company:** Responsab LLC.  
**Website:** https://www.responsab.com

## Overview

This plugin provides a comprehensive listing management system for affordable housing properties (condos and rentals) with advanced filtering, map integration, and availability tracking. The plugin is designed to work independently with minimal manual setup. Most features are automatically configured on plugin activation.

## Table of Contents

1. [Fresh Database Setup](#fresh-database-setup)
2. [Automatic Setup](#automatic-setup)
3. [Manual Setup Steps](#manual-setup-steps)
4. [Plugin Settings](#plugin-settings)
5. [Shortcodes](#shortcodes)
6. [Troubleshooting](#troubleshooting)

---

## Fresh Database Setup

When setting up from a fresh database snapshot, follow these steps in order:

### Step 1: Activate Required Plugins

1. **Activate Toolset Types**
   - **Important:** Toolset Types must be activated first
   - All Toolset field groups and custom fields should already exist in your database
   - The plugin does NOT create or modify Toolset field groups or fields
   - The plugin only reads existing Toolset field data

2. **Activate Maloney Affordable Listings Plugin**
   - All post types and taxonomies will be created automatically
   - The plugin will NOT create or modify any Toolset field groups or fields
   - The plugin assumes all Toolset fields and field groups already exist

### Step 2: Verify Divi Child Theme Setup

**Important:** The plugin requires the Divi Child theme to be active and properly configured.

1. **Verify Divi Child Theme is Active**
   - Go to **Appearance → Themes**
   - Ensure "Divi Child" is active (not parent Divi theme)

2. **Verify Required Files Exist**
   The Divi Child theme should have these files:
   - `wp-content/themes/Divi-Child/functions.php` - Should define `MALONEY_LISTINGS_PLUGIN_DIR` constant
   - `wp-content/themes/Divi-Child/page-listings.php` - Template for listings page (optional, for page template)

3. **If Files Are Missing**
   - The `functions.php` file should include code to define the plugin directory constant
   - If you're using a page template, ensure `page-listings.php` exists
   - See the plugin's Divi Child theme files for reference

### Step 3: Configure Permalinks

1. Go to **Settings → Permalinks**
2. Click **"Save Changes"** (even if you don't change anything)
   - This flushes rewrite rules and ensures proper URL structure
   - The plugin also does this automatically on activation, but it's good to verify
   - **Important:** After changing the post type slug (from `listing` to `listings`), you must flush permalinks for the new URLs to work

### Step 4: Assign Toolset Field Groups to Listing Post Type

**Important:** All Toolset field groups and custom fields should already exist in your database. The plugin does NOT create them.

To assign existing field groups to the `listing` post type:

1. Go to **Listings → Assign Field Groups** (this redirects to Toolset's native interface)
   - OR go directly to **Toolset → Custom Fields**
2. For each field group you want to use with listings:
   - Click on the field group name
   - Under "Post Types", check the box for **"listing"**
   - Click **"Save"**

**Common field groups to assign:**
- **Property Info** - Common fields for all listings (address, city, zip, etc.)
- **Condominiums** - Fields specific to condo listings
- **Condo Lotteries** - Lottery-specific fields for condos
- **Rental Properties** - Fields specific to rental listings
- **Rental Lotteries** - Lottery-specific fields for rentals
- **Current Rental Availability** - Repetitive fields for tracking available units

**Important: Zip Code Field**
- The **Zip Code** field is automatically created when you run the "Extract Zip Codes" feature (see Step 6)
- The field will be automatically added to the **Property Info** field group and positioned right after the State field
- Field slug: `zip-code` (Toolset creates it as `wpcf-zip-code` in the database)
- If you need to create it manually:
  1. Go to **Toolset → Custom Fields**
  2. Click on **Property Info** field group
  3. Add a new field: **Zip Code** (slug: `zip-code`)
  4. Set field type to **Text** or **Number**
  5. Save the field group

**Note:** The plugin will NOT create or modify these field groups. They must already exist in your Toolset installation.

### Step 5: Migrate Existing Data (If Applicable)

#### 4a. Migrate Existing Listings

If you have existing listings in the old `condominiums` or `rental-properties` post types:

1. Go to **Listings → Migrate Existing Condos and Rental Properties**
2. Review the migration options
3. Click **"Start Migration"**
4. This will:
   - Copy all posts to the new `listing` post type
   - Preserve all custom fields and taxonomies
   - Map old post types to `listing_type` taxonomy
   - Maintain all relationships and metadata

**Note:** This is a one-time migration. After migration, you can continue using the new `listing` post type.

#### 4b. Migrate Available Units

If you have available units data in Ninja Tables (Table ID 790):

1. Go to **Listings → Migrate Available Units**
2. Click **"Start Migration"**
3. This will migrate all availability data to the new repetitive field structure
4. After migration, manage availability through:
   - **Listings → Current Availability** (dedicated page)
   - Individual listing edit pages (availability fieldset)

**Note:** This is a one-time migration. After migration, manage availability through the admin interface.

### Step 6: Extract Zip Codes (If Needed)

If your listings have addresses but no zip codes:

1. Go to **Listings → Extract Zip Codes**
2. Review the list of listings without zip codes
3. Click **"Geocode & Extract Zip Codes"** (recommended - uses geocoding API)
   - OR click **"Extract Zip Codes from Addresses"** (simple pattern matching)
4. Wait for processing to complete
5. Review successful extractions and any errors
6. Verify extracted zip codes in listing edit pages

**Note:** This will automatically create the **Zip Code** field in the Property Info field group if it doesn't exist, and position it right after the State field.

### Step 7: Geocode Addresses

To enable map functionality, geocode your listing addresses:

1. Go to **Listings → Geocode Addresses**
2. Review the geocoding status
3. Click **"Start Geocoding"** to batch geocode all listings
   - This uses the OpenStreetMap Nominatim API (free, but rate-limited)
   - For production, consider using a paid geocoding service
4. Or geocode individual listings from the listing edit page

**Important:** Listings without geocoded addresses will not appear on the map.

### Step 8: Create Listings Page

The plugin does not automatically create a listings page. You need to create one manually:

#### Option A: Use the Archive Page (Easiest)

1. The plugin creates an archive page automatically at `/listings/`
2. Visit `/listings/` to see all listings
3. No additional setup needed - the archive page works out of the box

#### Option B: Create a Custom Page with Shortcode

1. Go to **Pages → Add New**
2. Give it a title (e.g., "Listings" or "Find a Home")
3. Add the shortcode: `[maloney_listings_view]`
4. Publish the page
5. Set it as your listings page in your navigation menu

#### Option C: Create a Page with Gutenberg Block

1. Go to **Pages → Add New**
2. Give it a title (e.g., "Listings" or "Find a Home")
3. Click the "+" button to add a block
4. Search for "Listings View" in the block inserter
5. Add the block
6. Configure the listing type filter in the block settings (right sidebar)
7. Publish the page
8. Set it as your listings page in your navigation menu

**Note:** The shortcode and block will display the full listings interface with map, filters, and search functionality.

### Step 9: Update Navigation Menu

After creating your listings page, update your main navigation menu to include it and update existing rental/buy links:

#### Add Listings Page to Menu

1. Go to **Appearance → Menus**
2. Select your main navigation menu (or create a new one)
3. In the left sidebar, find **Pages** and check the box for your **Listings** page
4. Click **"Add to Menu"**
5. Drag the page to your desired position in the menu
6. Click **"Save Menu"**

#### Update Rental and Buy Links with Filters

To link directly to filtered listings (rentals or condos), you can either:

**Option A: Use Custom Links with URL Parameters**

1. In the menu editor, click **"Custom Links"** in the left sidebar
2. For **Rentals** link:
   - **URL:** `/listings/?type=rental`
   - **Link Text:** "Rentals" or "Apartment Rentals"
   - Click **"Add to Menu"**
3. For **Condos/Buy** link:
   - **URL:** `/listings/?type=condo`
   - **Link Text:** "Buy" or "Condominiums"
   - Click **"Add to Menu"**
4. Drag these links to your desired positions
5. Click **"Save Menu"**

**Option B: Use the Listings Link Shortcode (in page content)**

If you want to add links within page content, use the shortcode:
- `[maloney_listings_link type="rental" text="View Rentals"]`
- `[maloney_listings_link type="condo" text="View Condos"]`

**Note:** The `?type=rental` and `?type=condo` parameters will automatically filter the listings page to show only rentals or condos respectively.

### Step 10: Update Current Availability Page

If you have a "Current Rental Availability" page, update it to use the shortcode:

1. Go to **Pages** and find your "Current Rental Availability" page (or create a new one)
2. Edit the page
3. Remove any old content or Toolset Views shortcodes
4. Add the shortcode: `[maloney_available_units]`
   - Or with a custom title: `[maloney_available_units title="Current Rental Availability"]`
5. **Alternative:** Use the Gutenberg block:
   - Click the "+" button to add a block
   - Search for "Current Rental Availability"
   - Add the block
   - Customize the title in block settings if needed
6. Publish/Update the page

**Shortcode Details:**
- **Shortcode:** `[maloney_available_units]`
- **Optional Parameter:** `title="Your Title Here"` (default: "Current Rental Availability")
- **What it does:** Displays a comprehensive table of all available rental units from all properties
- **Block Name:** "Current Rental Availability" (in Widgets category)

### Step 11: Check and Update Key Pages

Verify and update these important pages:

#### Homepage

1. Go to **Pages** and edit your homepage
2. Add the **Listings Search Form** using either method:

   **Option A: Using Gutenberg Block**
   - Click the "+" button to add a block
   - Search for "Listings Search Form"
   - Add the block
   - Customize settings in the block sidebar (show/hide tabs, placeholder text, button text)

   **Option B: Using Shortcode** (for page builders or classic editor)
   - Add the shortcode: `[maloney_listings_search_form]`
   - Or with custom settings: `[maloney_listings_search_form placeholder="Enter city or zip" button_text="Search"]`
   - To hide tabs: `[maloney_listings_search_form show_tabs="0"]`

3. **Listings Search Form Details:**
   - **Block Name:** "Listings Search Form" (in Widgets category)
   - **Shortcode:** `[maloney_listings_search_form]`
   - **Features:** Condo/Rental tabs, location autocomplete, redirects to listings page with filters
   - **Shortcode Parameters:**
     - `placeholder="text"` - Custom placeholder text
     - `button_text="text"` - Custom button text
     - `show_tabs="1"` or `show_tabs="0"` - Show/hide Condo/Rental tabs
   - **Block Settings:**
     - Show Condo/Rental Tabs (toggle on/off)
     - Placeholder Text (default: "Search location or zip code...")
     - Button Text (default: "Get started")
4. Save/Update the page

#### Apartment Rentals Page (`/apartment-rentals/`)

1. Go to **Pages** and find or create the "Apartment Rentals" page
2. Edit the page
3. Add the listings view filtered for rentals:
   - **Option A:** Use shortcode: `[maloney_listings_view type="rental"]`
   - **Option B:** Use Gutenberg block:
     - Add "Listings View" block
     - In block settings, set "Listing Type Filter" to "Rentals Only"
4. Save/Update the page
5. Update the page slug to `apartment-rentals` if needed (in Page Settings → Permalink)

#### Listings Page

1. Verify your main listings page is set up (from Step 8)
2. Ensure it uses either:
   - The archive page at `/listings/` (automatic)
   - OR a custom page with `[maloney_listings_view]` shortcode
   - OR a custom page with "Listings View" Gutenberg block
3. Test the page to ensure filters and map are working

### Step 12: Available Shortcodes and Blocks Summary

#### Shortcodes

1. **`[maloney_listings_view]`** - Main listings view with map and filters
   - Parameters: `type="units|condo|rental"` (optional)
   - Example: `[maloney_listings_view type="rental"]`

2. **`[maloney_available_units]`** - Current rental availability table
   - Parameters: `title="Your Title"` (optional)
   - Example: `[maloney_available_units title="Current Rental Availability"]`

3. **`[maloney_listings_link]`** - Link to listings page with filter
   - Parameters: `type="condo|rental"` (optional), `text="Link Text"` (optional)
   - Example: `[maloney_listings_link type="rental" text="View Rentals"]`

4. **`[maloney_listing_availability]`** - Availability table for a specific listing
   - Parameters: `id="123"` or `slug="listing-slug"` (optional, uses current post if not provided)
   - Example: `[maloney_listing_availability id="123"]`

5. **`[maloney_listings_home_option]`** - Home page option card
   - Parameters: `type="condo|rental|units"`, `title="Title"`, `image="url"`, etc.
   - Example: `[maloney_listings_home_option type="rental"]`

6. **`[maloney_listings_search_form]`** - Search form with Condo/Rental tabs
   - Parameters: `placeholder="text"` (optional), `button_text="text"` (optional), `show_tabs="1|0"` (optional)
   - Example: `[maloney_listings_search_form]`
   - Example: `[maloney_listings_search_form placeholder="Enter city or zip" button_text="Search"]`
   - Example: `[maloney_listings_search_form show_tabs="0"]` - Hide tabs

#### Gutenberg Blocks

1. **"Listings View"** - Main listings view (same as shortcode)
   - Category: Widgets
   - Settings: Listing Type Filter (All Listings, Condominiums Only, Rentals Only)

2. **"Current Rental Availability"** - Availability table (same as shortcode)
   - Category: Widgets
   - Settings: Title (customizable)

3. **"Listings Search Form"** - Search form with Condo/Rental tabs
   - Category: Widgets
   - **Available as:** Both Gutenberg block AND shortcode
   - **Shortcode:** `[maloney_listings_search_form]`
   - **Shortcode Parameters:**
     - `placeholder="Search location or zip code..."` (optional)
     - `button_text="Get started"` (optional)
     - `show_tabs="1"` (optional, use "1" to show tabs, "0" to hide)
   - **Shortcode Examples:**
     - `[maloney_listings_search_form]` - Default settings
     - `[maloney_listings_search_form placeholder="Enter city or zip" button_text="Search"]`
     - `[maloney_listings_search_form show_tabs="0"]` - Hide Condo/Rental tabs
   - **Block Settings:**
     - Show Condo/Rental Tabs (toggle)
     - Placeholder Text
     - Button Text
   - **Features:**
     - Location autocomplete (cities and zip codes)
     - Redirects to listings page with filters applied
     - URL format: `/listings/?type=rental&city=Boston` or `/listings/?type=condo&zip=02115`

### Step 13: Set Up Toolset Templates (Optional)

If you want to use custom templates for listings:

1. Go to **Listings → Setup Toolset Templates**
2. Click **"Migrate Templates"** to copy conditional templates from old post types
3. This will migrate templates from `condominiums` and `rental-properties` to `listing` with proper conditions
4. The plugin will automatically replace old availability blocks with the new shortcode

**Note:** This is only needed if you have existing Toolset Views templates you want to migrate.

### Step 14: Configure Plugin Settings

1. Go to **Listings → Settings**
2. Configure the following options:
   - **Enable "Search this area" feature** - Toggle map area search
   - **Rental Badge Color** - Customize rental badge color (default: rgba(232, 105, 98, 1))
   - **Condo Badge Color** - Customize condo badge color (default: rgba(228, 199, 128, 1))
   - **Enable Directions Button** - Show/hide directions button on individual listing maps
   - **Enable Street View Button** - Show/hide street view button on individual listing maps
   - **"Just Listed" Period** - Set the period for "just listed" filter (1, 3, 7, or 14 days)
   - **Enable Bathrooms Filter** - Show/hide bathrooms filter on listings page
   - **Enable Income Limits Filter** - Show/hide income limits filter on listings page

### Step 15: Test the System

1. **Test Listings Page:**
   - Visit the listings archive page (`/listings/`) or your custom listings page
   - Verify filters are working
   - Check that map displays correctly
   - Test mobile view toggle

2. **Test Shortcode/Block:**
   - Create a test page
   - Add the shortcode `[maloney_listings_view]` or the "Listings View" block
   - Verify the listings display correctly with all features

3. **Test Individual Listings:**
   - Visit a few individual listing pages
   - Verify all fields display correctly
   - Check that maps load properly
   - Test "Back to Results" link

3. **Test Admin Functions:**
   - Add a new listing (both condo and rental)
   - Verify conditional fields show/hide correctly
   - Add availability data for a rental
   - Test geocoding from edit page

---

## Automatic vs Manual Setup

### What Happens Automatically

When you activate the plugin, the following are set up **automatically** (no action required):

1. **Custom Post Type:** `listing` post type is registered
2. **Taxonomies:** All required taxonomies are created:
   - `listing_type` (Condo, Rental)
   - `listing_status` (various statuses)
   - `amenities` (Air Conditioning, Dishwasher, Gym, Laundry Facilities, Parking, Pool)
   - `income_limit` (50%, 60%, 70%, 80%, 90%, 100%, 110%, 120%, 150%)
   - `concessions` (1 Month free)
   - `property_accessibility` (Elevator, Step-Free Entrance, Wheelchair Access, Roll-in Showers)
3. **Database Tables:** Vacancy notifications table is created
4. **Rewrite Rules:** Permalinks are flushed automatically

**Important:** The plugin does NOT create or modify Toolset field groups or custom fields. All Toolset fields and field groups must already exist in your database. The plugin only reads existing Toolset field data.

### What Requires Manual Setup

The following steps must be done **manually** after plugin activation:

1. **Assign Toolset Field Groups** - Link existing field groups to the `listing` post type (see Step 4 in Fresh Database Setup)
2. **Create Listings Page** - Set up the page where listings will be displayed (see Step 8 in Fresh Database Setup)
3. **Geocode Addresses** - Convert addresses to map coordinates (see Step 7 in Fresh Database Setup)
4. **Extract Zip Codes** - Extract zip codes from addresses if needed (see Step 6 in Fresh Database Setup)
5. **Configure Settings** - Customize plugin settings (see Step 10 in Fresh Database Setup)
6. **Migrate Data** - If migrating from old post types (see Step 5 in Fresh Database Setup)

**Note:** The "Fresh Database Setup" section above provides step-by-step instructions for all manual setup tasks. The sections below provide additional details for specific manual tasks.

---

## Plugin Settings

Configure plugin settings at **Listings → Settings**:

### Frontend Features
- **Enable "Search this area"** - Toggle the map area search feature
- **Rental Badge Color** - Customize the color of rental badges
- **Condo Badge Color** - Customize the color of condo badges
- **Enable Directions Button** - Show/hide directions button on individual listing maps
- **Enable Street View Button** - Show/hide street view button on individual listing maps

### Filter Settings
- **"Just Listed" Period** - Set the period for "just listed" filter (One day, 3 days, 7 days, 14 days)
- **Enable Bathrooms Filter** - Show/hide bathrooms filter on listings page
- **Enable Income Limits Filter** - Show/hide income limits filter on listings page

---

## Shortcodes

The plugin provides several shortcodes for displaying listing information:

### Available Units Block
Display all available units from all rental properties:

```
[maloney_available_units]
```

### Listing Availability
Display availability table for a specific listing (use on individual listing pages):

```
[maloney_listing_availability]
```

### Listings Link
Generate a link to the listings page with optional type filter:

```
[maloney_listings_link type="rental"]
[maloney_listings_link type="condo"]
[maloney_listings_link]
```

**For a complete list of shortcodes, see:** [`SHORTCODES.md`](SHORTCODES.md)

---

## Dependencies

### Required
- WordPress 5.0+
- PHP 7.4+

### Optional (Recommended)
- **Toolset Types** - For custom fields and field groups
- **Toolset Views** - For custom templates (optional)

### External Services
- **OpenStreetMap Nominatim API** - For geocoding (free, rate-limited)
- **Leaflet.js** - For maps (included in plugin)

---

## Troubleshooting

### Fields Not Showing
- Ensure Toolset Types is activated
- **Verify field groups exist:** Check that your Toolset field groups are present in **Toolset → Custom Fields**
- **Assign field groups:** Make sure field groups are assigned to the `listing` post type:
  - Go to **Listings → Assign Field Groups** (or **Toolset → Custom Fields**)
  - For each field group, ensure "listing" is checked under "Post Types"
- Verify field groups are not hidden in screen options
- **Note:** The plugin does NOT create field groups. They must already exist in your database.

### Templates Not Working
- Ensure Toolset Views is activated
- Run the template migration tool
- Check that templates have proper conditions set
- Verify templates are assigned to the `listing` post type

### Map Not Loading
- Ensure listings have geocoded addresses (latitude/longitude)
- Check browser console for JavaScript errors
- Verify Leaflet.js is loading correctly
- Check that map container exists on the page

### Filters Not Working
- Clear WordPress cache
- Check that taxonomies have terms assigned
- Verify custom fields are saved correctly
- Check browser console for JavaScript errors

### Geocoding Issues
- Check that addresses are properly formatted
- Verify OpenStreetMap Nominatim API is accessible
- Check for rate limiting (free API has limits)
- Review geocoding status page for errors

### Availability Data Not Showing
- Verify availability entries are saved correctly
- Check that the listing is a rental (availability is rental-only)
- Ensure the shortcode is placed correctly in the template
- Check that Toolset repetitive fields are working

### Conditional Fields Not Showing/Hiding
- Verify Toolset Types is activated
- Check that listing type taxonomy is set correctly
- Ensure field groups have proper conditional logic
- Clear browser cache and refresh

---

## Support

For issues or questions, contact:
- **Developer:** Ralph Francois
- **Company:** Responsab LLC.
- **Website:** https://www.responsab.com

---

## Additional Documentation

- **User Guide:** See [`USER_GUIDE.md`](USER_GUIDE.md) for step-by-step instructions on adding and managing listings (for content editors)
- **Shortcodes:** See [`SHORTCODES.md`](SHORTCODES.md) for complete shortcode documentation
- **Database Import:** See [`DOCUMENTATIONS/DATABASE_IMPORT_SETUP.md`](../../DOCUMENTATIONS/DATABASE_IMPORT_SETUP.md) for detailed database import instructions
