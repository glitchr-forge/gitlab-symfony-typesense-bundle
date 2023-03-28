<?php

declare(strict_types=1);

namespace Typesense\Bundle\Manager;

use Doctrine\ORM\ObjectManager;
use Doctrine\ORM\ObjectManagerInterface;
use Typesense\Bundle\Client\CollectionClient;
use Typesense\Bundle\Client\TypesenseClient;
use Typesense\Bundle\Transformer\AbstractTransformer;

class Transaction
{
    protected $collectionDefinitions;
    protected $collectionClient;
    protected $doctrineTransformer;

    protected $client;
    protected $typesenseManager;
    /**
     * @var ObjectManagerInterface
     */

    public function __construct(DocumentManager $document)
    {
        $this->client                = $client;
        $this->collectionClient      = $this->client->getCollectionClient();

        $this->typesenseManager = $typesenseManager;
    }

    public function getCollectionDefinitions(): array
    {
        return $this->collectionDefinitions;
    }

    public function getCollectionClient(): CollectionClient
    {
        return $this->collectionClient;
    }

    public function getManagedClassNames()
    {
        $managedClassNames = [];
        foreach ($this->collectionDefinitions as $name => $collectionDefinition) {
            $collectionName = $collectionDefinition['name'] ?? $name;
            $managedClassNames[$collectionName] = $collectionDefinition['entity'];
        }

        return $managedClassNames;
    }

    public function getCollection($collectionDefinitionName)
    {
        $list = $this->getAllCollections();
        dump($list, $collectionDefinitionName);
        exit(1);
    }

    public function getAllCollections()
    {
        return $this->collectionClient->list();
    }

    public function createAllCollections()
    {
        foreach ($this->collectionDefinitions as $name => $collectionDefinition) {
            $this->createCollection($name);
        }
    }

    public function deleteCollection($collectionDefinitionName)
    {
        $definition = $this->collectionDefinitions[$collectionDefinitionName];

        $this->collectionClient->delete($definition['name']);
    }

    public function createCollection($collectionDefinitionName)
    {
        $definition       = $this->collectionDefinitions[$collectionDefinitionName];
        $fieldDefinitions = $definition['fields'];
        $fields           = [];

        if($this->typesenseManager->getDoctrineTransformer($this->client->getConnectionName()) == null) return;

        foreach ($fieldDefinitions as $key => $fieldDefinition) {

            $fieldDefinition['type'] = $this->typesenseManager->getDoctrineTransformer($this->client->getConnectionName())->castType($fieldDefinition['type']);
            $fieldDefinition["name"] ??= $key;
            $fields[]                = $fieldDefinition;

        }

        //to pass the tests
        $tokenSeparators = array_key_exists('token_separators', $definition) ? $definition['token_separators'] : [];
        $symbolsToIndex  = array_key_exists('symbols_to_index', $definition) ? $definition['symbols_to_index'] : [];

        $this->collectionClient->create(
            $definition['name'],
            $fields,
            $definition['default_sorting_field'] ?? null,
            $tokenSeparators,
            $symbolsToIndex
        );
    }
}
