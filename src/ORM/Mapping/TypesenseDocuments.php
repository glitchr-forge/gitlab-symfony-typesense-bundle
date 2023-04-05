<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Mapping;

use Typesense\Bundle\DBAL\Connection;
use Typesense\Client;

class TypesenseDocuments
{
    private ?Connection $connection;
    protected TypesenseMetadata $metadata;

    public function __construct(TypesenseMetadata $metadata, Connection $connection)
    {
        $this->metadata = $metadata;
        $this->connection = $connection;
    }

    public function connection() { return $this->connection->getClient(); }
    public function delete(string|int $id): ?array
    {
        if (!$this->connection?->isConnected()) {
            return null;
        }

        $documents = $this->connection?->getCollections()[$this->metadata->getName()]->documents;
        return $documents[$id]?->delete();
    }

    public function create($data): ?array
    {
        if (!$this->connection?->isConnected()) {
            return null;
        }

        $collectionName = $this->metadata->getName();
        $collection = $this->connection?->getCollections()[$collectionName];
        $documents = $collection->documents;

        return $documents?->create($data);
    }

    public function update($data): ?array
    {
        if (!$this->connection?->isConnected()) {
            return null;
        }

        $collectionName = $this->metadata->getName();
        $collection = $this->connection?->getCollections()[$collectionName];
        $documents = $collection->documents;

        return $documents?->update($data);
    }

    public function search(array $searchParams): ?array
    {
        if (!$this->connection?->isConnected()) {
            return null;
        }

        $collectionName = $this->metadata->getName();
        $collection = $this->connection?->getCollections()[$collectionName];
        $documents = $collection->documents;

        return $documents?->search($searchParams);
    }

    public function import(array $data, string $action = 'create'): null|array|string
    {
        if (!$this->connection->isConnected() || empty($data)) {
            return [];
        }

        $collectionName = $this->metadata->getName();
        $collection = $this->connection?->getCollections()[$collectionName];
        $documents = $collection->documents;

        return $documents?->import($data, ['action' => $action]);
    }
}
