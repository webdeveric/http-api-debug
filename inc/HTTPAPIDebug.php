<?php

namespace WDE\HTTPAPIDebug;

class HTTPAPIDebug
{
    protected $db;
    protected $version_db_key;
    protected $log_table;
    protected $headers_table;
    protected $main_page_hook;
    protected $main_page_slug;

    public function __construct($wpdb)
    {
        $this->db             = &$wpdb;
        $this->version_db_key = 'http-api-debug-version';
        $this->log_table      = $this->db->prefix . "http_api_debug_log";
        $this->headers_table  = $this->db->prefix . "http_api_debug_log_headers";
        $this->main_page_slug = 'http-api-debug';

        register_activation_hook( HTTP_API_DEBUG_FILE, array(&$this, 'activate') );
        register_deactivation_hook( HTTP_API_DEBUG_FILE, array(&$this, 'deactivate') );

        // add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'wp_cron_wp_error_response_code'), 1, 3);
        // add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'timed_out_wp_error_response_code'), 2, 3);
        // add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'no_dns_record_wp_error_response_code'), 10, 3);

        add_action( 'plugins_loaded', array(&$this, 'update_db_check') );
        add_action( 'http_api_debug', array(&$this, 'http_api_debug'), 10, 5);
        add_action( 'admin_menu', array(&$this, 'admin_menu'), 10, 0);
        
        add_filter( 'admin_footer_text', array(&$this, 'admin_footer_text'), PHP_INT_MAX, 1);
    }

    public function admin_menu()
    {
        $this->main_page_hook = \add_menu_page(
            'HTTP API Debug',
            'HTTP API Debug',
            'update_plugins',
            $this->main_page_slug,
            array(&$this, 'admin_page'),
            'dashicons-info'            
        );

        \add_submenu_page(
            $this->main_page_slug,
            'HTTP API Debug Options',
            'Options',
            'update_plugins',
            'http-api-debug-options',
            array(&$this, 'options_admin_page')
        );

        add_action('admin_print_styles', array(&$this, 'admin_styles') );
        add_action('admin_print_scripts', array(&$this, 'admin_scripts') );
        add_action('load-' . $this->main_page_hook, array(&$this, 'screen_options') );

    }

    protected function is_http_api_debug_admin_page()
    {
        static $is_admin_page;

        if ( ! isset($is_admin_page) ) {
            $screen = \get_current_screen();
            $is_admin_page = $screen->base === $this->main_page_hook || str_starts_with($screen->base, 'http-api-debug_page');
        }

        return $is_admin_page;
    }

    public function admin_styles()
    {
        if ($this->is_http_api_debug_admin_page()) {
            wp_enqueue_style('highlightjs', '//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.2/styles/default.min.css', array(), null);
            wp_enqueue_style('http-api-debug', plugins_url('/css/dist/main.min.css', HTTP_API_DEBUG_FILE), array(), HTTP_API_DEBUG_VERSION);
        }
    }

    public function admin_scripts()
    {
        if ($this->is_http_api_debug_admin_page()) {
            wp_enqueue_script('highlightjs', '//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.2/highlight.min.js', array(), null);
            wp_enqueue_script('http-api-debug', plugins_url('/js/dist/main.min.js', HTTP_API_DEBUG_FILE), array(), HTTP_API_DEBUG_VERSION);
        }
    }

    public function admin_page()
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
                $this->display_log_entry();
                break;
            /*
            case 'delete':
                delete_log_entry_confirm();
                break;
            */
            default:
                $this->main_admin_page();
        }

        echo '</div>';
    }

    public function main_admin_page()
    {
        include __DIR__ . '/HTTPAPIDebugLogTable.php';

        $log_table = new HTTPAPIDebugLogTable();
        $log_table->prepare_items();
        ?>
        <div class="wrap">
            <h2>
                HTTP API Debug Log
                <?php

                if ( array_key_exists('s', $_REQUEST) && ! empty( $_REQUEST['s'] ) ) {

                    printf('<span class="subtitle">Search: <strong>%s</strong></span>', esc_html( $_REQUEST['s'] ) );

                } elseif ( isset( $_REQUEST['host'] ) && ! empty( $_REQUEST['host'] ) ) {

                    printf('<span class="subtitle">Host: <strong>%s</strong></span>', esc_html( $_REQUEST['host'] ) );

                }

                ?>
            </h2>

            <?php $log_table->views(); ?>

            <form id="http-api-debug-log-filter" method="get" action="">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <?php
                    $log_table->search_box( 'Search', 'http-api-debug' );
                    $log_table->display();
                ?>
            </form>


        </div>
        <?php
    }

    public function display_log_entry()
    {
        $entry = get_log_entry( $_REQUEST['log_id'] );

        if ( is_object( $entry ) ) {

            if ( property_exists($entry, 'response') )
                $entry->response = json_decode($entry->response);

            $entry = apply_filters('http_api_debug_log_entry', $entry);

            if (isset($entry))
                include __DIR__ . '/single-entry.php';

        } else {

            echo '<h1>Entry not found</h1>';
            printf('<p><a href="%s">Go back</a></p>', admin_url('tools.php?page=http-api-debug'));

        }

    }

    public function options_admin_page()
    {
        echo 'options';
    }

    public function screen_options()
    {
        $screen = get_current_screen();

        if( ! is_object($screen) || $screen->base != $this->main_page_hook )
           return;

        if ( array_key_exists('log_id', $_REQUEST ) ) {
            
            if ( (int)$_REQUEST['log_id'] > 0 ) {
                // Screen options for viewing single log entry go here.
            }

        } else {

            \add_screen_option(
                'per_page',
                array(
                    'label'   => 'Log entries per page',
                    'default' => 20,
                    'option'  => 'http_api_debug_log_per_page'
                )
            );

        }
    }

    public function admin_footer_text($text)
    {
        if ($this->is_http_api_debug_admin_page())
            return sprintf('HTTP Debug API Version %1$s', $this->version() );
        return $text;
    }

    public function version()
    {
        return defined('HTTP_API_DEBUG_VERSION') ? constant('HTTP_API_DEBUG_VERSION') : 'unknown';
    }

    public function dbVersion()
    {
        return get_site_option($this->version_db_key, '0.0.0');
    }

    public function activate()
    {
        $this->update_db_check();

        // Fire off a few requests so that there is something in the log table after you activate.
        \wp_remote_get('http://ip.phplug.in/');
        \wp_remote_get('http://ip.phplug.in/?output=json');
        \wp_remote_get('http://ip.phplug.in/?output=xml');
    }

    public function deactivate()
    {
    }

    public function install()
    {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $charset_collate = '';

        if ( ! empty( $this->db->charset ) ) {
            $charset_collate = "DEFAULT CHARACTER SET {$this->db->charset}";
        }

        if ( ! empty( $this->db->collate ) ) {
            $charset_collate .= " COLLATE {$this->db->collate}";
        }

        $create_log_table = "CREATE TABLE {$this->log_table} (
            log_id BIGINT unsigned NOT NULL AUTO_INCREMENT,
            site_id BIGINT(20) unsigned NOT NULL default 0,
            blog_id BIGINT(20) unsigned NOT NULL default 0,
            method varchar(10) not null default '',
            host varchar(255) NOT NULL default '',
            url varchar(2048) NOT NULL default '',
            status INT(3) UNSIGNED ZEROFILL NOT NULL default 0,
            request_args LONGTEXT NOT NULL,
            request_body LONGTEXT NOT NULL,
            response_data LONGTEXT NOT NULL,
            response_body LONGTEXT NOT NULL,
            context varchar(32) NOT NULL default 'response',
            transport varchar(32) NOT NULL default '',
            log_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (log_id),
            INDEX site_blog_ids (site_id, blog_id),
            INDEX request_info (method, host, status)
        ) $charset_collate;";

        $create_headers_table = "CREATE TABLE {$this->headers_table} (
            log_header_id BIGINT unsigned NOT NULL AUTO_INCREMENT,
            log_id BIGINT unsigned,
            header_type char(3) NOT NULL,
            header_name varchar(64) NOT NULL default '',
            header_value LONGTEXT NOT NULL,
            PRIMARY KEY (log_header_id),
            FOREIGN KEY (log_id) REFERENCES {$this->log_table} (log_id) ON DELETE cascade ON UPDATE cascade,
            INDEX header_type (header_type),
            INDEX header_name (header_name)            
        ) $charset_collate;";

        /*
        $this->db->show_errors();
        $this->db->print_error();
        */

        \dbDelta( $create_log_table );
        \dbDelta( $create_headers_table );

        $this->set_db_version( $this->version() );
    }

    protected function set_db_version($version)
    {
    	\update_option( $this->version_db_key, $version );
    }

    public function update_db_check() {
        if ( ! table_exists('http_api_debug_log') || ! table_exists('http_api_debug_log_headers') || version_compare($this->dbVersion(), $this->version(), '<')) {
            $this->install();
            if (current_filter() === 'plugins_loaded')
                admin_notice('Updating DB for HTTP API Debug plugin.');
        }
    }

    protected function get_response_code($url, $response)
    {
        if (is_wp_error($response)) {
            return apply_filters('http_api_debug_wp_error_response_code', 0, $url, $response);
        } else {
            return isset($response['response'], $response['response']['code']) ? $response['response']['code'] : 0;
        }
    }

    protected function wp_error_request_timedout(\WP_Error $response)
    {
        $messages = $response->get_error_messages('http_request_failed');
        foreach ($messages as &$message) {
            if (str_starts_with($messages, 'Operation timed out'))
                return true;
        }
        return false;
    }

    // Should I use fake response codes for wp bugs?
    public function wp_cron_wp_error_response_code($code, $url, \WP_Error $response)
    {
        if ($code === 0 && is_cron_request($url)) {
            return 999;
        }
        return $code;
    }

    // http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
    public function timed_out_wp_error_response_code($code, $url, \WP_Error $response)
    {
        if ($code === 0 && $this->wp_error_request_timedout($response)) {
            return 408; // 408 Request Timeout
        }
        return $code;
    }

    public function no_dns_record_wp_error_response_code($code, $url, $response)
    {
        if ($code === 0 && ! checkdnsrr($url, 'A') ) {
            return 410; // Gone - Is this the best code to use when there isn't a DNS record for $url?
        }
        return $code;
    }

    public function http_api_debug($response, $context, $transport_class, $request_args, $url)
    {
        $log_this_entry = apply_filters('http_api_debug_record_log', true, $response, $context, $transport_class, $request_args, $url);
        
        $this->db->show_errors();

        if ( ! $log_this_entry)
            return;

        $request_method = '';
        if (isset($request_args['method'])) {
            $request_method = $request_args['method'];
            unset($request_args['method']);
        }

        $request_headers = array();
        if (isset($request_args['headers'])) {
            $request_headers = $request_args['headers'];
            unset($request_args['headers']);
        }

        $request_body = '';
        if (isset($request_args['body'])) {
            $request_body = $request_args['body'];
            unset($request_args['body']);
        }

        $response_headers = array();
        if (isset($response['headers'])) {
            $response_headers = $response['headers'];
            unset($response['headers']);
        }

        $response_body = '';
        if (isset($response['body'])) {
            $response_body = $response['body'];
            unset($response['body']);
        }

        $request_args = json_encode($request_args);

        $response_data = json_encode($response);

        $insert_log_entry = $this->db->prepare(
            "insert into {$this->log_table}
                (site_id, blog_id, method, host, url, status, request_args, request_body, response_data, response_body, context, transport, log_time)
                values
                (%d, %d, %s, %s, %s, %d, %s, %s, %s, %s, %s, %s, NOW())",
            function_exists('get_current_site') ? \get_current_site() : 0,
            get_current_blog_id(),
            $request_method,
            parse_url($url, PHP_URL_HOST),
            $url,
            $this->get_response_code($url, $response),
            $request_args,
            $request_body,
            $response_data,
            $response_body,
            $context,
            $transport_class
        );

        $num_rows = $this->db->query( $insert_log_entry );

        $log_id = $this->db->insert_id;

        if ($log_id) {

            foreach ( array('req' => &$request_headers, 'res' => &$response_headers) as $header_type => &$headers ) {
                foreach ($headers as $header_name => &$header_value) {
                    $this->db->query(
                        $this->db->prepare(
                            "insert into {$this->headers_table} (log_id, header_type, header_name, header_value) values (%d, %s, %s, %s)",
                            $log_id,
                            $header_type,
                            strtolower($header_name),
                            $header_value
                        )
                    );
                }
            }

        }

    }

}
