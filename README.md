# BC Cache

Simple disk cache for WordPress inspired by Cachify.

## Requirements
* Apache webserver with [mod_rewrite](https://httpd.apache.org/docs/current/mod/mod_rewrite.html) enabled
* [PHP](https://secure.php.net/) 7.0 or newer
* [WordPress](https://wordpress.org/) 4.7 or newer with [pretty permalinks](https://codex.wordpress.org/Using_Permalinks) on

## Limitations

* BC Cache has not been tested on WordPress multisite installation.

## Installation

You have to configure your Apache webserver to serve cached files. One way to do it is to add the lines below to the root `.htaccess` file (ie. the same file to which WordPress automatically writes pretty permalinks configuration). Note that the configuration below assumes that you have WordPress installed in `wordpress` subdirectory - if it is not your case, simply drop the `/wordpress` part from the following rules:

* `RewriteCond %{REQUEST_URI} !^/wordpress/(wp-admin|wp-content/cache)/.*`
* `RewriteCond %{DOCUMENT_ROOT}/wordpress/wp-content/cache/bc-cache/%{ENV:BC_CACHE_HOST}%{ENV:BC_CACHE_PATH}%{ENV:BC_CACHE_FILE} -f`
* `RewriteRule .* /wordpress/wp-content/cache/bc-cache/%{ENV:BC_CACHE_HOST}%{ENV:BC_CACHE_PATH}%{ENV:BC_CACHE_FILE} [L,NS]`

```.apacheconf
# BEGIN BC Cache
AddDefaultCharset utf-8

<IfModule mod_rewrite.c>
  RewriteEngine on

  # Set scheme and hostname directories
  RewriteCond %{ENV:HTTPS} =on
  RewriteRule .* - [E=BC_CACHE_HOST:https/%{HTTP_HOST}]
  RewriteCond %{ENV:HTTPS} !=on
  RewriteRule .* - [E=BC_CACHE_HOST:http/%{HTTP_HOST}]

  # Set path subdirectory
  RewriteCond %{REQUEST_URI} /$
  RewriteRule .* - [E=BC_CACHE_PATH:%{REQUEST_URI}]
  RewriteCond %{REQUEST_URI} ^$
  RewriteRule .* - [E=BC_CACHE_PATH:/]

  # gzip
  RewriteRule .* - [E=BC_CACHE_FILE:index.html]
  <IfModule mod_mime.c>
    RewriteCond %{HTTP:Accept-Encoding} gzip
    RewriteRule .* - [E=BC_CACHE_FILE:index.html.gz]
    AddType text/html .gz
    AddEncoding gzip .gz
  </IfModule>

  # Main rules
  RewriteCond %{REQUEST_METHOD} !=POST
  RewriteCond %{QUERY_STRING} =""
  RewriteCond %{ENV:BC_CACHE_PATH} !=""
  RewriteCond %{REQUEST_URI} !^/wordpress/(wp-admin|wp-content/cache)/.*
  RewriteCond %{HTTP_COOKIE} !(wp-postpass|wordpress_logged_in|comment_author)_
  RewriteCond %{DOCUMENT_ROOT}/wordpress/wp-content/cache/bc-cache/%{ENV:BC_CACHE_HOST}%{ENV:BC_CACHE_PATH}%{ENV:BC_CACHE_FILE} -f
  RewriteRule .* /wordpress/wp-content/cache/bc-cache/%{ENV:BC_CACHE_HOST}%{ENV:BC_CACHE_PATH}%{ENV:BC_CACHE_FILE} [L,NS]
</IfModule>
# END BC Cache
```

## Configuration

BC Cache has no settings. You can modify plugin behavior with following filters:
* `bc-cache/filter:can-user-flush-cache` - filters whether current user can clear the cache. By default, any user with `manage_options` capability can clear the cache.
* `bc-cache/filter:flush-hooks` - filters list of actions that trigger cache flushing. Filter is executed in a hook registered to `init` action with priority 10, so make sure to register your hook earlier (for example within `plugins_loaded` or `after_setup_theme` actions).
* `bc-cache/filter:html-signature` - filters HTML signature appended to HTML files stored in cache. You can use this filter to get rid of the signature: `add_filter('bc-cache/filter:html-signature', '__return_empty_string');`
* `bc-cache/filter:skip-cache` - filters whether response to current HTTP(S) request should be cached. Filter is only executed, when none from [built-in skip rules](#cache-exclusions) is matched - this means that you cannot override built-in skip rules with this filter, only add your own rules.

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

## Credits

* Sergej Müller & Plugin Kollektiv for inspiration in form of [Cachify plugin](https://wordpress.org/plugins/cachify/).
* Font Awesome for [HDD icon](http://fontawesome.io/icon/hdd-o/)
