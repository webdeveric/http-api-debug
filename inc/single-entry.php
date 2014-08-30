<?php
namespace WDE\HTTPAPIDebug;

$nav_link_format = '<a href="%1$s" class="log-nav log-nav-%3$s tooltip tooltip-bottom" data-tooltip="%2$s log entry"><span>%2$s</span></a>';
$nav_link_format_placeholder = '<span class="log-nav log-nav-placeholder tooltip tooltip-bottom" data-tooltip="No %1$s log entry"><span>&nbsp;</span></span>';

$prev_url = get_prev_log_entry_url($entry->log_id);
$next_url = get_next_log_entry_url($entry->log_id);

?>
<article class="log-entry">

    <header>
        <h3>Log Entry #<?php echo intval($entry->log_id); ?> @ <time datetime="<?php echo \esc_attr($entry->log_time); ?>"><?php echo $entry->log_time; ?></time></h3>
        <div class="log-entry-meta">
            <?php

            if ($prev_url !== false)
                printf($nav_link_format, $prev_url, 'Previous', 'prev');
            else
                printf($nav_link_format_placeholder, 'previous');

            if ($next_url !== false)
                printf($nav_link_format, $next_url, 'Next', 'next');
            else
                printf($nav_link_format_placeholder, 'next');

            ?><span class="status status-<?php echo $entry->status; ?>" data-tooltip="HTTP Status Code">
                <?php echo $entry->status === '000' ? '?' : $entry->status; ?>
            </span><span class="method" data-tooltip="HTTP Method">
                <?php echo strtoupper( $entry->method ); ?>
            </span><span class="url tooltip-top" data-tooltip="Requested URL">
                <?php echo $entry->url; ?>
            </span>
        </div>

    </header>

    <?php // var_dump($entry); ?>

    <?php if (isset($entry->request_args)): ?>
   
    <section>
        <h2>Request Arguments</h2>
        <?php echo key_value_table($entry->request_args, array('Argument', 'Value')); ?>
    </section>
    
    <?php endif; ?>

    <?php if (isset($entry->response_data)): ?>
   
    <section>
        <h2>Response Data</h2>
        <?php 
        // var_dump($entry->response_data);
        echo key_value_table($entry->response_data, array('Argument', 'Value')); ?>
    </section>
    
    <?php endif; ?>

    <section>
        <h2>Headers</h2>
        <?php if (isset($entry->request_headers)): ?>
            <h3>Request Headers</h3>
            <?php // echo data_table($entry->request_headers, array('header_name:th:Header Name', 'header_value:td:Header Value')); ?>
            <?php echo key_value_table($entry->request_headers, array('Header', 'Value')); ?>
        <?php endif; ?>

        <?php if (isset($entry->response_headers)): ?>
            <h3>Response Headers</h3>
            <?php echo key_value_table($entry->response_headers, array('Header', 'Value')); ?>
        <?php endif; ?>
    </section>

    <?php foreach (array('response', 'request') as $r):
        $body = $r . '_body';
        $parsed = $body . '_parsed';
    ?>
        <section class="full-width">
            <h2><?php echo ucfirst($r); ?> Body</h2>

            <?php if (isset($entry->$body)): ?>
                <?php if (isset($entry->$parsed)): ?>
                    <h3>Raw</h3>
                <?php endif; ?>
                <code class="body-output"><?php echo htmlentities($entry->$body); ?></code>
                    
                <?php
                    if (isset($entry->$parsed) && is_array($entry->$parsed))
                        echo '<h3>Parsed</h3>', key_value_table($entry->$parsed);
                ?>

            <?php endif; ?>

        </section>

    <?php endforeach; ?>

</article>