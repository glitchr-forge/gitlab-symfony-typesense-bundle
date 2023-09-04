<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM;

use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;

/**
 *
 */
interface TypesenseManagerInterface
{
    public function getCollection(string $collectionName): ?TypesenseCollection;

    public function getFinder(string $collectionName): ?TypesenseFinder;

    public function getMetadata(string $className): ?TypesenseMetadata;
}
