<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Query;

use Symfony\Component\ErrorHandler\Error\ClassNotFoundError;

class Request
{
    private array $headers = [];

    public const TERM = "q";
    public const QUERY_BY = "query_by";

    public function q(string $q): self { return $this->addHeader(self::TERM, $q); }
    public function term(string $q): self
    {
        return $this->addHeader(self::TERM, $q);
    }

    public function queryBy(string $filterBy): self { return $this->addHeader(self::TERM, $filterBy); }
    public function addQueryBy(string $filterBy): self
    {
        $_filterBy = $this->getHeader("query_by");
        $filterBy = $_filterBy ? trim($_filterBy . ", " . $filterBy) : $filterBy;

        return $this->addHeader('query_by', $filterBy);
    }

    public function __construct(string $queryBy, string $term = "")
    {
        $this->addHeader(self::QUERY_BY, $queryBy);
        $this->addHeader(self::TERM, $term);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $key): ?string
    {
        return $this->headers[$key] ?? null;
    }

    public function hasHeader(string $key): bool
    {
        return isset($this->headers[$key]);
    }

    public function addHeader($key, $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }
}
