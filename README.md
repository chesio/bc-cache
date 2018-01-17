# BC Cache

Simple disk cache for WordPress inspired by Cachify.

## Requirements
* Apache webserver with [mod_rewrite](https://httpd.apache.org/docs/current/mod/mod_rewrite.html) enabled
* [PHP](https://secure.php.net/) 7.0 or newer
* [WordPress](https://wordpress.org/) 4.7 or newer with [pretty permalinks](https://codex.wordpress.org/Using_Permalinks) on

## Limitations

* BC Cache has not been tested on WordPress multisite installation.

## Installation

You have to configure your Apache webserver to serve cached files. One way to do it is to add the lines below to the root `.htaccess` file (ie. the same file to which WordPress automatically writes pretty permalinks configuration). Note that the configuration below assumes that you have WordPress installed in `wordpress` subdirectory - if it is not your case, simply drop the `/wordpress` part from the last `RewriteCond` and `RewriteRule`:

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

Plugin has no settings, so if you need to modify plugin behavior, use provided filters.

## Credits

* Sergej Müller & Plugin Kollektiv for inspiration in form of [Cachify plugin](https://wordpress.org/plugins/cachify/).
* Font Awesome for [HDD icon](http://fontawesome.io/icon/hdd-o/)
