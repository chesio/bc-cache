# BC Cache Changelog

## Version 2.0.1 (2021-10-08)

This is a hotfix release:

* Avoid falling into infinite loop when server starts having problems with loopback requests [#62](https://github.com/chesio/bc-cache/issues/62)

## Version 2.0.0 (2021-09-02)

WordPress 5.5 or newer is required.

This release brings major new feature: __cache warm up__ [#15](https://github.com/chesio/bc-cache/issues/15). See [README](README.md#cache-warm-up) for more information.

This release also contains some breaking changes:

* Use of `add_theme_support` has been refactored [#48](https://github.com/chesio/bc-cache/issues/48).
* Cache directory structure has been flattened [#45](https://github.com/chesio/bc-cache/issues/45). As a nice side-effect of this change, the `.htaccess` rules are now compatible with [7G firewall](https://perishablepress.com/7g-firewall/).

Other notable changes in this release:

* The `_gl` query string tracking parameter does not interfere with caching [#53](https://github.com/chesio/bc-cache/issues/53). Note that `.htaccess` file should be updated accordingly to make full use of this feature.
* On WordPress 5.8 and newer the plugin cannot be accidentally overriden from WordPress.org Plugins Directory [#51](https://github.com/chesio/bc-cache/issues/51).

Some bugs have been fixed too:

* Sortable columns in Cache Viewer can be used for sorting again [#54](https://github.com/chesio/bc-cache/issues/54).

## Version 1.9.2 (2021-08-29)

This bugfix release properly excludes XML sitemap stylesheet from caching [#47](https://github.com/chesio/bc-cache/issues/47).

## Version 1.9.1 (2021-08-16)

* test with WordPress 5.7
* prefix Disallow directive in robots.txt with User-Agent rule [#52](https://github.com/chesio/bc-cache/issues/52)

## Version 1.9.0 (2020-09-22)

WordPress 5.3 is now required.

* fix/improve internal handling of timestamps [#40](https://github.com/chesio/bc-cache/issues/40)
* automatically flush the cache when a term is created/deleted/edited [#44] or site widgets configuration changes [#46](https://github.com/chesio/bc-cache/issues/46)
* do not cache default WordPress sitemap introduced in WP 5.5 [#47](https://github.com/chesio/bc-cache/issues/47)

## Version 1.8.0 (2020-05-22)

WordPress 5.1 and PHP 7.2 are now required.

* caching can be utilized for front-end users too [#41](https://github.com/chesio/bc-cache/issues/41)
* plugin can be installed from Packagist [#42](https://github.com/chesio/bc-cache/issues/42)
* several minor optimizations [#43](https://github.com/chesio/bc-cache/issues/43) and [#29](https://github.com/chesio/bc-cache/issues/29)

## Version 1.7.1 (2019-08-28)

Fixes problem when cache has not been flushed when a post of public post type has been published or trashed - see [#39](https://github.com/chesio/bc-cache/issues/39) for more background.

## Version 1.7.0 (2019-08-14)

* Optimized handling of cache information (age, size) [#36](https://github.com/chesio/bc-cache/issues/36)
* Fixed inconsistency in cache size reporting [#37](https://github.com/chesio/bc-cache/issues/37)
* Minor code optimizations

## Older releases

Changelog for older releases can be found (here)[https://github.com/chesio/bc-cache/releases].
