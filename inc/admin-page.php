<?php

namespace WDE\HTTPAPIDebug;

if ( ! \is_admin())
    return;

function display_log_table()
{
    include __DIR__ . '/HTTPAPIDebugLogTable.php';

    $log_table = new HTTPAPIDebugLogTable();
    $log_table->prepare_items();
    ?>

    <h2>HTTP API Debug Log</h2>

    <p class="message">
        Log size: <?php echo convert_bytes( table_size('http_api_debug_log', true), false, 0 ); ?>
    </p>

    <form id="movies-filter" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
        <?php $log_table->display(); ?>
    </form>

    <?php
}

function display_log_entry()
{
    echo '<h2>Show single entry here.</h2>';
}

function delete_log_entry_confirm()
{
    echo '<h2>Show confirmation for deletion.</h2>';
}

function http_api_debug_admin_menu()
{
    \add_management_page( 'HTTP API Debug', 'HTTP API Debug', 'update_plugins', 'http-api-debug', __NAMESPACE__ . '\http_api_debug_admin_page');
}

function http_api_debug_admin_page()
{
    $valid_actions = array(
        'view',
        // 'delete'
    );

    $action = '';

    if ( isset( $_REQUEST['action'] ) && in_array($_REQUEST['action'], $valid_actions) )
        $action = $_REQUEST['action'];

    echo '<div class="wrap">';

    switch ($action) {
        case 'view':
            display_log_entry();
            break;
        /*
        case 'delete':
            delete_log_entry_confirm();
            break;
        */
        default:
            display_log_table();
    }

    echo '</div>';
}

\add_action('admin_menu', __NAMESPACE__ . '\http_api_debug_admin_menu');
