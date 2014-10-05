<?php
namespace WDE\Plugin;
use WDE\Util\DataBag;

class Config extends DataBag
{
    protected $option;

    public function __construct( $config_option, array $default = array() )
    {
        $this->option = $config_option;
        $data = \get_site_option( $config_option, $default );
        $data = array_merge( $default, $data );
        parent::__construct( $data );
    }

    public function update()
    {
        return \update_site_option( $this->option, $this->data );
    }

    public function delete()
    {
        return \delete_site_option( $this->option );
    }
}
