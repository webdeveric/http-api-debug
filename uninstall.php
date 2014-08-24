<?php

global $wpdb;
$wpdb->query("DROP TABLE {$wpdb->prefix}http_api_debug_log_headers");
$wpdb->query("DROP TABLE {$wpdb->prefix}http_api_debug_log");
