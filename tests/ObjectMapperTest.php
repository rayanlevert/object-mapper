<?php

namespace RayanLevert\ObjectMapper\Tests;

use RayanLevert\ObjectMapper\ObjectMapper;

class ObjectMapperTest extends \PHPUnit\Framework\TestCase
{
    public function test(): void
    {
        $o = new class
        {
            protected string $a;
        };

        ObjectMapper::fromJSON(json_encode(['test' => 'test']), $o);
    }
}
