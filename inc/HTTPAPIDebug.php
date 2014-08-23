<?php

namespace WDE\HTTPAPIDebug;

class HTTPAPIDebug
{
    protected $version_db_key = 'http-api-debug-version';
    protected $db;
    protected $table_name;

    public function __construct($wpdb)
    {
        $this->db = &$wpdb;
        $this->table_name = $this->db->prefix . "http_api_debug_log";

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
    }

    public function deactivate()
    {
    }

    public function install()
    {
        $charset_collate = '';

        if ( ! empty( $this->db->charset ) ) {
            $charset_collate = "DEFAULT CHARACTER SET {$this->db->charset}";
        }

        if ( ! empty( $this->db->collate ) ) {
            $charset_collate .= " COLLATE {$this->db->collate}";
        }

        $sql = "CREATE TABLE $this->table_name (
            log_id BIGINT unsigned NOT NULL AUTO_INCREMENT,
            site_id BIGINT(20) unsigned NOT NULL default 0,
            blog_id BIGINT(20) unsigned NOT NULL default 0,
            url TEXT NOT NULL,
            method varchar(10) not null default '',
            args TEXT NOT NULL,
            response TEXT NOT NULL,
            status INT(3) UNSIGNED ZEROFILL,
            context varchar(32) NOT NULL default 'response',
            transport varchar(32) NOT NULL default '',
            log_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (log_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        \dbDelta( $sql );

        \update_option( $this->version_db_key, $this->version() );

    }

    public function update_db_check() {
        if ( ! table_exists('http_api_debug_log') || version_compare($this->dbVersion(), $this->version(), '<')) {
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

    protected function is_cron_request($url)
    {
        $url_parts = parse_url($url);
        $site_host = parse_url(get_site_url(), PHP_URL_HOST);

        if ( isset($url_parts['host'], $url_parts['path']) &&
             $url_parts['host'] == $site_host &&
             str_ends_with($url_parts['path'], '/wp-cron.php') ) {
            return true;
        }
        return false;
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
        if ($code === 0 && $this->is_cron_request($url)) {
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
        $num_rows = $this->db->query( 
            $this->db->prepare(
                "insert into {$this->table_name}
                    (site_id, blog_id, url, method, args, response, status, context, transport, log_time)
                    values
                    (%d, %d, %s, %s, %s, %s, %d, %s, %s, NOW())",
                function_exists('get_current_site') ? get_current_site() : 0,
                get_current_blog_id(),
                $url,
                isset($request_args['method']) ? $request_args['method'] : '',
                json_encode($request_args),
                json_encode($response),
                $this->get_response_code($url, $response),
                $context,
                $transport_class
            )
        );
    }

}
