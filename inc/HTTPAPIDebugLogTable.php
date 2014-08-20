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

    protected function json_column($id, $column, $data)
    {
        // return sprintf('<script>var %1$s%2$d = JSON.parse(\'%3$s\');</script>', $column, $id, addslashes( json_encode( json_decode( $data ) ) ) );
        $data = json_decode( $data );
        return implode(', ', array_keys( get_object_vars($data) ) );

    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
        	case 'log_id':
        	case 'context':
        	case 'transport':
        	case 'log_time':
        		return $item[$column_name];
			case 'url':
				return sprintf('<a href="%1$s" target="_blank">%1$s</a>', $item[$column_name] );
			case 'args':
			case 'response':
				return $this->json_column( $item['log_id'], $column_name, $item[$column_name] );
            default:
                return print_r(array_keys($item),true);
        }
    }

    public function column_url($item)
    {
        $actions = array(
            'view'     => sprintf('<a href="?page=%s&action=%s&log_id=%d">View</a>', $_REQUEST['page'], 'view', $item['log_id'] ),
            'delete'   => sprintf('<a href="?page=%s&action=%s&log_id=%d">Delete</a>', $_REQUEST['page'], 'delete', $item['log_id'] ),
            'visiturl' => sprintf('<a href="%1$s" target="_blank">Visit URL</a>', $item['url'] )
        );

        return sprintf(
            '%1$s%2$s',
            $item['url'],
            $this->row_actions($actions)
        );
    }

    public function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            $this->_args['singular'],
            $item['log_id']
        );
    }

    public function get_columns()
    {
        $columns = table_columns('http_api_debug_log', true);
		$columns = array_map('ucfirst', array_combine($columns, $columns) );

        $columns = array_merge(
			array(
            	'cb' => '<input type="checkbox" />'
	        ),
			$columns
        );
		$columns['log_id']   = 'ID';
		$columns['url']      = 'URL';
		$columns['log_time'] = 'When';

        return $columns;
    }

    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'log_id'   => array('log_id', false),
            'log_time' => array('log_time', true),
            'url'      => array('url', false)
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

    public function process_bulk_action()
    {
        if ( $this->current_action() === 'delete' ) {
            wp_die('Delete log entries here!');
        }
    }

    public function prepare_items()
    {
        global $wpdb;

        $per_page = $this->get_items_per_page('http_api_debug_log_per_page', 20);

		$current_page = $this->get_pagenum();

        $total_items = (int)$wpdb->get_var("select count(*) from {$wpdb->prefix}http_api_debug_log");

        $page_offset = $current_page > 1 ? ($current_page - 1) * $per_page : 0;

		$columns = $this->get_columns();

        $hidden = array('log_id', 'context', 'transport');

        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $order_by = isset($_REQUEST['orderby']) && array_key_exists( $_REQUEST['orderby'], $columns ) ? $_REQUEST['orderby'] : 'log_time';
		
		$order = 'DESC';

		if ( isset( $_REQUEST['order'] )  && in_array( strtoupper( $_REQUEST['order'] ), array('ASC', 'DESC') ) ) {
			$order = $_REQUEST['order'];
		}

        $data = $wpdb->get_results(
        	"select * from {$wpdb->prefix}http_api_debug_log order by {$order_by} {$order} limit {$page_offset}, {$per_page}",
        	'ARRAY_A'
    	);

        $this->items = $data;

        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
        ) );
    }

}
