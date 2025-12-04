# Admin Tweaks Summary

**Developer:** Responsab LLC  
**Website:** https://www.responsab.com

This document summarizes the admin interface tweaks and answers to questions.

---

## 1. ✅ Removed Status Filter from Backend Listings Page

**Status:** Completed

The status filter dropdown has been removed from the backend listings page (`edit.php?post_type=listing`). Only the "Type" and "Has Units" filters remain.

**Files Modified:**
- `includes/class-admin.php` - Removed status filter from `add_admin_list_filters()` and `apply_admin_list_filters()`

---

## 2. ✅ Added Description Under Title in Backend Listings Page

**Status:** Completed

A helpful description now appears below the "Listings" title on the backend listings page, explaining how to use the filters.

**Implementation:**
- Uses `admin_notices` hook to display an info notice
- Only shows on the listings edit page (`edit.php?post_type=listing`)

**Files Modified:**
- `includes/class-admin.php` - Added `add_listings_page_description()` method

---

## 3. ✅ Fixed Current Rental Availability Showing for Condos

**Status:** Completed

The "Current Rental Availability" meta box now only appears when:
- Editing an existing rental property
- Adding a new listing with `unit_type=rental` in the URL
- The unit_type field is set to "rental"

**Implementation:**
- Updated `add_availability_meta_box()` to conditionally add the meta box only for rentals
- JavaScript in `conditional-fields.js` also hides it for condos as a backup
- Meta box render function double-checks and shows a message if accessed for non-rentals

**Files Modified:**
- `includes/class-admin.php` - Updated `add_availability_meta_box()` method
- `assets/js/conditional-fields.js` - Already had logic to hide it (backup)

---

## 4. ✅ Removed Custom Fields Meta Box

**Status:** Completed

The default WordPress "Custom Fields" meta box has been removed from the listing edit screen. This prevents confusion since all custom fields are managed through Toolset Types field groups.

**Implementation:**
- Uses `remove_meta_box('postcustom', 'listing', 'normal')` on `add_meta_boxes` hook with priority 99

**Files Modified:**
- `includes/class-admin.php` - Added `remove_custom_fields_meta_box()` method

---

## 5. ✅ Added Description Under Filter by Property

**Status:** Completed

A helpful description has been added under the "Filter by Property" field on the Current Availability page, explaining that users should search for a property to filter entries, and use the "+ Add New Current Availability" button to add new entries.

**Files Modified:**
- `includes/class-admin.php` - Added description paragraph in `render_add_availability_page()`

---

## 6. ❓ Grouping Taxonomies Under Submenu

**Status:** Research Needed

**Question:** Can we group listing taxonomies (Listing Types, Listing Status, Amenities, Income Limits, Concessions, Property Accessibility) under a new "Listing Taxonomies" submenu?

**Answer:** Yes, this is possible but requires custom menu manipulation using WordPress hooks. However, it's not straightforward because:

1. **WordPress Default Behavior:** Taxonomies automatically appear in the admin menu under their associated post type (Listings)
2. **Custom Implementation Required:** We would need to:
   - Remove taxonomies from their default locations
   - Create a custom submenu page under "Listings"
   - Manually add links to each taxonomy management page
   - Handle menu highlighting and active states

**Complexity:** Medium to High

**Recommendation:** Since you mentioned "if not possible or hard, don't do it", I recommend **NOT implementing this** because:
- It requires significant custom code
- It may break with WordPress updates
- The current organization (taxonomies under Listings) is standard WordPress behavior
- Users familiar with WordPress will expect taxonomies under the post type menu

**Alternative:** If you want better organization, we could:
- Add a separator in the menu
- Use menu icons to visually group related items
- Add a "Taxonomies" link that opens a page listing all taxonomy management links

Would you like me to implement the alternative approach, or leave it as-is?

---

## 7. 📋 Vacancy Notifications - What Is It?

**Status:** Documented

**What It Is:**
Vacancy Notifications is a feature that allows users to subscribe to be notified when a listing becomes available.

**Original Purpose:**
The system was designed to:
1. Allow users to sign up for email notifications when a property they're interested in becomes available
2. Track subscribers in a database table
3. Automatically send email notifications when a listing's status changes to "Available"
4. Manage notification subscriptions in the admin area

**How It Works:**
1. **Frontend:** Users can fill out a form on listing pages (when status is "Waitlist" or "Not Available") with:
   - Email (required)
   - Name (optional)
   - Phone (optional)

2. **Database:** Subscriptions are stored in `wp_vacancy_notifications` table with:
   - Listing ID
   - Email address
   - Name and phone (optional)
   - Status (pending, notified, cancelled)
   - Created and notified timestamps

3. **Automatic Notifications:** When a listing's status changes to "Available":
   - The system finds all pending subscribers for that listing
   - Sends an email notification to each subscriber
   - Updates their status to "notified"

4. **Admin Management:** Admins can view all notifications at **Listings → Vacancy Notifications**

**Current Status:**
- Database table is created automatically
- AJAX handlers are registered
- Admin page exists
- Frontend form may need to be added to listing templates (check if it's already there)

**Files:**
- `includes/class-vacancy-notifications.php` - Main class
- `templates/single-listing.php` - May contain form (check)
- `templates/parts/single-listing-content.php` - May contain form (check)

---

## 8. 🔍 Two Template Blocks Menus - Which One to Keep?

**Status:** Analysis Complete

There are **two** template blocks management pages:

### Menu 1: "Template Blocks (Beta)"
- **Location:** `Listings → Template Blocks (Beta)`
- **File:** `includes/class-template-tools.php`
- **Class:** `Maloney_Listings_Template_Tools`
- **Features:**
  - Simple block replacement interface
  - Uses `parse_blocks()` and `serialize_blocks()`
  - Quick URL endpoint for replacements
  - Basic pattern matching
  - **Status:** Marked as "Beta"

### Menu 2: "Template Blocks"
- **Location:** `Listings → Template Blocks`
- **File:** `includes/class-admin.php` (method: `render_template_blocks_page()`)
- **Class:** Uses `Maloney_Listings_Toolset_Template_Blocks`
- **Features:**
  - More comprehensive interface
  - Insert blocks functionality
  - Replace blocks functionality
  - Debug/view blocks in templates
  - Better error handling
  - More detailed feedback
  - **Status:** Production-ready

**Recommendation:** **Keep Menu 2 ("Template Blocks")** and **Remove Menu 1 ("Template Blocks (Beta)")**

**Reasoning:**
1. Menu 2 is more comprehensive and feature-complete
2. Menu 2 is the one referenced in the setup documentation
3. Menu 2 has better error handling and user feedback
4. Menu 1 is marked as "Beta" and appears to be an earlier version
5. Having two similar menus is confusing

**Action Required:**
- Remove or comment out the initialization of `Maloney_Listings_Template_Tools` in `maloney-listings.php`
- Or remove the `add_tools_page()` call from the class

---

## Summary of Changes Made

✅ **Completed:**
1. Removed status filter from backend listings page
2. Added description under title in backend listings page
3. Fixed Current Rental Availability to only show for rentals
4. Removed Custom Fields meta box
5. Added description under Filter by Property

❓ **Needs Decision:**
6. Grouping taxonomies - Possible but complex, recommend NOT doing it

📋 **Documented:**
7. Vacancy Notifications - Explained what it is and how it works

🔍 **Recommendation:**
8. Keep "Template Blocks" menu, remove "Template Blocks (Beta)" menu

---

**Last Updated:** 2025-01-XX  
**Plugin Version:** 1.0.0

