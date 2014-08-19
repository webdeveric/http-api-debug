<?php

namespace WDE\HTTPAPIDebug;

include __DIR__ . '/inc/HTTPAPIDebug.php';

global $wpdb;

new HTTPAPIDebug($wpdb);

// wp_remote_get('http://ip.phplug.in/');


/*
SELECT table_name AS "Table", 
round(((data_length + index_length) / 1024 / 1024), 2) "Size in MB" 
FROM information_schema.TABLES 
WHERE table_schema = "wp"
AND table_name = "wde_http_api_debug_log";
*/