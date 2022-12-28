<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Finder;

interface CollectionFinderInterface
{
    public function rawQuery(TypesenseQuery $query);

    public function query(TypesenseQuery $query);
}
