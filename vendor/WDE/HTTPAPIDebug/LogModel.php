<?php
namespace WDE\HTTPAPIDebug;
use WDE\Util\DataBag;

/*
    @todo Extend a generic Model object that gets table information from the DB
*/
class LogModel extends DataBag
{
    protected $db;
    protected $log_table;
    protected $headers_table;

    public function __construct($wpdb)
    {
        $this->db = $wpdb;
        $this->log_table     = $this->db->prefix . "http_api_debug_log";
        $this->headers_table = $this->db->prefix . "http_api_debug_log_headers";
        parent::__construct();
    }

    /*
    public function load($id)
    {
        admin_notice('Load log entry here.');
        return $this;
    }
    */

    public function save()
    {
        $insert_log_entry = $this->db->prepare(
            "insert into {$this->log_table}
                (site_id, blog_id, method, host, url, status, request_args, request_body, response_data, response_body, backtrace, context, transport, microtime)
                values
                (%d, %d, %s, %s, %s, %d, %s, %s, %s, %s, %s, %s, %s, %f)",
            $this->data['site_id'],
            $this->data['blog_id'],
            $this->data['method'],
            $this->data['host'],
            $this->data['url'],
            $this->data['status'],
            $this->data['request_args'],
            $this->data['request_body'],
            $this->data['response_data'],
            $this->data['response_body'],
            $this->data['backtrace'],
            $this->data['context'],
            $this->data['transport'],
            $this->data['microtime']
        );

        $num_rows = $this->db->query( $insert_log_entry );

        if ( ! $num_rows )
            return false;

        $log_id = $this->db->insert_id;

        if ($log_id) {

            $header_types = array(
                'req' => &$this->data['request_headers'],
                'res' => &$this->data['response_headers']
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

        return $log_id;
    }

}
