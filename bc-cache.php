<?php

/**
 * Plugin Name: BC Cache
 * Plugin URI: https://github.com/chesio/bc-cache
 * Description: Simple full page cache plugin inspired by Cachify.
 * Version: 2.0.1
 * Author: Česlav Przywara <ceslav@przywara.cz>
 * Author URI: https://www.chesio.com
 * Requires PHP: 7.2
 * Requires WP: 5.5
 * Tested up to: 5.8
 * Text Domain: bc-cache
 * GitHub Plugin URI: https://github.com/chesio/bc-cache
 * Update URI: https://github.com/chesio/bc-cache
 */

if (version_compare(PHP_VERSION, '7.2', '<')) {
    // Warn user that his/her PHP version is too low for this plugin to function.
    add_action('admin_notices', function () {
        echo '<div class="error"><p>';
        echo esc_html(
            sprintf(
                __('BC Cache plugin requires PHP 7.2 to function properly, but you have version %s installed. The plugin has been auto-deactivated.', 'bc-cache'),
                PHP_VERSION
            )
        );
        echo '</p></div>';
        // https://make.wordpress.org/plugins/2015/06/05/policy-on-php-versions/
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }, 10, 0);

    // Self deactivate.
    add_action('admin_init', function () {
        deactivate_plugins(plugin_basename(__FILE__));
    }, 10, 0);

    // Bail.
    return;
}


// Register autoloader for this plugin.
require_once __DIR__ . '/autoload.php';

// Construct plugin instance.
$bc_cache = new \BlueChip\Cache\Plugin(__FILE__);

// Register activation hook.
register_activation_hook(__FILE__, [$bc_cache, 'activate']);
// Register deactivation hook.
register_deactivation_hook(__FILE__, [$bc_cache, 'deactivate']);
// Ideally, uninstall hook would be registered here, but WordPress allows only static method in uninstall hook...

// Load the plugin.
$bc_cache->load();
