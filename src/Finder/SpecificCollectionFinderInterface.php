<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Finder;

interface SpecificCollectionFinderInterface
{
    public function search($query, $queryBy);
}
