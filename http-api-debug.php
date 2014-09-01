<?php

namespace WDE\HTTPAPIDebug;

include __DIR__ . '/inc/functions.php';
include __DIR__ . '/inc/HTTPAPIDebug.php';
// include __DIR__ . '/inc/admin-page.php';
include __DIR__ . '/inc/filters-and-actions.php';
// include __DIR__ . '/inc/meta-boxes.php';

global $wpdb;

new HTTPAPIDebug($wpdb);

// wp_remote_get('http://ip.phplug.in/');
// wp_remote_get('http://ip.phplug.in/?output=json');
// wp_remote_get('http://ip.phplug.in/?output=xml');

// wp_remote_get('http://phplug.in/404');