<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM;

use Typesense\Bundle\ORM\Query\Request;
use Typesense\Bundle\ORM\Query\Response;

interface TypesenseFinderInterface
{
    public function name():string;

    public function query(Request $query, bool $cacheable = false): Response;
    public function raw(Request $query): Response;

    public function facet(string $facetBy, ?Request $query = null): mixed;
}
