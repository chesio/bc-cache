# BC Cache

Simple disk cache for WordPress inspired by Cachify.

## Requirements
* Apache webserver with [mod_rewrite](https://httpd.apache.org/docs/current/mod/mod_rewrite.html) enabled
* [PHP](https://secure.php.net/) 7.0 or newer
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

  # Optionally, serve gzipped version of HTML file.
  RewriteRule .* - [E=BC_CACHE_FILE:index.html]
  <IfModule mod_mime.c>
    RewriteCond %{HTTP:Accept-Encoding} gzip
    RewriteRule .* - [E=BC_CACHE_FILE:index.html.gz]
    AddType text/html .gz
    AddEncoding gzip .gz
  </IfModule>

  # Main rules: serve only GET requests without query string from anonymous users.
  RewriteCond %{REQUEST_METHOD} GET
  RewriteCond %{QUERY_STRING} =""
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
* `bc-cache/filter:flush-hooks` - filters list of actions that trigger cache flushing. Filter is executed in a hook registered to `init` action with priority 10, so make sure to register your hook earlier (for example within `plugins_loaded` or `after_setup_theme` actions).
* `bc-cache/filter:html-signature` - filters HTML signature appended to HTML files stored in cache. You can use this filter to get rid of the signature: `add_filter('bc-cache/filter:html-signature', '__return_empty_string');`
* `bc-cache/filter:skip-cache` - filters whether response to current HTTP(S) request should be cached. Filter is only executed, when none from [built-in skip rules](#cache-exclusions) is matched - this means that you cannot override built-in skip rules with this filter, only add your own rules.
* `bc-cache/filter:request-variant` - filters name of [request variant](#request-variants) of current HTTP request.

## Cache exclusions

A response to HTTP(S) request is cached by BC Cache if **none** of the conditions below is true:

1. Request is a POST request.
1. Request is a GET request with non-empty query string.
1. Request is not routed through main `index.php` file (ie. AJAX, WP-CLI or WP-Cron calls are not cached).
1. Request comes from logged in user or non-anonymous user (ie. user that left a comment or accessed password protected page/post)
1. Request/response type is one of the following: search, 404, feed, trackback, robots.txt, preview or password protected post.
1. `DONOTCACHEPAGE` constant is set and evaluates to true.
1. Return value of `bc-cache/filter:skip-cache` filter evaluates to true.

**Important!** Cache exclusion rules are essentialy defined in two places:
1. In PHP code (including `bc-cache/filter:skip-cache` filter), the rules are used to determine whether current HTTP(S) request should be *written* to cache.
1. In `.htaccess` file, the rules are used to determine whether current HTTP(S) request should be *served* from cache.

When you add new rule for *cache writing* via `bc-cache/filter:skip-cache` filter, you should always consider whether the rule should be also enforced for *cache reading* via `.htaccess` file. In general, if your rule has no relation to request URI (for example you check cookies or `User-Agent` string), you probably want to have the rule in both places.

## Request variants

Sometimes a different HTML is served as response to request to the same URL, typically when particular cookie is set or request is made by particular browser/bot. In such cases, BC Cache allows to define request variants and cache/serve different HTML responses based on configured conditions. A typical example in EU countries is the situation in which cookie policy notice is displayed to user until the user accepts it. The state (cookie policy accepted or not) is often determined based on presence of particular cookie. Using request variants, BC Cache can serve both users that have and have not accepted the cookie policy.

### Example

A website has two variants: one with cookie notice (_cookie_notice_accepted_ cookie is not set) and one without (_cookie_notice_accepted_ cookie is already set).

Request variant name should be set whenever cookie notice is accepted (example uses API of [Cookie Notice](https://wordpress.org/plugins/cookie-notice/) plugin):
```php
add_filter('bc-cache/filter:request-variant', function (string $default_variant): string {
    return cn_cookies_accepted() ? '_cna' : $default_variant;
}, 10, 1);
```

The [default configuration](#installation) needs to be extended in the following way:

```.apacheconf

  # gzip
  RewriteRule .* - [E=BC_CACHE_FILE:index.html]
  RewriteCond %{HTTP_COOKIE} cookie_notice_accepted=true
  RewriteRule .* - [E=BC_CACHE_FILE:index_cna.html]
  <IfModule mod_mime.c>
    RewriteCond %{HTTP:Accept-Encoding} gzip
    RewriteRule .* - [E=BC_CACHE_FILE:index.html.gz]
    RewriteCond %{HTTP:Accept-Encoding} gzip
    RewriteCond %{HTTP_COOKIE} cookie_notice_accepted=true
    RewriteRule .* - [E=BC_CACHE_FILE:index_cna.html.gz]
    AddType text/html .gz
    AddEncoding gzip .gz
  </IfModule>
```

Notice, how viariant name `_cna` is appended to basename part of cache file names, so `index.html` becomes `index_cna.html` and `index.html.gz` becomes `index_cna.html.gz`. To make sure your setup will work, use only letters from `[a-z0-9_-]` set in variant name.

## Credits

* Sergej Müller & Plugin Kollektiv for inspiration in form of [Cachify plugin](https://wordpress.org/plugins/cachify/).
* Font Awesome for [HDD icon](http://fontawesome.io/icon/hdd-o/)
* Tim Lochmüller for inspirational tweaks to `.htaccess` configuration taken from his [Static File Cache](https://github.com/lochmueller/staticfilecache) extension
