<?php
/**
 * A wrapper for javascript function calls.
 * Use to pass callback functions in the DataTables configuration.
 */

namespace DataTables\Lib;

class JSFunction implements \JsonSerializable
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
    public static function resolve(string $json) : string
    {
        return strtr($json, self::$_placeholders);
    }

    /**
     * Holds this specific object's hash to be passed in jsonSerialize()
     * @var string
     */
    protected $hash;

    /**
     * JSFunction constructor.
     * @param string $name Name of Javascript function to call
     * @param array $args Optional array of arguments to be passed when calling
     */
    function __construct(string $name, array $args = [])
    {
        $code = 'function (args) { ';
        foreach ($args as $a) {
            $arg = json_encode($a);
            $code .= "Array.prototype.push.call(arguments, $arg);";
        }
        $code .= "return $name.apply(this, arguments); }";

        // use sizeof placeholders as prefix to ensure uniqueness
        $this->hash = md5($code);
        self::$_placeholders["\"$this->hash\""] = $code;
    }

    /**
     * Get code generated for this JS function call
     * @return string the generated code
     */
    function code() : string
    {
        return self::$_placeholders["\"$this->hash\""];
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
