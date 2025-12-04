# Maloney Listings Plugin - Shortcodes Documentation

This document describes all available shortcodes provided by the Maloney Affordable Listings plugin.

---

## Table of Contents

1. [Listings View Shortcode](#listings-view-shortcode)
2. [Home Option Shortcode](#home-option-shortcode)
3. [Available Units Shortcode](#available-units-shortcode)
4. [Listing Availability Shortcode](#listing-availability-shortcode)

---

## Listings View Shortcode

Displays a comprehensive listings view with map and filtering capabilities, similar to Toolset Views.

### Usage

```
[maloney_listings_view type="units|condo|rental"]
```

### Parameters

- **type** (optional): Filter listings by type
  - `units` (default): Shows all listings (condos and rentals)
  - `condo`: Shows only condominium listings
  - `rental`: Shows only rental property listings

### Examples

```
[maloney_listings_view]
[maloney_listings_view type="condo"]
[maloney_listings_view type="rental"]
[maloney_listings_view type="units"]
```

### Features

- Interactive map with clustering
- Filterable listing cards
- Search functionality
- Pagination
- Responsive design

---

## Home Option Shortcode

Displays a styled option card for the home page, typically used to link to different listing types.

### Usage

```
[maloney_listings_home_option type="condo|rental|units" title="Title" image="url" description="Text" button_text="Button" link="url"]
```

### Parameters

- **type** (optional): Type of listing option
  - `units` (default): All units
  - `condo`: Condominiums
  - `rental`: Rental properties
- **title** (optional): Custom title for the card. If not provided, defaults based on type:
  - `condo`: "CONDOMINIUMS FOR SALE"
  - `rental`: "APARTMENT RENTALS"
  - `units`: "ALL UNITS"
- **image** (optional): URL to an image for the card
- **description** (optional): Description text. If not provided, defaults based on type:
  - `condo`: "Search current affordable condo resale and lottery listings"
  - `rental`: "Search our current listings of affordable rental opportunities"
  - `units`: "Search all available affordable housing units"
- **button_text** (optional): Text for the button (default: "Search")
- **link** (optional): URL for the button link. If not provided, defaults to the listings page

### Examples

```
[maloney_listings_home_option type="condo"]
[maloney_listings_home_option type="rental" title="Find Rentals" button_text="Browse Rentals"]
[maloney_listings_home_option type="units" image="https://example.com/image.jpg" description="Custom description"]
```

### Features

- Color-coded cards based on type
- Customizable title, description, and button
- Optional image support
- Responsive design

---

## Available Units Shortcode

Displays a comprehensive table of all available rental units from all properties.

### Usage

```
[maloney_available_units title="Current Rental Availability"]
```

### Parameters

- **title** (optional): Title displayed above the table (default: "Current Rental Availability")

### Examples

```
[maloney_available_units]
[maloney_available_units title="All Available Units"]
[maloney_available_units title="Current Rental Opportunities"]
```

### Features

- Displays all availability entries from all rental properties
- Shows comprehensive information:
  - Property name (linked to property page)
  - Town/City
  - Unit Size
  - Total Monthly Leasing Price
  - Minimum Income
  - Income Limit (AMI %)
  - Type (Lottery or FCFS)
  - Units Available (with full text, e.g., "1 (ADA-M Unit)")
  - Accessible Units
  - "View & Apply" button
- Automatically filters out entries with 0 units
- Sorted by property name, then unit size
- Responsive table design
- Hover effects for better UX

### Table Columns

1. **Property**: Property name (clickable link to property page)
2. **Town**: City/Town and County
3. **Unit Size**: Bedroom count (Studio, 1-Bedroom, 2-Bedroom, etc.)
4. **Total Monthly Leasing Price**: Monthly rent
5. **Minimum Income**: Required minimum income
6. **Income Limit (AMI %)**: Area Median Income percentage (70% or 80%)
7. **Type**: Application method (Lottery or FCFS)
8. **Units Available**: Number of available units (may include additional text like "(ADA-M Unit)" or "(55+ Age-Restricted Unit)")
9. **Accessible Units**: Description of accessible units (shows "0" if none)
10. **Learn More**: "View & Apply" button linking to property page

---

## Listing Availability Shortcode

Displays the current rental availability table for a specific listing property. This is useful for embedding availability information on any page or post.

### Usage

```
[maloney_listing_availability id="123" title="Current Rental Availability"]
[maloney_listing_availability slug="property-name" title="Current Rental Availability"]
[maloney_listing_availability]
```

### Parameters

- **id** (optional): The post ID of the listing. If not provided, will try to use the current post if it's a listing.
- **slug** (optional): The post slug/name of the listing. Alternative to using `id`.
- **title** (optional): Title displayed above the table (default: "Current Rental Availability")

### Examples

```
[maloney_listing_availability id="456"]
[maloney_listing_availability slug="366-broadway" title="Current Availability"]
[maloney_listing_availability id="789" title="Available Units"]
```

### Features

- Displays availability table for a specific rental property
- Shows comprehensive information:
  - # Beds (Unit Size)
  - Total Monthly Leasing Price
  - Minimum Income
  - Income Limit (AMI %)
  - Units Available (with full text, e.g., "1 (ADA-M Unit)")
  - Accessible Units
- Shows "no availability" message if property has no available units
- Only works for rental properties (returns message for condos)
- Can be used on any page or post
- Automatically detects current listing if used on a listing page without ID/slug

### Table Columns

1. **# Beds**: Unit type (Studio, 1-Bedroom, 2-Bedroom, etc.)
2. **Total Monthly Leasing Price**: Monthly rent (formatted as currency)
3. **Minimum Income**: Required minimum income (formatted as currency)
4. **Income Limit (AMI %)**: Area Median Income percentage (70%, 80%, etc.)
5. **Units Available**: Number of available units (may include additional text like "(ADA-M Unit)" or "(55+ Age-Restricted Unit)")
6. **Accessible Units**: Description of accessible units (shows "0" if none)

### Notes

- If neither `id` nor `slug` is provided, the shortcode will attempt to use the current post ID (useful when placed on a listing page)
- The shortcode will return an error message if the listing is not found or is not a rental property
- The table format matches the availability display on individual listing pages

---

## Gutenberg Blocks

The plugin provides three Gutenberg blocks for easy integration into pages and posts.

---

### Listings View Block

Displays the full listings page with map, filters, and search functionality.

#### Block Name
**Listings View**

#### Category
Widgets

#### Usage
1. Edit any page/post in the Gutenberg editor
2. Click the "+" button to add a block
3. Search for "Listings View" or look in the "Widgets" category
4. Add the block
5. Customize the listing type filter in the block settings (right sidebar)

#### Block Settings
- **Listing Type Filter**: Choose which listings to display
  - `All Listings` (default): Shows both condos and rentals
  - `Condominiums Only`: Shows only condominium listings
  - `Rentals Only`: Shows only rental property listings

#### Features
- Interactive map with clustering
- Filterable listing cards
- Search functionality
- Pagination
- Responsive design
- All filters and sorting options

#### Examples
- Add to any page to display all listings
- Use with "Condominiums Only" filter to show only condos
- Use with "Rentals Only" filter to show only rentals

---

### Current Rental Availability Block

Displays a table of all available rental units from all properties.

#### Block Name
**Current Rental Availability**

#### Category
Widgets

#### Usage
1. Edit any page/post in the Gutenberg editor
2. Click the "+" button to add a block
3. Search for "Current Rental Availability" or look in the "Widgets" category
4. Add the block
5. Customize the title in the block settings (right sidebar)

#### Block Settings
- **Title**: Customize the title displayed above the availability table

---

### Listings Search Form

A search form with Condo/Rental tabs that redirects users to the listings page with their search pre-filled.

**Available as:** Both Gutenberg block AND shortcode

---

#### Shortcode Usage

```
[maloney_listings_search_form]
[maloney_listings_search_form placeholder="Enter city or zip" button_text="Search"]
[maloney_listings_search_form show_tabs="0"]
```

#### Shortcode Parameters

- **placeholder** (optional): Customize the placeholder text in the search input
  - Default: "Search location or zip code..."
  - Example: `placeholder="Enter city or zip code"`

- **button_text** (optional): Customize the search button text
  - Default: "Get started"
  - Example: `button_text="Search"`

- **show_tabs** (optional): Show or hide the Condo/Rental selection tabs
  - Default: "1" (show tabs)
  - Use "0" to hide tabs
  - Example: `show_tabs="0"`

#### Shortcode Examples

```
[maloney_listings_search_form]
[maloney_listings_search_form placeholder="Enter city or zip" button_text="Search"]
[maloney_listings_search_form show_tabs="0" placeholder="Search listings"]
[maloney_listings_search_form placeholder="Find a home" button_text="Get started" show_tabs="1"]
```

---

#### Gutenberg Block Usage

1. Edit any page/post in the Gutenberg editor
2. Click the "+" button to add a block
3. Search for "Listings Search Form" or look in the "Widgets" category
4. Add the block
5. Customize settings in the block settings (right sidebar)

#### Block Settings
- **Show Condo/Rental Tabs**: Toggle to show/hide the Condo and Rental selection tabs (default: enabled)
- **Placeholder Text**: Customize the placeholder text in the search input (default: "Search location or zip code...")
- **Button Text**: Customize the search button text (default: "Get started")

#### Features
- **Tab Selection**: Users can select "Condo" or "Rental" before searching
- **Location Search with Autocomplete**: Users can enter:
  - City name (e.g., "Boston", "Quincy") - with autocomplete suggestions
  - Zip code (e.g., "02115", "02125") - with autocomplete suggestions
- **Autocomplete**: Works exactly like the listings page search field:
  - Shows matching cities and zip codes as you type
  - Filters out street addresses (only shows location/city/zip)
  - Sorted alphabetically (numbers first, then letters)
  - Click a suggestion to select it
- **Smart Redirect**: When submitted, redirects to the listings page with:
  - The selected type (Condo or Rental) pre-selected
  - The location/city/zip pre-filled and active
  - Filters automatically applied

#### Examples
- User selects "Rental" tab, types "Boston" (or selects from autocomplete), clicks "Get started"
  - Redirects to: `/listing/?type=rental&city=Boston`
  - Listings page shows only rentals in Boston with the search active

- User selects "Condo" tab, types "02115" (or selects from autocomplete), clicks "Get started"
  - Redirects to: `/listing/?type=condo&zip=02115`
  - Listings page shows only condos in zip code 02115 with the search active

#### Styling
- Modern, clean design with tab navigation
- Responsive layout (stacks on mobile)
- Red "Get started" button (customizable)
- White input field with border
- Active tab highlighted with blue underline

---

## Notes

- All shortcodes are responsive and work on mobile devices
- The shortcodes automatically enqueue necessary CSS and JavaScript files
- The Available Units shortcode pulls data from the "Current Rental Availability" repetitive field group
- Property names in the Available Units table are automatically linked to their respective property pages

---

## Listings Link Shortcode

Generates a simple link to the listings page with an optional type filter applied.

### Usage

```
[maloney_listings_link type="condo" text="View Condos"]
[maloney_listings_link type="rental" text="View Rentals"]
[maloney_listings_link text="View All Listings"]
```

### Parameters

- **type** (optional): Filter listings by type
  - `condo`: Link to condominium listings only
  - `rental`: Link to rental property listings only
  - Empty (default): Link to all listings
- **text** (optional): Link text. If not provided, defaults based on type:
  - `condo`: "View Condos"
  - `rental`: "View Rentals"
  - Empty: "View Listings"
- **class** (optional): Additional CSS classes to add to the link

### Examples

```
[maloney_listings_link type="condo"]
[maloney_listings_link type="rental" text="Browse Rental Properties"]
[maloney_listings_link type="condo" text="Find Condos" class="button"]
[maloney_listings_link text="See All Listings"]
```

### Features

- Automatically generates correct URL with type filter parameter
- Default link text based on type
- Customizable link text and CSS classes
- Works with the listings page filter system

### URL Format

- Condos: `/listing/?type=condo`
- Rentals: `/listing/?type=rental`
- All Listings: `/listing/`

---

## Support

For issues or questions about these shortcodes, please contact Responsab LLC at https://www.responsab.com

---

**Plugin:** Maloney Affordable Listings  
**Author:** Responsab LLC  
**Version:** 1.0.0
