<?php

namespace WDE\HTTPAPIDebug;

function format_log_entry_bodies($entry)
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
                case 'text/plain':
                    if ( looks_like_json( $entry->$body ) ) {
                        $decoded = maybe_json_decode( $entry->$body, true );
                        if ( $decoded !== $entry->$body )
                            $entry->$parsed = $decoded;
                    }
                    break;
                case 'application/json':
                    $entry->$parsed = json_decode( $entry->$body, true );
                    break;
                case 'application/xml':
                case 'application/atom+xml':
                case 'application/rss+xml':
                case 'text/xml':
                    $parser = xml_parser_create();
                    xml_parse_into_struct($parser, $entry->$body, $entry->$parsed );
                    xml_parser_free($parser);
                    break;
                case 'text/yaml' && function_exists('yaml_parse'):
                    $entry->$parsed = yaml_parse( $entry->$body, -1 );
                    break;
            }

            if ( isset( $entry->$parsed ) && is_array( $entry->$parsed ) ) {

                foreach ( $entry->$parsed as &$value ) {
                    if ( looks_like_json( $value ) )
                        $value = maybe_json_decode( $value, true );
                }

            }

        }

    }

    return $entry;
}
add_filter('http_api_debug_log_entry', __NAMESPACE__ . '\format_log_entry_bodies', 10, 1 );


function format_log_entry_response_data($entry)
{
    $entry->response_data = json_decode($entry->response_data, true);
    return $entry;
}
add_filter('http_api_debug_log_entry', __NAMESPACE__ . '\format_log_entry_response_data', 10, 1 );


function format_log_entry_url($entry)
{
    $parse_url_parts = array(
        'scheme'   => array(
            'add_to_class' => true,
            'after' => '://'
        ),
        'user'     => array(
            'after' => array(
                'field' => 'pass',
                'empty' => true,
                'content' => '@'
            )
        ),
        'pass'     => array(
            'before' => array(
                'field' => 'user',
                'empty' => false,
                'content' => ':'
            ),
            'after' => '@'
        ),
        'host'     => '',
        'port'     => array('before' => ':'),
        'path'     => '',
        'query'    => array('before' => '?'),
        'fragment' => array('before' => '#')
    );

    $default_parse_url_parts = array_combine(
        array_keys($parse_url_parts),
        array_fill(0, count($parse_url_parts), '')
    );

    $parts = array_merge(
        $default_parse_url_parts,
        parse_url($entry->url)
    );

    $url_parts = array();

    foreach ( $parse_url_parts as $part => $extra ) {
        if ( array_key_exists($part, $parts) && ! empty( $parts[ $part ] ) ) {

            $extra_classes = '';

            if ( is_array( $extra ) && array_key_exists('add_to_class', $extra) && $extra['add_to_class'] === true ) {
                $extra_classes = $part . '-' . esc_attr( strtolower( $parts[ $part ] ) );
            }

            $part_html = sprintf( '<span class="%1$s %3$s" data-tooltip="%1$s">%2$s</span>', $part, $parts[ $part ], $extra_classes );

            if ( is_array( $extra ) ) {

                $formats = array(
                    'before' => '<span class="separator separator-before separator-%1$s">%2$s</span>%3$s',
                    'after'  => '%3$s<span class="separator separator-after separator-%1$s">%2$s</span>'
                );

                foreach ( $formats as $position => $format ) {

                    if ( isset( $extra[ $position ] ) && ! empty( $extra[ $position ] ) ) {

                        $extra_content = $extra[ $position ];

                        if ( is_array( $extra_content ) ) {
                             
                            if ( isset( $extra_content['field'], $parts[ $extra_content['field'] ] ) && empty( $parts[ $extra_content['field'] ] ) == $extra_content['empty'] ) {

                                $part_html = sprintf( $format, $part, $extra_content['content'], $part_html );

                            }

                        } else {

                            $part_html = sprintf( $format, $part, $extra_content, $part_html );

                        }

                    }

                }

            }

            $url_parts[] = $part_html;
        }
    }

    $entry->url = implode('', $url_parts);

    return $entry;
}
add_filter('http_api_debug_log_entry', __NAMESPACE__ . '\format_log_entry_url', 10, 2 );


function log_size($which)
{
    if ($which === 'top'):
    ?>
        <span class="log-size tooltip-top" data-tooltip="The size is the sum of the data in the tables and the indexes on the tables.">
            Log Size: <strong><?php echo convert_bytes( table_size('http_api_debug_log') + table_size('http_api_debug_log_headers'), false, 2 ); ?></strong>
        </span>
    <?php
    endif;
}
add_action('http-api-debug-extra-tablenav', __NAMESPACE__ . '\log_size', 10, 1 );


function domain_dropdown($which)
{
    static $dropdown;

    if ( isset( $dropdown ) ) {
        echo $dropdown;
        return;
    }

    global $wpdb;

    $hosts = $wpdb->get_results("select host, count(*) as number_of_entries from {$wpdb->prefix}http_api_debug_log group by host order by host asc");

    $options = array();

    foreach ( $hosts as &$host ) {
        $options[] = sprintf('<option value="%1$s">%1$s (%2$d)</option>', $host->host, $host->number_of_entries );
    }

    $dropdown = sprintf(
        '<div class="alignleft actions"><label class="screen-reader-text" for="host-selector-%1$s">Choose a domain</label><select class="host-selector" id="host-selector-%1$s" name="host">%2$s</select></div>',
        $which,
        implode('', $options)
    );

    /*
    <script>
    jQuery('.host-selector').change()
    </script>
    */

    echo $dropdown;

}
// add_action('http-api-debug-extra-tablenav', __NAMESPACE__ . '\domain_dropdown', 1, 1 );



