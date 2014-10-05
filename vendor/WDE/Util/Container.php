<?php
namespace WDE\Util;

class Container implements \ArrayAccess, \IteratorAggregate, \Countable
{
    protected $data;

    public function __construct( array $data = array() )
    {
        $this->setData( $data );
    }

    public function __destruct()
    {
    }

    public function setData( array $data )
    {
        $this->data = $data;
    }

    public function mergeData( array $data )
    {
        $this->data = array_merge( $this->data, $data );
    }

    public function __get( $key )
    {
        return ( isset( $this->data[ $key ] ) ) ? $this->data[ $key ] : null;
    }

    public function get( $key )
    {
        return ( isset( $this->data[ $key ] ) ) ? $this->data[ $key ] : null;
    }

    public function __set( $key, $value )
    {
        return $this->data[ $key ] = $value;
    }

    public function set( $key, $value )
    {
        return $this->data[ $key ] = $value;
    }

    public function __isset( $key )
    {
        return isset( $this->data[ $key ] );
    }

    public function __unset( $key )
    {
        if ( array_key_exists( $key, $this->data ) ) {
            unset( $this->data[ $key ] );
        }
    }

    public function offsetSet( $key, $value )
    {
        if( is_null( $key ) )
            $this->data[] = $value;
        else
            $this->data[ $key ] = $value;
    }

    public function offsetGet( $key )
    {
        return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null;
    }

    public function offsetUnset( $key )
    {
        if ( isset( $this->data[ $key ] ) ) {
            unset( $this->data[ $key ] );
        }
    }

    public function offsetExists( $offset )
    {
        return isset( $this->data[ $offset ] );
    }

    public function &getData()
    {
        return $this->data;
    }

    public function getIterator()
    {
        return new ArrayIterator( $this->data );
    }

    public function count()
    {
        return count( $this->data );
    }

    public function contains( $obj)
    {
        return in_array( $obj, $this->data );
    }
}
