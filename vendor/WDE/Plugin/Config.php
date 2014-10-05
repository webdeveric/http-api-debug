<?php
namespace WDE\Plugin;
use WDE\Util\Container;

class Config extends Container
{
    protected $option;

    public function __construct( $config_option )
    {
        $this->option = $config_option;
        parent::__construct( \get_site_option( $config_option, array() ) );
        admin_debug( $this->data );
    }

    public function save()
    {
        return \update_site_option( $this->option, $this->data );
    }
}
