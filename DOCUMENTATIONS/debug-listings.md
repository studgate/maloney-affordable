## Listings filters debugging notes

### Symptoms we’re chasing
- Removing location/type chips sometimes leaves hidden filters or stale lat/lng, causing counts to drop to 0.
- URL lat/lng/zoom sometimes disappear or linger as null.
- Switching listing type to “Any” can shrink results instead of expanding.

### Temporary logging added
- `wp-content/plugins/maloney-listings/includes/class-ajax.php`
  - Logs incoming filter params (location/search_location, lat/lng, listing_type, status filters, has_units, available_unit_type, map_bounds, page).
  - Logs query meta/tax counts and orderby.
  - Logs post IDs (first 20) and found_posts after filtering/pagination.
- Remove these `error_log` calls after we’re done to avoid noisy logs.

### Client-side guardrails
- `frontend.js` now:
  - Trims typed search and ignores the hidden location select when a typed search is present.
  - Clears stored coords when no location/search is active.
  - Preserves URL lat/lng if valid; deletes them when no coords exist.
  - Removes price from listing payloads (server-side).

### How to reproduce and verify
1) Hard-refresh with cache disabled.
2) Boston flow:
   - Search “Boston”, toggle Rental/Condo/Any; counts should expand on Any.
   - Remove location chip(s); results should return to full set; URL should drop lat/lng.
3) Quincy flow:
   - Search “Quincy”; remove chips; ensure no 0-result drop unless truly none.
4) Watch `filter_listings` logs for:
   - `listing_type` values and `location` vs `search_location`.
   - lat/lng presence/absence matching UI.
   - post IDs/found_posts aligning with expected counts.

### Where to clean up
- Remove the temporary `error_log` blocks in `class-ajax.php` once behavior is stable.
