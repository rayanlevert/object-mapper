<?php

namespace RayanLevert\ObjectMapper;

use Attribute;

/**
 * Retrieves the property name if the class' one is different from the data source
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Property
{
    public function __construct(protected string $propertyName)
    {
    }
}
