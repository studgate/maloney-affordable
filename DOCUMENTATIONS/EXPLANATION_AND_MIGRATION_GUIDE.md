# Explanation & Migration Guide

## What I Built - Explained

### What is the "Admin Management Interface"?

The **Admin Management Interface** is a custom admin page in WordPress that gives you a centralized dashboard to manage all your listings. Here's what it does:

**Location:** WordPress Admin → Listings → Manage Listings

**Features:**
- Shows ALL listings in one table (both condos and rentals)
- Displays key info: Type, Status, Location, Bedrooms, Price
- **Bulk Actions**: Select multiple listings and:
  - Change status to "Available", "Waitlist", or "Not Available"
  - Delete multiple listings at once
- Quick links to edit or view each listing
- Color-coded status badges for easy visual scanning

**Why it's useful:** Instead of managing condos and rentals separately, you can see and manage everything in one place.

---

## What Needs to Happen Now

You have existing:
1. **Post Types**: `condominium` and `rental` (managed via Toolset Types)
2. **Custom Fields**: Existing fields for each post type
3. **Frontend Pages**: `/condos-for-sale/` and `/rental-properties/`

We need to:
1. **Migrate** existing posts to the new unified `listing` post type
2. **Map** existing fields to the new system
3. **Add conditional fields** (show condo fields when "Condo" selected, rental fields when "Rental" selected)
4. **Create** new unified frontend page `/listings/` with all filters
5. **Preserve** existing data

---

## Step-by-Step Plan

### Step 1: Identify Existing Fields

First, we need to see what fields exist for your current post types. The migration script will:
- Find all existing `condominium` posts
- Find all existing `rental` posts  
- List all their custom fields
- Map them to the new `listing` post type

### Step 2: Create Migration Script

The script will:
- Create new `listing` posts from existing `condominium` and `rental` posts
- Copy all custom field values
- Set the `listing_type` taxonomy (Condo or Rental)
- Preserve all relationships (locations, etc.)
- Create redirects from old URLs to new ones

### Step 3: Add Conditional Fields

When editing a listing:
- If "Condo" is selected → Show condo-specific fields
- If "Rental" is selected → Show rental-specific fields
- Common fields (bedrooms, bathrooms, etc.) always show

### Step 4: Create Unified Frontend Page

Create a new page at `/listings/` that:
- Shows ALL listings (condos + rentals)
- Has filters for type, location, bedrooms, price, etc.
- Toggle between Card View and Map View
- Replaces or works alongside existing pages

---

## Next Steps

I'll now create:
1. A field discovery script (to see what fields you have)
2. A migration script (to move data)
3. Conditional field logic (to show/hide fields based on type)
4. A unified frontend page template

Let me know:
- What are the exact post type slugs? (e.g., `condominium`, `rental`, or something else?)
- Should I create the migration script first so you can see what fields exist?
- Do you want to keep the old pages (`/condos-for-sale/` and `/rental-properties/`) or redirect them to the new `/listings/` page?

