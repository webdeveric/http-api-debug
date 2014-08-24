<?php

namespace WDE\HTTPAPIDebug;

function format_log_entry($entry)
{
    if (isset($entry->args, $entry->args->headers, $entry->args->headers->{'Content-Type'}, $entry->args->body) && $entry->args->body !== '') {
        $content_type = $entry->args->headers->{'Content-Type'};
        if (get_content_type($content_type) == 'application/x-www-form-urlencoded') {
            parse_str($entry->args->body, $entry->args->body);
            // $entry->args->body = urldecode($entry->args->body);
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
