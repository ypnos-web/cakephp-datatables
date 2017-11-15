<?php

namespace DataTables\Lib;

/**
 * A convenience array wrapper that holds a single column definition
 * @method ColumnDefintion visible()
 * @method ColumnDefintion notVisible()
 * @method ColumnDefintion orderable()
 * @method ColumnDefintion notOrderable()
 * @method ColumnDefintion searchable()
 * @method ColumnDefintion notSearchable()
 */
class ColumnDefinition implements \JsonSerializable, \ArrayAccess
{
    /** @var array holding all column properties */
    public $content = [];

    /** @var ColumnDefinitions */
    protected $owner = null;

    protected $switchesPositive = ['visible', 'orderable', 'searchable'];
    // will be filled in constructor
    protected $switchesNegative = [];

    public function __construct(array $template, ColumnDefinitions $owner)
    {
        $this->content = $template;
        $this->owner = $owner;

        $this->switchesNegative = array_map(function ($e) {
            return 'not'.ucfirst($e);
        }, $this->switchesPositive);
    }

    /**
     * Refer back to owner's add()
     * A convenient way to add another column
     */
    public function add(...$args) : ColumnDefinition
    {
        return $this->owner->add(...$args);
    }

    /**
     * Set one or many properties
     * @param $key string|array If array given, it should be key -> value
     * @param $value: The singular value to set, if string $key given
     * @return ColumnDefinition
     */
    public function set($key, $value = null) : ColumnDefinition
    {
        if (is_array($key)) {
            if (!empty($value))
                throw new \InvalidArgumentException("Provide either array or key/value pair!");

            $this->content = $key + $this->content;
        } else {
            $this->content[$key] = $value;
        }
        return $this;
    }

    /* provide some convenience wrappers for set() */
    public function __call($name, $arguments) : ColumnDefinition
    {
        if (in_array($name, $this->switchesPositive)) {
            if (!empty($arguments))
                throw new \InvalidArgumentException("$name() takes no arguments!");

            $this->content[$name] = true;
        }
        if (in_array($name, $this->switchesNegative)) {
            if (!empty($arguments))
                throw new \InvalidArgumentException("$name() takes no arguments!");

            $name = lcfirst(substr($name, 3));
            $this->content[$name] = false;
        }

        return $this;
    }

    public function unset(string $key) : ColumnDefinition
    {
        unset($this->content[$key]);
        return $this;
    }

    /**
     * @param $name: see CallbackFunction::__construct
     * @param $args: see CallbackFunction::__construct
     * @return ColumnDefinition
     */
    public function render(string $name, array $args = []) : ColumnDefinition
    {
        $this->content['render'] = new CallbackFunction($name, $args);
        return $this;
    }

    public function jsonSerialize() : array
    {
        return $this->content;
    }

    public function offsetExists($offset)
    {
        return isset($this->content[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->content[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->content[] = $value;
        } else {
            $this->content[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->content[$offset]);
    }

}
