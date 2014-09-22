<?php
namespace WDE\HTTPAPIDebug;

class Installer
{
    protected $db;
    protected $version_db_key;
    protected $log_table;
    protected $headers_table;

    public function __construct($wpdb)
    {
        $this->db                = &$wpdb;
        $this->version_db_key    = 'http-api-debug-version';
        $this->log_table         = $this->db->prefix . "http_api_debug_log";
        $this->headers_table     = $this->db->prefix . "http_api_debug_log_headers";
    }

    public function version()
    {
        return defined('HTTP_API_DEBUG_VERSION') ? HTTP_API_DEBUG_VERSION : '0.0.0';
    }

    public function dbVersion()
    {
        return get_site_option($this->version_db_key, '0.0.0');
    }

    public function activate()
    {
        $this->check_database();
        // Fire off a request so that there is something in the log table after you activate.
        \wp_remote_get('http://ip.phplug.in/?output=json');
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
            backtrace LONGTEXT NOT NULL,
            context varchar(32) NOT NULL default 'response',
            transport varchar(32) NOT NULL default '',
            microtime DOUBLE UNSIGNED NOT NULL DEFAULT 0,
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

    public function check_database()
    {
        if ( ! table_exists( $this->log_table ) || ! table_exists( $this->headers_table ) || version_compare($this->dbVersion(), $this->version(), '<')) {
            $this->install();
            if (current_filter() === 'plugins_loaded')
                admin_notice('Updating DB for HTTP API Debug plugin.');
        }
    }
}
