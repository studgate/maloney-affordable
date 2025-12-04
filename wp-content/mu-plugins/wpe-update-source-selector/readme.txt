=== WP Engine Update Source Selector ===
Contributors: wpengine
Tags: wordpress core downloads, plugin downloads, theme downloads, search, updates
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Install or update WordPress core, plugins, and themes from the source that works best for your site and workflows.

== Description ==

When you install or update WordPress core, plugins, and themes, those assets are downloaded from a remote source via an API. If you do not wish to use the default source for your site, choose the source that works best for your site and workflows.

WP Engine Update Source Selector was developed by WP Engine to empower the WordPress community with the freedom to choose where WordPress core, plugins, and themes are downloaded from. With this plugin, your ability to keep your website up to date and secure no longer depends on a single source.

== Frequently Asked Questions ==

= I can't see the menu option for the settings page? =

For security, the settings page is disabled by default.

To enable the settings page, you'll need to implement the `wpe_uss_enable_admin_ui` filter,
in a custom must-use plugin, as seen at the bottom of this readme.

= Who can access the user interface? =

Only Administrators or Network Administrators (on multisites) can access the settings page.

= Can it be used as a Must-Use Plugin? =

Yes!

1. Unzip the plugin into the `mu-plugins` directory to create the `wpe-update-source-selector` subdirectory.
2. Symlink, move or copy the `wpe-update-source-selector/wpe-update-source-selector.php` entry file into the `mu-plugins` directory.

= Can it be used in a Multisite install? =

Yes!

It will be active at the network level and visible to Network Administrators only.

== Screenshots ==

1. Settings page showing default update source.
2. Preferred update source saved.
3. Default update source configured in hosting account.
4. Host override enforced.
5. Host override enforced with custom notice icon, title and message.
6. About tab.

== Changelog ==

= 1.1.5 =

= 1.1.4 =
* Fix: Protect against 3rd party plugins mistakenly calling wp_remote_get() with non-string URL argument.

= 1.1.3 =
* Fix the URL returned by the wpe_uss_get_settings_page_url filter when called from a multisite subsite.

= 1.1.2 =
* A fatal error no longer occurs when another plugin has already loaded a class named Autoloader.

= 1.1.1 =
* Improve compatibility with WP-CLI.

= 1.1.0 =
* Initialization of the API Request Manager now only happens the 1st time a remote request is made.

= 1.0.0 =
* Initial version.

== Filters ==

The plugin fires a number of filters that can be used by hosting providers to change the default preferred source,
including supplying their own source, or overriding the site admin's selection should there be connectivity issues
affecting performance or security.

= Sources =

There are two sources bundled with the plugin:

* `wordpress`: The default source used by WordPress Core providing updates from WordPress.org.
* `wpengine`: WP Engine’s Mirror of the WordPress.org update service that provides an availability cache of the WordPress.org API.

When setting the preferred source with any of the following filters, you can use either `wordpress` or `wpengine` as the Source Key.

It's also possible to define a custom source, please see the unit tests for an example of this advanced use:

Within the [GitHub repository](https://github.com/wpengine/wpe-update-source-selector/), navigate to the [test_get_alt_sources_with_extra_source](tests/classes/test-wpe-update-source-selector.php#L69) test.

= Host Preference =

The host can implement this filter to set a preferred source.

```php
/**
 * Filter enables a host to set a preferred source.
 *
 * @param string   $source_key  Source key, default none (empty string).
 * @param string[] $source_keys An array of source keys that may be selected from.
 *
 * @return string
 */
function my_wpe_uss_get_host_preference( $source_key, array $source_keys ) {
    if ( in_array( 'wpengine', $source_keys ) ) {
        return 'wpengine';
    }

    return $source_key;
}
add_filter( 'wpe_uss_get_host_preference', 'my_wpe_uss_get_host_preference', 10, 2 );
```

This is the lowest priority level at which a preferred source can be set, allowing for the hosting account and site admins to override with their preference via the settings page.

= Hosting Account Preference =

The host can implement this filter to pass on a hosting account's preferred source.

```php
/**
 * Filter enables a hosting account to set a preferred source.
 *
 * @param string   $source_key  Source key, default none (empty string).
 * @param string[] $source_keys An array of source keys that may be selected from.
 *
 * @return string
 */
function my_wpe_uss_get_hosting_account_preference( $source_key, array $source_keys ) {
    if ( in_array( 'wpengine', $source_keys ) ) {
        return 'wpengine';
    }

    return $source_key;
}
add_filter( 'wpe_uss_get_hosting_account_preference', 'my_wpe_uss_get_hosting_account_preference', 10, 2 );
```

This will override a host's preferred source, but allow a site admin to override with their preference via the settings page.

= Host Override =

The host can implement this filter to temporarily override any preference and force use of a particular source.

This may be needed if there are connectivity or security issues with one or more sources.

```php
/**
 * Filter enables a host to override any source preferences in order to
 * overcome widespread connectivity or security issues.
 *
 * @param string   $source_key  Source key, default none (empty string).
 * @param string[] $source_keys An array of source keys that may be selected from.
 *
 * @return string
 */
function my_wpe_uss_get_host_override( $source_key, array $source_keys ) {
    if ( in_array( 'wpengine', $source_keys ) ) {
        return 'wpengine';
    }

    return $source_key;
}
add_filter( 'wpe_uss_get_host_override', 'my_wpe_uss_get_host_override', 10, 2 );
```

This will override all preferences, including disabling selection of an alternative source via the settings page.

A warning notice will also be displayed within the settings page to inform site admins of the temporary override.

= Host Override Notice =

If the host override has been implemented, a warning notice is shown in the settings page. This filter allows for changing the icon, title and message shown within that notice.

```php
/**
 * Allows filtering of the data used to create the host override notice.
 *
 * @param array<string,string> $args             An associative array with keys dashicon, imgsrc, title and msg.
 *                                               `dashicon` is an optional string for dashicon to show before the title, e.g. "dashicons-warning".
 *                                               `imgsrc` is an optional URL to be used as the src for an img tag, can be a data URL. Takes priority over the dashicon.
 *                                               `title` is an optional string to set as the warning notice's title. If not supplied, icon will not be shown either.
 *                                               `msg` is a required string used as the warning notice's main text. If not supplied, a default is used.
 * @param string               $core_source_name The name of the core source used in the message.
 * @param string               $alt_source_name  The name of the alternative source used in the message.
 *
 * @return array<string,string>
 */
function my_wpe_uss_host_override_notice( $args, string $core_source_name, string $alt_source_name ) {
    $args['imgsrc'] = 'data:image/svg+xml;base64,LOTSOFBASE64ENCODEDSVGDATASHOWNAT18PXHEIGHT';
    $args['title']  = 'Wibble Wobble Hosting Co is temporarily managing your source';

    $msg = sprintf(
        'We\'re experiencing connectivity issues with various sources, so have temporarily set %1$s as the source.',
        $alt_source_name
    );

    $args['msg'] = $msg;

    return $args;
}
add_filter( 'wpe_uss_host_override_notice', 'my_wpe_uss_host_override_notice', 10, 3 );
```

= Enable Admin UI =

The admin settings page is disabled by default, but can be enabled with this filter.

```php
/**
 * Filter whether the admin UI is enabled or not.
 *
 * @param bool $enable_admin_ui Whether admin UI may be shown to users with appropriate capabilities, default false.
 *
 * @return bool
 */
function my_wpe_uss_enable_admin_ui( $enable_admin_ui ) {
    return true;
}
add_filter( 'wpe_uss_enable_admin_ui', 'my_wpe_uss_enable_admin_ui' );
```

Or just ...

```php
add_filter( 'wpe_uss_enable_admin_ui', '__return_true' );
```
