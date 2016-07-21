<?php
/**
 * A wrapper for javascript function calls.
 * Use to pass callback functions in the DataTables configuration.
 */

namespace DataTables\Lib;

class CallbackFunction implements \JsonSerializable
{

    /**
     * Holds all prepared JS statements to be injected into JSON
     * @var array
     */
    protected static $_placeholders = [];

    /**
     * Resolve all hashes in a JSON string with their respective javascript code
     * @param string $json JSON-encoded data
     * @return string JSON-encoded data where hashes are replaced with javascript code
     */
    public static function resolve($json)
    {
        return strtr($json, self::$_placeholders);
    }

    /**
     * Holds this specific object's hash to be passed in jsonSerialize()
     * @var string
     */
    protected $hash;

    /**
     * CallbackFunction constructor.
     * @param string $name Name of Javascript function to call
     * @param array $args Optional array of arguments to be passed when calling
     */
    function __construct($name, array $args = [])
    {
        if (empty($args)) {
            $code = $name;
        } else {
            $code = 'function (args) { ';
            foreach ($args as $a) {
                $arg = json_encode($a);
                $code .= "Array.prototype.push.call(arguments, {$arg});";
            }
            $code .= "return {$name}.apply(this, arguments); }";
        }

        $this->setHash($code);
    }

    /**
     * Set hash for this wrapper and register in placeholder list
     * @param $code: payload
     */
    protected function setHash($code)
    {
        $this->hash = md5($code);
        // use parenthesis as this is how it will show up in json
        self::$_placeholders['"'.$this->hash.'"'] = $code;
    }

    /**
     * Get code generated for this JS function call
     * @return string the generated code
     */
    function code()
    {
        return self::$_placeholders['"'.$this->hash.'"'];
    }

    /**
     * Serialize to a placeholder in json
     * @return string a unique hash to be replaced by resolve() after json_encode()
     */
    function jsonSerialize()
    {
        return $this->hash;
    }
}
