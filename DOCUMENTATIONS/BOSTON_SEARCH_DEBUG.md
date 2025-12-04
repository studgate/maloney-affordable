# Boston Search Debugging Report

**Date:** November 2025  
**Issue:** Only 10 results showing for "Boston" + "Rental" search

---

## Issues Identified

### Issue #1: Default Pagination is 10 (Too Low)

**Location:** `class-ajax.php` line 75

**Problem:**

```php
'posts_per_page' => isset($_POST['per_page']) ? intval($_POST['per_page']) : 10,
```

**Root Cause:**

- Default `posts_per_page` is set to **10**
- Frontend doesn't send `per_page` parameter
- Only 10 results are returned per page, even if more exist
- Inconsistent with other parts of code that use 12 as default (lines 1661, 2046)

**Impact:**

- User sees only 10 results when there might be 20+ Boston rentals
- Pagination exists but user might not realize there are more results

---

### Issue #2: "Boston, MA" Becomes "Boston" (By Design, But Should Still Work)

**Location:** `class-ajax.php` lines 270-275

**Code:**

```php
// Strip trailing commas and anything after the first comma (e.g., "Quincy, MA" -> "Quincy")
$search_location = preg_replace('/,+$/', '', $search_location);
if (strpos($search_location, ',') !== false) {
    $parts = explode(',', $search_location);
    $search_location = trim($parts[0]);
}
```

**Analysis:**

- This is **intentional** - strips state abbreviation for search
- The LIKE query searches for `%Boston%` which SHOULD match:
  - "Boston" in city field
  - "Boston, MA" in city field
  - "Boston" in address field
  - "Boston, MA" in address field

**Why It Should Work:**

- The LIKE query uses `%Boston%` pattern (line 219, 363)
- This matches "Boston" anywhere in the field, including "Boston, MA"
- So "Boston, MA" → "Boston" should still find all listings

**Potential Issue:**

- If listings are stored as "Boston, MA" exactly, the LIKE query should still work
- But if there's an exact match requirement somewhere, it might fail

---

## Database Query Analysis

### Expected Query Behavior

When searching for "Boston" with listing_type "rental", the query should:

1. **Taxonomy Filter:**

   ```php
   'taxonomy' => 'listing_type',
   'terms' => array('rental', 'rental-properties'),
   'operator' => 'IN',
   ```

2. **Location Meta Query:**

   ```php
   array(
       'key' => 'wpcf-city',
       'value' => '%Boston%',
       'compare' => 'LIKE',
   ),
   // OR
   array(
       'key' => '_listing_city',
       'value' => '%Boston%',
       'compare' => 'LIKE',
   ),
   // OR
   array(
       'key' => 'wpcf-address',
       'value' => '%Boston%',
       'compare' => 'LIKE',
   ),
   // OR
   array(
       'key' => '_listing_address',
       'value' => '%Boston%',
       'compare' => 'LIKE',
   ),
   ```

3. **Pagination:**
   - Default: 10 results per page
   - If 20+ Boston rentals exist, only first 10 show

---

## Root Causes

### Primary Issue: Pagination Limit

- **Default `posts_per_page` = 10** is too low
- Frontend doesn't send `per_page` parameter
- User sees only 10 results, even if more exist
- Pagination exists but might not be obvious

### Secondary Issue: Inconsistent Defaults

- Line 75: Default = 10
- Line 1661: Default = 12 (when post-processing filters)
- Line 2046: Default = 12 (when no post-processing filters)
- **Inconsistency causes confusion**

### Location Search: Working as Designed

- "Boston, MA" → "Boston" is intentional
- LIKE query should still match "Boston, MA" in database
- If not working, might be a database storage issue (exact match vs LIKE)

---

## Solution Plan

### Fix #1: Increase Default Posts Per Page ✅ IMPLEMENTED

**Change:** Line 75 in `class-ajax.php`

**From:**

```php
'posts_per_page' => isset($_POST['per_page']) ? intval($_POST['per_page']) : 10,
```

**To:**

```php
'posts_per_page' => isset($_POST['per_page']) ? intval($_POST['per_page']) : 12,
```

**Rationale:**

- Matches other defaults in code (12)
- Shows more results per page
- Better user experience
- Still allows pagination for large result sets

### Fix #2: Fix LIKE Query Missing Wildcards ✅ IMPLEMENTED

**Change:** Lines 508-530 in `class-ajax.php`

**Problem:**

- Single city search LIKE query was using `$search_location` directly
- WordPress LIKE compare does NOT automatically add wildcards
- Should use `%$search_location%` pattern like the regular location filter does

**Fix:**

- Added `$search_location_like = '%' . $wpdb->esc_like($search_location) . '%';`
- Updated all LIKE queries to use `$search_location_like` instead of `$search_location`
- This ensures "Boston" matches "Boston, MA" in database fields

---

### Fix #2: Verify Location Search Logic

**Action:** Test if LIKE query properly matches "Boston, MA"

**Test Query:**

```php
// Should match both:
// - wpcf-city = "Boston"
// - wpcf-city = "Boston, MA"
// - wpcf-address = "123 Main St, Boston, MA"
```

**If Not Working:**

- Check database: How are cities stored? ("Boston" vs "Boston, MA")
- Verify LIKE query is using `%Boston%` pattern correctly
- Check if there's an exact match requirement somewhere

---

### Fix #3: Add Debug Logging

**Action:** Add temporary logging to see:

- How many total Boston rentals exist
- What the actual query returns
- What gets filtered out

**Code to Add:**

```php
error_log('=== BOSTON RENTAL SEARCH DEBUG ===');
error_log('Search location: ' . $search_location);
error_log('Query found_posts: ' . $query->found_posts);
error_log('Query post_count: ' . $query->post_count);
error_log('Posts per page: ' . $args['posts_per_page']);
error_log('Total filtered: ' . count($query->posts));
```

---

## Testing Plan

### Test 1: Verify Total Boston Rentals

1. Query database directly for all rentals with "Boston" in city/address
2. Count total results
3. Compare to what search returns

### Test 2: Verify Pagination

1. Search "Boston" + "Rental"
2. Check if pagination shows (should if > 12 results)
3. Click page 2, verify more results appear

### Test 3: Verify Location Matching

1. Search "Boston, MA" + "Rental"
2. Verify it finds same results as "Boston" + "Rental"
3. Check database: How are cities stored?

### Test 4: Check URL Parameters

1. Search "Boston, MA"
2. Verify URL shows `city=Boston` (not `city=Boston, MA`)
3. This is expected behavior (state stripped)

---

## Expected Behavior After Fix

1. **Default Results Per Page:** 12 (instead of 10)
2. **Pagination:** Shows if > 12 results
3. **Location Search:** "Boston, MA" and "Boston" both work
4. **Total Count:** Shows correct total (e.g., "20 Results" not "10 Results")

---

## Next Steps

1. **Immediate:** Change default `posts_per_page` from 10 to 12
2. **Verify:** Test search to see if more results appear
3. **Debug:** Add logging to see actual query results
4. **Database Check:** Verify how cities are stored in database
5. **Test:** Verify pagination works correctly

---

**End of Report**
