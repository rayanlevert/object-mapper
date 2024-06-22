<?php

namespace RayanLevert\ObjectMapper;

use ReflectionClass;
use ReflectionException;
use stdClass;

use function json_decode;
use function is_string;
use function class_exists;
use function json_last_error_msg;

/**
 * Object mapping class from different sources of data
 *
 * Has only static methods trying to map data to a PHP user defined class
 */
class ObjectMapper
{
    /**
     * Maps an object from a JSON string
     *
     * @param string $json JSON data
     * @param string|object $mappedClass Either a string containing the name of the class, or an object
     * @param int $depth User specified recursion depth
     * @param int $flags Bitmask of JSON decode options
     *
     * @throws Exception If the JSON is incorrect or data is missing for the mapped class
     */
    public static function fromJSON(string $json, string|object $mappedClass, int $depth = 512, int $flags = 0): object
    {
        if (is_string($mappedClass) && !class_exists($mappedClass)) {
            throw new Exception("Class $mappedClass does not exist.");
        }

        $json = json_decode($json, depth: $depth, flags: $flags);

        if (!$json instanceof stdClass) {
            $error = json_last_error_msg();

            throw new Exception('JSON data could not have been decoded' . ($error ? ", error message: $error" : '.'));
        }

        try {
            foreach ((new ReflectionClass($mappedClass))->getProperties() as $oProperty) {
                if ($oProperty->hasDefaultValue()) {
                    
                }
            }
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
}
