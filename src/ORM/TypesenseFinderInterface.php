<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM;

interface TypesenseFinderInterface
{
    public function className():string;

    public function query(Request $query, bool $cacheable = false): Response;
    public function raw(Request $query): Response;

    public function facet(string $facetBy, ?Request $query = null): mixed;
}
