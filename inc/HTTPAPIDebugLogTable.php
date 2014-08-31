<?php

namespace WDE\HTTPAPIDebug;


class HTTPAPIDebugLogTable extends \WP_List_Table
{
    public function __construct()
    {
        global $status, $page;

        parent::__construct(
            array(
                'singular'  => 'http_api_debug_log',
                'plural'    => 'http_api_debug_logs',
                'ajax'      => false
            )
        );
    }

    /*
    public function get_views()
    {
        return array(
            'test' => '<a href="http://ericking.us/">ericking.us</a>'
        );
    }
    */

    protected function json_column($id, $column, $data)
    {
        $data = json_decode( $data );

        if ( ! isset($data))
            return 'Unable do decode JSON.';

        return implode(
            ', ',
            array_keys(
                array_filter(
                    get_object_vars( $data )
                )
            )
        );
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'log_id':
            case 'context':
            case 'transport':
            case 'log_time':
            case 'url':
                return $item[$column_name];
            case 'status':
                return sprintf('<span class="status status-%2$s">%1$s</span>', $item[$column_name], esc_attr($item[$column_name]));
            case 'host':
                $admin_url = remove_query_arg('paged');
                $admin_url = remove_query_arg('log_id');
                $admin_url = add_query_arg( 'host', $item[$column_name] );
                return sprintf('<a href="%1$s">%2$s</a>', $admin_url, $item[$column_name] );
            case 'request_args':
                return $this->json_column( $item['log_id'], $column_name, $item[$column_name] );
            case 'request_body':
            case 'response_body':
                $body = $item[$column_name];
                return empty($body) ? 'Not Sent' : substr(htmlentities($body), 0, 200);
            case 'request_headers':
            case 'response_headers':
                return $column_name;
            default:
                return print_r($item[$column_name], true);
        }
    }

    public function column_url($item)
    {        
        $menu_page = menu_page_url( $_REQUEST['page'], false );
        
        $view_url = add_query_arg(
            array(
                'action' => 'view',
                'log_id' => $item['log_id']
            ),
            $menu_page
        );

        $delete_url = add_query_arg(
            array(
                'action' => 'delete',
                'log_id' => $item['log_id']
            ),
            $menu_page
        );

        $delete_url = wp_nonce_url( $delete_url, 'delete-log-entry', 'delete_nonce' );

        $actions = array(
            'view'   => sprintf('<a href="%1$s" class="http-api-debug-details-action">View Details</a>', $view_url ),
            'delete' => sprintf('<a href="%1$s" class="http-api-debug-delete-action">Delete</a>', $delete_url )
        );

        return sprintf(
            '<span class="url">%1$s</span>%2$s',
            $item['url'],
            $this->row_actions($actions)
        );
    }

    public function column_cb($item){
        if (isset($item['log_id']))
            return sprintf(
                '<input type="checkbox" name="%1$s[]" value="%2$s" />',
                $this->_args['singular'],
                $item['log_id']
            );
        return '';
    }

    private function column_title($column)
    {
        $title = ucwords( str_replace('_', ' ', $column) );
        $title = str_replace(' Id', ' ID', $title);
        return $title;
    }

    public function get_columns()
    {
        $log_table_columns = table_columns('http_api_debug_log', true);
        $log_table_columns = array_map(array(&$this, 'column_title'), array_combine($log_table_columns, $log_table_columns) );

        $columns['log_id']   = 'ID';
        $columns['url']      = 'URL';
        $columns['status']   = 'Status';
        $columns['method']   = 'Method';
        $columns['host']     = 'Host';        

        $columns = array_merge(
            array(
                'cb' => '<input type="checkbox" />',
            ),
            $columns,
            $log_table_columns
        );
        
        $columns['log_time'] = 'When';


        return $columns;
    }

    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'log_id'   => array('log_id', false),
            'log_time' => array('log_time', true),
            'url'      => array('url', false),
            'status'   => array('status', false)
        );
        return $sortable_columns;
    }

    public function get_bulk_actions()
    {
        $actions = array(
            'delete' => 'Delete'
        );
        return $actions;
    }

    public function extra_tablenav( $which )
    {
        if ($which === 'top'):
        ?>
            <span class="log-size tooltip-top" data-tooltip="The size is the sum of the data in the tables and the indexes on the tables.">
                Log Size: <strong><?php echo convert_bytes( table_size('http_api_debug_log') + table_size('http_api_debug_log_headers'), false, 2 ); ?></strong>
            </span>
        <?php
        endif;
    }

    public function no_items() {
        _e( 'No log entries found.' );
    }

    public function process_bulk_action()
    {
        if ( $this->current_action() === 'delete' ) {

            if ( isset($_REQUEST['_wpnonce']) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'] ) ) {

                $http_api_debug_log = isset($_REQUEST['http_api_debug_log']) ? $_REQUEST['http_api_debug_log'] : array();

                if ( ! empty($http_api_debug_log) ) {

                   array_map( __NAMESPACE__ .'\delete_log_entry', $http_api_debug_log );
                
                } else {
                    
                    echo '<div class="error"><p>Please select some log entries.</p></div>';

                }

            } else {

                echo '<div class="error"><p>Invalid NONCE value.</p></div>';

            }
        }
    }

    public function prepare_items()
    {
        global $wpdb;

        $per_page = $this->get_items_per_page('http_api_debug_log_per_page', 20);

        $current_page = $this->get_pagenum();

        $total_items = 0;

        if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {

            $total_items = (int)$wpdb->get_var(
                $wpdb->prepare(
                    'select count(*) from ' . $wpdb->prefix . 'http_api_debug_log where
                        host like "%1$s" or
                        url like "%1$s" or
                        request_args like "%1$s" or
                        request_body like "%1$s" or
                        response_data like "%1$s" or
                        response_body like "%1$s"',
                    '%' . $_REQUEST['s'] . '%'
                )
            );

        } elseif ( isset( $_REQUEST['host'] ) && ! empty( $_REQUEST['host'] ) ) {

            $total_items = (int)$wpdb->get_var(
                $wpdb->prepare(
                    "select count(*) from {$wpdb->prefix}http_api_debug_log where host = %s",
                    $_REQUEST['host']
                )
            );

        } else {

            $total_items = (int)$wpdb->get_var("select count(*) from {$wpdb->prefix}http_api_debug_log");

        }

        if ($per_page * $current_page > $total_items)
            admin_notice( ($per_page * $current_page) . ' > ' . $total_items );

        $page_offset = $current_page > 1 ? ($current_page - 1) * $per_page : 0;

        $columns = $this->get_columns();

        $hidden = array(
            'log_id',
            'context',
            'transport',
            'request_args',
            'request_body',
            'response_body',
            'response_data'
        );

        if ( ! is_multisite()) {
            $hidden[] = 'site_id';
            $hidden[] = 'blog_id';
        }

        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $order_by = isset($_REQUEST['orderby']) && array_key_exists( $_REQUEST['orderby'], $columns ) ? $_REQUEST['orderby'] : 'log_time';

        $order = 'DESC';

        if ( isset( $_REQUEST['order'] )  && in_array( strtoupper( $_REQUEST['order'] ), array('ASC', 'DESC') ) ) {
            $order = $_REQUEST['order'];
        }

        $data = array();

        if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {

            $data = $wpdb->get_results(
                $wpdb->prepare(
                    'select * from ' . $wpdb->prefix . 'http_api_debug_log where
                        host like "%1$s" or
                        url like "%1$s" or
                        request_args like "%1$s" or
                        request_body like "%1$s" or
                        response_data like "%1$s" or
                        response_body like "%1$s"' .
                    "order by {$order_by} {$order} limit {$page_offset}, {$per_page}",
                    '%' . $_REQUEST['s'] . '%'
                ),
                'ARRAY_A'
            );

        } elseif ( isset( $_REQUEST['host'] ) && ! empty( $_REQUEST['host'] ) ) {

            $data = $wpdb->get_results(
                $wpdb->prepare(
                    "select * from {$wpdb->prefix}http_api_debug_log where host = %s order by {$order_by} {$order} limit {$page_offset}, {$per_page}",
                    $_REQUEST['host']
                ),
                'ARRAY_A'
            );

        } else {

            $data = $wpdb->get_results(
                "select * from {$wpdb->prefix}http_api_debug_log order by {$order_by} {$order} limit {$page_offset}, {$per_page}",
                'ARRAY_A'
            );

        }

        $this->items = $data;

        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
        ) );
    }

}
