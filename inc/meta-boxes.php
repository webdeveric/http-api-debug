<?php

namespace WDE\HTTPAPIDebug;

function add_meta_boxes()
{
    \add_meta_box( $id, $title, $callback, $post_type, $context, $priority, $callback_args );
}
add_action('add_meta_boxes', __NAMESPACE__ . '\add_meta_boxes');
