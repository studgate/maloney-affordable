# Maloney Affordable Listings - User Guide

**Created by:** Responsab LLC  
**Website:** www.responsab.com  
**Last Updated:** November 2025

**For:** Content Editors and Administrators  
**Purpose:** Step-by-step guide for managing property listings in the new system

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Adding a New Listing](#adding-a-new-listing)
3. [Adding Rental Availability](#adding-rental-availability)
4. [Checking and Verifying Addresses](#checking-and-verifying-addresses)
5. [Geocoding Addresses](#geocoding-addresses)
6. [Editing Existing Listings](#editing-existing-listings)
7. [Common Tasks](#common-tasks)
8. [Important Notes](#important-notes)
9. [Troubleshooting](#troubleshooting)

---

## Getting Started

### ⚠️ Important: Use the New System

**DO NOT use the old post types:**
- ❌ **Condominiums** (old post type - do not use)
- ❌ **Rental Properties** (old post type - do not use)

**ALWAYS use:**
- ✅ **Listings** (new unified post type - use this!)

All new listings must be added using the **Listings** post type. The old post types are deprecated and will not appear on the website.

### Accessing Listings

1. In WordPress admin, look for **"Listings"** in the left sidebar
2. Click **"Listings"** to see all existing listings
3. Click **"Add New"** to create a new listing

---

## Adding a New Listing

### Step 1: Select Listing Type

1. Go to **Listings → Add New**
2. A modal will appear asking you to select the listing type
3. Click either:
   - **Condo** - For condominium properties
   - **Rental** - For rental properties
4. The edit screen will open with the listing type already set

**Note:** You don't need to change the listing type in the sidebar - it's already set from the modal. From here, you should add the listing info as you used to before. Be careful to use a full address and include the zip code, as an accurate address helps with the map display and geocoding.

### Step 2: Enter Property Name

1. In the title field at the top, enter the **Property Name**
   - Examples: "The Overlook at Boston" or "123 Main Street"
2. You can save as draft now or wait until you've filled in more fields

### Step 3: Fill in Property Information

The **Property Info** field group appears for all listings. Fill in:

#### Required Fields:

- **Property Address** - ⚠️ **IMPORTANT: Use a complete street address**
  - ✅ **Correct:** "123 Main Street, Boston, MA 02101"
  - ✅ **Correct:** "456 Park Avenue, Dorchester, MA 02125"
  - ❌ **Wrong:** "Fenway" (region/neighborhood only)
  - ❌ **Wrong:** "Jamaica Plain" (region/neighborhood only)
  - ❌ **Wrong:** "123 Main Street" (missing city, state, zip)
  
  **Why this matters:** The address is used for geocoding (map location). Incomplete or regional addresses will result in incorrect map placement or geocoding failure.

- **City** - City name
  - Examples: "Boston", "Cambridge", "Somerville"
  - You can include neighborhood if needed: "Fenway / Boston"
  
- **State** - State abbreviation (e.g., "MA")

- **Zip Code** - 5-digit zip code
  - Will be automatically extracted from the address when you save
  - If missing, it will be created automatically

#### Optional Fields:
- **Property Photos** - Upload property images
- **Main Description** - Brief description of the property
- **Neighborhood** - Neighborhood information
- **Telephone** - Contact phone number
- **Email** - Contact email address

### Step 4: Fill in Type-Specific Fields

#### For Condos:
The **Condominiums** field group will appear. Fill in:
- **Condo Status** - Select: FCFS Condo Sales, Active Condo Lottery, Closed Condo Lottery, etc.
- **Income Limits** - Select: Boston Inclusionary, HUD, or Custom
- **Eligibility** - Eligibility requirements
- **Unit Sizes** - Check all that apply (Studio, One Bedroom, Two Bedroom, etc.)
- **Current Condo Listings Table** - If using Ninja Tables for available units
- **Lottery Process** - If applicable
- **Application Info** - How to apply

#### For Rentals:
The **Rental Properties** field group will appear. Fill in:
- **Status** - Select: Active Rental, Open Lottery, Closed Lottery, etc.
- **Income Limits** - Select: Boston Inclusionary, HUD, or Custom
- **Eligibility** - Eligibility requirements
- **Unit Sizes** - Check all that apply
- **Online Application URL** - Link to application form
- **Application Period Starts** - Start date for applications
- **Application Deadline** - End date for applications
- **How to Request an Application** - Instructions
- **How to Submit an Application** - Instructions

### Step 5: Set Featured Image (Optional)

1. In the right sidebar, find **"Featured Image"**
2. Click **"Set featured image"**
3. Upload or select the best property photo
4. This image appears in listing cards and search results

**Note:** You don't need to set taxonomies (Amenities, Income Limit, etc.) in the sidebar right now - these can be added later if needed.

### Step 6: Publish

1. Review all fields, especially:
   - Property Address is complete (street, city, state, zip)
   - All required fields are filled in
   - Listing type is correct (already set from modal)
2. Click **"Publish"** when ready

**What happens automatically:**
- ✅ The address will be automatically geocoded (converted to map coordinates)
- ✅ The zip code will be automatically extracted from the address
- ✅ The listing will appear on the website at `/listings/` and on the map

**Note:** You don't need to manually geocode addresses - this happens automatically when you save the listing. Just make sure the address is complete and correct!

---

## Adding Rental Availability

**⚠️ Important:** You do NOT need to use Ninja Forms to add availability. All available units have been imported for you automatically from the Ninja Table data.

**If a unit is missing:**
1. Go to **Listings → Migrate Available Units**
2. Check the **"Units That Could Not Be Imported"** section
3. This will show you any units that couldn't be imported and why (e.g., property not found, property is not a rental, etc.)
4. If needed, you can manually add missing units using the method below

**Note:** This only applies to **Rental** listings.

### Method 1: From the Listing Edit Page (Manual Entry)

1. Edit the rental listing
2. Find the **"Current Rental Availability"** field group
3. Click **"Add Row"** or the **"+"** button
4. Fill in for each available unit:
   - **Unit Size** - Studio, 1-Bedroom, 2-Bedroom, etc.
   - **Bathrooms** - Number of bathrooms
   - **Total Monthly Leasing Price** - Monthly rent
   - **Minimum Income** - Minimum income required
   - **Income Limit (AMI %)** - Percentage of Area Median Income
   - **Type** - Lottery or First Come First Serve
   - **Units Available** - Number of units
   - **Accessible Units** - Description if applicable
5. Click **"Add Row"** again for additional units
6. Click **"Update"** to save

### Method 2: From the Availability Management Page (Manual Entry)

**Note:** This is only needed if units are missing after migration. Most units are imported automatically.

1. Go to **Listings → Current Availability**
2. Click **"Add New Availability"**
3. Select the **Property** from the dropdown
4. Fill in all unit details (same as Method 1)
5. Click **"Publish"**

### Tips:
- Add availability as units become available
- Remove or update availability when units are filled
- Keep the information current for accurate search results

---

## Checking and Verifying Addresses

### Why This Matters

- **Incorrect addresses** = listings won't appear on the map
- **Incomplete addresses** = geocoding will fail
- **Wrong locations** = users will be misled

### How to Check Addresses

#### 1. Check the Property Address Field

1. Edit the listing
2. Find the **"Property Address"** field in the **Property Info** group
3. Verify the address is complete:
   - ✅ **Good:** "123 Main Street, Boston, MA 02101"
   - ✅ **Good:** "456 Park Avenue, Dorchester, MA 02125"
   - ❌ **Bad:** "123 Main Street" (missing city/state/zip)
   - ❌ **Bad:** "Boston, MA" (missing street address)

#### 2. Check City and State Fields

1. Verify **City** field matches the address
2. Verify **State** field is correct (should be "MA" for Massachusetts)
3. If city has neighborhood format (e.g., "Fenway / Boston"), that's fine

#### 3. Check Zip Code

1. Look for **Zip Code** field in Property Info group
2. Verify it's a 5-digit number (e.g., "02101")
3. If missing, see [Extracting Zip Codes](#extracting-zip-codes) below

#### 4. Verify Geocoding

1. Scroll to **"Geocode Address"** meta box
2. Check if **Latitude** and **Longitude** are populated
3. If empty, the address needs to be geocoded (see [Geocoding Addresses](#geocoding-addresses))

### Common Address Issues

| Issue | Solution |
|-------|----------|
| Address missing city/state | Add city and state to the Property Address field |
| Zip code missing | Run zip code extraction (see below) |
| Address geocoded to wrong location | Check address spelling, try geocoding again |
| Duplicate city/town in address | This is a known issue - contact developer to fix |

---

## Geocoding Addresses

### What is Geocoding?

Geocoding converts addresses into map coordinates (latitude/longitude). Without geocoding, listings won't appear on the map.

### When to Geocode

- ✅ When adding a new listing
- ✅ When updating an address
- ✅ When a listing doesn't appear on the map
- ✅ After bulk address updates

### Method 1: Geocode Individual Listing (If Needed)

**Note:** Geocoding happens automatically when you save. Only use this if automatic geocoding failed.

1. Edit the listing
2. Scroll to **"Geocode Address"** meta box
3. Verify the address is complete and correct
4. Click **"Geocode Address"** button
5. Wait for latitude and longitude to appear
6. Click **"Update"** to save

### Method 2: Batch Geocode All Listings

1. Go to **Listings → Geocode Addresses**
2. Review the status:
   - **Listings with coordinates:** Already geocoded
   - **Listings needing geocoding:** Need to be geocoded
3. Click **"Start Geocoding"** button
4. Wait for the process to complete (may take several minutes)
5. Refresh the page to see updated counts
6. Repeat until "Listings needing geocoding" shows 0

### Troubleshooting Geocoding

| Problem | Solution |
|---------|----------|
| "Could not geocode address" | Check that address is complete (street, city, state, zip) |
| Geocoding stuck at certain percentage | Wait a few minutes, then refresh and try again |
| Wrong location on map | Verify address spelling, try geocoding again |
| Rate limit error | Wait 1-2 minutes, then try again (free API has limits) |

---

## Extracting Zip Codes

If listings have addresses but no zip codes:

1. Go to **Listings → Extract Zip Codes**
2. Review the list of listings without zip codes
3. Click **"Geocode & Extract Zip Codes"** (recommended)
   - This uses geocoding API to get accurate zip codes
4. OR click **"Extract Zip Codes from Addresses"**
   - This uses pattern matching (faster but less accurate)
5. Wait for processing to complete
6. Review successful extractions
7. Check individual listings to verify zip codes

**Note:** This will automatically create the Zip Code field if it doesn't exist.

---

## Editing Existing Listings

### Basic Editing

1. Go to **Listings → All Listings**
2. Find the listing you want to edit
3. Click on the listing title or **"Edit"** link
4. Make your changes
5. Click **"Update"** to save

### Bulk Actions

1. Go to **Listings → All Listings**
2. Check the boxes next to listings you want to modify
3. Select an action from the **"Bulk Actions"** dropdown:
   - **Edit** - Change multiple fields at once
   - **Move to Trash** - Delete listings
4. Click **"Apply"**

### Quick Edit

1. Go to **Listings → All Listings**
2. Hover over a listing
3. Click **"Quick Edit"**
4. Make quick changes (title, status, date, etc.)
5. Click **"Update"**

---

## Common Tasks

### Making a Listing Active/Inactive

1. Edit the listing
2. In the right sidebar, find **"Listing Status"**
3. Select:
   - **Active** - Listing appears on website
   - **Inactive** - Listing hidden from website
4. Click **"Update"**

### Updating Availability for Rentals

1. Edit the rental listing
2. Find **"Current Rental Availability"** field group
3. To add: Click **"Add Row"** and fill in details
4. To edit: Modify existing rows
5. To remove: Delete the row or set "Units Available" to 0
6. Click **"Update"**

### Changing Listing Type (Condo ↔ Rental)

**Note:** Listing type is set when you first create the listing via the modal. If you need to change it:

1. Edit the listing
2. In right sidebar, find **"Listing Type"** taxonomy
3. Remove the current type and add the new type
4. **Note:** This will change which field groups appear
5. Fill in the appropriate fields for the new type
6. Click **"Update"**

### Finding Listings Without Addresses

1. Go to **Listings → Geocode Addresses**
2. Look at the "Listings needing geocoding" section
3. These listings likely have incomplete or missing addresses
4. Edit each listing and add/verify the address

### Finding Listings Without Zip Codes

1. Go to **Listings → Extract Zip Codes**
2. Review the list of listings without zip codes
3. These listings need zip code extraction or manual entry

---

## Important Notes

### ⚠️ Do NOT Use Old Post Types

- **Never** add listings using "Condominiums" or "Rental Properties" post types
- These are deprecated and won't appear on the website
- Always use **"Listings"** post type

### Address Format

- ⚠️ **Always use a complete street address** - Never use just a region or neighborhood
- ✅ **Correct format:** "123 Main Street, Boston, MA 02101"
- ✅ **Correct format:** "456 Park Avenue, Dorchester, MA 02125"
- ❌ **Wrong:** "Fenway" (region only - no street address)
- ❌ **Wrong:** "Jamaica Plain" (neighborhood only - no street address)
- ❌ **Wrong:** "123 Main Street" (missing city, state, zip)
- ❌ **Wrong:** "Boston | East Boston, MA" (duplicate city information)

**Why this matters:**
- Incomplete addresses cause geocoding to fail
- Regional addresses (like "Fenway" or "Jamaica Plain") will place the marker in the wrong location
- The map needs a specific street address to show the correct location

### Geocoding is Automatic

- ✅ Addresses are automatically geocoded when you save a listing
- ✅ You don't need to manually geocode - it happens automatically
- ⚠️ **Important:** Make sure the address is complete and correct (street, city, state, zip)
- If geocoding fails, check that the address is properly formatted

### Availability is Rental-Only

- Only **Rental** listings have availability fields
- **Condo** listings don't have availability (they use different systems)

### Field Groups

- **Property Info** - Appears for all listings
- **Condominiums** - Only appears for Condo listings
- **Rental Properties** - Only appears for Rental listings
- **Current Rental Availability** - Only appears for Rental listings

---

## Troubleshooting

### Listing Not Appearing on Website

**Check:**
1. Is the listing published? (not draft or pending)
2. Is the listing status set to "Active"?
3. Is the listing type set correctly?
4. Does it match any active filters on the listings page?

### Listing Not Appearing on Map

**Check:**
1. Is the address complete? (street, city, state, zip)
2. Is the listing geocoded? (check for latitude/longitude)
3. Go to **Listings → Geocode Addresses** and geocode if needed

### Fields Not Showing

**Check:**
1. Is the correct listing type selected? (Condo vs Rental)
2. Are field groups assigned to the listing post type?
3. Check screen options (top right) - are field groups hidden?

### Can't Save Listing

**Check:**
1. Are all required fields filled in?
2. Is there a validation error? (check for red error messages)
3. Try saving as draft first, then publish

### Address Geocoding Fails

**Check:**
1. Is the address complete and correctly formatted?
2. Try geocoding from the individual listing page
3. Check for typos in the address
4. Wait a few minutes if you see rate limit errors

### Availability Not Showing

**Check:**
1. Is this a Rental listing? (Condos don't have availability)
2. Are availability rows added in the "Current Rental Availability" field group?
3. Is "Units Available" greater than 0?
4. Is the listing published and active?

---

## Getting Help

If you encounter issues not covered in this guide:

1. Check the [Setup Guide](SETUP_GUIDE.md) for technical details
2. Contact the development team
3. Check WordPress admin for error messages
4. Verify all required fields are filled in

---

## Quick Reference

### Where to Find Things

| Task | Location |
|------|----------|
| Add new listing | **Listings → Add New** |
| View all listings | **Listings → All Listings** |
| Geocode addresses | **Listings → Geocode Addresses** |
| Extract zip codes | **Listings → Extract Zip Codes** |
| Manage availability | **Listings → Current Availability** |
| Plugin settings | **Listings → Settings** |

### Required Fields Checklist

**For All Listings:**
- [ ] Property Name (title)
- [ ] Listing Type (Condo or Rental)
- [ ] Property Address
- [ ] City
- [ ] State
- [ ] Zip Code (can be auto-extracted)
- [ ] Geocoded (latitude/longitude)

**For Condos:**
- [ ] Condo Status
- [ ] Income Limits
- [ ] Eligibility

**For Rentals:**
- [ ] Status
- [ ] Income Limits
- [ ] Eligibility
- [ ] Online Application URL (if active)

---

**Last Updated:** November 2025  
**Version:** 1.0

