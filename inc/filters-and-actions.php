<?php

namespace WDE\HTTPAPIDebug;

function format_log_entry($entry)
{
    if (isset($entry->request_headers, $entry->request_headers['Content-Type'], $entry->request_body) && $entry->request_body !== '') {
        if (get_content_type($entry->request_headers['Content-Type']) == 'application/x-www-form-urlencoded') {
            parse_str($entry->request_body, $entry->request_body_parsed);
        }
    }
    return $entry;
}
add_filter('http_api_debug_log_entry', __NAMESPACE__ . '\format_log_entry', 10, 1 );


function dont_log_these_urls($record_log, $response, $context, $transport_class, $request_args, $url)
{
    if ( in_array( parse_url($url, PHP_URL_HOST), array('api.wordpress.org', 'rizzo.lonelyplanet.com') ) )
        return false;

    if (is_cron_request($url))
        return false;

    return $record_log;

}
add_filter('http_api_debug_record_log', __NAMESPACE__ . '\dont_log_these_urls', 10, 6);
