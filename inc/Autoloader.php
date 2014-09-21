<?php
namespace WDE\Autoloader;

/*
    This autoloader is based on the PSR-4 autoloader.
    https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader-examples.md
*/
class Autoloader
{
    protected $prefixes = array();

    public function register()
    {
        spl_autoload_register( array($this, 'loadClass') );
    }

    public function unregister()
    {
        spl_autoload_unregister( array($this, 'loadClass') );
    }

    public function addNamespace( $prefix, $base_dir, $prepend = false )
    {
        $prefix   = trim( $prefix, '\\' ) . '\\';
        $base_dir = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

        if ( ! array_key_exists( $prefix, $this->prefixes ) || ! is_array( $this->prefixes[ $prefix ] ) ) {
            $this->prefixes[ $prefix ] = array();
        }

        if ( $prepend ) {
            array_unshift( $this->prefixes[ $prefix ], $base_dir );
        } else {
            $this->prefixes[ $prefix ][] = $base_dir;
        }
    }

    public function loadClass( $class )
    {
        $prefix = $class;

        while ( false !== $pos = strrpos( $prefix, '\\' ) ) {
            $prefix = substr( $class, 0, $pos + 1 );
            $relative_class = substr( $class, $pos + 1 );
            if ( $mapped_file = $this->loadMappedFile( $prefix, $relative_class ) )
                return $mapped_file;
            $prefix = rtrim( $prefix, '\\' );
        }

        return false;
    }

    protected function loadMappedFile( $prefix, $relative_class )
    {
        if ( ! isset( $this->prefixes[ $prefix ] ) )
            return false;

        foreach ( $this->prefixes[ $prefix ] as $base_dir ) {
            $file = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
            if ( $this->requireFile( $file ) )
                return $file;
        }

        return false;
    }

    protected function requireFile( $file )
    {
        if ( file_exists( $file ) && is_readable( $file ) ) {
            require $file;
            return true;
        }
        return false;
    }
}
