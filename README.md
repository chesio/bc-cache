# BC Cache

Simple full page cache plugin for WordPress inspired by Cachify.

## Requirements

* Apache webserver with [mod_rewrite](https://httpd.apache.org/docs/current/mod/mod_rewrite.html) enabled
* [PHP](https://www.php.net/) 7.1 or newer
* [WordPress](https://wordpress.org/) 4.7 or newer with [pretty permalinks](https://codex.wordpress.org/Using_Permalinks) on

## Limitations

* BC Cache has not been tested on WordPress multisite installation.
* BC Cache has not been tested on Windows servers.
* BC Cache can only serve requests without filename in path, ie. `/some/path` or `some/other/path/`, but not `/some/path/to/filename.html`.

## Installation

You have to configure your Apache webserver to serve cached files. Most common way to do it is to add the lines below to the root `.htaccess` file (ie. the same file to which WordPress automatically writes pretty permalinks configuration).

Note: the configuration below assumes that you have WordPress installed in `wordpress` subdirectory - if it is not your case, simply drop the `/wordpress` part from the following rule: `RewriteRule .* - [E=BC_CACHE_ROOT:%{DOCUMENT_ROOT}/wordpress]`. In general, you may need to make some tweaks to the configuration below to fit your server environment.

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
* `bc-cache/filter:flush-hooks` - filters list of actions that trigger cache flushing. Filter is executed in a hook registered to `init` action with priority 10, so make sure to register your hook earlier (for example within `plugins_loaded` or `after_setup_theme` actions).
* `bc-cache/filter:html-signature` - filters HTML signature appended to HTML files stored in cache. You can use this filter to get rid of the signature: `add_filter('bc-cache/filter:html-signature', '__return_empty_string');`
* `bc-cache/filter:skip-cache` - filters whether response to current HTTP(S) request should be cached. Filter is only executed, when none from [built-in skip rules](#cache-exclusions) is matched - this means that you cannot override built-in skip rules with this filter, only add your own rules.
* `bc-cache/filter:request-variant` - filters name of [request variant](#request-variants) of current HTTP request.
* `bc-cache/filter:request-variants` - filters list of all available [request variants](#request-variants). You should use this filter, if you use variants and want to have complete and proper information about cache entries listed in [Cache Viewer](#cache-viewer).
* `bc-cache/filter:query-string-fields-whitelist` - filters list of [query string](https://en.wikipedia.org/wiki/Query_string#Structure) fields that do not prevent cache write.

## Cache exclusions

A response to HTTP(S) request is cached by BC Cache if **none** of the conditions below is true:

1. Request is a POST request.
2. Request is a GET request with one or more query string fields that are not whitelisted. By default, the whitelist consists of [Google click IDs](https://support.google.com/searchads/answer/7342044), [Facebook Click Identifier](https://fbclid.com/) and standard [UTM parameters](https://en.wikipedia.org/wiki/UTM_parameters), but it can be [filtered](#configuration).
3. Request is not routed through main `index.php` file (ie. `WP_USE_THEMES` is not set to `true`). Output of AJAX, WP-CLI or WP-Cron calls is never cached.
4. Request comes from logged in user or non-anonymous user (ie. user that left a comment or accessed password protected page/post)
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

You may notice that Cache Viewer displays cache size twice - there is a subtle, but sometimes important [difference](https://github.com/chesio/bc-cache/issues/35):
1. Apparent cache directory size includes size of all directories and files within root cache directory and should always match the output of Unix `du -sb` command.
2. Cache files size is sum of sizes of all valid cache files, ie. what Cache Viewer reports in the table. If list of all available [request variants](#request-variants) is set up correctly via `bc-cache/filter:request-variants` filter, the difference to apparent cache directory size should be negligible as it should only equal to total size of (sub)directories.

## Request variants

Sometimes a different HTML is served as response to request to the same URL, typically when particular cookie is set or request is made by particular browser/bot. In such cases, BC Cache allows to define request variants and cache/serve different HTML responses based on configured conditions. A typical example in EU countries is the situation in which cookie policy notice is displayed to user until the user accepts it. The state (cookie policy accepted or not) is often determined based on presence of particular cookie. Using request variants, BC Cache can serve both users that have and have not accepted the cookie policy.

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
