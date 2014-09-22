<?php
namespace WDE\DI;

use SplObjectStorage;
use Closure;
use Exception;
use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use WDE\Exceptions\UnresolvableAliasException;
use WDE\Exceptions\UnresolvableClassException;
use WDE\Exceptions\UnresolvableParameterException;

/*
This container should do the following:

1.  Associate an object instance to an alias.
    There will only be one instance (singleton) returned when the alias is requested.

2.  Generate new instances of classes either by
        A.  Calling a factory to generate an instance.
                I.  To register a factory you need to call $app->factory( $alias, $callback )
        B.  Using PHP Reflection classes to figure it out.

3.  The objects are not instantiated until requested.

4.  Create aliases for classes/interfaces.
    This is so you can associate an abstract class or interface to a concrete class instance (1) or factory (2)

5.  The default behavior when assigning a property to the container ($app->something = function(){}) is to treat that like (1)
    The $name passed to __set will become the $alias and the $value is the callable used to instantiate the singleton.
*/
class Container implements ArrayAccess, IteratorAggregate
{
    protected $objects;
    protected $callbacks;
    protected $aliases;
    protected $arguments;
    protected $factories;

    public function __construct(){
        $this->objects   = array();
        $this->callbacks = array();
        $this->aliases   = array();
        $this->arguments = array();
        $this->factories = new SplObjectStorage();
    }

    public function arg($key, $value)
    {
        $this->arguments[$key] = $value;
    }

    public function getArg($key)
    {
        if ( array_key_exists($key, $this->arguments) )
            return $this->arguments[$key];
        return null;
    }


    public function register($name, $callback, $lookup_alias = false)
    {
        $name = trim( $name, '\\' );

        if ( $lookup_alias )
            $name = $this->resolveAlias( $name );

        // check for existence and possibly throw exception for duplicates
        if ( ! $callback instanceOf Closure) {
            $callback = $this->makeClosure($callback);
        }
        return $this->callbacks[$name] = $callback;
    }

    public function alias($alias_name, $original_name)
    {
        $alias_name    = trim( $alias_name, '\\' );
        $original_name = trim( $original_name, '\\' );
        $this->aliases[$alias_name] = $original_name;
    }

    public function prefixAlias($prefix, $alias)
    {
        if ( ! is_array($alias) )
            $alias = array($alias);

        $prefix = trim( $prefix, '\\' );

        foreach ($alias as &$alias_name) {
            $alias_name = trim( $alias_name, '\\' );
            $this->aliases[$alias_name] = $prefix . '\\' . $alias_name;
        }
    }

    public function resolveAlias($alias)
    {
        if ( ! isset($this->aliases[$alias])) {
            return $alias;
        }

        $counter = 0;

        do {
            $alias = $this->aliases[$alias];
        } while ( ++$counter < 50 && isset( $this->aliases[$alias] ) );
        // Do it again if there is another alias for the current $alias

        if ( $counter >= 50 ) {
            throw new UnresolvableAliasException(
                sprintf('Alias resolve limit (50) reached for %1$s at alias %1$s', func_get_arg(0), $alias)
            );                
        }

        return $alias;
    }

    public function instance($name, $object)
    {
        return $this->objects[$name] = $object;
    }

    public function factory($name, $callback)
    {
        $callback = $this->register($name, $callback);
        $this->factories->attach($callback);
        return $callback;
    }

    public function has($name)
    {
        $fields = array('callbacks', 'objects', 'aliases');

        foreach ( $fields as &$field ) {
            if (array_key_exists($name, $this->$field))
                return true;
        }

        return false;
    }

    public function get($name)
    {
        foreach ( array(false, true) as $lowercase ) {
            try {
                if($lowercase) {
                    $name = strtolower($name);
                }

                $name = $this->resolveAlias($name);

                // Get instance
                if (isset($this->objects[$name])) {
                    // printf('<p>Object exists [%s]</p>', $name);
                    return $this->objects[$name];
                }

                // First initialization OR call factory
                if (isset($this->callbacks[$name])) {

                    if ($this->factories->contains($this->callbacks[$name])) {
                        // printf('<p>factory exists [%s]</p>', $name);
                        return $this->callbacks[$name]($this);
                    }

                    // printf('<p>Callback exists [%s]</p>', $name);
                    return $this->objects[$name] = $this->callbacks[$name]($this);
                }

                return $this->resolve($name);

            } catch (Exception $e) {

            }
        }
        throw new UnresolvableClassException($e->getMessage());
    }

    protected function makeClosure($callback)
    {
        return function (Container $container) use ($callback) {
            return $callback($container);
        };
    }

    public function resolve($name)
    {
        try {

            $ref = new ReflectionClass($name);

            if ($ref->isInstantiable()) {

                $constructor = $ref->getConstructor();

                if (is_null($constructor)) {
                    // Nothing to construct so no arguments are needed
                    return new $name;
                }

                $params = $constructor->getParameters();

                if (empty($params)) {
                    // Constructor doesn't take any parameters so just construct it and send it back.
                    return new $name;
                }

                $parameters = array();
                foreach ($params as $param) {
                    $parameters[] = $this->resolveParameter($param, $ref);
                }

                return $ref->newInstanceArgs($parameters);
            }

            switch(true) {
                case $ref->isAbstract():
                    throw new UnresolvableClassException("Unresolvable Abstract Class [$name]");
                case $ref->isInterface():
                    throw new UnresolvableClassException("Unresolvable Interface [$name]");
                case $ref->isTrait():
                    throw new UnresolvableClassException("Unresolvable Trait [$name]");                
                default:
                    throw new UnresolvableClassException("Unresolvable Class [$name]");
            }

        } catch (ReflectionException $e) {// Class does not exist

            throw new UnresolvableClassException($e->getMessage());

        } catch (Exception $e) {

            throw $e;

        }
    }

    public function resolveParameter(ReflectionParameter $param, ReflectionClass $ref)
    {
        $ref_class = $param->getClass();

        if (is_null($ref_class)) {

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            if (array_key_exists($param->name, $this->arguments)) {
                return $this->arguments[$param->name];
            }

            throw new UnresolvableParameterException(sprintf('Unresolvable %2$s - %1$s', $param, $ref->getName()));

        } else {

            return $this->get($ref_class->name);

        }
    }

    /*
    public function __call($name , array $arguments)
    {
    }

    public static function __callStatic($name, array $arguments)
    {
    }
    */

    private function __clone(){}

    public function __invoke($key)
    {
        return $this->get($key);
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        return $this->register($key, $value);
    }

    public function __isset($key)
    {
        return isset($this->objects[$key]);
    }

    public function __unset($key)
    {
        // This should remove any objects, factories, and aliases from the container.
        if (isset($this->objects[$key])) {
            // if ($this->factories->contains($this->objects[$key])) {
                $this->factories->detach($this->objects[$key]);
            // }
            unset($this->objects[$key]);
        }
    }

    public function offsetSet($key, $value)
    {
        return $this->register($key, $value);
    }

    public function offsetGet($key)
    {
        return $this->get($key);
    }

    public function offsetUnset($key)
    {
        $this->__unset($key);
    }

    public function offsetExists($key)
    {
        return $this->__isset($key);
    }

    public function getIterator()
    {
        return new ArrayIterator($this->objects);
    }

}
