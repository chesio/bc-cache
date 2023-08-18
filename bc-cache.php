<?php

/**
 * Plugin Name: BC Cache
 * Plugin URI: https://github.com/chesio/bc-cache
 * Description: Simple full page cache plugin inspired by Cachify.
 * Version: 3.3.0-dev
 * Author: Česlav Przywara <ceslav@przywara.cz>
 * Author URI: https://www.chesio.com
 * Requires PHP: 8.0
 * Requires at least: 6.1
 * Tested up to: 6.3
 * Text Domain: bc-cache
 * GitHub Plugin URI: https://github.com/chesio/bc-cache
 * Update URI: https://github.com/chesio/bc-cache
 */

// Check plugin requirements, disable if they are not met.
if (
    false === \call_user_func(
        function () {
            $php_version_ok = \version_compare(PHP_VERSION, '8.0', '>=');
            $pretty_permalinks_on = (bool) get_option('permalink_structure');

            if (!$php_version_ok) {
                // Warn user that his/her PHP version is too low for this plugin to function.
                add_action('admin_notices', function () {
                    echo '<div class="error"><p>';
                    echo esc_html(
                        \sprintf(
                            __('BC Cache plugin requires PHP 8.0 to function properly, but you have version %s installed. The plugin has been auto-deactivated.', 'bc-cache'),
                            PHP_VERSION
                        )
                    );
                    echo '</p></div>';
                }, 10, 0);
            }

            if (!$pretty_permalinks_on) {
                // Warn user that plugin does not work without pretty permalinks activated.
                add_action('admin_notices', function () {
                    echo '<div class="error"><p>';
                    echo \sprintf(
                        __('BC Cache plugin requires %s to be activated, but your website have them turned off. The plugin has been auto-deactivated.', 'bc-cache'),
                        \sprintf(
                            '<a href="%s">%s</a>',
                            admin_url('options-permalink.php'),
                            esc_html('pretty permalinks', 'bc-cache')
                        )
                    );
                    echo '</p></div>';
                }, 10, 0);
            }

            if (!$php_version_ok || !$pretty_permalinks_on) {
                add_action('admin_notices', function () {
                    // https://make.wordpress.org/plugins/2015/06/05/policy-on-php-versions/
                    if (isset($_GET['activate'])) {
                        unset($_GET['activate']);
                    }
                }, 10, 0);

                // Self deactivate.
                add_action('admin_init', function () {
                    deactivate_plugins(plugin_basename(__FILE__));
                }, 10, 0);

                // Requirements check failed.
                return false;
            }

            // Requirements check passed.
            return true;
        }
    )
) {
    // Bail.
    return;
}


// Register autoloader for this plugin.
require_once __DIR__ . '/autoload.php';

return \call_user_func(function () {
    // Construct plugin instance.
    $bc_cache = new \BlueChip\Cache\Plugin(
        __FILE__,
        \defined('BC_CACHE_FILE_LOCKING_ENABLED') ? \constant('BC_CACHE_FILE_LOCKING_ENABLED') : true,
        \defined('BC_CACHE_WARM_UP_ENABLED') ? \constant('BC_CACHE_WARM_UP_ENABLED') : true
    );

    // Register activation hook.
    register_activation_hook(__FILE__, [$bc_cache, 'activate']);
    // Register deactivation hook.
    register_deactivation_hook(__FILE__, [$bc_cache, 'deactivate']);

    // Boot up the plugin after all plugins are loaded.
    add_action('plugins_loaded', [$bc_cache, 'load'], 10, 0);

    // Return the instance.
    return $bc_cache;
});
