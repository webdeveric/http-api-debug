<?php

namespace webdeveric\HTTPAPIDebug;

?>
<div class="wrap http-api-debug">
    <h2>
        <a href="<?php echo $home_url; ?>">HTTP API Debug</a>
        <?php
            if ( isset( $header_links ) && is_array($header_links) ) {
                echo implode(' ', $header_links);
            }
        ?>
    </h2>

    <div class="http-api-debug-wrap">
        <?php echo $content; ?>
    </div>
</div>
