<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Unit\ORM\Query;

use PHPUnit\Framework\TestCase;
use Typesense\Bundle\ORM\Query\Response;

class ResponseTest extends TestCase
{
    private function makeResult(): array
    {
        return [
            'found' => 2,
            'page' => 1,
            'search_time_ms' => 3,
            'hits' => [
                ['document' => ['id' => '1', 'title' => 'Shoe A']],
                ['document' => ['id' => '2', 'title' => 'Shoe B']],
            ],
            'facet_counts' => [
                [
                    'field_name' => 'category',
                    'counts' => [
                        ['value' => 'sneakers', 'count' => 5],
                        ['value' => 'boots', 'count' => 2],
                    ],
                ],
            ],
        ];
    }

    public function testGettersReadFromTheRawResult(): void
    {
        $response = new Response($this->makeResult());

        $this->assertSame(2, $response->getFound());
        $this->assertSame(1, $response->getPage());
        $this->assertCount(2, $response->getHits());
        $this->assertSame($response->getHits(), $response->getRawResults());
    }

    public function testGetResultsReturnsRawHitsWhenNotHydrated(): void
    {
        $response = new Response($this->makeResult());

        $this->assertSame($response->getRawResults(), $response->getResults());
    }

    public function testGetResultsReturnsHydratedHitsAfterSetHydrated(): void
    {
        $response = new Response($this->makeResult());
        $hydrated = ['entity-a', 'entity-b'];
        $response->setHydratedHits($hydrated)->setHydrated(true);

        $this->assertSame($hydrated, $response->getResults());
    }

    public function testGetHitLooksUpByHydratedHitIndex(): void
    {
        $response = new Response($this->makeResult());
        $entityA = new \stdClass();
        $entityB = new \stdClass();
        $response->setHydratedHits([$entityA, $entityB]);

        $this->assertSame(['document' => ['id' => '2', 'title' => 'Shoe B']], $response->getHit($entityB));
    }

    public function testGetHitReturnsNullForAnUnknownHydratedHit(): void
    {
        $response = new Response($this->makeResult());
        $response->setHydratedHits([new \stdClass()]);

        $this->assertNull($response->getHit(new \stdClass()));
    }

    public function testGetStatusUsesTheExplicitStatusWhenGiven(): void
    {
        $response = new Response(null, 404);

        $this->assertSame(404, $response->getStatus());
    }

    /**
     * getStatus() only infers a status when the *constructor* was given an
     * explicit status of 0 (the constructor's own default is 200, not 0 —
     * status must be passed explicitly to exercise the inference branch at
     * all): 200 if results were found, else 500 if there's error content,
     * else 404.
     */
    public function testGetStatusInfersOkWhenFound(): void
    {
        $response = new Response(['found' => 1], 0);

        $this->assertSame(200, $response->getStatus());
    }

    public function testGetStatusInfersServerErrorWhenNotFoundButThereIsContent(): void
    {
        $response = new Response(['found' => 0], 0, [Response::MESSAGE => 'boom']);

        $this->assertSame(500, $response->getStatus());
    }

    public function testGetStatusInfersNotFoundWhenNothingFoundAndNoContent(): void
    {
        $response = new Response(null, 0);

        $this->assertSame(404, $response->getStatus());
    }

    public function testGetContentReadsTheMessageHeader(): void
    {
        $response = new Response(null, 500, [Response::MESSAGE => 'Something broke']);

        $this->assertSame('Something broke', $response->getContent());
    }

    public function testNullResultLeavesEverythingNull(): void
    {
        $response = new Response(null);

        $this->assertNull($response->getFound());
        $this->assertNull($response->getHits());
        $this->assertSame([], $response->getFacetCounts());
    }

    public function testGetFacetCountsMarksCheckedValuesFromTheCheckboxState(): void
    {
        $response = new Response($this->makeResult());

        $facetCounts = $response->getFacetCounts(['category' => ['sneakers']]);

        $this->assertTrue($facetCounts[0]['counts'][0]['checked']);
        $this->assertFalse($facetCounts[0]['counts'][1]['checked']);
    }

    public function testGetFacetCountsCanSortByNameInsteadOfFrequency(): void
    {
        $response = new Response($this->makeResult());

        $facetCounts = $response->getFacetCounts([], true);

        $this->assertSame('boots', $facetCounts[0]['counts'][0]['value']);
        $this->assertSame('sneakers', $facetCounts[0]['counts'][1]['value']);
    }

    public function testGetFacetCountsCanBeKeyedByFieldAndValueName(): void
    {
        $response = new Response($this->makeResult());

        $facetCounts = $response->getFacetCounts([], false, Response::FACETS_USE_KEY);

        $this->assertArrayHasKey('category', $facetCounts);
        $this->assertArrayHasKey('sneakers', $facetCounts['category']['counts']);
        $this->assertArrayHasKey('boots', $facetCounts['category']['counts']);
    }
}
