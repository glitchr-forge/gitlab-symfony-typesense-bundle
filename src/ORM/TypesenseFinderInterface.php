<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM;

use Typesense\Bundle\ORM\Query\Query;
use Typesense\Bundle\ORM\Query\Request;
use Typesense\Bundle\ORM\Query\Response;

/**
 *
 */
interface TypesenseFinderInterface
{
    public const USE_KEY = 1;
    public const USE_INDEX = 0;
    
    public function name(): string;

    public function query(Request $request, bool $cacheable = false): Response;

    public function raw(Request $request): Response;

    public function facet(string $facetBy, ?Query $query = null): mixed;
}
