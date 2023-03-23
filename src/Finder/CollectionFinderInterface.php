<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Finder;

interface CollectionFinderInterface
{
    public function query(TypesenseQuery $query, bool $cacheable = false): TypesenseResponse;
    public function rawQuery(TypesenseQuery $query): TypesenseResponse;

    public function facet(string $facetBy, ?TypesenseQuery $query = null): mixed;
}
