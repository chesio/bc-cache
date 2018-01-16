<?php
/**
 * Perform plugin uninstall.
 *
 * @package BC_Cache
 */

// If file is not invoked by WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Register autoloader for this plugin.
require_once __DIR__ . '/autoload.php';

// Construct plugin instance.
$bc_cache = new \BlueChip\Cache\Plugin(__DIR__ . '/bc-cache.php');
// Run uninstall actions.
$bc_cache->uninstall();
