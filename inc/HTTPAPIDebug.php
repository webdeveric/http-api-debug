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
    protected $options_group;

    public function __construct($wpdb)
    {
        $this->db                = &$wpdb;
        $this->version_db_key    = 'http-api-debug-version';
        $this->log_table         = $this->db->prefix . "http_api_debug_log";
        $this->headers_table     = $this->db->prefix . "http_api_debug_log_headers";
        $this->main_page_slug    = 'http-api-debug';
        $this->options_page_slug = 'http-api-debug-options';
        $this->options_group     = 'http-api-debug';
        $this->require_wp_debug  = \get_site_option( 'http-api-debug-require-wp-debug', 0 );
        $this->ignore_cron       = \get_site_option( 'http-api-debug-ignore-cron', 0 );
        $this->domain_filter     = \get_site_option( 'http-api-debug-domain-filter', 'exclude' );
        $this->domains           = array_map('trim', explode( "\n", \get_site_option( 'http-api-debug-domains', '' ) ) );
        $this->logs_to_keep      = abs( \get_site_option( 'http-api-debug-logs-to-keep', 0 ) );
        $this->purge_after       = abs( \get_site_option( 'http-api-debug-purge-after', 0 ) );

        register_activation_hook( HTTP_API_DEBUG_FILE, array(&$this, 'activate') );
        register_deactivation_hook( HTTP_API_DEBUG_FILE, array(&$this, 'deactivate') );

        // add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'wp_cron_wp_error_response_code'), 1, 3);
        add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'timed_out_wp_error_response_code'), 2, 3);
        // add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'no_dns_record_wp_error_response_code'), 10, 3);

        add_action( 'plugins_loaded', array( &$this, 'update_db_check' ) );
        add_action( 'admin_menu', array( &$this, 'admin_menu' ), 10, 0 );
        add_action( 'admin_init', array( &$this, 'admin_init' ), 10, 0 );

        add_filter( 'admin_footer_text', array( &$this, 'admin_footer_text' ), PHP_INT_MAX, 1);
        add_filter( 'set-screen-option', array( &$this, 'set_screen_option' ), 10, 3 );

        if ( ! $this->require_wp_debug || ( defined('WP_DEBUG') && constant('WP_DEBUG') == true ) )
            add_action( 'http_api_debug', array( &$this, 'http_api_debug' ), 10, 5 );

        if ( $this->ignore_cron )
            add_filter('http_api_debug_record_log', array( &$this, 'ignore_cron'), 10, 6);

    }

    public function ignore_cron($record_log, $response, $context, $transport_class, $request_args, $url)
    {
        if (is_cron_request($url))
            return false;

        return $record_log;
    }

    public function admin_menu()
    {
        $this->main_page_hook = \add_menu_page(
            'HTTP API Debug',
            'HTTP API Debug',
            'activate_plugins',
            $this->main_page_slug,
            array(&$this, 'admin_page'),
            'dashicons-info'            
        );

        \add_submenu_page(
            $this->main_page_slug,
            'HTTP API Debug Options',
            'Options',
            'activate_plugins',
            $this->options_page_slug,
            array(&$this, 'options_admin_page')
        );

        add_action( 'admin_print_styles', array( &$this, 'admin_styles' ) );
        add_action( 'admin_print_scripts', array( &$this, 'admin_scripts' ) );
        add_action( 'load-' . $this->main_page_hook, array( &$this, 'screen_options' ) );
    }

    public function admin_init()
    {
        $this->register_settings();
    }

    public function sanitize_settings($value)
    {
        return $value;
    }

    protected function register_settings()
    {
        add_settings_section(
            $this->options_page_slug . '-basic',
            'Basic Options',
            function() {
            },
            $this->options_page_slug
        );

        register_setting( $this->options_group, 'http-api-debug-require-wp-debug', 'intval' );

        register_setting( $this->options_group, 'http-api-debug-ignore-cron', 'intval' );

        register_setting(
            $this->options_group,
            'http-api-debug-domain-filter',
            function($value) {
                if ( $value == 'exclude' || $value == 'include' ) {
                    return $value;
                }
                return 'exclude';
            }
        );
        
        register_setting(
            $this->options_group,
            'http-api-debug-domains',
            function($value) {
                $lines = array_filter(
                    explode( "\n", trim( $value ) ),
                    function($v) {
                        return trim( $v ) === '' ? false : $v;
                    }
                );
                $lines = implode("\n", $lines);
                return $lines;
            }
        );

        add_settings_section(
            $this->options_page_slug . '-purge',
            'Purge Options',
            function() {
                echo '<p>Entries are only deleted when a new entry is added to the log.</p>';
            },
            $this->options_page_slug
        );

        register_setting( $this->options_group, 'http-api-debug-logs-to-keep', 'abs' );

        register_setting( $this->options_group, 'http-api-debug-purge-after', 'abs' );

        $require_wp_debug = $this->require_wp_debug;
        $ignore_cron      = $this->ignore_cron;
        $domain_filter    = $this->domain_filter;
        $domains          = implode("\n", $this->domains);
        $logs_to_keep     = $this->logs_to_keep;
        $purge_after      = $this->purge_after;

        add_settings_field(
            'http-api-debug-require-wp-debug',
            'Only log requests if WP_DEBUG is true',
            function($args) use ($require_wp_debug) {
                $checked = \checked( 1, $require_wp_debug, false );
                $input = '<input type="checkbox" value="1" name="http-api-debug-require-wp-debug" ' . $checked . ' />';
                printf('<label>%1$s <span>WP_DEBUG = %2$s</span></label>', $input, defined('WP_DEBUG') ? var_export( constant('WP_DEBUG'), true) : 'not defined' );
            },
            $this->options_page_slug,
            $this->options_page_slug . '-basic'
        );

        add_settings_field(
            'http-api-debug-ignore-cron',
            'Don&#8217;t log cron requests',
            function($args) use ($ignore_cron) {
                $checked = \checked( 1, $ignore_cron, false );
                echo '<input type="checkbox" value="1" name="http-api-debug-ignore-cron" ', $checked, ' />';
            },
            $this->options_page_slug,
            $this->options_page_slug . '-basic'
        );

        add_settings_field(
            'http-api-debug-domain-filter',
            'Domain filter',
            function($args) use ($domain_filter) {
                foreach ( array('exclude', 'include') as $filter ) {
                    printf(
                        '<p><label><input type="radio" value="%1$s" name="http-api-debug-domain-filter" %3$s /><span>%2$s</span></label></p>',
                        $filter,
                        ucfirst($filter),
                        \checked( $filter, $domain_filter, false )
                    );
                }
            },
            $this->options_page_slug,
            $this->options_page_slug . '-basic'
        );

        add_settings_field(
            'http-api-debug-domains',
            'Only these domains<br /><small>(one domain per line)</small>',
            function($args) use ($domains) {
                echo '<textarea class="widefat" rows="6" name="http-api-debug-domains">', $domains, '</textarea>';
            },
            $this->options_page_slug,
            $this->options_page_slug . '-basic'
        );

        add_settings_field(
            'http-api-debug-logs-to-keep',
            'Maximum log entries to keep<br /><small>(0 = no limit)</small>',
            function($args) use ($logs_to_keep) {
                printf(
                    '<input type="number" name="http-api-debug-logs-to-keep" value="%d" min="0" />',
                    $logs_to_keep
                );
            },
            $this->options_page_slug,
            $this->options_page_slug . '-purge'
        );

        add_settings_field(
            'http-api-debug-purge-after',
            'Delete log entries older than X seconds<br /><small>(0 = disabled)</small>',
            function($args) use ($purge_after) {
                ?>

                <input type="number" name="http-api-debug-purge-after" id="http-api-debug-purge-after" value="<?php echo $purge_after; ?>" min="0" required list="predefined-times" />
                <output name="human-purge-time" id="human-purge-time"></output>
                <div id="purge-time-quick-links"></div>
                <datalist id="predefined-times">
                    <option value="0">Disabled</option>
                    <option value="86400">One day</option>
                    <option value="604800">One week</option>
                    <option value="26297434">One month</option>
                </datalist>

                <?php
            },
            $this->options_page_slug,
            $this->options_page_slug . '-purge'
        );

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

        $home_url = menu_page_url( isset($_REQUEST['page']) ? $_REQUEST['page'] : '', false );

        $header_links = array();

        ob_start();

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
                // $header_links[] = '<a href="#" class="add-new-h2">Test URL</a>';
        }

        $content = ob_get_clean();

        include __DIR__ . '/main-page.php';

    }

    public function main_admin_page()
    {
        include __DIR__ . '/HTTPAPIDebugLogTable.php';

        $log_table = new HTTPAPIDebugLogTable();
        $log_table->prepare_items();

        if (array_key_not_empty('s', $_REQUEST) || array_key_not_empty('host', $_REQUEST)):
        ?>

        <h3>
            <a href="<?php menu_page_url( isset($_REQUEST['page']) ? $_REQUEST['page'] : ''); ?>">Log Entries</a>
            <?php

            if ( array_key_not_empty('s', $_REQUEST) ) {

                printf('<span class="subtitle">Search: <strong>%s</strong></span>', esc_html( $_REQUEST['s'] ) );

            } elseif ( array_key_not_empty('host', $_REQUEST) ) {

                printf('<span class="subtitle">Host: <strong>%s</strong></span>', esc_html( $_REQUEST['host'] ) );

            }

            ?>
        </h3>

        <?php
        endif;

        $log_table->views();
        ?>

        <form id="http-api-debug-log-filter" method="get" action="">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php
                $log_table->search_box( 'Search', 'http-api-debug' );
                $log_table->display();
            ?>
        </form>
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

        if (filter_has_var(INPUT_GET, 'settings-updated') && $_GET['settings-updated'] )
            echo updated_message('Options saved!');
        ?>

        <div class="wrap">
            <h2>HTTP API Debug</h2>
            <form method="post" action="options.php">
                <?php
                    settings_fields($this->options_group);
                    do_settings_sections($this->options_page_slug);
                    submit_button('Save Options');
                ?>
            </form>
        </div>

        <?php
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

    public function set_screen_option($status, $option, $value)
    {
        if ( $option == 'http_api_debug_log_per_page' )
            return $value;
        return $status;
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
        return get_site_option( $this->version_db_key, '0.0.0' );
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
            backtrace LONGTEXT NOT NULL,
            context varchar(32) NOT NULL default 'response',
            transport varchar(32) NOT NULL default '',
            microtime DOUBLE UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (log_id),
            INDEX site_blog_ids (site_id, blog_id),
            INDEX request_info (method, host, status),
            INDEX log_microtime (microtime)
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

        \dbDelta( $create_log_table );
        \dbDelta( $create_headers_table );

        $this->set_db_version( $this->version() );
    }

    protected function set_db_version($version)
    {
        \update_site_option( $this->version_db_key, $version );
    }

    public function update_db_check()
    {
        if ( ! table_exists('http_api_debug_log') || ! table_exists('http_api_debug_log_headers') || version_compare( $this->dbVersion(), $this->version(), '<') ) {
            $this->install();

            if ( current_filter() === 'plugins_loaded' ) {
                admin_notice('Updating DB for HTTP API Debug plugin.');
            }
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
        foreach ( $messages as &$message ) {
            if (str_starts_with($message, 'Operation timed out'))
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
        $host = parse_url($url, PHP_URL_HOST);

        if ($this->domain_filter == 'exclude' && in_array($host, $this->domains) ) {
            return;
        }

        if ($this->domain_filter == 'include' && ! in_array($host, $this->domains) ) {
            return;
        }

        $log_this_entry = apply_filters('http_api_debug_record_log', true, $response, $context, $transport_class, $request_args, $url);

        $this->db->show_errors();

        if ( ! $log_this_entry ) {
            return;
        }

        $request_method = '';

        $request_headers = array();
        $request_body = '';

        $response_headers = array();
        $response_body = '';

        if (isset($request_args['method'])) {
            $request_method = $request_args['method'];
            unset($request_args['method']);
        }

        if (isset($request_args['headers'])) {
            $request_headers = $request_args['headers'];
            unset($request_args['headers']);
        }

        if (isset($request_args['body'])) {
            $request_body = $request_args['body'];
            unset($request_args['body']);
        }

        if ( is_wp_error($response) ) {

            // var_dump(func_get_args());

        } else {

            if (isset($response['headers'])) {
                $response_headers = $response['headers'];
                unset($response['headers']);
            }

            if (isset($response['body'])) {
                $response_body = $response['body'];
                unset($response['body']);
            }

        }

        $request_args = json_encode($request_args);

        $response_data = json_encode($response);

        $backtrace = print_r( \debug_backtrace(), true );
        $backtrace = str_replace( ABSPATH, '/', $backtrace );

        foreach ( array('DB_USER', 'DB_PASSWORD') as $field ) {
            $backtrace = str_replace( constant($field), 'hidden for your protection', $backtrace );
        }

        $insert_log_entry = $this->db->prepare(
            "insert into {$this->log_table}
                (site_id, blog_id, method, host, url, status, request_args, request_body, response_data, response_body, backtrace, context, transport, microtime)
                values
                (%d, %d, %s, %s, %s, %d, %s, %s, %s, %s, %s, %s, %s, %f)",
            function_exists('get_current_site') ? (\get_current_site())->id : 0,
            get_current_blog_id(),
            $request_method,
            $host,
            $url,
            $this->get_response_code($url, $response),
            $request_args,
            $request_body,
            $response_data,
            $response_body,
            $backtrace,
            $context,
            $transport_class,
            microtime(true)
        );

        $num_rows = $this->db->query( $insert_log_entry );

        $log_id = $this->db->insert_id;

        if ($log_id) {

            $header_types = array(
                'req' => &$request_headers,
                'res' => &$response_headers
            );

            foreach ( $header_types as $header_type => &$headers ) {

                foreach ( $headers as $header_name => &$header_value ) {

                    if ( is_array( $header_value ) )
                        $header_value = json_encode( $header_value );

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

        if ( $this->logs_to_keep > 0 ) {
            log_entries_delete_all_except( $this->logs_to_keep );
        }

        if ( $this->purge_after > 0 ) {
            log_entries_delete_older_than( $this->purge_after );
        }
    }

}
