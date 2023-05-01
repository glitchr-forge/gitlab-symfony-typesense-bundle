<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM;

use Typesense\Bundle\ORM\Query\Request;
use Typesense\Bundle\ORM\Query\Response;

interface TypesenseFinderInterface
{
    public function name(): string;

    public function query(Request $request, bool $cacheable = false): Response;

    public function raw(Request $request): Response;

    public function facet(string $facetBy, ?Request $query = null): mixed;
}
