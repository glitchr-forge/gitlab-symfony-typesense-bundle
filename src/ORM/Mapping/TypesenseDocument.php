<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Mapping;

use Typesense\Bundle\Client\Connection;

class TypesenseDocument
{
    private $client;

    public function __construct(Connection $client)
    {
        $this->client = $client;
    }

    public function delete($collection, $id)
    {
        if (!$this->client->isOperationnal()) {
            return null;
        }

        return $this->client->getCollection($collection)->documents[$id]->delete();
    }

    public function index($collection, $data)
    {
        if (!$this->client->isOperationnal()) {
            return null;
        }

        return $this->client->getCollection($collection)->documents->create($data);
    }

    public function update($collection, $data)
    {
        if (!$this->client->isOperationnal()) {
            return null;
        }

        return $this->client->getCollection($collection)->documents->update($data);
    }

    public function import(string $collection, array $data, string $action = 'create')
    {
        if (!$this->client->isOperationnal() || empty($data)) {
            return [];
        }

        return $this->client->getCollection($collection)->documents->import($data, ['action' => $action]);
    }
}
