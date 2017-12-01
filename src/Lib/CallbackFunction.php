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
    public static function resolve(string $json) : string
    {
        /* allow one recursion (a callback with a callback as argument) */
        $replacements = [];
        foreach (self::$_placeholders as $id => $content) {
            $replacements[$id] = strtr($content, self::$_placeholders);
        }
        return strtr($json, $replacements);
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
    public function __construct(string $name, array $args = [])
    {
        if (empty($args)) {
            $code = $name;
        } else {
            $code = 'function () { ';
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
    protected function setHash(string $code)
    {
        $this->hash = md5($code);
        // use parenthesis as this is how it will show up in json
        self::$_placeholders['"'.$this->hash.'"'] = $code;
    }

    /**
     * Get code generated for this JS function call
     * @return string the generated code
     */
    public function code() : string
    {
        return self::$_placeholders['"'.$this->hash.'"'];
    }

    /**
     * Serialize to a placeholder in json
     * @return: a unique hash to be replaced by resolve() after json_encode()
     */
    public function jsonSerialize() : string
    {
        return $this->hash;
    }
}
