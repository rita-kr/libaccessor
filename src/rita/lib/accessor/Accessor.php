<?php

/*
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 */

declare(strict_types=1);

namespace rita\lib\accessor;

/**
 * Creates an Accessor instance that wraps the given object or class name
 *
 * @param object|string $value
 */
function access($value, int $flags = Accessor::FLAG_WRAP_ARRAY) : Accessor{
    return Accessor::from($value, $flags);
}

/**
 * Class Accessor is provides a method to easiest access non-public elements of object
 *
 * Quickly wrap object and make all elements accessible
 *
 * @link https://accessor.docs.present.kim/
 *
 * ===================================
 */
class Accessor implements IAccessor{
    public const FLAG_WRAP_NONE = 0x00;
    public const FLAG_WRAP_ARRAY = 0x01;
    public const FLAG_WRAP_OBJECT = 0x02;

    public const FLAG_WRAP_ALL = self::FLAG_WRAP_ARRAY | self::FLAG_WRAP_OBJECT;

    public static function init() : void{ }

    /**
     * Creates an Accessor instance that wraps the given object or class name
     *
     * @param object|string $value
     */
    public static function from($value, int $flags = self::FLAG_WRAP_ARRAY) : Accessor{
        return new self($value, $flags);
    }

    /** @var string */
    protected $className;

    /** @var object|null */
    protected $origin = null;

    /** @var int */
    protected $flags;

    /** @var \ReflectionClass */
    protected $reflection;

    /** @var \ReflectionProperty[] */
    protected $properties = [];

    /** @var \ReflectionMethod[] */
    protected $methods = [];

    /** @param object|string $value */
    protected function __construct($value, int $flags = self::FLAG_WRAP_ARRAY){
        if(is_object($value)){
            $this->className = get_class($value);
            $this->origin = $value;
        }elseif(is_string($value)){
            if(class_exists($value)){
                $this->className = $value;
            }else{
                throw new \RuntimeException("An unknown class name was given : $value");
            }
        }else{
            throw new \RuntimeException("Argument 1 passed must be of the object or string, " . gettype($value) . " given");
        }
        try{
            $this->reflection = new \ReflectionClass($this->className);
        }catch(\ReflectionException $exception){
            throw new \RuntimeException("Cannot be access to {$this->className} class");
        }
        $this->flags = $flags;
    }

    /** Returns original class name */
    public function getClassName() : string{
        return $this->className;
    }

    /** Returns original object or null */
    public function getOrigin() : ?object{
        return $this->origin;
    }

    /** Returns original object or null */
    public function __getReflection() : \ReflectionClass{
        return $this->reflection;
    }

    protected function getProperty(string $name) : \ReflectionProperty{
        if(!isset($this->properties[$name])){
            try{
                $this->properties[$name] = $this->reflection->getProperty($name);
                $this->properties[$name]->setAccessible(true);
            }catch(\ReflectionException $exception){
                throw new \RuntimeException("Undefined property: {$this->className}::\${$name}");
            }
        }
        if(!$this->properties[$name]->isStatic() && $this->origin === null)
            throw new \RuntimeException("Accessor for which no object is given cannot access member property.");

        return $this->properties[$name];
    }

    protected function getMethod(string $name) : \ReflectionMethod{
        if(!isset($this->methods[$name])){
            try{
                $this->methods[$name] = $this->reflection->getMethod($name);
                $this->methods[$name]->setAccessible(true);
            }catch(\ReflectionException $exception){
                throw new \RuntimeException("Undefined method: {$this->className}::{$name}()");
            }
        }
        if(!$this->methods[$name]->isStatic() && $this->origin === null)
            throw new \RuntimeException("Accessor for which no object is given cannot access member method.");

        return $this->methods[$name];
    }

    public function __getDirect(string $name){
        $property = $this->getProperty($name);
        return $property->getValue($property->isStatic() ? null : $this->origin);
    }

    public function __setDirect(string $name, $value) : void{
        $property = $this->getProperty($name);
        $property->setValue($property->isStatic() ? null : $this->origin, $value);
    }

    public function __isset(string $name) : bool{
        try{
            $this->getProperty($name);
            return true;
        }catch(\RuntimeException $exception){
            return false;
        }
    }

    public function __get(string $name){
        $value = $this->__getDirect($name);
        if(is_array($value) && ($this->flags & self::FLAG_WRAP_ARRAY) !== 0){
            $value = new ArrayProp($this, $name);
        }elseif(is_object($value) && ($this->flags & self::FLAG_WRAP_OBJECT) !== 0){
            $value = new Accessor($this, $this->flags);
        }
        return $value;
    }

    public function __set(string $name, $value) : void{
        $this->__setDirect($name, $value instanceof IAccessor ? $value->getOrigin() : $value);
    }

    public function __call(string $name, $args){
        $method = $this->getMethod($name);
        return $method->invokeArgs($method->isStatic() ? null : $this->origin, $args);
    }
}