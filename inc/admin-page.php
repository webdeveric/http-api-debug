<?php

namespace WDE\HTTPAPIDebug;

if ( ! \is_admin())
    return;

\add_action('admin_menu', function () {

    \add_management_page( 'HTTP API Debug', 'HTTP API Debug', 'update_plugins', 'http-api-debug', function () {
        
        include __DIR__ . '/HTTPAPIDebugLogTable.php';

        $log_table = new HTTPAPIDebugLogTable();
        $log_table->prepare_items();

        ?>

        <div class="wrap">

            <h2>HTTP API Debug Log</h2>

            <p class="message">
                Log size: <?php echo convert_bytes( table_size('http_api_debug_log', true), false, 0 ); ?>
            </p>

            <form id="movies-filter" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <?php $log_table->display(); ?>
            </form>

        </div>

        <?php

    } );

} );
