<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Typesense\Bundle\DBAL\Connection;
use Typesense\Client;

/**
 * Exercises the real typesense-php client against a real server (see
 * docker-compose.test.yml / TYPESENSE_URL+TYPESENSE_KEY env vars) — no
 * mocks. This is deliberately kept below the bundle's own Connection/
 * TypesenseCollection layer so a failure here always means "the real
 * Typesense wire protocol changed or the server is unreachable", not "a
 * bundle abstraction broke" (that's what the Unit/ suite is for).
 */
class DocumentRoundTripTest extends TestCase
{
    private Client $client;
    private string $collectionName;

    protected function setUp(): void
    {
        $url = getenv('TYPESENSE_URL') ?: 'http://localhost:8108';
        $parts = parse_url($url);

        $this->client = new Client([
            'nodes' => [[
                'host' => $parts['host'],
                'port' => $parts['port'] ?? 8108,
                'protocol' => $parts['scheme'] ?? 'http',
            ]],
            'api_key' => getenv('TYPESENSE_KEY') ?: 'xyz',
            'connection_timeout_seconds' => 2,
        ]);

        try {
            $this->client->getHealth()->retrieve();
        } catch (\Throwable $e) {
            $this->markTestSkipped('No reachable Typesense server at ' . $url . ': ' . $e->getMessage());
        }

        $this->collectionName = 'typesense_bundle_test_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        try {
            $this->client->getCollections()[$this->collectionName]?->delete();
        } catch (\Throwable $e) {
            // Already gone / never created — fine.
        }
    }

    public function testCreateIndexSearchAndDeleteADocument(): void
    {
        $this->client->getCollections()->create([
            'name' => $this->collectionName,
            'fields' => [
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'category', 'type' => 'string', 'facet' => true],
            ],
            'default_sorting_field' => '',
        ]);

        $collection = $this->client->getCollections()[$this->collectionName];
        $collection->documents->create(['id' => '1', 'title' => 'Wireless mouse', 'category' => 'electronics']);
        $collection->documents->create(['id' => '2', 'title' => 'Wired keyboard', 'category' => 'electronics']);

        $results = $collection->documents->search([
            'q' => 'wireless',
            'query_by' => 'title',
        ]);

        $this->assertSame(1, $results['found']);
        $this->assertSame('1', $results['hits'][0]['document']['id']);

        $collection->documents['1']->delete();

        $resultsAfterDelete = $collection->documents->search(['q' => 'wireless', 'query_by' => 'title']);
        $this->assertSame(0, $resultsAfterDelete['found']);
    }

    public function testHealthCheck(): void
    {
        $this->assertTrue($this->client->getHealth()->retrieve()['ok']);
    }
}
