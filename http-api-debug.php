<?php

namespace WDE\HTTPAPIDebug;

include __DIR__ . '/inc/functions.php';
include __DIR__ . '/inc/HTTPAPIDebug.php';
include __DIR__ . '/inc/filters-and-actions.php';

global $wpdb;

new HTTPAPIDebug($wpdb);
