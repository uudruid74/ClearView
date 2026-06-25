<?php

namespace ClearView;

use ClearView\Crystal;
use ClearView\Exception;
use ClearView\Pane;
use ProcessWire;
use ProcessWire\HookEvent;
use ReflectionClass;

/**
 * Crystal for managing sanitization in ProcessWire.
 *
 * Acts as a wrapper for sanitization, storing a list of sanitizer names in $pwObject.
 * Supports setting sanitizer methods via setVar, retrieving wrapped Sanitizer instances via getVar,
 * and sanitizing/validating values with chained sanitizers.
 *
 * Example:
 * ```php
 * // Register a custom sanitizer method
 * public function devolveSanitizer($value) { return strtoupper($value); }
 * Mosaic setVar("Sanitizer::devolve", $this); // register sanitizer
 * // Use it like this ...
 * $myhello = (new Sanitizer("devolve"))->validate("hello"); // returns "HELLO"
 * ```
 *
 * @see \ClearView\Crystal
 */
class Sanitizer extends Crystal
{
    /**
     * Initializes the Sanitizer Crystal with an optional list of sanitizer names.
     *
     * Registers methods ending with 'Sanitizer' as ProcessWire sanitizers via registerSanitizers on itself, but initializes pwObject as empty.
     *
     * Why: Sets up sanitization within ClearView’s data model.
     *
     * @param mixed $pwObject Array of sanitizer names (defaults to empty array).
     */
    public function __construct($pwObject=null,$panename=null,$inlayname=null,$mos)
    {
        parent::__construct(\ProcessWire\sanitizer(),$panename,$inlayname,$mos);
        $this->initFields([
            'sanitizerList'         => self::getSanitizerList($pwObject),
            'isShardFormat'         => false,   /** @var bool Flag to indicate if the pwObject is key/value pairs */
            'failCallbackObject'    => null,    /** @var object The last variable to fail validation */
            'failCallbackMethod'    => null     /** @var string The method name to call as callback */
        ]);
        self::registerSanitizers($this);
    }

    /**
     * Used to set a callback method on failure.  The callback method will be passed the variable name and sanitizer that failed.
     * @param object $obj The object to call.
     * @param string $method The method name to call.
     * @return self for chaining
     */
    public function onFail($obj, string $method)
    {
        $this->setFields([
            'failCallbackObject'    => $obj,
            'failCallbackMethod'    => $method
        ]);
        return $this;
    }

    /**
     * Sanitizer method: Replaces double curly braces with Unicode small curly
     * brackets to prevent triggering replacement strings.
     *
     * WARNING!  ALWAYS call 'noBraces\noScript\'
     * on all untrusted data **before** saving it to ProcessWire!
     *
     * @param string $value The input value to sanitize.
     * @return string The sanitized value with {{ replaced by U+FE5B and }} replaced by U+FE5C.
     */
    public function noBracesSanitizer($value)
    {
        return strtr($value,[
                '{{'    => "\u{FE5B}\u{FE5B}",
                '}}'    => "\u{FE5C}\u{FE5C}"
        ]);
    }

    /**
     * Sanitizer method: Replaces angle brackets in script tags with Unicode
     * single quotation marks to prevent script execution
     *
     * @param string $value The input value to sanitize
     * @return string The sanitized value with <script>...</script> tags
     * having < replaced by U+2039 and > by U+203A.
     */
    public function noScriptsSanitizer($value)
    {
        $pattern = '/<script\b[^>]*>.*?<\/script>/is';
        return preg_replace_callback($pattern, function ($match) {
            return strtr($match[0], [
                '<'     => "\u{2039}",
                '>'     => "\u{203A}"
            ]);
        }, $value);
    }

    /**
     * Sanitizer method: Validates a 5-digit zip code.
     *
     * @param string $value The input value to sanitize.
     * @return string The sanitized value or empty string if invalid.
     */
    public function zipSanitizer($value)
    {
        $value = \ProcessWire\sanitizer()->digits($value, 5);
        return strlen($value) === 5 ? $value : '';
    }

    /**
     * Sanitizer method: Limits string to a maximum length with ellipsis.
     *
     * @param string $value The input value to sanitize.
     * @return string The truncated value with ellipsis if needed.
     */
    public function truncifySanitizer($value)
    {
        $maxLength = 50; // Example max length
        $value = \ProcessWire\sanitizer()->text($value, ['maxLength' => $maxLength]);
        return strlen($value) >= $maxLength ? $value . '...' : $value;
    }

    /**
     * Wrap PHP functions to use as sanitizers
     */
    public function lowercaseSanitizer($value) {
        return strtolower($value);
    }
    public function uppercaseSanitizer($value) {
        return strtoupper($value);
    }
    public function ucfirstSanitizer($value) {
        return ucfirst($value);
    }
    public function ucwordsSanitizer($value) {
        return ucwords($value);
    }
    public function requiredSanitizer($value) {
        return empty($value) ? null : $value;
    }

    /**
     * Registers all sanitizers from the provided object.  
     * Can be called with an inlay to register all sanitizers in the inlay.
     * Looks for methods ending with 'Sanitizer', hooks them under their short names.
     *
     * @param object $obj The object containing sanitizer methods.
     */
    public static function registerSanitizers($obj): void
    {
        Exception::debug("registerSanitizers");
        $reflection = new \ReflectionClass($obj);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $sanitizerNames = [];
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (str_ends_with($methodName, 'Sanitizer')) {
                $shortName = substr($methodName, 0, -9); // Strip 'Sanitizer'
                Exception::debug("Registering $shortName");
                \ProcessWire\sanitizer()->addHook($shortName, function (HookEvent $event) use ($obj, $methodName) {
                    $value = $event->arguments(0);
                    $event->return = $obj->$methodName($value);
                });
                $sanitizerNames[] = $shortName;
            }
        }
    }

    /**
     * Sets pwObject to a key/value array for Shard format.
     * Each value is a string split on '\' to get sanitizer lists.  Commas let PW handle it.
     *
     * @param array $array Key/value pairs where values are sanitizer strings (e.g., "text\zip").
     * @return void
     */
    public function setVars(array $array, ?string $inlay = null): void
    {
        foreach ($array as $key => $sanitizersString) {
            $this[$key] = self::getSanitizers($sanitizersString);
        }
        $this['isShardFormat'] = true;
    }

    /**
     * Sanitizes a value or Shard using the specified or stored sanitizers.
     *
     * Applies sanitizers from $pwObject and/or $sanitizers in order. 
     * For Shard format, sanitizes in-place and returns the Shard.
     * For Shards, the sanitizer should be an associative array.
     * Matching keys between the sanitizer and the shard will sanitize the matching field of the Shard
     *
     * TODO: Parse the length suffix and hide it and original string length in
     *      the Sanitizer Shard so custom sanitizers can be more intelligent
     *
     * @param mixed $value The value or Shard to sanitize.
     * @param array|string $sanitizers Optional array of sanitizer names or single sanitizer name.
     * @return mixed The sanitized value or Shard.
     * @throws Exception If no sanitizers are specified or sanitizer doesn't exist.
     */
    public static function sanitize($value, $sanitizers)
    {
        if (empty($sanitizers)) {
            throw new Exception("No sanitizers specified for sanitization.");
        }
        if ($value instanceof Shard) {
            foreach ($value as $key => $keySanitizers) {
                $shardValue = $value->getField($key);
                foreach ($keySanitizers as $sanitizerName) {
                    if (method_exists(\ProcessWire\sanitizer(), $sanitizerName)) {
                        $shardValue = \ProcessWire\sanitizer()->$sanitizerName($shardValue);
                    } else {
                        throw new Exception("Sanitizer '$sanitizerName' does not exist.");
                    }
                }
                $value->setField($key, $shardValue);
            }
            return $value; // Return modified Shard
        } else {
            $result = $value;
            $sanitizerList = self::getSanitizerList($sanitizers);
            foreach ($sanitizerList as $sanitizerName) {
                //if (method_exists(\ProcessWire\sanitizer(), $sanitizerName)) {
                $result = \ProcessWire\sanitizer()->$sanitizerName($result);
                //} else {
                //    throw new Exception("Sanitizer '$sanitizerName' does not exist.");
                //}
            }
            return $result;
        }
    }

    /**
     * Validates a value or Shard using the specified or stored sanitizers.
     *
     * Applies sanitizers and checks if the input matches the sanitized output and is non-empty/non-null.
     * For Shard format, validates each key and checks if 'required' is defined. If callbacks are set,
     * the callback can display information to the user on what went wrong, and can return a new value or
     * return false to have validate() fail.  The called function is passed the variable name, sanitizer name,
     * and the original value that failed.  FIXME: Needs work and should be static
     *
     * @param mixed|null $value The value or Shard to validate, null for current Pane/Inlay
     * @param array|string|null $sanitizers Optional array of sanitizer names or single sanitizer name.
     * @return bool True if the value is valid, false otherwise.
     * @throws Exception If no sanitizers are specified.
     */
    public function validate($value = null, $sanitizers = null)
    {
        $value = $value ?? Framework::instance();
        $sanitizerList = self::getSanitizerList($sanitizers);
        if (empty($sanitizerList)) {
            throw new Exception("No sanitizers specified for validation.");
        }
        if ($this['isShardFormat'] && $value instanceof Shard) {
            foreach ($this[Config::PAGE_PWOBJECT] as $key => $keySanitizers) {
                $shardValue = $value->getField($key);
                $sanitized = $shardValue;
                foreach ($keySanitizers as $sanitizerName) {
                    if (method_exists(\ProcessWire\sanitizer(), $sanitizerName)) {
                        $sanitized = \ProcessWire\sanitizer()->$sanitizerName($sanitized);
                    } else {
                        throw new Exception("Sanitizer '$sanitizerName' does not exist.");
                    }
                    if ($shardValue !== $sanitized || $sanitized === '' || $sanitized === null) {
                        if (method_exists($this['failCallbackObject'], $this['failCallbackMethod'])) {
                            $shardValue = $this['failCallbackObject']->{$this['failCallbackMethod']}($key,$sanitizerName,$value);
                            if (empty($shardValue)) {
                                return false;
                            }
                        } else {
                            return false;
                        }
                    }
                }
            }
            return true;
        } else {
            $sanitized = $value;
            foreach ($sanitizerList as $sanitizerName) {
                if (method_exists(\ProcessWire\sanitizer(), $sanitizerName)) {
                    $sanitized = \ProcessWire\sanitizer()->$sanitizerName($sanitized);
                } else {
                    throw new Exception("Sanitizer '$sanitizerName' does not exist.");
                }
            }
            return $value === $sanitized && $sanitized !== '' && $sanitized !== null;
        }
    }

    /**
     * Gets a new Sanitizer instance for the specified sanitizer name.
     *
     * @param string|null $key The sanitizer name (e.g., 'text', 'zip').
     * @return Sanitizer|null A new Sanitizer instance with the specified sanitizer, or null if not found.
     */
    public function getVar($key = null)
    {
        if ($key === null || $key === '') {
            return $this;
        }
        Exception::debug("Sanitizer getVar $key");
        if (method_exists(\ProcessWire\sanitizer(), $key)) {
            // just return a callable closure instead of a new Sanitizer instance
            return function($value) use ($key) {
                return \ProcessWire\sanitizer()->$key($value);
            };
        }
        return null;
    }

    /**
     * Gets a list of all available sanitizer names.
     *
     * Includes both custom sanitizers (e.g., 'zip', 'truncate') and ProcessWire’s built-in sanitizers.
     *
     * @param mixed $keys Ignored, as this returns all sanitizers.
     * @return array List of sanitizer names.
     */
    public function getVars($keys = null): array
    {
        $sanitizer = \ProcessWire\sanitizer();
        $reflection = new \ReflectionClass($sanitizer);
        $methods = array_map(function ($method) {
            return $method->getName();
        }, $reflection->getMethods(\ReflectionMethod::IS_PUBLIC));
        return array_filter($methods, function ($method) {
            // Filter out non-sanitizer methods (e.g., __construct, get, etc.) FIXME: This is bad!
            return !in_array($method, ['__construct', 'get', 'set', 'addHook', 'addHookMethod']);
        });
    }

    /**
     * Handles calls to undefined methods, allowing short names for sanitizers.
     *
     * If the method name + 'Sanitizer' exists on this object, calls that. Otherwise, falls back to ProcessWire's sanitizer method.
     *
     * @param string $name The method name called.
     * @param array $arguments The arguments passed.
     * @return mixed The result of the sanitizer method.
     * @throws Exception If the method does not exist in custom or ProcessWire sanitizers.
     */
    public function __call($name, $arguments)
    {
        Exception::debug("Sanitizer __call $name");
        $sanitizerMethod = $name . 'Sanitizer';
        if (method_exists($this, $sanitizerMethod)) {
            return call_user_func_array([$this, $sanitizerMethod], $arguments);
        }
        $sanitizer = \ProcessWire\sanitizer();
        if (method_exists($sanitizer, $name)) {
            return call_user_func_array([$sanitizer, $name], $arguments);
        }
        throw new Exception("Sanitizer method '$name' does not exist.");
    }

    /**
     * If $sanitizers is a string, splits it on '\' to create an array of sanitizer names.
     *
     * @param string|null $sanitizers string of sanitizers separated by '\'.
     * @return array List of sanitizer names to apply.
     */
    private static function getSanitizerList($sanitizers): array
    {
        if (is_null($sanitizers)) {
            return [];
        }
        if (is_array($sanitizers)) {
            return $sanitizers;
        }
        if (str_contains($sanitizers, '\\')) {
            return explode('\\', $sanitizers);
        } else {
            return [ $sanitizers ];
        }
    }
}
