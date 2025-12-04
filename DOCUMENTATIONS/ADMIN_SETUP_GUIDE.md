Maloney Listings — Quick Setup Guide (Non‑Developer)

Use this checklist after importing a new database to get Listings working.

1) Log in and activate the plugin
- Go to Plugins → activate “Maloney Affordable Listings”. Also activate “Toolset Types”.
- Optional: activate “Ninja Tables” if you use vacancy tables (not required).

2) Fix links and Permalinks
- Settings → Permalinks → click Save.
- If the site URL changed, update it in Settings → General (WordPress Address & Site Address).

3) Assign Toolset field groups to Listings (one time)
- Go to Listings → Assign Field Groups.
- Check the Toolset groups you want to show on “Listing” (Property Info, Rental Fields, Condo Fields, etc.).
- For each group, set “Visibility” to:
  - Both (shared fields), Condo only, or Rental only.
- Click Save. This controls which groups appear when you pick Condo or Rental.

4) Create a new Listing (Condo or Rental)
- Add New under Listings. A small dialog asks “Condo” or “Rental”. Pick one.
- In the right sidebar Unit Type box (or the small dropdown), make sure your selection is set.
- The correct Toolset field groups will now appear automatically based on your selection.

5) Rebuild Bedrooms/Bathrooms (recommended once per import)
- Lists → Index Health → Run Dry Run to see what’s missing.
- Click “Rebuild Now” to backfill `_listing_bedrooms` and `_listing_bathrooms` so filters work properly.

6) Geocode addresses (add map coordinates)
- Listings → Geocode Addresses → click “Start Geocoding”.
- The tool geocodes in batches; you can re‑click until the “needing geocode” count reaches zero.
- When editing a single listing, you can also use the “Geocode Address” box to geocode just that listing.

7) Check the public pages
- Visit the Listings page (or the page that contains the [maloney_listings_view] shortcode).
- Confirm you see map markers and that filters change the results count.
- Click a listing to confirm the single page shows a map with “Directions” and “Street View”.

Notes and tips
- Location taxonomy is not required; Town filter is hidden. You can keep it unused or ask a developer to remove it.
- Similar Properties are shown automatically on single pages; no extra setup required.
- If a listing’s fields don’t appear: verify Step 3 (Assign Field Groups) and re‑select Unit Type in the editor.

Need help?
- Listings → Debug Listing lets a developer see the raw values for a post.
- Or share the URL `wp-admin/?ml_dump_listing=POST_ID` with a developer for a JSON dump of that listing.

