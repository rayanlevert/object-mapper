<?php

namespace RayanLevert\ObjectMapper;

use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;

use function json_decode;
use function is_string;
use function class_exists;
use function json_last_error_msg;
use function property_exists;
use function ucfirst;

/**
 * Object mapping class from different sources of data
 *
 * Has only static methods trying to map data to a PHP user defined class
 */
class ObjectMapper
{
    /** Exception code when a JSON string could not been decoded */
    public const int JSON_INVALID = 1;

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

            throw new Exception(
                'JSON data could not have been decoded' . ($error ? " ($error)" : '') . '.',
                self::JSON_INVALID
            );
        }

        try {
            $oReflectionClass = new ReflectionClass($mappedClass);
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        $aArgs = [];

        // 1st step -> we get the properties from the constructor and instanciate it
        if ($oReflectionClass->hasMethod('__construct')) {
            foreach ($oReflectionClass->getMethod('__construct')->getParameters() as $oParameter) {
                $parameterName = self::getPropertyName($oParameter);

                // Verifies the type of the constructor preventing TypeError from PHP
                if (property_exists($json, $parameterName)) {
                    if (!self::isTypeValid($oParameter, $json->$parameterName)) {
                        throw new Exception("Parameter '$parameterName' has the the wrong type from JSON.");
                    }

                    $aArgs[$oParameter->getName()] = $json->$parameterName;

                    continue;
                }

                // Property doesn't exist from JSON and is required by the constructor -> exception
                if (!$oParameter->isOptional()) {
                    throw new Exception("Required parameter '$parameterName' is not found from JSON.");
                }

                $aArgs[] = $oParameter->getDefaultValue();
            }
        }

        $instance = $oReflectionClass->newInstanceArgs($aArgs);

        // 2nd step we check for setters after the constructor
        foreach ($oReflectionClass->getProperties() as $oProperty) {
            $parameterName   = self::getPropertyName($oProperty);
            $phpPropertyName = $oProperty->getName();

            if (!property_exists($json, $parameterName)) {
                continue;
            } elseif (isset($aArgs[$phpPropertyName])) {
                // Skips already handled constructor arguments
                continue;
            }

            $setterName = 'set' . ucfirst($phpPropertyName);

            if (!$oReflectionClass->hasMethod($setterName)) {
                continue;
            }

            $oSetter = $oReflectionClass->getMethod($setterName);

            if ($oSetter->getNumberOfParameters() < 1) {
                continue;
            } elseif (!self::isTypeValid($oSetter->getParameters()[0], $json->$parameterName)) {
                throw new Exception(
                    'Setter method set' . ucfirst($phpPropertyName)
                        . ' has incorrect argument type for its property ' . $parameterName
                );
            }

            $instance->{$setterName}($json->$parameterName);
        }

        return $instance;
    }

    /**
     * Checks if the type of a parameter correlates from one value
     *
     * @todo Move to a trait after other data mappers are done
     */
    private static function isTypeValid(ReflectionParameter|ReflectionProperty $parameter, mixed $value): bool
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

    /**
     * Returns either the class property name, or if an attribute Property is found, from it
     */
    private static function getPropertyName(ReflectionParameter|ReflectionProperty $parameter): string
    {
        // Argument from a parameter, if not promoted we retrieve the PHP property
        if ($parameter instanceof ReflectionParameter && !$parameter->isPromoted()) {
            if (!$parameter->getDeclaringClass()->hasProperty($parameter->getName())) {
                throw new Exception(
                    "Argument name {$parameter->getName()} does not have its property"
                );
            }

            $parameter = $parameter->getDeclaringClass()->getProperty($parameter->getName());
        }

        $oAttribute = $parameter->getAttributes(Property::class)[0] ?? null;

        return $oAttribute?->getArguments()[0] ?: $parameter->getName();
    }
}
