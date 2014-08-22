<?php

namespace WDE\HTTPAPIDebug;


function table_size( $table, $add_prefix = false )
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


function table_columns( $table, $add_prefix = false )
{
    global $wpdb;

    if ($add_prefix)
        $table = $wpdb->prefix . $table;

    return $wpdb->get_col( 'DESCRIBE ' . $table, 0 );
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
