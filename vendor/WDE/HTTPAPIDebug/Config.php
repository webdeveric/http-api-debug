<?php
namespace WDE\HTTPAPIDebug;
use WDE\Plugin\Config as PluginConfig;

class Config extends PluginConfig
{
    public function __construct( $config_option, array $default = array() )
    {
        $default = array_merge(
            array(
                'admin_notify'     => true,
                'filter'           => 'exclude',
                'ignore_cron'      => true,
                'require_wp_debug' => true,
                'domains'          => '',                
            ),
            $default
        );

        parent::__construct( $config_option, $default );

        if ( isset( $this->data['domains'] ) && ! empty( $this->data['domains'] ) ) {
            $this->data['domains'] = array_map('trim', explode( "\n", $this->data['domains'] ) );
        } else {
            $this->data['domains'] = array();
        }
    }
}
