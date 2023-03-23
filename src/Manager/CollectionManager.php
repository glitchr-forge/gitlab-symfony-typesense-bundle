<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Manager;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\Typesense\Client\CollectionClient;
use Symfony\UX\Typesense\Client\TypesenseClient;
use Symfony\UX\Typesense\Traits\DiscriminatorTrait;
use Symfony\UX\Typesense\Transformer\AbstractTransformer;

class CollectionManager
{
    protected $collectionDefinitions;
    protected $collectionClient;
    protected $doctrineTransformer;

    protected $client;
    protected $typesenseManager;
    /**
     * @var EntityManagerInterface
     */

    public function __construct(TypesenseManager $typesenseManager, ?string $connectionName = null)
    {
        $this->client = $typesenseManager->getConnection($connectionName);
        $this->collectionDefinitions = $this->client->getCollectionDefinitions();
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
            $collectionName = $collectionDefinition['typesense_name'] ?? $name;
            $managedClassNames[$collectionName] = $collectionDefinition['entity'];
        }

        return $managedClassNames;
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

        $this->collectionClient->delete($definition['typesense_name']);
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
            $definition['typesense_name'],
            $fields,
            $definition['default_sorting_field'],
            $tokenSeparators,
            $symbolsToIndex
        );
    }
}
