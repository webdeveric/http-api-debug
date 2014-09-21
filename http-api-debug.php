<?php

namespace WDE\HTTPAPIDebug;
use WDE\Autoloader\Autoloader;

include __DIR__ . '/inc/functions.php';
include __DIR__ . '/inc/Autoloader.php';

global $wpdb;

$autoloader = new Autoloader();
$autoloader->addNamespace('WDE', __DIR__ . '/vendor/WDE/');
$autoloader->register();

$app = new \WDE\DI\Container;


$app->arg('wpdb', $wpdb);

add_action('plugins_loaded', function() use ($app) {

    $plugin = $app->get('WDE\HTTPAPIDebug\HTTPAPIDebug');

} );
