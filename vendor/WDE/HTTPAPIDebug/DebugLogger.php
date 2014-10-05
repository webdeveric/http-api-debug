<?php
namespace WDE\HTTPAPIDebug;

class DebugLogger
{
    protected $log;
    protected $config;

    public function __construct(LogModel $log, Config $config)
    {
        $this->log    = $log;
        $this->config = $config;

        if ( $this->config->require_wp_debug && ! wp_debug() )
            return;

        add_action( 'http_api_debug', array( &$this, 'http_api_debug' ), 10, 5 );

        // add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'wp_cron_wp_error_response_code'), 1, 3);
        add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'timed_out_wp_error_response_code'), 2, 3);
        // add_filter( 'http_api_debug_wp_error_response_code', array(&$this, 'no_dns_record_wp_error_response_code'), 10, 3);

        if ( $this->config->ignore_cron )
            add_filter( 'http_api_debug_record_log', array( &$this, 'ignore_cron' ), 10, 6 );
    }

    public function ignore_cron($record_log, $response, $context, $transport_class, $request_args, $url)
    {
        if (is_cron_request($url))
            return false; // false means do not log the request.
        return $record_log;
    }

    protected function get_response_code($url, $response)
    {
        if (\is_wp_error($response)) {
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

        if ($this->config->filter == 'exclude' && in_array($host, $this->config->domains) ) {
            return;
        }

        if ($this->config->filter == 'include' && ! in_array($host, $this->config->domains) ) {
            return;
        }

        $log_this_entry = apply_filters('http_api_debug_record_log', true, $response, $context, $transport_class, $request_args, $url);

        if ( ! $log_this_entry )
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

        if ( ! \is_wp_error($response) ) {

            if (isset($response['headers'])) {
                $response_headers = $response['headers'];
                unset($response['headers']);
            }

            if (isset($response['body'])) {
                $response_body = $response['body'];
                unset($response['body']);
            }

        }

        $backtrace = print_r( \debug_backtrace(), true );
        $backtrace = str_replace( ABSPATH, '/', $backtrace );

        foreach ( array('DB_USER', 'DB_PASSWORD') as $field ) {
            $backtrace = str_replace( constant($field), 'hidden for your protection', $backtrace );
        }

        $log_entry = array(
            'site_id'          => function_exists('get_current_site') ? \get_current_site() : 0,
            'blog_id'          => \get_current_blog_id(),
            'method'           => $request_method,
            'host'             => $host,
            'url'              => $url,
            'status'           => $this->get_response_code($url, $response),
            'request_args'     => json_encode($request_args),
            'request_body'     => $request_body,
            'response_data'    => json_encode($response),
            'response_body'    => $response_body,
            'backtrace'        => $backtrace,
            'context'          => $context,
            'transport'        => $transport_class,
            'microtime'        => microtime(true),
            'request_headers'  => $request_headers,
            'response_headers' => $response_headers,
        );

        $this->log->setData( $log_entry );
        $log_id = $this->log->save();

        do_action('http_api_debug_log_entry_added', $log_id, $this->log );
    }

}
