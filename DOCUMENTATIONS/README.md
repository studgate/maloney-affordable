# Maloney Affordable Listings Plugin

A comprehensive listing management system for WordPress, designed for managing condos and rental properties with advanced filtering, map views, and vacancy notifications.

## Features

- ✅ Unified listing post type for condos and rentals
- ✅ Color-coded property type filters
- ✅ Advanced filtering (income level, location, bedrooms, amenities, availability)
- ✅ Card View and Map View toggle
- ✅ Leaflet/OpenStreetMap integration (free, no API key required)
- ✅ Similar Properties feature
- ✅ Centralized backend management screen
- ✅ Vacancy notification system
- ✅ Geocoding support for addresses

## Installation

1. **Activate Child Theme**
   - Go to Appearance > Themes
   - Activate "Divi Child" theme
   - This protects your customizations from theme updates

2. **Activate Plugin**
   - Go to Plugins > Installed Plugins
   - Activate "Maloney Affordable Listings"
   - The plugin will automatically create:
     - Custom post type: `listing`
     - Taxonomies: `listing_type`, `listing_status`, `location`, `amenities`
     - Database table for vacancy notifications

3. **Flush Rewrite Rules**
   - Go to Settings > Permalinks
   - Click "Save Changes" (no need to change anything)
   - This ensures listing URLs work correctly

## Setup Instructions

### 1. Create Listing Types
The plugin automatically creates "Condo" and "Rental" types. You can add more:
- Go to Listings > Listing Types
- Add new types as needed

### 2. Create Locations
- Go to Listings > Locations
- Create hierarchical locations (City > Neighborhood)
- Example:
  - New York (Parent)
    - Manhattan (Child)
    - Brooklyn (Child)

### 3. Create Amenities
- Go to Listings > Amenities
- Add amenities like: Parking, Laundry, Elevator, Pet-friendly, etc.

### 4. Create Listings
- Go to Listings > Add New
- Fill in all fields:
  - **Title**: Property name/address
  - **Content**: Full description
  - **Featured Image**: Main property photo
  - **Listing Details**: Bedrooms, bathrooms, square feet, unit number
  - **Location Information**: Address (use "Geocode Address" button to auto-fill coordinates)
  - **Pricing**: Rent price or purchase price, income level requirements
  - **Taxonomies**: Select type, status, location, amenities

### 5. Configure Map Center
Edit the map center coordinates in `assets/js/frontend.js`:
```javascript
// Line ~165: Change these coordinates to your city/region center
const map = L.map('listings-map').setView([40.7128, -74.0060], 12);
```

### 6. Create Listings Archive Page (Optional)
- Create a new page
- Set the page template to use the listing archive
- Or visit `/listing/` directly (archive page)

## Usage

### Frontend

**Listing Archive Page:**
- Visit `/listing/` to see all listings
- Use filters to narrow down results
- Toggle between Card View and Map View
- Click "View Details" to see full listing

**Single Listing Page:**
- View full listing details
- See similar properties below
- Subscribe to vacancy notifications (if not available)

### Backend

**Manage Listings:**
- Go to Listings > Manage Listings
- Bulk actions: Change status, delete multiple listings
- Quick access to edit/view each listing

**Vacancy Notifications:**
- Go to Listings > Vacancy Notifications
- View all notification subscriptions
- See notification status and dates

## Customization

### Styling
- Child theme styles: `wp-content/themes/Divi-Child/style.css`
- Plugin frontend styles: `wp-content/plugins/maloney-listings/assets/css/frontend.css`
- Plugin admin styles: `wp-content/plugins/maloney-listings/assets/css/admin.css`

### Template Overrides
To override templates, copy them to your child theme:
1. Create: `wp-content/themes/Divi-Child/maloney-listings/`
2. Copy template files from plugin to this directory
3. Modify as needed

## Database Schema

### Vacancy Notifications Table
- `id` - Primary key
- `listing_id` - Reference to listing post
- `email` - Subscriber email
- `name` - Subscriber name (optional)
- `phone` - Subscriber phone (optional)
- `status` - pending, notified, cancelled
- `created_at` - Subscription date
- `notified_at` - Notification sent date

## API Endpoints

### AJAX Actions

**Filter Listings:**
```javascript
POST /wp-admin/admin-ajax.php
action: filter_listings
nonce: [nonce]
listing_type: condo|rental
status: available|waitlist|not-available
location: [term_id]
bedrooms: [number]
price_min: [number]
price_max: [number]
income_level: [number]
amenities[]: [term_id, term_id, ...]
```

**Get Similar Listings:**
```javascript
POST /wp-admin/admin-ajax.php
action: get_similar_listings
nonce: [nonce]
listing_id: [post_id]
```

**Submit Vacancy Notification:**
```javascript
POST /wp-admin/admin-ajax.php
action: submit_vacancy_notification
nonce: [nonce]
listing_id: [post_id]
email: [email]
name: [name]
phone: [phone]
```

**Geocode Address (Admin):**
```javascript
POST /wp-admin/admin-ajax.php
action: geocode_address
nonce: [nonce]
address: [full address]
```

## Troubleshooting

### Map Not Showing
- Check browser console for JavaScript errors
- Verify Leaflet CSS/JS are loading
- Check that listings have latitude/longitude coordinates

### Filters Not Working
- Check browser console for AJAX errors
- Verify nonce is correct
- Check WordPress permalink structure

### Geocoding Not Working
- Nominatim has rate limits (1 request/second)
- Check browser console for errors
- Verify address format is correct

### Similar Properties Not Loading
- Check listing ID is correct
- Verify listings exist with similar attributes
- Check browser console for errors

## Support

For issues or questions, check:
1. WordPress error logs
2. Browser console for JavaScript errors
3. Plugin code comments for customization options

## License

GPL v2 or later

## Version

1.0.0

