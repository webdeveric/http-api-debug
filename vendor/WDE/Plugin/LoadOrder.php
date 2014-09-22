<?php
namespace WDE\Plugin;

class LoadOrder
{
    protected $plugin_file;
    protected $position;

    public function __construct( $plugin_file, $position = 'first' )
    {
        $this->plugin_file = $plugin_file;
        $this->position    = $position;
        add_filter( 'pre_update_option_active_plugins',  array( &$this, 'filter_active_plugins' ),  PHP_INT_MAX, 2 );    
    }

    public function filter_active_plugins( $active_plugins, $old_active_plugins )
    {
        $plugin_file = plugin_basename( $this->plugin_file );
        $plugin_key  = array_search( $plugin_file, $active_plugins );

        if ( $plugin_key !== false ) { // Do we have something to work with?

            array_splice( $active_plugins, $plugin_key, 1 ); // Remove it from its current location.

            if ( $this->position == 'first' ) {

                array_unshift( $active_plugins, $plugin_file ); // Add it to the front.

            } elseif ( $this->position == 'last' ) {

                $active_plugins[] = $plugin_file; // Add it to the back.

            } else {

                array_splice( $active_plugins, (int)$this->position, 0, $plugin_file ); // Put it at a specific index.

            }

            $active_plugins = array_unique($active_plugins);

        }

        return $active_plugins;
    }
}
