<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Finder;

interface CollectionFinderInterface
{
    public function rawQuery(TypesenseQuery $query): TypesenseResponse;

    public function query(TypesenseQuery $query, bool $cacheable = false): TypesenseResponse;
}
