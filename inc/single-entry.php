<?php
namespace WDE\HTTPAPIDebug;
?>
<article class="log-entry">

    <header>
        <h1>Log Entry #<?php echo intval($entry->log_id); ?> @ <time datetime="<?php echo \esc_attr($entry->log_time); ?>"><?php echo $entry->log_time; ?></time></h1>
        <div class="log-entry-meta">
            <span class="status status-<?php echo $entry->status; ?>">
                <?php echo $entry->status; ?>
            </span><span class="method">
                <?php echo $entry->method; ?>
            </span><span class="url">
                <?php echo $entry->url; ?>
            </span>
        </div>
    </header>

    <?php // var_dump($entry);?>

    <?php if (isset($entry->request_args)): ?>
   
    <section>
        <h2>Request Arguments</h2>
        <?php echo key_value_table($entry->request_args, array('Argument', 'Value')); ?>
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

    <section class="full-width">
        <h2>Request Body</h2>

        <?php if (isset($entry->request_body)): ?>
            <?php if (isset($entry->request_body_parsed)): ?>
                <h3>Raw</h3>
            <?php endif; ?>
            <code class="body-output"><?php echo htmlentities($entry->request_body); ?></code>
                
            <?php
                if (isset($entry->request_body_parsed) && is_array($entry->request_body_parsed))
                    echo '<h3>Parsed</h3>', key_value_table($entry->request_body_parsed);
            ?>

        <?php endif; ?>

    </section>

    <section class="full-width">
        <h2>Response Body</h2>

        <?php if (isset($entry->response_body)): ?>

            <code class="body-output"><?php echo htmlentities($entry->response_body); ?></code>

        <?php elseif (isset($entry->response->errors, $entry->response->error_data)):

            $errors_types = (array)$entry->response->errors;
            ?>
            <dl>
            <?php
                foreach ($errors_types as $error_type => &$error_messages) {
                    echo '<dt>', $error_type, '</dt><dd><ul>';
                    foreach ($error_messages as $error_key => &$error) {
                        printf('<li>%s</li>', $error);
                    }
                    echo '</ul></dd>';
                }
            ?>
            </dl>
        <?php endif; ?>

    </section>

</article>