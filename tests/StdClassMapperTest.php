<?php

namespace RayanLevert\ObjectMapper\Tests;

use RayanLevert\ObjectMapper\ObjectMapper;
use RayanLevert\ObjectMapper\Property;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

class StdClassMapperTest extends \PHPUnit\Framework\TestCase
{
    public function testNoConstructNoProperty(): void
    {
        $o = new class()
        {
        };

        $oMapped = ObjectMapper::fromStdClass(new stdClass(), $o::class);

        $this->assertInstanceOf($o::class, $oMapped);
    }

    public function testNoConstructOneRequiredProperty(): void
    {
        $o = new class()
        {
            protected int $id;
        };

        $oMapped = ObjectMapper::fromStdClass((object) ['id' => 11], $o::class);

        $this->assertInstanceOf($o::class, $oMapped);
        $this->assertFalse((new ReflectionProperty($oMapped, 'id'))->isInitialized($oMapped));
    }

    public function testOneOptionalNotInConstructor(): void
    {
        $o = new class()
        {
            public function __construct(protected ?int $id = null)
            {
            }
        };

        $oMapped = ObjectMapper::fromStdClass(new stdClass(), $o::class);

        $this->assertInstanceOf($o::class, $oMapped);
        $this->assertNull((new ReflectionProperty($oMapped, 'id'))->getValue($oMapped));
    }

    public function testOneOptionalButInConstructor(): void
    {
        $o = new class()
        {
            public function __construct(protected int $id = 10)
            {
            }
        };

        $oMapped = ObjectMapper::fromStdClass((object) ['id' => 11], $o::class);

        $this->assertInstanceOf($o::class, $oMapped);
        $this->assertSame(11, (new ReflectionProperty($oMapped, 'id'))->getValue($oMapped));
    }

    public function testOneOptionalButInNoTypeConstructor(): void
    {
        $o = new class()
        {
            public function __construct(protected $id = 10)
            {
            }
        };

        $oMapped = ObjectMapper::fromStdClass((object) ['id' => 'testValue'], $o::class);

        $this->assertInstanceOf($o::class, $oMapped);
        $this->assertSame("testValue", (new ReflectionProperty($oMapped, 'id'))->getValue($oMapped));
    }

    public function testOneOptionalNotInNoTypeConstructor(): void
    {
        $o = new class()
        {
            public function __construct(protected $id = 10)
            {
            }
        };

        $oMapped = ObjectMapper::fromStdClass(new stdClass(), $o::class);

        $this->assertInstanceOf($o::class, $oMapped);
        $this->assertSame(10, (new ReflectionProperty($oMapped, 'id'))->getValue($oMapped));
    }

    public function testOneOptionalButInWrongTypeConstructor(): void
    {
        $o = new class()
        {
            public function __construct(protected int $id = 10)
            {
            }
        };

        $this->expectExceptionMessage("Parameter 'id' has the the wrong type from stdClass.");

        ObjectMapper::fromStdClass((object) ['id' => 'valueTest'], $o::class);
    }

    public function testOneRequiredNotInConstructor(): void
    {
        $o = new class(1)
        {
            public function __construct(protected int $id)
            {
            }
        };

        $this->expectExceptionMessage("Required parameter 'id' is not found from stdClass.");

        ObjectMapper::fromStdClass((object) ['test' => 'valueTest'], $o::class);
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

        $oMapped = ObjectMapper::fromStdClass((object) ['a' => 'valueA', 'b' => 11], $o::class);

        $this->assertInstanceOf($o::class, $oMapped);

        $o = new ReflectionClass($oMapped);

        $this->assertSame('valueA', $o->getProperty('a')->getValue($oMapped));
        $this->assertSame(11, $o->getProperty('b')->getValue($oMapped));
    }

    public function testOnePropertyWithSetterWrongType(): void
    {
        $o = new class('a')
        {
            protected string $a = '';

            public function __construct()
            {
            }

            public function setA(int $value): void
            {
                $this->a = $value;
            }
        };

        $this->expectExceptionMessage('Setter method setA has incorrect argument type for its property a');

        ObjectMapper::fromStdClass((object) ['a' => 'valueA', 'b' => 11], $o::class);
    }

    public function testOnePropertyWithSetterNotConstruct(): void
    {
        $o = new class('a')
        {
            protected string $a = '';

            public function __construct()
            {
            }

            public function setA(string $value): void
            {
                $this->a = $value;
            }

            public function getA(): string
            {
                return $this->a;
            }
        };

        $o = ObjectMapper::fromStdClass((object) ['a' => 'valueA', 'b' => 11], $o::class);

        $this->assertInstanceOf($o::class, $o);
        $this->assertSame('valueA', $o->getA());
    }

    public function testOnePropertyWithSetterConstruct(): void
    {
        $o = new class('a')
        {
            public function __construct(protected string $a = '')
            {
            }

            public function setA(string $value): void
            {
                $this->a = 'test';
            }

            public function getA(): string
            {
                return $this->a;
            }
        };

        $o = ObjectMapper::fromStdClass((object) ['a' => 'valueA', 'b' => 11], $o::class);

        $this->assertInstanceOf($o::class, $o);
        $this->assertSame('valueA', $o->getA());
    }

    public function testOnePropertyFromAttributeNoIn(): void
    {
        $o = new class(1)
        {
            public function __construct(#[Property('value_type')] protected string $valueType)
            {
            }
        };

        $this->expectExceptionMessage('Required parameter \'value_type\' is not found from stdClass');

        ObjectMapper::fromStdClass((object) ['valueType' => 'value'], $o::class);
    }

    public function testOnePropertyPromotedFromAttributeIn(): void
    {
        $o = new class(1)
        {
            public function __construct(#[Property('value_type')] protected string $valueType)
            {
            }
        };

        $o = ObjectMapper::fromStdClass((object) ['value_type' => 'value'], $o::class);

        $this->assertSame('value', (new ReflectionProperty($o, 'valueType'))->getValue($o));
    }

    public function testOnePropertyFromAttributeIn(): void
    {
        $o = new class(1)
        {
            #[Property('value_type')]
            protected string $valueType;

            public function __construct(string $valueType)
            {
                $this->valueType = $valueType;
            }
        };

        $o = ObjectMapper::fromStdClass((object) ['value_type' => 'value'], $o::class);

        $this->assertSame('value', (new ReflectionProperty($o, 'valueType'))->getValue($o));
    }

    public function testOnePropertyFromAttributeInParameterNotSameTypo(): void
    {
        $o = new class(1)
        {
            #[Property('value_type')]
            protected string $valueType;

            public function __construct(string $valuetype)
            {
                $this->valueType = $valuetype;
            }
        };

        $this->expectExceptionMessage('Argument name valuetype does not have its property');

        ObjectMapper::fromStdClass((object) ['value_type' => 'value'], $o::class);
    }

    public function testOnePropertyFromAttributeInSetter(): void
    {
        $o = new class(1)
        {
            #[Property('identifiant')]
            protected int $id = 0;

            public function __construct(#[Property('value_type')] protected string $valueType)
            {
            }

            public function setId(int $id): self
            {
                $this->id = $id;

                return $this;
            }
        };

        $o = ObjectMapper::fromStdClass((object) ['value_type' => 'value', 'identifiant' => 1], $o::class);

        $this->assertSame('value', (new ReflectionProperty($o, 'valueType'))->getValue($o));
        $this->assertSame(1, (new ReflectionProperty($o, 'id'))->getValue($o));
    }

    public function testNoConstructOneFalseTypeNotFalse(): void
    {
        $o = new class(false)
        {
            public function __construct(protected false $id)
            {
            }
        };

        $this->expectExceptionMessage("Parameter 'id' has the the wrong type from stdClass.");

        ObjectMapper::fromStdClass((object) ['id' => true], $o::class);
    }

    public function testNoConstructOneFalseTypeFalse(): void
    {
        $o = new class(false)
        {
            public function __construct(protected false $id)
            {
            }
        };

        $o = ObjectMapper::fromStdClass((object) ['id' => false], $o::class);

        $this->assertFalse((new ReflectionProperty($o, 'id'))->getValue($o));
    }

    public function testNoConstructOneTrueTypeTrue(): void
    {
        $o = new class(true)
        {
            public function __construct(protected true $id)
            {
            }
        };

        $o = ObjectMapper::fromStdClass((object) ['id' => true], $o::class);

        $this->assertTrue((new ReflectionProperty($o, 'id'))->getValue($o));
    }

    public function testUnionType(): void
    {
        $o = new class('identfiant')
        {
            public function __construct(protected string|int $id)
            {
            }
        };

        $o = ObjectMapper::fromStdClass((object) ['id' => 'string-value'], $o::class);
        $this->assertSame('string-value', (new ReflectionProperty($o, 'id'))->getValue($o));

        $o = ObjectMapper::fromStdClass((object) ['id' => 20], $o::class);
        $this->assertSame(20, (new ReflectionProperty($o, 'id'))->getValue($o));

        $this->expectExceptionMessage("Parameter 'id' has the the wrong type from stdClass.");

        ObjectMapper::fromStdClass((object) ['id' => 89.0], $o::class);
    }
}
