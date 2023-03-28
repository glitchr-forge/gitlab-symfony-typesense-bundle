<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM;

interface CollectionFinderInterface
{
    public function facet(string $facetBy, ?TypesenseQuery $query = null): mixed;

    public function query(TypesenseQuery $query, bool $cacheable = false): TypesenseResponse;

    public function rawQuery(TypesenseQuery $query): TypesenseResponse;
}
