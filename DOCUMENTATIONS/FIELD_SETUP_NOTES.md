# Field Setup Notes

## Required Fields in Property Info Group

The following fields should be added to the **Property Info** custom field group in Toolset Types:

### 1. Bathrooms Field
- **Field Name:** `bathrooms`
- **Field Slug:** `bathrooms` (will create `wpcf-bathrooms` meta key)
- **Field Type:** Number (or Text if decimals needed like 1.5, 2.5)
- **Purpose:** Store the number of bathrooms for each listing
- **Note:** Currently, bathrooms are derived from other fields via data normalization, but having a direct field in Property Info will make it easier to manage and ensure consistency.

### 2. Zip Code Field
- **Field Name:** `zip` or `zip_code`
- **Field Slug:** `zip` (will create `wpcf-zip` meta key)
- **Field Type:** Text
- **Purpose:** Store the zip code for each listing
- **Note:** Currently, the code checks for `wpcf-zip` and `_listing_zip` meta keys, but if these fields don't exist in Toolset, they should be added to the Property Info group.

## Current Implementation

The plugin currently:
- Checks for `wpcf-bathrooms` and `_listing_bathrooms` meta keys for bathrooms
- Checks for `wpcf-zip` and `_listing_zip` meta keys for zip codes
- Derives bathrooms from other fields if not directly set (via `Maloney_Listings_Data_Normalization`)

## Recommendation

1. **Add Bathrooms field** to Property Info group in Toolset Types
2. **Add Zip Code field** to Property Info group in Toolset Types
3. These fields will automatically be available for all listings (both condos and rentals) since Property Info is shown for all listing types

## How to Add Fields in Toolset Types

1. Go to **Toolset → Custom Fields**
2. Find the **Property Info** field group
3. Click **Add Field**
4. Create the fields as described above
5. Ensure they are assigned to the **Listing** post type

