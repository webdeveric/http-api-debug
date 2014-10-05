<?php
namespace WDE\HTTPAPIDebug;

class LogModel
{
    protected $db;

    public function __construct($wpdb)
    {
        $this->db = $wpdb;
    }

    public function save()
    {
        admin_notice('Save log entry here.');
        return true;
    }

    public function load($id)
    {
        return $this;
    }


    public function http_api_debug($response, $context, $transport_class, $request_args, $url)
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($this->domain_filter == 'exclude' && in_array($host, $this->domains) ){
            return;
        }

        if ($this->domain_filter == 'include' && ! in_array($host, $this->domains) ){
            return;
        }

        $log_this_entry = apply_filters('http_api_debug_record_log', true, $response, $context, $transport_class, $request_args, $url);

        $this->db->show_errors();

        if ( ! $log_this_entry)
            return;

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
            function_exists('get_current_site') ? \get_current_site() : 0,
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

        if ( $this->logs_to_keep > 0 )
            log_entries_delete_all_except( $this->logs_to_keep );

        if ( $this->purge_after > 0 )
            log_entries_delete_older_than( $this->purge_after );

    }

}
