# Changelog
All notable changes to this project will be documented in this file, formatted via [this recommendation](https://keepachangelog.com/).

## [2.11.0] - 2025-03-27
### IMPORTANT
- Support for PHP 7.0 has been discontinued. If you are running PHP 7.0, you MUST upgrade PHP before installing this addon. Failure to do that will disable addon functionality.

### Changed
- The minimum WPForms version supported is 1.9.4.

### Fixed
- Address data from a resumed link is now correctly restored if Address Autocomplete settings are enabled.
- When a Geolocation field was added to Google Sheets, the smart tag was not converted.
- State subfield in the address field now is hidden if user is filling address in the country which does not have states.
- Several issues with the visibility of the address State subfield.
- Address autocomplete didn't work properly in some cases.

## [2.10.0] - 2024-06-11
### Added
- Compatibility with WPForms 1.8.9.

### Changed
- The minimum WPForms version supported is 1.8.9.

### Fixed
- The Address autocomplete field did not work without the map for the MapBox provider.
- The Geolocation data flag icon was broken on the entry page.
- The subpremise part disappeared from the address field with enabled autocomplete for the Google Places provider.

## [2.9.0] - 2024-04-16
### Fixed
- The map was not initialized when opening the popup in Elementor.
- Error in the console when the Display map setting was disabled.
- Various RTL problems in the admin dashboard.
- Layout issue on Edit Entry page.
- The map address list was not available when opening the popup in Elementor.
- The Mapbox address autocomplete didn't work when the search query didn't contain digits.

## [2.8.0] - 2024-01-09
### Added
- Compatibility with WPForms 1.8.6.

## [2.7.0] - 2023-11-08
### Added
- Compatibility with WPForms 1.8.5.

## [2.6.0] - 2023-09-26
### IMPORTANT
- Support for PHP 5.6 has been discontinued. If you are running PHP 5.6, you MUST upgrade PHP before installing WPForms Geolocation 2.6.0. Failure to do that will disable WPForms Geolocation functionality.
- Support for WordPress 5.4 and below has been discontinued. If you are running any of those outdated versions, you MUST upgrade WordPress before installing WPForms Geolocation 2.6.0. Failure to do that will disable WPForms Geolocation functionality.

### Changed
- Minimum WPForms version supported is 1.8.4.

### Fixed
- Entry geolocation value was not added to the CSV file for the email notifications.

## [2.5.0] - 2023-08-22
### Added
- Ability to display Geolocation data when printing and displaying an entry data.

### Changed
- Minimum WPForms version supported is 1.8.3.

## Fixed
- Map for the Address field was displayed incorrectly when a form contained page breaks.

## [2.4.0] - 2023-03-13
### Added
- Compatibility with the upcoming WPForms 1.8.1.

## [2.3.1] - 2022-08-30
### Fixed
- Error when using location with autocomplete.

## [2.3.0] - 2022-07-05
### IMPORTANT
- Algolia Places has been discontinued by Algolia. All Algolia functionality in the addon has been deprecated and removed.

### Added
- New Places Provider: Mapbox.
- Added Preview area on the Settings > Geolocation admin page.
- Added new filters to change the map appearance and location sources.

### Changed
- Increased minimum WPForms supported version to 1.7.5.
- Browser no longer automatically completes the Text field if Address Autocomplete is enabled.
- Improved detection of the user's current location.
- In the address and text field search, users can now hit the Enter key to select an address.

### Fixed
- Fixed map styling inside the Full Site Editor in WordPress 6.0.
- Geolocation coordinates are correct for Address Autocomplete with custom scheme.
- Address autocomplete fills in the Address > City subfield.
- `{entry_geolocation}` smart tag works in Confirmation messages.
- Compatibility with the Conversational Forms addon has been improved.

## [2.2.0] - 2022-05-11
### IMPORTANT
- Algolia Places has been discontinued by Algolia. If you are using it you need to switch to Google Places to prevent disruptions in form geolocation features.

### Added
- New filter `wpforms_geolocation_places_providers_google_places_query_args` that can be used to improve multi-language support.

### Fixed
- Users geolocation detection on the Entry page was working incorrectly with KeyCDN API.

## [2.1.0] - 2022-03-16
### Added
- Compatibility with WPForms 1.6.8 and the updated Form Builder.
- Compatibility with WPForms 1.7.3 and Form Revisions.

### Changed
- Minimum WPForms version supported is 1.6.7.1.

### Fixed
- Address field filling.
- Value with mask is not saved in a Text field when Address Autocomplete is enabled.
- Various typos reported by translators.

## [2.0.0] - 2021-02-18
### Added
- New Places Providers selection: Google Places, Algolia Places.
- Address and Text fields can have address autocomplete enabled on typing.
- Display a map before or after the field to select location on a map without typing.
- Retrieve user's current location with a browser prompt and prefill address/text fields with address autocomplete enabled.
- Added own WPForms geolocation API endpoint to retrieve users geolocation based their IP address.

### Changed
- Removed map image preview from email notifications due to Google API restrictions.

### Fixed
- Geolocation: display and save only existing data (sometimes ZIP code may be missing).

## [1.2.0] - 2019-07-23
### Added
- Complete translations for French and Portuguese (Brazilian).

## [1.1.1] - 2019-02-26
### Fixed
- Geolocation provider fallback logic.
- Referencing geolocation providers no longer accessible.

## [1.1.0] - 2019-02-06
### Added
- Complete translations for Spanish, Italian, Japanese, and German.

### Fixed
- Typos, grammar, and other i18n related issues.

## [1.0.3] - 2017-09-28
### Changed
- Use HTTPS when requesting location data via ipinfo.io
- Use bundled SSL certificates (since WordPress 3.7) to verify properly target sites SSL certificates

## [1.0.2]
### Changed
- Always use SSL connection to check user IPs location data
- Always verify SSL certificates of the services we use to get location data

## [1.0.1] - 2016-08-04
### Fixed
- Bug preventing IP addresses from processing

## [1.0.0] - 2016-08-03
### Added
- Initial release
