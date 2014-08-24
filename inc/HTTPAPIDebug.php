<?php

namespace WDE\HTTPAPIDebug;

class HTTPAPIDebug
{
    protected $version_db_key = 'http-api-debug-version';
    protected $db;
    protected $log_table;
    protected $headers_table;

    public function __construct($wpdb)
    {
        $this->db = &$wpdb;
        $this->log_table = $this->db->prefix . "http_api_debug_log";
        $this->headers_table = $this->db->prefix . "http_api_debug_log_headers";

        register_activation_hook( HTTP_API_DEBUG_FILE, array(&$this, 'activate') );
        register_deactivation_hook( HTTP_API_DEBUG_FILE, array(&$this, 'deactivate') );

        // add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'wp_cron_wp_error_response_code'), 1, 3);
        // add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'timed_out_wp_error_response_code'), 2, 3);
        // add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'no_dns_record_wp_error_response_code'), 10, 3);

        add_action( 'plugins_loaded', array(&$this, 'update_db_check') );
        add_action( 'http_api_debug', array(&$this, 'http_api_debug'), 10, 5);
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

        if (str_starts_with(current_filter(), 'activate')) {
        	// Fire off a quick request so that there is something in the log table after you activate.
        	\wp_remote_get('http://ip.phplug.in/');
        	\wp_remote_get('http://ip.phplug.in/?output=json');
        	\wp_remote_get('http://ip.phplug.in/?output=xml');
        }
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

        $request_args  = json_encode($request_args);
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
                            $header_name,
                            $header_value
                        )
                    );
                }
            }

        }

    }

}
