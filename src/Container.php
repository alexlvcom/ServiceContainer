<?php

namespace alexlvcom\ServiceContainer;

use \Exception;
use \ErrorException;
use \InvalidArgumentException;
use \Reflection;
use \ReflectionClass;
use \ReflectionException;

/**
 * IoC Container by AlexLV
 *
 * @version 0.0.1
 * @author AlexLV
 */
class Container
{
    /**
     * Holds created objects
     *
     * @var array
     */
    protected $objects = [];

    /**
     * Holds processed objects, used to check for circular dependency.
     * @var array
     */
    private $processing = [];

    public function __construct()
    {
        set_error_handler([$this, 'catchableFatalErrorHandler']);
    }

    /**
     * @param string $name Unique Identifier
     * @param mixed $binding can be 1) Closure 2) existing object 3) class name
     */
    public function bind($name, $binding)
    {
        if (array_key_exists($name, $this->objects)) {
            throw new InvalidArgumentException("$name is already registered.");
        }

        $this->objects[$name] = $binding;
    }

    /**
     * Creates an object either that is exising in container or just an object outside container
     *
     * @param $name
     * @param array $constructorParameters
     * @return mixed
     * @throws ContainerResolveException
     */
    public function make($name, ...$constructorParameters)
    {
        // Prevent endless recursion which could be cause circular dependency (when object A requires object B, and object B requires A as a dependency)
        if (array_key_exists($name, $this->processing)) {
            throw new InvalidArgumentException("Circular dependency detected for $name.");
        }

        $this->processing[$name] = true;

        if (array_key_exists($name, $this->objects)) {
            $binding = $this->objects[$name];
            try {
                if (is_callable($binding)) {
                    $service = $binding($this);
                } elseif (is_object($binding)) {
                    $service = $binding;
                } elseif (class_exists($binding)) {
                    $service = $this->makeExistingClass($binding, $constructorParameters);
                } else {
                    throw new ContainerResolveException("$binding is not a callable neither is pre-created object or path to the class.");
                }
                unset($this->processing[$name]);
                return $service;
            } catch (\Exception $e) {
                throw new ContainerResolveException("Unable to resolve $name: ".$e->getMessage(), $e->getCode());
            }
        } elseif (class_exists($name)) {
            return $this->makeExistingClass($name, $constructorParameters);
        } else {
            throw new InvalidArgumentException("$name not found in Container neither it is existing object.");
        }
    }

    /**
     * @param string $name Class name with the namespace
     * @param array $constructorParameters for the given oject
     * @return $this|mixed
     * @throws ContainerResolveException
     */
    private function makeExistingClass($name, $constructorParameters = [])
    {
        // we don't want to create new service container object - use existing!
        if ($name === __CLASS__) {
            try {
                $this->bind($name, $this);
            } catch (Exception $e) {
                // do nothing
            }
            unset($this->processing[$name]);
            return $this;
        }

        $service = $this->resolveExistingObject($name, $constructorParameters);
        unset($this->processing[$name]);
        return $service;
    }


    /**
     * Resolves existing object that has not been added to the Container
     * Automatically injects dependencies into the contructor
     *
     * @param string $objectName Object name with the namespace
     * @param array $constructorParameters
     * @return mixed
     * @throws ContainerResolveException
     */
    private function resolveExistingObject($objectName, $constructorParameters = [])
    {

        $reflector = new ReflectionClass($objectName);

        try {
            $constructor = $reflector->getMethod('__construct');
        } catch (ReflectionException $e) {
            // if there is no constructor, then just create and return an object
            return new $objectName;
        }


        $modifiers = Reflection::getModifierNames($constructor->getModifiers());

        // if constructor is not public, throw an exception
        if (!in_array('public', $modifiers)) {
            throw new ContainerResolveException("Unable to resolve: $objectName's constructor is not public.");
        }

        // if there is no parameters or there is no required parameters, then just create and return object
        if ($constructor->getNumberOfParameters() === 0 || $constructor->getNumberOfRequiredParameters() === 0) {
            return new $objectName(...$constructorParameters);
        }

        $dependencies = [];
        $i            = 1;
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isOptional() === false) {
                try {
                    // We found type hint class, resolving with ServiceContainer
                    $typeHintClass = $parameter->getClass();
                    if ($typeHintClass) {
                        $dependencies[] = $this->make($typeHintClass->getName());
                    } else {
                        if (array_key_exists($i - 1, $constructorParameters)) {
                            $dependencies[] = $constructorParameters[$i - 1];
                        } else {
                            throw new ContainerResolveException("Constructor parameter #$i '".$parameter->getName()."' for $objectName is required.");
                        }

                    }
                } catch (ReflectionException $e) {
                    throw new ContainerResolveException("Unable to resolve dependency #$i for $objectName: ".$e->getMessage());
                }
            }
            $i++;
        }

        // if dependencies are resolved, create and object with all the dependecies resolved automatically.
        if (count($dependencies) > 0) {
            return new $objectName(...$dependencies);
        }

        throw new ContainerResolveException("Unable to resolve $objectName: Unknown error.");
    }

    /**
     * Cathces PHP Catchable fatal errors
     *
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @return bool
     * @throws ErrorException
     */
    public function catchableFatalErrorHandler($errno, $errstr, $errfile, $errline)
    {
        if (E_RECOVERABLE_ERROR === $errno) {
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        }
        return false;
    }
}
