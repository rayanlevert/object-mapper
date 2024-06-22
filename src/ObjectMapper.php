<?php

namespace RayanLevert\ObjectMapper;

use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use stdClass;

use function json_decode;
use function is_string;
use function class_exists;
use function json_last_error_msg;
use function property_exists;

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

        // JSON must be a decoded object
        if (!$json instanceof stdClass) {
            $error = json_last_error_msg();

            throw new Exception('JSON data could not have been decoded' . ($error ? ", error message: $error" : '.'));
        }

        try {
            $oReflectionClass = new ReflectionClass($mappedClass);

            // 1st step -> we get the properties from the constructor and instanciate it
            if ($oReflectionClass->hasMethod('__construct')) {
                $aArgs = [];

                foreach ($oReflectionClass->getMethod('__construct')->getParameters() as $oParameter) {
                    $parameterName = $oParameter->getName();

                    // Verifies the type of the constructor preventing TypeError from PHP
                    if (property_exists($json, $parameterName)) {
                        if (!self::isTypeValid($oParameter, $json->$parameterName)) {
                            throw new Exception("Parameter '$parameterName' has the the wrong type from JSON.");
                        }

                        $aArgs[] = $json->$parameterName;

                        continue;
                    }

                    // Property doesn't exist from JSON and is required by the constructor -> exception
                    if (!$oParameter->isOptional()) {
                        throw new Exception("Required parameter '$parameterName' is not found from JSON.");
                    }

                    $aArgs[] = $oParameter->getDefaultValue();
                }

                return $oReflectionClass->newInstanceArgs($aArgs);
            }
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Checks if the type of a parameter correlates from one value
     *
     * @todo Move to a trait after other data mappers are done
     */
    private static function isTypeValid(ReflectionParameter $parameter, mixed $value): bool
    {
        // No type specified -> the value can be of any type
        if (!$oType = $parameter->getType()) {
            return true;
        } elseif ($oType->allowsNull() && $value === null) {
            return true;
        }

        $typeName = $oType->getName();

        // Correlation with gettype/built-in type function (todo: false/true)
        return match ($typeName) {
            'bool', 'int', 'float' => "\is_$typeName"($value),
            default                => gettype($value) === $typeName
        };
    }
}
