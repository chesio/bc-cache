# BC Cache

[![GitHub Actions](https://github.com/chesio/bc-cache/workflows/CI%20test%20suite/badge.svg)](https://github.com/chesio/bc-cache/actions)
[![Packagist](https://img.shields.io/packagist/v/chesio/bc-cache.svg?color=34D058&style=popout)](https://packagist.org/packages/chesio/bc-cache)

Modern and simple full page cache plugin for WordPress inspired by [Cachify](https://wordpress.org/plugins/cachify/).

BC Cache has no settings page - it is intended for webmasters who are familiar with `.htaccess` files and WordPress actions and filters.

## Requirements

* Apache webserver with [mod_rewrite](https://httpd.apache.org/docs/current/mod/mod_rewrite.html) enabled
* [PHP](https://www.php.net/) 7.2 or newer
* [WordPress](https://wordpress.org/) 5.3 or newer with [pretty permalinks](https://codex.wordpress.org/Using_Permalinks) on

## Limitations

* BC Cache has not been tested with [WordPress block editor](https://wordpress.org/support/article/wordpress-editor/).
* BC Cache has not been tested on WordPress multisite installation.
* BC Cache has not been tested on Windows servers.
* BC Cache can only serve requests without filename in path, ie. `/some/path` or `some/other/path/`, but not `/some/path/to/filename.html`.

## Installation

BC Cache is not available at WordPress Plugins Directory, but there are several other ways you can get it.

### Using WP-CLI

If you have [WP-CLI](https://wp-cli.org/) installed, you can install (and optionally activate) BC Cache with a single command:
```
wp plugin install [--activate] https://github.com/chesio/bc-cache/archive/master.zip
```

### Using Composer

[Composer](https://getcomposer.org/) is a great tool for managing PHP project dependencies. Although WordPress itself does not make it easy to use Composer to manage WordPress installation as a whole, there are [multiple](https://composer.rarst.net/recipe/site-stack/) [ways](https://roots.io/bedrock/) how to do it.

BC Cache is available at [Packagist](https://packagist.org/packages/chesio/bc-cache), so just run `composer require chesio/bc-cache` as usual.

### Using Git

Master branch always contains latest stable version, so you can install BC Cache by cloning it from within your plugins directory:
```
cd [your-project]/wp-content/plugins
git clone --single-branch --branch master https://github.com/chesio/bc-cache.git
```

Updating is as easy as:
```
cd [your-project]/wp-content/plugins/bc-cache
git pull
```

### Using GitHub Updater plugin

BC Cache can be installed and updated via [GitHub Updater](https://github.com/afragen/github-updater) plugin.

### Direct download

This method is the least recommended, but it works without any other tool. You can download BC Cache directly from [GitHub](https://github.com/chesio/bc-cache/releases/latest). Make sure to unpack the plugin into correct directory and drop the version number from folder name.

## Setup

You have to configure your Apache webserver to serve cached files. Most common way to do it is to add the lines below to the root `.htaccess` file. This is the same file to which WordPress automatically writes pretty permalinks configuration - you **must** put the lines below **before** the pretty permalinks configuration.

Note: the configuration below assumes that you have WordPress installed in `wordpress` subdirectory - if it is not your case, simply drop the `/wordpress` part from the following rule: `RewriteRule .* - [E=BC_CACHE_ROOT:%{DOCUMENT_ROOT}/wordpress]`. In general, you may need to make some tweaks to the example configuration below to fit your server environment.

```.apacheconf
# BEGIN BC Cache
AddDefaultCharset utf-8

<IfModule mod_rewrite.c>
  RewriteEngine on

  # Configure document root.
  RewriteRule .* - [E=BC_CACHE_ROOT:%{DOCUMENT_ROOT}/wordpress]

  # Get request scheme (either http or https).
  RewriteCond %{ENV:HTTPS} =on [OR]
  RewriteCond %{HTTP:X-Forwarded-Proto} https
  RewriteRule .* - [E=BC_CACHE_SCHEME:https]
  RewriteCond %{ENV:HTTPS} !=on
  RewriteCond %{HTTP:X-Forwarded-Proto} !https
  RewriteRule .* - [E=BC_CACHE_SCHEME:http]

  # Clean up hostname (drop optional port number).
  RewriteCond %{HTTP_HOST} ^([^:]+)(:[0-9]+)?$
  RewriteRule .* - [E=BC_CACHE_HOST:%1]

  # Set path subdirectory (must end with slash).
  RewriteRule .* - [E=BC_CACHE_PATH:%{REQUEST_URI}/]
  RewriteCond %{REQUEST_URI} /$
  RewriteRule .* - [E=BC_CACHE_PATH:%{REQUEST_URI}]

  # Set request variant (by default there is only empty one).
  RewriteRule .* - [E=BC_CACHE_REQUEST_VARIANT:]

  # Optionally, serve gzipped version of HTML file.
  RewriteRule .* - [E=BC_CACHE_FILE:index%{ENV:BC_CACHE_REQUEST_VARIANT}.html]
  <IfModule mod_mime.c>
    RewriteCond %{HTTP:Accept-Encoding} gzip
    RewriteRule .* - [E=BC_CACHE_FILE:index%{ENV:BC_CACHE_REQUEST_VARIANT}.html.gz]
    AddType text/html .gz
    AddEncoding gzip .gz
  </IfModule>

  # Main rules: serve only GET requests with whitelisted query string fields coming from anonymous users.
  RewriteCond %{REQUEST_METHOD} GET
  RewriteCond %{QUERY_STRING} ^(?:(?:gclid|gclsrc|fbclid|utm_(?:source|medium|campaign|term|content))=[\w\-]*(?:&|$))*$
  RewriteCond %{HTTP_COOKIE} !(wp-postpass|wordpress_logged_in|comment_author)_
  RewriteCond %{ENV:BC_CACHE_ROOT}/wp-content/cache/bc-cache/%{ENV:BC_CACHE_SCHEME}/%{ENV:BC_CACHE_HOST}%{ENV:BC_CACHE_PATH}%{ENV:BC_CACHE_FILE} -f
  RewriteRule .* %{ENV:BC_CACHE_ROOT}/wp-content/cache/bc-cache/%{ENV:BC_CACHE_SCHEME}/%{ENV:BC_CACHE_HOST}%{ENV:BC_CACHE_PATH}%{ENV:BC_CACHE_FILE} [L,NS]

  # Do not allow direct access to cache entries.
  RewriteCond %{REQUEST_URI} /wp-content/cache/bc-cache/
  RewriteCond %{ENV:REDIRECT_STATUS} ^$
  RewriteRule .* - [F,L]
</IfModule>
# END BC Cache
```

## Configuration

BC Cache has no settings. You can modify plugin behavior with filters and [theme features](https://developer.wordpress.org/reference/functions/add_theme_support/):

### Filters

If there was a settings page, following filters would be likely turned into settings as they alter basic functionality:

* `bc-cache/filter:can-user-flush-cache` - filters whether current user can clear the cache. By default, any user with `manage_options` capability can clear the cache.
* `bc-cache/filter:disable-cache-locking` - filters whether cache locking should be disabled. By default, cache locking is enabled, but if your webserver has issues with [flock()](https://secure.php.net/manual/en/function.flock.php) or you notice degraded performance due to cache locking, you may want to disable it.
* `bc-cache/filter:html-signature` - filters HTML signature appended to HTML files stored in cache. You can use this filter to get rid of the signature: `add_filter('bc-cache/filter:html-signature', '__return_empty_string');`

#### Filters for advanced functions

Following filters can be used to tweak [automatic cache flushing](#automatic-cache-flushing):

* `bc-cache/filter:flush-hooks` - filters list of actions that trigger cache flushing. Filter is executed in a hook registered to `init` action with priority 10, so make sure to register your hook earlier (for example within `plugins_loaded` or `after_setup_theme` actions).
* `bc-cache/filter:is-public-post-type` - filters whether given post type should be deemed as public or not. Publishing or trashing of public post type items triggers [automatic cache flushing](#special-handling-of-posts-and-terms), but related action hooks cannot be adjusted with the `bc-cache/filter:flush-hooks` filter, you have to use this filter.
* `bc-cache/filter:is-public-taxonomy` - filters whether given taxonomy should be deemed as public or not. Creating, deleting or editing terms from public taxonomy triggers [automatic cache flushing](#special-handling-of-posts-and-terms), but related action hooks cannot be adjusted with the `bc-cache/filter:flush-hooks` filter, you have to use this filter.

Following filters can be used to extend list of [cache exclusions](#cache-exclusions) or whitelist some query string parameters:

* `bc-cache/filter:skip-cache` - filters whether response to current HTTP(S) request should be cached. Filter is only executed, when none from [built-in skip rules](#cache-exclusions) is matched - this means that you cannot override built-in skip rules with this filter, only add your own rules.
* `bc-cache/filter:query-string-fields-whitelist` - filters list of [query string](https://en.wikipedia.org/wiki/Query_string#Structure) fields that do not prevent cache write.

Following filters are necessary to set up [request variants](#request-variants):

* `bc-cache/filter:request-variant` - filters name of request variant of current HTTP request.
* `bc-cache/filter:request-variants` - filters list of all available request variants. You should use this filter, if you use variants and want to have complete and proper information about cache entries listed in [Cache Viewer](#cache-viewer).

Following filters are only useful if your theme declares support for [caching for front-end users](#front-end-users-and-caching):

* `bc-cache/filter:frontend-user-capabilities` - filters list of capabilities of front-end users.
* `bc-cache/filter:is-frontend-user` - filters whether current user is a front-end user.
* `bc-cache/filter:frontend-user-cookie-name` - filters name of front-end user cookie.
* `bc-cache/filter:frontend-user-cookie-value` - filters contents of front-end user cookie.

### Theme features

Some advanced features must be supported by your theme and are active only if the theme explicitly declares its support for particular feature:
* `add_theme_support('bc-cache/feature:frontend-users');` - activates [caching for front-end users](#front-end-users-and-caching).

## Automatic cache flushing

The cache is flushed automatically on core actions listed below. The list of actions can be [filtered](#filters) with `bc-cache/filter:flush-hooks` filter.

* WordPress gets updated:
  1. [`_core_updated_successfully`](https://developer.wordpress.org/reference/hooks/_core_updated_successfully/)

* Frontend changes:
  1. [`switch_theme`](https://developer.wordpress.org/reference/hooks/switch_theme/)
  2. [`wp_update_nav_menu`](https://developer.wordpress.org/reference/hooks/wp_update_nav_menu/)

* Post state changes from publish to another one (except trash). Note: publish and trash related actions are handled separately and for public posts only - [see below](#special-handling-of-posts-and-terms)):
  1. [`publish_to_draft`](https://developer.wordpress.org/reference/hooks/old_status_to_new_status/)
  2. [`publish_to_future`](https://developer.wordpress.org/reference/hooks/old_status_to_new_status/)
  3. [`publish_to_pending`](https://developer.wordpress.org/reference/hooks/old_status_to_new_status/)

* Comment changes:
  1. [`comment_post`](https://developer.wordpress.org/reference/hooks/comment_post/)
  2. [`edit_comment`](https://developer.wordpress.org/reference/hooks/edit_comment/)
  3. [`delete_comment`](https://developer.wordpress.org/reference/hooks/delete_comment/)
  4. [`wp_set_comment_status`](https://developer.wordpress.org/reference/hooks/wp_set_comment_status/)
  5. [`wp_update_comment_count`](https://developer.wordpress.org/reference/hooks/wp_update_comment_count/)

* Site widgets configuration changes:
  1. [`update_option_sidebars_widgets`](https://developer.wordpress.org/reference/hooks/update_option_option/) - the configuration is saved in `sidebars_widgets` option, so cache is flushed whenever this option is updated.

### Special handling of posts and terms

In WordPress, posts can be used to hold various types of data - including data that is not presented on frontend in any way. To make cache flushing as sensible as possible, when a post is published or trashed the cache is flushed only when post type is **public**. You may use `bc-cache/filter:is-public-post-type` [filter](#filters) to override whether a particular post type is deemed as public for cache flushing purposes or not.

Note: Changing post status to _draft_, _future_ or _pending_ always triggers cache flush (regardless of the post type).

Terms (taxonomies) are handled in a similar manner - cache is automatically flushed when a term is created, deleted or edited, but only in case of terms from a public taxonomy. You may use `bc-cache/filter:is-public-taxonomy` [filter](#filters) to override whether a particular taxonomy should be deemed as public or not.

## Cache exclusions

A response to HTTP(S) request is **not** cached by BC Cache if **any** of the conditions below evaluates as true:

1. Request is a POST request.
2. Request is a GET request with one or more query string fields that are not whitelisted. By default, the whitelist consists of [Google click IDs](https://support.google.com/searchads/answer/7342044), [Facebook Click Identifier](https://fbclid.com/) and standard [UTM parameters](https://en.wikipedia.org/wiki/UTM_parameters), but it can be [filtered](#filters).
3. Request is not for a front-end page (ie. [`wp_using_themes`](https://developer.wordpress.org/reference/functions/wp_using_themes/) returns `false`). Output of AJAX, WP-CLI or WP-Cron calls is never cached.
4. Request comes from a non-anonymous user (ie. user that is logged in, left a comment or accessed password protected page/post). The rule can be tweaked to ignore [front-end users](#front-end-users-and-caching) if your theme supports it.
5. Request/response type is one of the following: XML sitemap, search, 404, feed, trackback, robots.txt, preview or password protected post.
6. [Fatal error recovery mode](https://make.wordpress.org/core/2019/04/16/fatal-error-recovery-mode-in-5-2/) is active.
7. `DONOTCACHEPAGE` constant is set and evaluates to true. This constant is for example [automatically set](https://docs.woocommerce.com/document/configuring-caching-plugins/#section-1) by WooCommerce on certain pages.
8. Return value of `bc-cache/filter:skip-cache` filter evaluates to true.

**Important!** Cache exclusion rules are essentialy defined in two places:
1. In PHP code (including `bc-cache/filter:skip-cache` filter), the rules are used to determine whether current HTTP(S) request should be *written* to cache.
1. In `.htaccess` file, the rules are used to determine whether current HTTP(S) request should be *served* from cache.

When you add new rule for *cache writing* via `bc-cache/filter:skip-cache` filter, you should always consider whether the rule should be also enforced for *cache reading* via `.htaccess` file. In general, if your rule has no relation to request URI (for example you check cookies or `User-Agent` string), you probably want to have the rule in both places.
The same applies to `bc-cache/filter:query-string-fields-whitelist` filter - any extra whitelisted fields will not prevent *cache writing* anymore, but will still prevent *cache reading* unless they are integrated into respective rule in `.htaccess` file.

## Cache viewer

Contents of cache can be inspected (by any user with `manage_options` capability) via _Cache Viewer_ management page (under _Tools_). Users who can flush the cache are able to delete individual cache entries.

You may encounter a warning in Cache Viewer about total size of cache files being different from total size of files in cache folder - this usually means that you failed to correctly provide list of all available [request variants](#request-variants) via `bc-cache/filter:request-variants` filter.

## Front-end users and caching

_Note: front-end user is any user that has no business accessing `/wp-admin` area despite being able to log in via `wp-login.php`. Although the implementation details do not presume any particular plugin, following text is written with WooCommerce (and registered customers as front-end users) in mind._

Depending on your theme, the HTML served to front-end users can be identical to the HTML served to anonymous users. Such themes most often fetch any personalized content (like items added to cart) via a JavaScript call. In such case there is no reason to exclude front-end users from full page caching.

### There is a catch though...

Unlike some other content management systems, WordPress does not distinguish between back-end and front-end users. The same authentication mechanism is used to authenticate back-end users (like shop managers) and front-end users (like shop customers). As a fact, you cannot use the same email address to create a test customer account as you had used for shop manager account.

BC Cache by default does not read from or write to cache when HTTP request comes from any logged-in user:
1. When call to [`is_user_logged_in`](https://developer.wordpress.org/reference/functions/is_user_logged_in/) function returns true, response to HTTP request is not written to cache.
2. When HTTP request has a cookie with `wordpress_logged_in` in its name, response to HTTP request is not read from cache - this check must be [configured](#setup) in `.htaccess` file.

When your theme declares support for [front-end user caching](#theme-features):

The first check is relaxed automatically with some reasonable defaults: any user that has `read` and `customer` capabilities **only** is considered to be front-end user and any pages he/she visits are written to cache normally. You may [filter](#filters-for-advanced-functions) the capabilities list or the output of the check if you wish so.

To make it possible to relax the second check, BC Cache sets an **additional session cookie** whenever front-end user logs in. The rule in `.htaccess` file that deals with login cookie has to be extended as follows:

```.apacheconf
# The legacy rule is replaced by 3 rules below:
# RewriteCond %{HTTP_COOKIE} !(wp-postpass|wordpress_logged_in|comment_author)_
RewriteCond %{HTTP_COOKIE} !(wp-postpass|comment_author)_
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_ [OR]
RewriteCond %{HTTP_COOKIE} bc_cache_is_fe_user=true
```

This way cached pages can be served to front-end users too. Cookie name and content can be adjusted by [designated filters](#filters-for-advanced-functions) - make sure to adapt respective `.htaccess` rule if you change them.

## Request variants

Sometimes a different HTML is served as response to request to the same URL, typically when particular cookie is set or request is made by particular browser/bot. In such cases, BC Cache allows to define request variants and cache/serve different HTML responses based on configured conditions. A typical example is the situation in which privacy policy notice is displayed until site visitor accepts it. The state (cookie policy accepted or not) is often determined based on presence of particular cookie. Using request variants, BC Cache can serve visitors regardless if they have or have not accepted the cookie policy.

### Example

A website has two variants: one with cookie notice (_cookie_notice_accepted_ cookie is not set) and one without (_cookie_notice_accepted_ cookie is already set).

Request variant name should be set whenever cookie notice is accepted (example uses API of [Cookie Notice](https://wordpress.org/plugins/cookie-notice/) plugin):
```php
add_filter('bc-cache/filter:request-variant', function (string $default_variant): string {
    return cn_cookies_accepted() ? '_cna' : $default_variant;
}, 10, 1);

add_filter('bc-cache/filter:request-variants', function (array $variants): array {
    $variants['_cna'] = 'Cookie notice accepted';
    return $variants;
}, 10, 1);
```

The [default configuration](#installation) needs to be extended as well and set the new variant accordingly:

```.apacheconf
  # Set request variants (default and "cookie notice accepted"):
  RewriteRule .* - [E=BC_CACHE_REQUEST_VARIANT:]
  RewriteCond %{HTTP_COOKIE} cookie_notice_accepted=true
  RewriteRule .* - [E=BC_CACHE_REQUEST_VARIANT:_cna]
```

Important: Variant names are appended to basename part of cache file names, so `index.html` becomes `index_cna.html` and `index.html.gz` becomes `index_cna.html.gz` in the example above. To make sure your setup will work, use only letters from `[a-z0-9_-]` range as variant names.

## Flushing the cache programmatically

If you want to flush BC Cache cache from within your code, just call `do_action('bc-cache/action:flush-cache')`. Note that the action is available after the `init` hook with priority `10` is executed.

## Autoptimize integration

[Autoptimize](https://wordpress.org/plugins/autoptimize/) is a very popular plugin to optimize script and styles by aggregation, minification, caching etc. BC Cache automatically flushes its cache whenever Autoptimize cache is purged.

## WP-CLI integration

You might use [WP-CLI](https://wp-cli.org/) to delete specific posts/pages form cache, flush entire cache or get size information. BC Cache registers `bc-cache` command with following subcommands:

* `delete <post-id>` - deletes cache data (all request variants) of post/page with given ID
* `remove <url>` - deletes cache data (all request variants) of given URL
* `flush` - flushes entire cache
* `size [--human-readable]` -- retrieves cache directory apparent size, optionally in human readable format

## Credits

* Sergej Müller & Plugin Kollektiv for inspiration in form of [Cachify plugin](https://wordpress.org/plugins/cachify/).
* Font Awesome for [HDD icon](http://fontawesome.io/icon/hdd-o/)
* Tim Lochmüller for inspirational tweaks to `.htaccess` configuration taken from his [Static File Cache](https://github.com/lochmueller/staticfilecache) extension
