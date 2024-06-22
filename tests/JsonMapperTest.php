<?php

namespace RayanLevert\ObjectMapper\Tests;

use RayanLevert\ObjectMapper\ObjectMapper;
use ReflectionClass;
use ReflectionProperty;

class JsonMapperTest extends \PHPUnit\Framework\TestCase
{
    public function testOneOptionalNotInJsonConstructor(): void
    {
        $o = new class()
        {
            public function __construct(protected ?int $id = null)
            {
            }
        };

        $oMapped = ObjectMapper::fromJSON('{}', $o::class);

        $this->assertInstanceOf($o::class, $oMapped);
        $this->assertNull((new ReflectionProperty($oMapped, 'id'))->getValue($oMapped));
    }

    public function testOneOptionalButInJsonConstructor(): void
    {
        $o = new class()
        {
            public function __construct(protected int $id = 10)
            {
            }
        };

        $oMapped = ObjectMapper::fromJSON('{"id": 11}', $o::class);

        $this->assertInstanceOf($o::class, $oMapped);
        $this->assertSame(11, (new ReflectionProperty($oMapped, 'id'))->getValue($oMapped));
    }

    public function testOneOptionalButInJsonNoTypeConstructor(): void
    {
        $o = new class()
        {
            public function __construct(protected $id = 10)
            {
            }
        };

        $oMapped = ObjectMapper::fromJSON('{"id": "testValue"}', $o::class);

        $this->assertInstanceOf($o::class, $oMapped);
        $this->assertSame("testValue", (new ReflectionProperty($oMapped, 'id'))->getValue($oMapped));
    }

    public function testOneOptionalNotInJsonNoTypeConstructor(): void
    {
        $o = new class()
        {
            public function __construct(protected $id = 10)
            {
            }

            public function get(): string{}
        };

        $oMapped = ObjectMapper::fromJSON('{}', $o::class);

        $this->assertInstanceOf($o::class, $oMapped);
        $this->assertSame(10, (new ReflectionProperty($oMapped, 'id'))->getValue($oMapped));
    }

    public function testOneOptionalButInJsonWrongTypeConstructor(): void
    {
        $o = new class()
        {
            public function __construct(protected int $id = 10)
            {
            }
        };

        $this->expectExceptionMessage("Parameter 'id' has the the wrong type from JSON.");

        ObjectMapper::fromJSON('{"id": "valueTest"}', $o::class);
    }

    public function testOneRequiredNotInJsonConstructor(): void
    {
        $o = new class(1)
        {
            public function __construct(protected int $id)
            {
            }
        };

        $this->expectExceptionMessage("Required parameter 'id' is not found from JSON.");

        ObjectMapper::fromJSON('{"test": "valueTest"}', $o::class);
    }

    public function testTwoPropertiesSameNameAndType(): void
    {
        $o = new class('a', 1)
        {
            public function __construct(
                protected string $a,
                protected int $b
            ) {
            }
        };

        $oMapped = ObjectMapper::fromJSON('{"a": "valueA", "b": 11}', $o::class);

        $this->assertInstanceOf($o::class, $oMapped);

        $o = new ReflectionClass($oMapped);

        $this->assertSame('valueA', $o->getProperty('a')->getValue($oMapped));
        $this->assertSame(11, $o->getProperty('b')->getValue($oMapped));
    }
}
