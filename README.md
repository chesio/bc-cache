# BC Cache

Modern and simple full page cache plugin for WordPress inspired by [Cachify](https://wordpress.org/plugins/cachify/).

BC Cache has no settings page - it is intended for webmasters who are familiar with `.htaccess` files and WordPress actions and filters.

## Requirements

* Apache webserver with [mod_rewrite](https://httpd.apache.org/docs/current/mod/mod_rewrite.html) enabled
* [PHP](https://www.php.net/) 7.2 or newer
* [WordPress](https://wordpress.org/) 5.1 or newer with [pretty permalinks](https://codex.wordpress.org/Using_Permalinks) on

## Limitations

* BC Cache has not been tested on WordPress multisite installation.
* BC Cache has not been tested on Windows servers.
* BC Cache can only serve requests without filename in path, ie. `/some/path` or `some/other/path/`, but not `/some/path/to/filename.html`.

## Installation

BC Security is not available at WordPress Plugins Directory, but there are several other ways you can get it.

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

BC Cache has no settings. You can modify plugin behavior with following filters:
* `bc-cache/filter:can-user-flush-cache` - filters whether current user can clear the cache. By default, any user with `manage_options` capability can clear the cache.
* `bc-cache/filter:disable-cache-locking` - filters whether cache locking should be disabled. By default, cache locking is enabled, but if your webserver has issues with [flock()](https://secure.php.net/manual/en/function.flock.php) or you notice degraded performance due to cache locking, you may want to disable it.
* `bc-cache/filter:flush-hooks` - filters [list of actions](#automatic-cache-flushing) that trigger cache flushing. Filter is executed in a hook registered to `init` action with priority 10, so make sure to register your hook earlier (for example within `plugins_loaded` or `after_setup_theme` actions).
* `bc-cache/filter:is-public-post-type` - filters whether given post type should be deemed as public or not. Publishing or trashing of public post type items triggers [cache flush](#special-posts-handling), but related action hooks cannot be filtered with the `bc-cache/filter:flush-hooks` filter, you have to use this filter.
* `bc-cache/filter:html-signature` - filters HTML signature appended to HTML files stored in cache. You can use this filter to get rid of the signature: `add_filter('bc-cache/filter:html-signature', '__return_empty_string');`
* `bc-cache/filter:skip-cache` - filters whether response to current HTTP(S) request should be cached. Filter is only executed, when none from [built-in skip rules](#cache-exclusions) is matched - this means that you cannot override built-in skip rules with this filter, only add your own rules.
* `bc-cache/filter:request-variant` - filters name of [request variant](#request-variants) of current HTTP request.
* `bc-cache/filter:request-variants` - filters list of all available [request variants](#request-variants). You should use this filter, if you use variants and want to have complete and proper information about cache entries listed in [Cache Viewer](#cache-viewer).
* `bc-cache/filter:query-string-fields-whitelist` - filters list of [query string](https://en.wikipedia.org/wiki/Query_string#Structure) fields that do not prevent cache write.

## Automatic cache flushing

The cache is flushed automatically on core actions listed below. The list of actions can be [filtered](#configuration) with `bc-cache/filter:flush-hooks` filter.

* WordPress gets updated:
  1. [`_core_updated_successfully`](https://developer.wordpress.org/reference/hooks/_core_updated_successfully/)

* Frontend changes:
  1. [`switch_theme`](https://developer.wordpress.org/reference/hooks/switch_theme/)
  2. [`wp_update_nav_menu`](https://developer.wordpress.org/reference/hooks/wp_update_nav_menu/)

* Post state changes from publish to another one (except trash). Note: publish and trash related actions are handled separately and for public posts only - [see below](#special-posts-handling)):
  1. [`publish_to_draft`](https://developer.wordpress.org/reference/hooks/old_status_to_new_status/)
  2. [`publish_to_future`](https://developer.wordpress.org/reference/hooks/old_status_to_new_status/)
  3. [`publish_to_pending`](https://developer.wordpress.org/reference/hooks/old_status_to_new_status/)

* Comment changes:
  1. [`comment_post`](https://developer.wordpress.org/reference/hooks/comment_post/)
  2. [`edit_comment`](https://developer.wordpress.org/reference/hooks/edit_comment/)
  3. [`delete_comment`](https://developer.wordpress.org/reference/hooks/delete_comment/)
  4. [`wp_set_comment_status`](https://developer.wordpress.org/reference/hooks/wp_set_comment_status/)
  5. [`wp_update_comment_count`](https://developer.wordpress.org/reference/hooks/wp_update_comment_count/)

### Special posts handling

In WordPress, posts can be used to hold various types of data - including data that is not presented on frontend in any way. To make cache flushing as sensible as possible, when a post is published or trashed the cache is flushed only when post type is **public**. You may use `bc-cache/filter:is-public-post-type` [filter](#configuration) to determine whether a particular post type is deemed as public for cache flushing purposes or not.

Note: Changing post status to _draft_, _future_ or _pending_ always triggers cache flush (regardless of the post type).

## Cache exclusions

A response to HTTP(S) request is **not** cached by BC Cache if **any** of the conditions below evaluates as true:

1. Request is a POST request.
2. Request is a GET request with one or more query string fields that are not whitelisted. By default, the whitelist consists of [Google click IDs](https://support.google.com/searchads/answer/7342044), [Facebook Click Identifier](https://fbclid.com/) and standard [UTM parameters](https://en.wikipedia.org/wiki/UTM_parameters), but it can be [filtered](#configuration).
3. Request is not for a front-end page (ie. [`wp_using_themes`](https://developer.wordpress.org/reference/functions/wp_using_themes/) returns `false`). Output of AJAX, WP-CLI or WP-Cron calls is never cached.
4. Request comes from logged in user or non-anonymous user (ie. user that left a comment or accessed password protected page/post).
5. Request/response type is one of the following: search, 404, feed, trackback, robots.txt, preview or password protected post.
6. [Fatal error recovery mode](https://make.wordpress.org/core/2019/04/16/fatal-error-recovery-mode-in-5-2/) is active.
7. `DONOTCACHEPAGE` constant is set and evaluates to true.
8. Return value of `bc-cache/filter:skip-cache` filter evaluates to true.

**Important!** Cache exclusion rules are essentialy defined in two places:
1. In PHP code (including `bc-cache/filter:skip-cache` filter), the rules are used to determine whether current HTTP(S) request should be *written* to cache.
1. In `.htaccess` file, the rules are used to determine whether current HTTP(S) request should be *served* from cache.

When you add new rule for *cache writing* via `bc-cache/filter:skip-cache` filter, you should always consider whether the rule should be also enforced for *cache reading* via `.htaccess` file. In general, if your rule has no relation to request URI (for example you check cookies or `User-Agent` string), you probably want to have the rule in both places.
The same applies to `bc-cache/filter:query-string-fields-whitelist` filter - any extra whitelisted fields will not prevent *cache writing* anymore, but will still prevent *cache reading* unless they are integrated into respective rule in `.htaccess` file.

## Cache viewer

Contents of cache can be inspected (by any user with `manage_options` capability) via _Cache Viewer_ management page (under _Tools_). Users who can flush the cache are able to delete individual cache entries.

You may encounter a warning in Cache Viewer about total size of cache files being different from total size of files in cache folder - this usually means that you failed to correctly provide list of all available [request variants](#request-variants) via `bc-cache/filter:request-variants` filter.

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
