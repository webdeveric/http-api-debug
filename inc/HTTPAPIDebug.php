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
            log_id BIGINT unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
            url TEXT,
            args TEXT,
            response TEXT,
            context varchar(32) NOT NULL default 'response',
            transport varchar(32) NOT NULL default '',
            log_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        \dbDelta( $sql );

        \update_option( $this->version_db_key, $this->version() );

        // \WDE\admin_debug($sql);

    }

    public function update_db_check() {
        if (version_compare($this->dbVersion(), $this->version(), '<')) {
            $this->install();
        }
    }

    function http_api_debug($response, $context, $class, $args, $url)
    {
        $num_rows = $this->db->query( 
            $this->db->prepare(
                "insert into {$this->table_name} (url, args, response, context, transport, log_time) values (%s, %s, %s, %s, %s, NOW())",
                $url,
                json_encode($args),
                json_encode($response),
                $context,
                $class
            )
        );

        /*
        \WDE\admin_debug($response);
        \WDE\admin_debug($context);
        \WDE\admin_debug($class);
        \WDE\admin_debug($args);
        \WDE\admin_debug($url);
        */

    }

}
