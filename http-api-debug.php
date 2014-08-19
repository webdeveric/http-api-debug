<?php

namespace WDE\Util\HTTPAPIDebug;

include __DIR__ . '/inc/util/HTTPAPIDebug.php';

global $wpdb;

new HTTPAPIDebug($wpdb);

// wp_remote_get('http://ip.phplug.in/');
