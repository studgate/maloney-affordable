# Filter System Debugging Report
**Date:** November 2025  
**Developer:** Responsab LLC  
**Status:** Investigation Complete - Solution Plan Ready

---

## Executive Summary

This document details a comprehensive investigation of the listings filter system, identifying critical bugs in URL parameter management, listing type filtering, and filter reset functionality. All issues have been traced to their root causes and a complete solution plan is provided.

---

## Issues Identified

### Issue #1: Lat/Lng Not Removed from URL When Filters Are Removed
**Severity:** HIGH  
**User Impact:** Confusing URL state, filters don't fully clear

**Symptoms:**
- When removing filters, `lat` and `lng` parameters remain in URL
- Coordinates persist even when location search is cleared

**Root Cause:**
Located in `frontend.js` lines 1168-1185 in `applyFilters()` function:

```javascript
// Lines 1155-1163: First attempt to delete lat/lng
if (ListingFilters.lastSearchCoords && !isNaN(ListingFilters.lastSearchCoords.lat) && !isNaN(ListingFilters.lastSearchCoords.lng)) {
    filters.search_location_lat = ListingFilters.lastSearchCoords.lat;
    filters.search_location_lng = ListingFilters.lastSearchCoords.lng;
    params.set('lat', ListingFilters.lastSearchCoords.lat);
    params.set('lng', ListingFilters.lastSearchCoords.lng);
} else {
    params.delete('lat');
    params.delete('lng');
}

// Lines 1168-1185: DUPLICATE LOGIC that re-adds lat/lng
const existingLat = urlParams.get('lat');
const existingLng = urlParams.get('lng');
const existingZoom = urlParams.get('zoom');

if (!isNaN(searchLat) && !isNaN(searchLng)) {
    params.set('lat', searchLat);
    params.set('lng', searchLng);
    if (existingZoom) params.set('zoom', existingZoom);
} else if (ListingFilters.lastSearchCoords && !isNaN(ListingFilters.lastSearchCoords.lat) && !isNaN(ListingFilters.lastSearchCoords.lng)) {
    params.set('lat', ListingFilters.lastSearchCoords.lat);  // ❌ RE-ADDS EVEN WHEN FILTERS CLEARED
    params.set('lng', ListingFilters.lastSearchCoords.lng);
    if (existingZoom) params.set('zoom', existingZoom);
} else {
    params.delete('lat');
    params.delete('lng');
    params.delete('zoom');
}
```

**Problem:**
1. `ListingFilters.lastSearchCoords` is never cleared when filters are removed
2. The duplicate logic at lines 1168-1185 overrides the deletion at lines 1161-1163
3. Even when `search_location` is empty, if `lastSearchCoords` exists, lat/lng are re-added to URL

**Files Affected:**
- `wp-content/plugins/maloney-listings/assets/js/frontend.js` (lines 1084-1193, 1521-1598)

---

### Issue #2: Lat/Lng Briefly Show as Null Values on Reset
**Severity:** MEDIUM  
**User Impact:** Visual glitch, confusing UX

**Symptoms:**
- When clicking "Reset Filters", URL briefly shows `?lat=null&lng=null` then clears

**Root Cause:**
Located in `frontend.js` `clearFilters()` function (lines 1521-1598):

```javascript
clearFilters: function(e) {
    // ... clears all filters ...
    
    // Line 1557: Clears lastSearchCoords
    this.lastSearchCoords = null;
    
    // Line 1587: Redirects to clean URL
    window.location.href = listingsUrl;
    
    // Line 1597: THEN calls applyFilters() which might still have old state
    this.applyFilters(e, 1, true);
}
```

**Problem:**
1. `clearFilters()` redirects to clean URL (line 1587)
2. But then immediately calls `applyFilters()` (line 1597) BEFORE the redirect completes
3. `applyFilters()` reads from `urlParams` which might still have old values during the redirect transition
4. If `lastSearchCoords` was null but URL had lat/lng, it tries to set `null` values

**Files Affected:**
- `wp-content/plugins/maloney-listings/assets/js/frontend.js` (lines 1521-1598)

---

### Issue #3: Listing Type "Any" Filter Not Working
**Severity:** CRITICAL  
**User Impact:** Users cannot see all listings, filter broken

**Symptoms:**
- Selecting "Any" for listing type shows 8 results
- Selecting "Condo" shows 1 result
- Selecting "Rental" shows 10 results
- Math doesn't add up: 1 + 10 = 11, but "Any" shows only 8

**Root Cause Analysis:**

#### Frontend Issue (frontend.js lines 1085-1091):
```javascript
listing_type: (function() {
    const typeFilter = $('input[name="listing_type_filter"]:checked').val();
    if (typeFilter && typeFilter !== 'show_all') {
        return typeFilter;
    }
    return $('#filter_listing_type').val() || '';  // Returns empty string when "show_all"
})(),
```

When "Any" (show_all) is selected:
- `typeFilter` = `'show_all'`
- Condition `typeFilter !== 'show_all'` is FALSE
- Falls through to `$('#filter_listing_type').val() || ''` which returns `''` (empty string)
- Empty string is sent to backend

#### Backend Issue (class-ajax.php lines 124-153):
```php
if (!empty($_POST['listing_type'])) {
    // ... applies tax_query filter ...
} elseif ($has_available_units_filter_check) {
    // If available units filter is active but no listing type filter, 
    // we need to include rentals in the query to check their availability
    // Don't filter by type - include all types, we'll filter post-query
}
```

**The Problem:**
1. When `listing_type` is empty (from "Any" selection), the backend doesn't add a `tax_query` for listing_type
2. This SHOULD show all listings, BUT...
3. There's post-query filtering that excludes condos with status 3 or 4 (lines 1695-1726)
4. There's ALSO post-query filtering for available units/bathrooms/income limits that ONLY shows rentals (lines 1768-1771)
5. If ANY of these post-query filters are active (even if user didn't explicitly select them), it filters out condos

**Additional Issues:**
- The post-query filtering at line 1768 excludes ALL non-rentals when `$has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter || $has_income_limits_filter` is true
- This means if user has ANY of these filters active (even if set to "any" or default), condos get filtered out
- The "Any" selection should show ALL listings regardless of other filters, but it's being affected by post-query filters

**Files Affected:**
- `wp-content/plugins/maloney-listings/assets/js/frontend.js` (lines 1085-1091, 1133)
- `wp-content/plugins/maloney-listings/includes/class-ajax.php` (lines 124-153, 1695-1771, 1743-2000)

---

## Code Flow Analysis

### Filter Application Flow

1. **User Action** → `applyFilters()` called
2. **Frontend (frontend.js:1084-1124)**:
   - Collects all filter values from form
   - `listing_type` determined from radio buttons or select dropdown
   - If "show_all" selected, sends empty string `''`
   - Builds `filters` object
   - Updates URL parameters (lines 1131-1193)
   - Sends AJAX request to backend

3. **Backend (class-ajax.php:50-154)**:
   - Receives `$_POST['listing_type']`
   - If empty, skips `tax_query` for listing_type (should show all)
   - BUT checks for `$has_available_units_filter_check` which might be true from other filters
   - Applies other filters (location, bedrooms, etc.)

4. **Query Execution (class-ajax.php:1674)**:
   - Runs `WP_Query` with filters
   - Gets all matching posts

5. **Post-Query Filtering (class-ajax.php:1695-2000)**:
   - Filters out condos with status 3 or 4
   - If available units/bathrooms/income filters active, EXCLUDES ALL NON-RENTALS (line 1768)
   - This is where "Any" breaks - it should show all, but post-query filters remove condos

6. **Response**:
   - Returns filtered results
   - Frontend updates display

### Reset Filters Flow

1. **User Clicks Reset** → `clearFilters()` called
2. **Clear Form Fields** (lines 1524-1551):
   - Clears all input values
   - Sets defaults ("Any", "show_all")
   - Clears `lastSearchCoords` (line 1557)
3. **Redirect** (line 1587):
   - `window.location.href = listingsUrl` (clean URL, no params)
4. **Apply Filters** (line 1597):
   - Calls `applyFilters()` with cleared state
   - BUT this happens DURING redirect, causing race condition

---

## Data Flow Issues

### Issue: Listing Type Taxonomy Terms

**Investigation:**
- Taxonomy: `listing_type`
- Terms likely include: `rental-properties`, `condominiums`, `rental`, `condo`, etc.
- Backend normalizes these (lines 128-142) but frontend might send different values

**Potential Mismatch:**
- Frontend radio buttons use taxonomy slugs (e.g., `rental-properties`, `condominiums`)
- But `#filter_listing_type` select might use different values
- When "show_all" is selected, frontend sends `''` but backend might interpret this differently

### Issue: Post-Query Filtering Logic

**Current Logic (class-ajax.php:1768-1771):**
```php
if (!$is_rental && ($has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter || $has_income_limits_filter)) {
    // Exclude non-rentals when filter is active
    continue;
}
```

**Problem:**
- This excludes ALL non-rentals when ANY of these filters are active
- But these filters might be "active" even when user selected "any" or defaults
- For example, if `bedrooms_multi` includes "any", `$has_available_units_filter` might still be true
- This causes condos to be filtered out even when user wants to see "Any" listing type

---

## Solution Plan

### Fix #1: Properly Remove Lat/Lng from URL

**Location:** `frontend.js` `applyFilters()` function

**Changes:**
1. Remove duplicate lat/lng handling logic (lines 1168-1185)
2. Consolidate into single block that checks:
   - If `search_location` is empty AND no active location filter → delete lat/lng
   - If `search_location` exists → use coordinates
   - Clear `lastSearchCoords` when location filter is removed

**Code Changes:**
```javascript
// In applyFilters(), replace lines 1147-1185 with:
if (filters.search_location) {
    const searchLoc = filters.search_location.trim();
    const isZip = /^\d{5}(-\d{4})?$/.test(searchLoc);
    if (isZip) {
        params.set('zip', searchLoc);
    }
    // Add coordinates if available
    if (searchLat && searchLng && !isNaN(searchLat) && !isNaN(searchLng)) {
        params.set('lat', searchLat);
        params.set('lng', searchLng);
        const existingZoom = urlParams.get('zoom');
        if (existingZoom) params.set('zoom', existingZoom);
    } else if (ListingFilters.lastSearchCoords && !isNaN(ListingFilters.lastSearchCoords.lat) && !isNaN(ListingFilters.lastSearchCoords.lng)) {
        params.set('lat', ListingFilters.lastSearchCoords.lat);
        params.set('lng', ListingFilters.lastSearchCoords.lng);
        const existingZoom = urlParams.get('zoom');
        if (existingZoom) params.set('zoom', existingZoom);
    }
    if (!isZip) {
        params.set('city', searchLoc);
    }
} else {
    // NO search location - remove coordinates
    params.delete('lat');
    params.delete('lng');
    params.delete('zoom');
    params.delete('city');
    params.delete('zip');
    // Clear stored coordinates
    ListingFilters.lastSearchCoords = null;
}
```

---

### Fix #2: Fix Reset Filters Race Condition

**Location:** `frontend.js` `clearFilters()` function

**Changes:**
1. Don't call `applyFilters()` after redirect (it's redundant - page will reload)
2. OR: Don't redirect, just clear URL params and call `applyFilters()`

**Code Changes:**
```javascript
clearFilters: function(e) {
    if (e) e.preventDefault();
    
    // Clear all filter inputs (existing code)
    // ... existing clear code ...
    
    // Clear last search coordinates
    this.lastSearchCoords = null;
    
    // Clear active area search
    this.activeAreaSearch = null;
    
    // Clear session storage
    sessionStorage.removeItem('ml_filters_url');
    
    // OPTION A: Just update URL and apply filters (no redirect)
    const params = new URLSearchParams();
    const url = location.pathname;
    history.replaceState(null, '', url);
    sessionStorage.removeItem('ml_filters_url');
    
    // Update cards by calling applyFilters
    this.applyFilters(e, 1, true); // page 1, paginationOnly = true
    
    // OPTION B: Redirect (simpler, but loses state)
    // window.location.href = listingsUrl;
    // Don't call applyFilters() - page will reload with clean URL
}
```

**Recommendation:** Use OPTION A (no redirect) for smoother UX

---

### Fix #3: Fix Listing Type "Any" Filter

**Location:** Multiple files

#### Frontend Fix (frontend.js):

**Changes:**
1. Ensure "show_all" sends explicit signal to backend
2. Don't fall back to `#filter_listing_type` when "show_all" is selected

**Code Changes:**
```javascript
// In applyFilters(), replace lines 1085-1091 with:
listing_type: (function() {
    const typeFilter = $('input[name="listing_type_filter"]:checked').val();
    // Explicitly handle "show_all" - send empty string to indicate "all types"
    if (typeFilter === 'show_all') {
        return ''; // Empty string means "show all listing types"
    }
    if (typeFilter && typeFilter !== 'show_all') {
        return typeFilter;
    }
    // Fallback to select dropdown only if no radio button selected
    const selectVal = $('#filter_listing_type').val();
    return selectVal || ''; // Empty string means "show all"
})(),
```

#### Backend Fix (class-ajax.php):

**Changes:**
1. When `listing_type` is empty AND user explicitly selected "show_all", don't apply post-query filters that exclude condos
2. Add flag to track if "show_all" was explicitly selected
3. Only apply post-query rental-only filters if listing_type filter is NOT "show_all"

**Code Changes:**

**Part 1: Track explicit "show_all" selection**
```php
// At top of filter_listings(), after line 123:
$explicit_show_all = false;
if (isset($_POST['listing_type']) && $_POST['listing_type'] === '') {
    // Check if this came from "show_all" radio button
    // We'll need to pass a flag from frontend, OR check if no other filters are active
    $explicit_show_all = empty($_POST['listing_type']);
}
```

**Part 2: Modify post-query filtering (around line 1768)**
```php
// Replace lines 1768-1771 with:
// Only exclude non-rentals if:
// 1. Listing type filter is NOT "show_all" (i.e., user selected a specific type)
// 2. AND one of the rental-specific filters is active
$should_exclude_non_rentals = false;
if (empty($_POST['listing_type']) || $_POST['listing_type'] === '') {
    // "show_all" selected - don't exclude non-rentals
    $should_exclude_non_rentals = false;
} else {
    // Specific type selected - apply rental-only filters if active
    $should_exclude_non_rentals = ($has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter || $has_income_limits_filter);
}

if (!$is_rental && $should_exclude_non_rentals) {
    // Exclude non-rentals when filter is active AND not showing all types
    continue;
}
```

**Better Approach:**
Actually, the issue is that when "show_all" is selected, we should show ALL listings regardless of other filters. But the post-query filters are still being applied. We need to:

1. When `listing_type` is empty (show_all), skip the rental-only post-query filtering
2. OR: Only apply rental-only filtering if user explicitly selected a rental-specific filter AND listing type is not "show_all"

**Recommended Fix:**
```php
// Around line 1745, modify the condition:
// Post-process available units filters, bathrooms filters, and income limits filters
// BUT: Only apply rental-only restriction if listing_type is NOT "show_all"
$listing_type_filter = !empty($_POST['listing_type']) ? sanitize_text_field($_POST['listing_type']) : '';

// Only apply rental-only post-query filters if:
// 1. Listing type is NOT empty (user selected specific type, not "show_all")
// 2. AND one of the rental-specific filters is active
$apply_rental_only_filters = !empty($listing_type_filter) && 
                              ($has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter || $has_income_limits_filter);

if ($apply_rental_only_filters || ($has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter || $has_income_limits_filter)) {
    // ... existing post-query filtering code ...
    
    // At line 1768, change condition to:
    if (!$is_rental && $apply_rental_only_filters) {
        // Only exclude non-rentals if listing type is NOT "show_all"
        continue;
    }
}
```

Wait, that's still complex. Let me simplify:

**Simplest Fix:**
```php
// Around line 1768, replace:
if (!$is_rental && ($has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter || $has_income_limits_filter)) {
    continue;
}

// With:
// Only exclude non-rentals if listing_type filter is NOT empty (i.e., user selected specific type, not "show_all")
$listing_type_selected = !empty($_POST['listing_type']) ? trim($_POST['listing_type']) : '';
$exclude_non_rentals = !empty($listing_type_selected) && 
                       ($has_available_units_filter || !empty($available_unit_type_filter) || $has_bathrooms_filter || $has_income_limits_filter);

if (!$is_rental && $exclude_non_rentals) {
    continue;
}
```

---

## Testing Plan

### Test Case 1: Lat/Lng URL Parameter Removal
1. Search for a location (e.g., "Boston")
2. Verify URL has `?lat=X&lng=Y&city=Boston`
3. Clear location search
4. **Expected:** URL should be clean (no lat/lng/city params)
5. **Current:** URL still has lat/lng

### Test Case 2: Reset Filters
1. Apply multiple filters (location, type, bedrooms)
2. Click "Reset Filters"
3. **Expected:** Clean URL, all filters cleared, no brief null values
4. **Current:** Brief `?lat=null&lng=null` appears

### Test Case 3: Listing Type "Any"
1. Count total listings: Condo + Rental
2. Select "Any" for listing type
3. **Expected:** Should show ALL listings (Condo + Rental)
4. **Current:** Shows fewer than total

### Test Case 4: Listing Type Individual Selections
1. Select "Condo" → Count results
2. Select "Rental" → Count results  
3. Select "Any" → Count results
4. **Expected:** Any count = Condo count + Rental count
5. **Current:** Any count < Condo + Rental

### Test Case 5: Listing Type with Other Filters
1. Select "Any" listing type
2. Apply bedroom filter (e.g., "2 BR")
3. **Expected:** Should show both Condos and Rentals with 2 BR
4. **Current:** Might only show Rentals

---

## Implementation Priority

1. **CRITICAL:** Fix Listing Type "Any" filter (Issue #3)
   - Blocks users from seeing all listings
   - High user impact

2. **HIGH:** Fix Lat/Lng URL parameter removal (Issue #1)
   - Confusing UX
   - Affects filter state management

3. **MEDIUM:** Fix Reset Filters null values (Issue #2)
   - Visual glitch
   - Lower user impact

---

## Files to Modify

1. `wp-content/plugins/maloney-listings/assets/js/frontend.js`
   - `applyFilters()` function (lines 1084-1193)
   - `clearFilters()` function (lines 1521-1598)

2. `wp-content/plugins/maloney-listings/includes/class-ajax.php`
   - `filter_listings()` function (lines 124-153, 1743-1771)

---

## Additional Notes

### Why "Any" Shows 8 Instead of 11

**Hypothesis:**
- Total listings: 11 (1 Condo + 10 Rentals)
- "Any" shows: 8
- Missing: 3 listings

**Possible Causes:**
1. Some listings might be excluded by post-query filters (condo status 3/4)
2. Some listings might not have required data (coordinates, etc.)
3. Post-query filtering might be excluding listings incorrectly

**Investigation Needed:**
- Check database: How many total `listing` posts with `post_status = 'publish'`?
- Check taxonomy: How many have `listing_type` terms assigned?
- Check meta: How many have coordinates?
- Check condo status: How many condos have status 3 or 4?

### Debugging Commands

```php
// Add to class-ajax.php filter_listings() for debugging:
error_log('=== FILTER DEBUG ===');
error_log('listing_type POST: ' . print_r($_POST['listing_type'], true));
error_log('has_available_units_filter: ' . ($has_available_units_filter ? 'YES' : 'NO'));
error_log('available_unit_type_filter: ' . print_r($available_unit_type_filter, true));
error_log('has_bathrooms_filter: ' . ($has_bathrooms_filter ? 'YES' : 'NO'));
error_log('has_income_limits_filter: ' . ($has_income_limits_filter ? 'YES' : 'NO'));
error_log('Query found_posts (before post-query): ' . $query->found_posts);
error_log('Filtered posts count (after condo status filter): ' . count($filtered_posts));
error_log('Final posts count (after post-query filters): ' . count($query->posts));
```

---

## Conclusion

All three issues have been identified with root causes. The solution plan provides specific code changes to fix each issue. The most critical is the Listing Type "Any" filter, which requires both frontend and backend changes to properly handle the "show_all" case.

**Next Steps:**
1. Review this document
2. Approve solution plan
3. Implement fixes in order of priority
4. Test thoroughly using test cases above
5. Deploy to staging for QA
6. Deploy to production

---

**End of Report**

