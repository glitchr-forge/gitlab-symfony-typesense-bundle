<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Manager;

use Symfony\UX\Typesense\Client\TypesenseClient;

class DocumentManager
{
    private $client;

    public function __construct(TypesenseClient $client)
    {
        $this->client = $client;
    }

    public function delete($collection, $id)
    {
        if (!$this->client->isOperationnal()) {
            return null;
        }

        return $this->client->collections[$collection]->documents[$id]->delete();
    }

    public function index($collection, $data)
    {
        if (!$this->client->isOperationnal()) {
            return null;
        }

        return $this->client->collections[$collection]->documents->create($data);
    }

    public function update($collection, $data)
    {
        if (!$this->client->isOperationnal()) {
            return null;
        }

        return $this->client->collections[$collection]->documents->update($data);
    }

    public function import(string $collection, array $data, string $action = 'create')
    {
        if (!$this->client->isOperationnal() || empty($data)) {
            return [];
        }

        return $this->client->collections[$collection]->documents->import($data, ['action' => $action]);
    }
}
