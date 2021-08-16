# BC Cache Changelog

## Upcoming version 2.0.0 (????-??-??)

This release contains some breaking changes (see **!**):

* `_gl` query string tracking parameter does not interfere with caching [#53]
* make sure the plugin is not accidentally overriden from WordPress.org Plugins Directory [#51] - requires WordPress 5.8 or newer
* (**!**) refactor use of `add_theme_support` [#48]
* (**!**) flatten cache directory structure [#45]

## Version 1.9.1 (2021-08-16)

* test with WordPress 5.7
* prefix Disallow directive in robots.txt with User-Agent rule [#52]

## Version 1.9.0 (2020-09-22)

WordPress 5.3 is now required.

* fix/improve internal handling of timestamps [#40]
* automatically flush the cache when a term is created/deleted/edited [#44] or site widgets configuration changes [#46]
* do not cache default WordPress sitemap introduced in WP 5.5 [#47]

## Version 1.8.0 (2020-05-22)

WordPress 5.1 and PHP 7.2 are now required.

* caching can be utilized for front-end users too [#41]
* plugin can be installed from Packagist [#42]
* several minor optimizations [#43] and [#29]

## Version 1.7.1 (2019-08-28)

Fixes problem when cache has not been flushed when a post of public post type has been published or trashed - see #39 for more background.

## Version 1.7.0 (2019-08-14)

* Optimized handling of cache information (age, size) [#36]
* Fixed inconsistency in cache size reporting [#37]
* Minor code optimizations

## Older releases

Changelog for older releases can be found (here)[https://github.com/chesio/bc-cache/releases].
