<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Query;

/**
 * Minimum needed to send a request to typesense server
 */
class Request
{
    private array $headers = [];

    public const TERM = 'q';
    public const QUERY_BY = 'query_by';

    public function __construct(array|string $queryBy, ?string $term = null)
    {
        if(is_array($queryBy)) { $queryBy = implode(", ", (array) $queryBy); }
        $this->addHeader(self::QUERY_BY, $queryBy);
        $this->addHeader(self::TERM, $term ?? '');
    }
 
    public function getFields(): array
    {
        return explode(',', $this->getHeader(self::QUERY_BY) ?? '');
    }

    public function getNumFields(): int
    {
        return count($this->getFields());
    }
    
    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Headers aren't all strings — Query's own setters store ints
     * (maxHits, page, perPage, ...) and bools (prefix) through the same
     * addHeader() this reads back from.
     *
     * @param $key
     * @return mixed
     */
    public function getHeader(string $key): mixed
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

    public function queryBy(string $queryBy): self
    {
        return $this->addHeader(self::QUERY_BY, $queryBy);
    }

    public function addQueryBy(string $queryBy): self
    {
        $_queryBy = $this->getHeader(self::QUERY_BY);
        $queryBy = $_queryBy ? trim($_queryBy . ', ' . $queryBy) : $queryBy;

        return $this->addHeader(self::QUERY_BY, $queryBy);
    }
}
