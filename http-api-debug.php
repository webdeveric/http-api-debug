<?php

namespace WDE\HTTPAPIDebug;

include __DIR__ . '/inc/HTTPAPIDebug.php';
include __DIR__ . '/inc/admin-page.php';
include __DIR__ . '/inc/functions.php';

global $wpdb;

new HTTPAPIDebug($wpdb);
