<?php

namespace RayanLevert\ObjectMapper;

use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;

use function json_decode;
use function is_string;
use function class_exists;
use function json_last_error_msg;
use function property_exists;
use function ucfirst;

/**
 * Object mapping class from different sources of data using Reflection
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

        return self::stdClass('JSON', $json, $mappedClass);
    }

    /**
     * Maps an object from a stdClass (PHP typecasting to object)
     *
     * @throws Exception If data is missing for the mapped class
     */
    public static function fromStdClass(stdClass $class, string|object $mappedClass): object
    {
        return self::stdClass('stdClass', $class, $mappedClass);
    }

    /**
     * Maps an object from an array
     *
     * @param array<string, mixed> $array Array data
     *
     * @throws Exception If data is missing for the mapped class
     */
    public static function fromArray(array $array, string|object $mappedClass): object
    {
        return self::stdClass('array', (object) $array, $mappedClass);
    }

    /**
     * Maps an object from a stdClass (PHP typecasting to object)
     *
     * @throws Exception If data is missing for the mapped class
     */
    private static function stdClass(string $typeSource, stdClass $class, string|object $mappedClass): object
    {
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
                if (property_exists($class, $parameterName)) {
                    if (!self::isTypeValid($oParameter, $class->$parameterName)) {
                        throw new Exception("Parameter '$parameterName' has the the wrong type from $typeSource.");
                    }

                    $aArgs[$oParameter->getName()] = $class->$parameterName;

                    continue;
                }

                // Property doesn't exist from JSON and is required by the constructor -> exception
                if (!$oParameter->isOptional()) {
                    throw new Exception("Required parameter '$parameterName' is not found from $typeSource.");
                }

                $aArgs[] = $oParameter->getDefaultValue();
            }
        }

        $instance = $oReflectionClass->newInstanceArgs($aArgs);

        // 2nd step we check for setters after the constructor
        foreach ($oReflectionClass->getProperties() as $oProperty) {
            $parameterName   = self::getPropertyName($oProperty);
            $phpPropertyName = $oProperty->getName();

            if (!property_exists($class, $parameterName)) {
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
            } elseif (!self::isTypeValid($oSetter->getParameters()[0], $class->$parameterName)) {
                throw new Exception(
                    'Setter method set' . ucfirst($phpPropertyName)
                        . ' has incorrect argument type for its property ' . $parameterName
                );
            }

            $instance->{$setterName}($class->$parameterName);
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

        if ($oType instanceof ReflectionNamedType) {
            return self::assertType($oType->getName(), $value);
        }

        if ($oType instanceof ReflectionUnionType) {
            foreach ($oType->getTypes() as $oType) {
                if (self::assertType($oType->getName(), $value)) {
                    return true;
                }
            }

            return false;
        }

        throw new LogicException('Intersection types are not handled yet.');
    }

    /**
     * Verifies a value is of type (name from built-in argument type)
     */
    private static function assertType(string $typeName, mixed $value): bool
    {
        return match ($typeName) {
            'bool', 'int', 'float' => "\is_$typeName"($value),
            'false'                => $value === false,
            'true'                 => $value === true,
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
