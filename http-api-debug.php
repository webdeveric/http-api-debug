<?php
// Bootstrap the plugin here.

namespace WDE\HTTPAPIDebug;
use WDE\Autoloader\Autoloader;
use WDE\DI\Container;

include __DIR__ . '/inc/functions.php';
include __DIR__ . '/inc/Autoloader.php';

global $wpdb;

$autoloader = new Autoloader();
$autoloader->addNamespace('WDE', __DIR__ . '/vendor/WDE/');
$autoloader->register();

$app = new Container;

$app->arg('wpdb', $wpdb);
$app->arg('plugin_file', HTTP_API_DEBUG_FILE );

$app->prefixAlias( __NAMESPACE__, array(
    'Installer',
    'DebugLogger',
    'LogModel',
    'Config'
) );

// I only want one Config object so lets set it up to be a shared dependency.
$app->register(
    'Config',
    function(Container $app) {
        return new Config;
    },
    true
);

$app->register('LoadOrder', function(Container $app) {
    return new \WDE\Plugin\LoadOrder( HTTP_API_DEBUG_FILE, 'first' );
} );

add_action('plugins_loaded', function() use ($app) {

    try {
        // Check to see if the plugin needs to update the DB schema.
        $installer = $app->get('Installer');
        $installer->check_database();

        // Force this plugin to be first in the active_plugins option so that it will be loaded first.
        $app->get('LoadOrder');

        // This hooks into http_api_debug.
        $logger = $app->get('DebugLogger');

        if ( is_admin() ) {
            // Load admin specific functionality here.
            admin_debug( get_option('active_plugins' ) );
        }

    } catch (\Exception $e) {
        admin_error( '<strong>HTTP API Debug:</strong> ' . $e->getMessage() );
    }

} );

register_activation_hook( HTTP_API_DEBUG_FILE, function() use ($app) {
    $installer = $app->get('Installer');
    $installer->activate();
} );

register_deactivation_hook( HTTP_API_DEBUG_FILE, function() use ($app) {
    $installer = $app->get('Installer');
    $installer->deactivate();
} );
