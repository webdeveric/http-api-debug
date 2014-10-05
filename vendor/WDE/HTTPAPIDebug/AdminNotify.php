<?php
namespace WDE\HTTPAPIDebug;

class AdminNotify
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        add_action('http_api_debug_log_entry_added', array( &$this, 'notify' ), 10, 2 );
    }

    public function notify($log_id, $log)
    {
        if ( $this->config->admin_notify ) {
            // Load a view to display this information. This is just a place holder.
            admin_notice(
                sprintf(
                    '<strong>HTTP API Debug:</strong> <span class="http-status">Status: %2$d</span> &ndash; <span class="http-host" title="%4$s">Host: %3$s</span> <a href="#%1$d" onclick="alert(\'%1$s\'); return false;" class="button button-secondary">View Details</a>',
                    $log_id,
                    $log->status,
                    $log->host,
                    $log->url
                )
            );
        }
    }
}
