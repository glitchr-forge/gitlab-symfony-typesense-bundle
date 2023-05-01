<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM;

use Typesense\Bundle\ORM\Mapping\Collection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;

interface TypesenseManagerInterface
{
    public function getCollection(string $collectionName, ?string $connectionName = null): Collection;

    public function getFinder(string $collectionName, ?string $connectionName = null): ?TypesenseFinder;

    public function getMetadata(string $className, ?string $connectionName = null): ?TypesenseMetadata;
}
