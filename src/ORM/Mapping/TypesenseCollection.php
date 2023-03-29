<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Mapping;

use Doctrine\Persistence\ObjectManager;
use Typesense\Bundle\Client\Connection;
use Typesense\Bundle\ORM\Query;
use Typesense\Bundle\DBAL\TypesenseManager;

class TypesenseCollection
{
    protected $connection;
    protected $documents;

    protected TypesenseManager $metadata;
    public function __construct(string $name, Connection $connection, TypesenseManager $metadata)
    {
        $this->connection = $connection;

        $this->documents  = new TypesenseDocument($connection);
    }

    //
    // Metadata instances
    public function setMetadata(string $collectionName): ?TypesenseMetadata { $this->getCollection($collectionName)?->getMetadata(); }

    public function search(Request $query)
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        return $this->connection->getCollection($collectionName)->documents->search($query->getParameters());
    }

    public function multiSearch(array $searchRequests, ?Request $commonSearchParams)
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        $searches = [];
        foreach ($searchRequests as $sr) {
            if (!$sr instanceof Query) {
                throw new \Exception('searchRequests must be an array  of Request objects');
            }
            if (!$sr->hasParameter('collection')) {
                throw new \Exception('Request must have the key : `collection` in order to perform multiSearch');
            }
            $searches[] = $sr->getParameters();
        }

        return $this->connection->multiSearch->perform(
            [
                'searches' => $searches,
            ],
            $commonSearchParams ? $commonSearchParams->getParameters() : []
        );
    }

    public function list()
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        return $this->connection->collections->retrieve();
    }

    public function create($name, $fields, $defaultSortingField, array $tokenSeparators, array $symbolsToIndex)
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        $collectionDefinition = [];
        $collectionDefinition["name"]                  = $name;
        $collectionDefinition["fields"]                = $fields;

        if ($defaultSortingField)
            $collectionDefinition["default_sorting_field"] = $defaultSortingField;

        if ($tokenSeparators)
            $collectionDefinition["token_separators"]      = $tokenSeparators;

        if ($symbolsToIndex)
            $collectionDefinition["symbols_to_index"]      = $symbolsToIndex;

        if($fields)
            $this->connection->collections->create($collectionDefinition);
    }

    public function delete(string $name)
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        return $this->connection->collections[$name]->delete();
    }
}
