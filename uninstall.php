<?php

global $wpdb;

$wpdb->query("DROP TABLE {$wpdb->prefix}http_api_debug_log_headers");
$wpdb->query("DROP TABLE {$wpdb->prefix}http_api_debug_log");

$options = [
    'http-api-debug-logs-to-keep',
    'http-api-debug-purge-after',
    'http-api-debug-require-wp-debug',
    'http-api-debug-ignore-cron',
    'http-api-debug-domain-filter',
    'http-api-debug-domains'
];

foreach ($options as $option) {
    delete_site_option( $option );
}
