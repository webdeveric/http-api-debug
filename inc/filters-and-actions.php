<?php

namespace WDE\HTTPAPIDebug;

function format_log_entry($entry)
{
    foreach (array('response', 'request') as $r) {
        $headers = $r . '_headers';
        $headers = $entry->$headers;
        $body    = $r . '_body';
        $parsed  = $body . '_parsed';

        if (isset($headers['content-type'], $entry->$body) && $entry->$body !== '') {
            
            switch ( get_content_type( $headers['content-type'] ) ) {
                case 'application/x-www-form-urlencoded':
                    parse_str($entry->$body, $entry->$parsed);
                    break;
                case 'application/json':
                    $entry->$parsed = json_decode( $entry->$body, true );
                    break;
                case 'application/xml':
                    $parser = xml_parser_create();
                    xml_parse_into_struct($parser, $entry->$body, $entry->$parsed );
                    xml_parser_free($parser);
                    break;
            }
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
