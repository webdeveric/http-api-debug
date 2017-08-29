<?php
/*
Plugin Name: HTTP API Debug
Plugin Group: Utilities
Plugin URI: http://phplug.in/
Author: Eric King
Author URI: http://webdeveric.com/
Description: Debug HTTP request
Version: 0.1.3
*/

defined('ABSPATH') || exit;

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    if (is_admin()) {
        function http_api_debug_requirements_not_met()
        {
            echo '<div class="error"><p>PHP 5.3+ is required for HTTP API Debug. You have PHP ', PHP_VERSION, ' installed. This plugin has been deactivated.</p></div>';
            deactivate_plugins(plugin_basename(__FILE__));
            unset($_GET['activate']);
        }

        add_action( 'admin_notices', 'http_api_debug_requirements_not_met' );
    }

    return;
}

define('HTTP_API_DEBUG_FILE', __FILE__);
define('HTTP_API_DEBUG_VERSION', '0.1.3');

include dirname(__FILE__) . '/main.php';
