<article class="log-entry">

    <header>
        <h1>Log Entry #<?php echo $entry->log_id; ?></h1>
        <div class="log-entry-meta status-<?php echo $entry->status; ?>">
            <span class="status"><?php echo $entry->status; ?></span>
            <span class="method"><?php echo $entry->args->method; ?></span>
            <span class="url"><?php echo $entry->url; ?></span>
        </div>
    </header>

    <xmp><?php // print_r( array_keys( get_object_vars($entry) ) ); ?></xmp>

    <div class="response-body-wrapper">
        <h2>Response Body</h2>

        <?php if (isset($entry->response->body)): ?>

            <code class="response-body"><?php echo htmlentities($entry->response->body); ?></code>

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

    </div>

</article>