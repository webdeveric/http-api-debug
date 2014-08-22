<?php

namespace WDE\HTTPAPIDebug;

if ( ! \is_admin())
    return;

function admin_page_css()
{
    wp_enqueue_style('http-api-debug', plugins_url('/css/dist/main.min.css', HTTP_API_DEBUG_FILE), array(), HTTP_API_DEBUG_VERSION);
}

function display_log_table()
{
    include __DIR__ . '/HTTPAPIDebugLogTable.php';

    $log_table = new HTTPAPIDebugLogTable();
    $log_table->prepare_items();

    ?>

    <h2>HTTP API Debug Log</h2>

    <p class="message" title="The size is the sum of the data in the table and the indexes on the table.">
        Log Size: <strong><?php echo convert_bytes( table_size('http_api_debug_log', true), false, 2 ); ?></strong>
    </p>

    <form id="movies-filter" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
        <?php $log_table->display(); ?>
    </form>

    <?php
}

function display_log_entry()
{
    global $wpdb;
    $entry = $wpdb->get_results(
        $wpdb->prepare(
            "select * from {$wpdb->prefix}http_api_debug_log where log_id = %d limit 1",
            $_REQUEST['log_id']
        )
    );

    if ( ! empty( $entry ) ) {

        $entry = array_shift($entry);

        if ( property_exists($entry, 'args') )
            $entry->args = json_decode($entry->args);

        if ( property_exists($entry, 'response') )
            $entry->response = json_decode($entry->response);

        if (isset($entry))
            include __DIR__ . '/single-entry.php';

    } else {

        echo '<h1>Entry not found</h1>';
        printf('<p><a href="%s">Go back</a></p>', admin_url('tools.php?page=http-api-debug'));

    }

}

function delete_log_entry_confirm()
{
    echo '<h2>Show confirmation for deletion.</h2>';
}

function http_api_debug_admin_menu()
{
    global $http_api_debug_page;

    $http_api_debug_page = \add_management_page( 'HTTP API Debug', 'HTTP API Debug', 'update_plugins', 'http-api-debug', __NAMESPACE__ . '\http_api_debug_admin_page');

    add_action('admin_print_styles-' . $http_api_debug_page, __NAMESPACE__ . '\admin_page_css');

    add_action("load-$http_api_debug_page", __NAMESPACE__ . '\http_api_debug_screen_options');
}

function http_api_debug_screen_options()
{
    global $http_api_debug_page;

    $screen = get_current_screen();

    if(!is_object($screen) || $screen->id != $http_api_debug_page)
       return;
 
    $args = array(
        'label'   => 'Log entries per page',
        'default' => 20,
        'option'  => 'http_api_debug_log_per_page'
    );

    add_screen_option( 'per_page', $args );
}

function http_api_debug_set_screen_option($status, $option, $value)
{
    if ( $option == 'http_api_debug_log_per_page' )
        return $value;
}
add_filter('set-screen-option', __NAMESPACE__ . '\http_api_debug_set_screen_option', 10, 3);


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
