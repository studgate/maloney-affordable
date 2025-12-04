# Changelog
All notable changes to this project will be documented in this file, formatted via [this recommendation](https://keepachangelog.com/).

## [1.4.0] - 2024-12-03
### Added
- The new option on the Export entries page to export user journey data to CSV/XLS.

### Changed
- The minimum WPForms version supported is 1.9.1.

### Fixed
- Data was not saved if the site name contained an apostrophe.

## [1.3.0] - 2024-08-06
### Changed
- The minimum WPForms version supported is 1.9.0.

## [1.2.0] - 2023-11-07
### IMPORTANT
- Support for PHP 5.6 has been discontinued. If you are running PHP 5.6, you MUST upgrade PHP before installing WPForms User Journey 1.2.0. Failure to do that will disable WPForms User Journey functionality.
- Support for WordPress 5.4 and below has been discontinued. If you are running any of those outdated versions, you MUST upgrade WordPress before installing WPForms User Journey 1.2.0. Failure to do that will disable WPForms User Journey functionality.

### Added
- Compatibility with WPForms 1.8.5.

### Changed
- Minimum WPForms version supported is 1.8.5.
- Deprecated `wpmu_new_blog` hook was replaced by the `wp_initialize_site` hook.

### Fixed
- User journey entry value was not added to the CSV file for the email notifications.
- Single Entry styles were inconsistent in Safari.
- Dates in entries were displayed in inconsistent formats.

## [1.1.0] - 2023-08-22
### Changed
- Minimum WPForms version supported is 1.8.3.
- Improved compatibility with PHP 8.1.

### Fixed
- The user journey was not saved when a user visited a page with quote marks in the page title.

## [1.0.6] - 2022-10-20
### Fixed
- No user journey details were added to new entries after clean installation.

## [1.0.5] - 2022-10-04
### Fixed
- The `{entry_user_journey}` smart tag was not rendered in re-sent email notifications when this action was performed on the single Entry view page.
- A fatal error occurred during the addon activation when the core plugin was inactive.

## [1.0.4] - 2022-09-29
### Changed
- Minimum WPForms version supported is 1.7.7.

### Fixed
- The new `{entry_user_journey}` smart tag wasn't displayed in a list of available smart tags.

## [1.0.3] - 2022-09-21
### Added
- User Journey smart tag for Confirmation message and Notification Email (HTML and plain text).

### Fixed
- The addon was generating too big cookies that in certain cases resulted in site being non-operational.

## [1.0.2] - 2022-02-10
### Changed
- Improved compatibility with PHP 8.

### Fixed
- Issue with JavaScript code on front-end in Internet Explorer 11.

## [1.0.1] - 2021-03-31
### Fixed
- Issue with JavaScript code for collecting data in cookies in Safari v14.

## [1.0.0] - 2020-11-12
### Added
- Initial release.
