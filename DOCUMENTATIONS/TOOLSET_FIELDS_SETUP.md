# Toolset Types Field Groups Setup Guide

## Overview

The plugin now uses **Toolset Types field groups** instead of custom meta boxes. This means:
- All fields appear in the regular WordPress edit screen (as they did before)
- Fields are organized in collapsible sections (fieldsets)
- You can use Toolset Types conditional fields
- Your existing workflow is preserved

## What Changed

### Removed
- ❌ Custom meta boxes (Listing Details, Location, Property Info, etc.)
- ❌ Custom field rendering code
- ❌ Custom save handlers (Toolset Types handles this)

### Kept
- ✅ Unit Type field (small dropdown that syncs with Listing Type taxonomy)
- ✅ Admin columns (Type, Status, Location)
- ✅ Migration tools

## Setting Up Field Groups

### Step 1: Assign Existing Field Groups to "Listing" Post Type

You have two options:

**Option A: Use the Admin Tool (Easiest)**
1. Go to: **Listings → Assign Field Groups**
2. Select the field groups you want:
   - ✅ **Property Info** (applies to both condos and rentals)
   - ✅ **Rental Properties** (for rentals)
   - ✅ **Rental Lotteries** (for rentals)
   - ✅ **Condominiums** (for condos)
   - ✅ **Condo Lotteries** (for condos)
3. Click "Assign Selected Groups"

**Option B: Manual Assignment**
1. Go to: **Toolset → Custom Fields Group**
2. Edit each field group:
   - **Property Info** → Add "Listing" to Post Types
   - **Rental Properties** → Add "Listing" to Post Types
   - **Rental Lotteries** → Add "Listing" to Post Types
   - **Condominiums** → Add "Listing" to Post Types
   - **Condo Lotteries** → Add "Listing" to Post Types

### Step 2: Set Up Conditional Fields

For fields that should only show for Condos or Rentals:

1. Edit the field group in Toolset Types
2. For each field, set up conditional display:
   - **Field:** "Property Info → Some Field"
   - **Condition:** Show if "Listing Type" taxonomy equals "Condo" (or "Rental")
3. Use Toolset Types' built-in conditional logic

**Example:**
- Condo-specific fields → Show when `listing_type` = "Condo"
- Rental-specific fields → Show when `listing_type` = "Rental"
- Common fields → Show always

### Step 3: Test the Edit Screen

1. Go to: **Listings → Add New**
2. You should see:
   - **Unit Type** dropdown (sidebar) - Select Condo or Rental
   - **Property Info** fieldset (collapsible section)
   - **Rental Properties** fieldset (if Rental selected)
   - **Condominiums** fieldset (if Condo selected)
   - **Rental Lotteries** or **Condo Lotteries** (based on type)
3. All fields should work as before

## How It Works

### Unit Type Field
- Located in the sidebar when editing a listing
- Selecting "Condo" or "Rental" automatically:
  - Sets the `listing_type` taxonomy
  - Triggers Toolset Types conditional fields to show/hide

### Field Groups
- Toolset Types automatically displays field groups on the edit screen
- Fields appear as collapsible sections (fieldsets)
- All your existing fields are preserved
- Conditional fields work automatically

## Migration Notes

When you migrate posts:
- All existing fields are preserved
- Field groups need to be assigned to the "listing" post type
- Conditional fields will work automatically once assigned

## Troubleshooting

### Fields Not Showing
- Check that field groups are assigned to "listing" post type
- Verify Toolset Types is active
- Clear browser cache

### Conditional Fields Not Working
- Make sure "Unit Type" is selected (or Listing Type taxonomy is set)
- Check conditional logic in Toolset Types field settings
- Verify taxonomy slug matches (should be "condo" or "rental")

### Fields Not Saving
- Toolset Types handles saving automatically
- Check field permissions in Toolset Types
- Verify you have edit permissions

