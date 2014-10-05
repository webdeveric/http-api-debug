<?php
namespace WDE\HTTPAPIDebug;
use WDE\Plugin\Config;

class DebugLogger
{
    protected $log;
    protected $config;

    public function __construct(LogModel $log, Config $config)
    {
        $this->log    = $log;
        $this->config = $config;

        add_action( 'http_api_debug', array( &$this, 'http_api_debug' ), 10, 5 );
    }

    public function http_api_debug($response, $context, $transport_class, $request_args, $url)
    {
        // $this->log->context = $context;
        $this->log->save();
    }

}
