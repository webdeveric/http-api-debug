<?php

namespace WDE\HTTPAPIDebug;


function table_size( $table, $add_prefix = true )
{
    global $wpdb;

    if ($add_prefix)
        $table = $wpdb->prefix . $table;

    $bytes = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT data_length + index_length as bytes_size FROM information_schema.TABLES WHERE table_schema = (select database()) AND table_name = %s",
            $table
        )
    );

    return $bytes;
}


function table_columns( $table, $add_prefix = true )
{
    global $wpdb;

    if ($add_prefix)
        $table = $wpdb->prefix . $table;

    return $wpdb->get_col( 'DESCRIBE ' . $table, 0 );
}


function table_exists( $table, $add_prefix = true )
{
    global $wpdb;

    if ($add_prefix)
        $table = $wpdb->prefix . $table;

    $rows = $wpdb->query( $wpdb->prepare( 'show tables like %s', $table ) );

    return $rows > 0;
}


function convert_bytes( $bytes, $abbreviated = true, $precision = 2, $stop_at = null )
{
    $units = array(
        'B'  => 'Byte',
        'KB' => 'Kilobyte',
        'MB' => 'Megabyte',
        'GB' => 'Gigabyte',
        'TB' => 'Terabyte',
        'PB' => 'Petabyte',
        'EB' => 'Exabyte',
        'ZB' => 'Zetabyte',
        'YB' => 'Yottabyte'
    );

    $stop_key = false;

    $converted_size = 0;

    if ( isset( $stop_at ) ) {

        $stop_at = strtoupper($stop_at);

        if (array_key_exists($stop_at, $units)) {

            $stop_key = $stop_at;

        } else {

            $stop_at = ucfirst( rtrim( strtolower( $stop_at ), 's') );
            $stop_key = array_search( $stop_at, $units );

        }

    }

    foreach ($units as $unit_abbr => $unit_name) {
        if ($stop_key !== false && $stop_key === $unit_abbr)
            break;

        if ( ( $converted_size = $bytes/1024 ) < 1 )
            break;

        $bytes = $converted_size;
    }

    return number_format( $bytes, $precision ) . ( $abbreviated ? $unit_abbr : ' ' . $unit_name . ($bytes > 1 ? 's':'') );
}


function get_bytes( $val )
{
    $num = intval( $val );

    switch ( strtoupper( substr( trim( $val ) , -1 ) ) ) {
        case 'T': $num *= 1024; //Terabytes
        case 'G': $num *= 1024; //Gigabytes
        case 'M': $num *= 1024; //Megabytes
        case 'K': $num *= 1024; //Kilobytes
    }

    return $num; //Bytes
}


function str_starts_with($haystack, $needle)
{
    return $needle === "" || strpos($haystack, $needle) === 0;
}


function str_ends_with($haystack, $needle)
{
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

function admin_notice($message)
{
    add_action('admin_notices', function () use ($message) {
        echo '<div class="updated">', \wpautop($message), '</div>';
    });
}

function admin_debug($message)
{
    add_action('admin_notices', function () use ($message) {
        echo '<div class="error"><pre>', print_r($message, true), '</pre></div>';
    });
}

function str_value($arg)
{
    if (is_scalar($arg)) {
        if (is_bool($arg))
            return $arg ? 'true' : 'false';
        return (string)$arg;
    } else {
        if (is_object($arg)) {
            if (method_exists($arg, '__toString')) {
                return (string)$arg;
            } else {
                $arg = (array)$arg;
            }
        }

        if (is_array($arg))
            return key_value_table($arg);
    }
    return print_r($arg, true);
}

function key_value_table($data, $headers = array('Key', 'Value'))
{
    if (empty($data))
        return '';
    $rows = array();

    foreach ($data as $key => &$value) {
        $rows[] = sprintf(
            '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
            $key,
            str_value( $value )
        );
    }

    $html = sprintf(
        '<table><thead><tr><th scope="col">%1$s</th><th scope="col">%2$s</th></tr></thead><tbody>%3$s</tbody></table>',
        array_shift($headers),
        array_shift($headers),
        implode('', $rows)
    );

    return $html;
}

function data_table($data, $headers = array())
{
    if (empty($data))
        return '';

    $table_columns = array();
    $table_header_row = '';
    foreach ($headers as &$table_header) {
        list($col, $tag, $text) = explode(':', $table_header);
        $table_columns[$col] = isset($tag) ? $tag : 'td';
        $table_header_row .= '<th>'. ( isset($text)? $text : $col ) . '</th>';
    }

    if ( ! empty($table_header_row))
        $table_header_row = sprintf('<thead><tr>%s</tr></thead>', $table_header_row);

    unset($headers);

    $rows = array();

    foreach ($data as &$row) {

        $tr = '<tr>';
        foreach($row as $key => &$value) {
            $tag = isset($table_columns[$key]) ? $table_columns[$key] : 'td';
            $tr .= sprintf('<%1$s>%2$s</%1$s>', $tag, str_value( $value ) );
        }
        $tr .= '</tr>';

        $rows[] = $tr;

    }

    $html = sprintf(
        '<table>%1$s<tbody>%2$s</tbody></table>',
        $table_header_row,
        implode('', $rows)
    );

    return $html;
}

function get_content_type($content_type_header)
{
    $parts = explode(';', $content_type_header);
    return $parts[0];
}

function is_cron_request($url)
{
    $url_parts = parse_url($url);
    $site_host = parse_url(get_site_url(), PHP_URL_HOST);

    if ( isset($url_parts['host'], $url_parts['path']) &&
         $url_parts['host'] == $site_host &&
         str_ends_with($url_parts['path'], '/wp-cron.php') ) {
        return true;
    }
    return false;
}

function get_log_entry($log_id)
{
    global $wpdb;

    $entry = $wpdb->get_results(
        $wpdb->prepare(
            "select * from {$wpdb->prefix}http_api_debug_log where log_id = %d limit 1",
            $log_id
        )
    );

    $entry = array_shift($entry);

    if ( property_exists($entry, 'request_args') )
        $entry->request_args = json_decode($entry->request_args, true);

    $entry->request_headers = get_log_entry_headers($log_id, 'req');
    $entry->response_headers = get_log_entry_headers($log_id, 'res');

    return $entry;
}

function get_log_entry_headers($log_id, $header_type)
{
    global $wpdb;

    $headers = $wpdb->get_results(
        $wpdb->prepare(
            "select header_name, header_value from {$wpdb->prefix}http_api_debug_log_headers where log_id = %d and header_type = %s",
            $log_id,
            $header_type
        ),
        OBJECT_K
    );

    foreach ($headers as &$value) {
        $value = $value->header_value;
    }

    return $headers;
}



