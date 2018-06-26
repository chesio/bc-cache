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
* `RewriteRule .* /wordpress/wp-content/cache/bc-cache/%{ENV:BC_CACHE_HOST}%{ENV:BC_CACHE_PATH}%{ENV:BC_CACHE_FILE} [L]`

```
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
RewriteCond %{REQUEST_URI} !^/wordpress/(wp-admin|wp-content/cache)/.*
RewriteCond %{HTTP_COOKIE} !(wp-postpass|wordpress_logged_in|comment_author)_
RewriteCond %{DOCUMENT_ROOT}/wordpress/wp-content/cache/bc-cache/%{ENV:BC_CACHE_HOST}%{ENV:BC_CACHE_PATH}%{ENV:BC_CACHE_FILE} -f
RewriteRule .* /wordpress/wp-content/cache/bc-cache/%{ENV:BC_CACHE_HOST}%{ENV:BC_CACHE_PATH}%{ENV:BC_CACHE_FILE} [L]
</IfModule>
# END BC Cache

```

Plugin has no settings. You can modify plugin behavior with following filters:
* `bc-cache/filter:can-user-flush-cache` - filters whether current user can clear the cache. By default, any user with `manage_options` capability can clear the cache.
* `bc-cache/filter:flush-hooks` - filters list of actions that trigger cache flushing. Filter is executed in a hook registered to `init` action with priority 10, so make sure to register your hook earlier (for example within `plugins_loaded` or `after_setup_theme` actions).
* `bc-cache/filter:html-signature` - filters HTML signature appended to HTML files stored in cache. You can use this filter to get rid of the signature: `add_filter('bc-cache/filter:html-signature', '__return_empty_string');`
* `bc-cache/filter:skip-cache` - filters whether current HTTP(S) request should be cached. Filter is executed after built-in skip rules. By default, cache is not skipped.

## Credits

* Sergej Müller & Plugin Kollektiv for inspiration in form of [Cachify plugin](https://wordpress.org/plugins/cachify/).
* Font Awesome for [HDD icon](http://fontawesome.io/icon/hdd-o/)
