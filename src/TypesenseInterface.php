<?php

namespace Typesense\Bundle;

/**
 *
 */
interface TypesenseInterface
{
    public function __typesense(): ?string;

    public function __typesenseGetter(string $propertyName, array $propertyInfo): mixed;
}
