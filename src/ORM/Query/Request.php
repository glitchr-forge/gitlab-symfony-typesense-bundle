<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Query;

/**
 *
 */
class Request
{
    private array $headers = [];

    public const TERM = 'q';
    public const QUERY_BY = 'query_by';

    public function __construct(string $queryBy, ?string $term = null)
    {
        $this->addHeader(self::QUERY_BY, $queryBy);
        $this->addHeader(self::TERM, $term ?? '');
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param $key
     * @return ?string
     */
    public function getHeader(string $key): ?string
    {
        return $this->headers[$key] ?? null;
    }

    /**
     * @param $key
     * @return boolean
     */
    public function hasHeader(string $key): bool
    {
        return isset($this->headers[$key]);
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function addHeader($key, $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function q(string $q): self
    {
        return $this->addHeader(self::TERM, $q);
    }

    public function term(string $q): self
    {
        return $this->addHeader(self::TERM, $q);
    }

    public function queryBy(string $filterBy): self
    {
        return $this->addHeader(self::QUERY_BY, $filterBy);
    }

    public function addQueryBy(string $filterBy): self
    {
        $_filterBy = $this->getHeader(self::QUERY_BY);
        $filterBy = $_filterBy ? trim($_filterBy . ', ' . $filterBy) : $filterBy;

        return $this->addHeader(self::QUERY_BY, $filterBy);
    }
}
