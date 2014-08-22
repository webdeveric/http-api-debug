<?php
if ( ! isset( $entry ) )
    return;

printf('<h1>%1$s</h1>', $entry->url );

printf('<xmp>%1$s</xmp>', print_r( $entry, true ) );