# BC Cache Changelog

## Upcoming version 2.3.0 (????-??-??)

## Version 2.2.0 (2022-01-31)

This release has been tested with WordPress 5.9.

### Added

* WP-CLI command `bc-cache warm-up` to run cache warm up from command line [#71](https://github.com/chesio/bc-cache/issues/71).
* Cache warm up now works on any website with XML sitemap(s) [#73](https://github.com/chesio/bc-cache/issues/73).
* Cache Viewer now displays warm up progress as well [#74](https://github.com/chesio/bc-cache/issues/74).
* Warm up can be immediately started from Cache Viewer [#66](https://github.com/chesio/bc-cache/issues/66).

### Changed

* The `bc-cache/filter:cache-warm-up-enable` filter has been removed - use `BC_CACHE_WARM_UP_ENABLED` constant to disable cache warm up [#75](https://github.com/chesio/bc-cache/issues/75).
* The `bc-cache/filter:disable-cache-locking` filter has been removed - use `BC_CACHE_FILE_LOCKING_ENABLED` constant to disable file locking instead [#75](https://github.com/chesio/bc-cache/issues/75).

## Version 2.1.1 (2022-01-12)

### Fixed

* Re-activate warm up crawler after single cache entries are deleted either via WP-CLI command or Cache Viewer [#72](https://github.com/chesio/bc-cache/issues/72).

## Version 2.1.0 (2022-01-11)

Improve cache warm up feature and include some further improvements and tweaks.

### Added

* Plugin should be compatible with PHP 8.1 [#67](https://github.com/chesio/bc-cache/issues/67).
* Plugin deactivates itself automatically if pretty permalink structure is not activated [#69](https://github.com/chesio/bc-cache/issues/69).
* Cache warm up works on websites with XML sitemaps output by [The SEO Framework](https://wordpress.org/plugins/autodescription/) plugin [#58](https://github.com/chesio/bc-cache/issues/58). Note: version `4.2.0` or newer of The SEO Framework is required for the integration to work.
* Cache warm up works on websites with XML sitemaps output by [Yoast SEO](https://wordpress.org/plugins/wordpress-seo/) plugin [#57](https://github.com/chesio/bc-cache/issues/57). Note: version `17.0` or newer of Yoast SEO is required for the integration to work.
* Cache warm up works on [Polylang](https://wordpress.org/plugins/polylang/)-powered multilanguage websites [#59](https://github.com/chesio/bc-cache/issues/59).
* When a cache entry is deleted via WP-CLI or Cache Viewer, all variants of related URL are added to cache warm up queue automatically [#60](https://github.com/chesio/bc-cache/issues/60).
* Introduce `bc-cache/filter:cache-warm-up-initial-url-list` filter.

### Changed

* Change name of following cache warm up related filter: `bc-cache/filter:cache-warm-url-list` is now `bc-cache/filter:cache-warm-up-final-url-list`.

### Fixed

* WP-CLI `delete` and `remove` commands do actually work now [#61](https://github.com/chesio/bc-cache/issues/61).

### Removed

* Plugin no longer officially supports PHP 7.2, PHP 7.3 or newer is required.

## Version 2.0.2 (2021-10-20)

This is a hotfix release:

* Output buffer handling has been optimized to improve integration with other plugins [#63](https://github.com/chesio/bc-cache/issues/63)

## Version 2.0.1 (2021-10-08)

This is a hotfix release:

* Avoid falling into infinite loop when server starts having problems with loopback requests [#62](https://github.com/chesio/bc-cache/issues/62)

## Version 2.0.0 (2021-09-02)

__WordPress 5.5 or newer is required.__

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
