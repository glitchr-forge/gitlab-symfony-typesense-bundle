<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Query;

/**
 *
 */
class Response
{
    protected mixed $facetCounts;
    protected mixed $found;
    protected mixed $hits;
    protected array $hydratedHits;
    protected bool $isHydrated;
    protected mixed $page;
    protected mixed $searchTimeMs;

    protected array $headers;
    protected int $status;

    public function __construct(?array $result, int $status = 200, array $headers = [])
    {
        $this->facetCounts = $result['facet_counts'] ?? null;
        $this->found = $result['found'] ?? null;
        $this->hits = $result['hits'] ?? null;
        $this->page = $result['page'] ?? null;
        $this->searchTimeMs = $result['search_time_ms'] ?? null;
        $this->isHydrated = false;
        $this->hydratedHits = [];

        $this->status = $status;
        $this->headers = $headers;
    }

    public function getStatus(): int
    {
        if (0 == $this->status) {
            return 200;
        }

        return $this->status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getContent(): string
    {
        return $this->headers['message'] ?? '';
    }

    /**
     * Get the value of facetCounts.
     */
    public function getFacetCounts()
    {
        return $this->facetCounts;
    }

    /**
     * Get the value of hits.
     */
    public function getResults()
    {
        if ($this->isHydrated) {
            return $this->hydratedHits;
        }

        return $this->hits;
    }

    /**
     * @return mixed|null
     */
    public function getRawResults()
    {
        return $this->hits;
    }

    /**
     * Get the value of page.
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Get total hits.
     */
    public function getFound()
    {
        return $this->found;
    }

    /**
     * Set the value of hydratedHits.
     */
    public function setHydratedHits($hydratedHits): self
    {
        $this->hydratedHits = $hydratedHits;

        return $this;
    }

    /**
     * Set the value of isHydrated.
     */
    public function setHydrated(bool $isHydrated): self
    {
        $this->isHydrated = $isHydrated;

        return $this;
    }
}
