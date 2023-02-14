<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Manager;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\Typesense\Client\CollectionClient;
use Symfony\UX\Typesense\Traits\DiscriminatorTrait;
use Symfony\UX\Typesense\Transformer\AbstractTransformer;

class CollectionManager
{
    use DiscriminatorTrait;

    private $collectionDefinitions;
    private $collectionClient;
    private $transformer;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(EntityManagerInterface $entityManager, CollectionClient $collectionClient, AbstractTransformer $transformer, array $collectionDefinitions)
    {
        $this->entityManager = $entityManager;
        $this->collectionDefinitions = $this->extendsSubclasses($collectionDefinitions);
        $this->collectionClient      = $collectionClient;
        $this->transformer           = $transformer;
    }

    public function getCollectionDefinitions()
    {
        return $this->collectionDefinitions;
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

    public function deleteCollextion($collectionDefinitionName)
    {
        return $this->deleteCollection($collectionDefinitionName);
    }

    public function createCollection($collectionDefinitionName)
    {
        $definition       = $this->collectionDefinitions[$collectionDefinitionName];
        $fieldDefinitions = $definition['fields'];
        $fields           = [];

        foreach ($fieldDefinitions as $key => $fieldDefinition) {
            $fieldDefinition['type'] = $this->transformer->castType($fieldDefinition['type']);
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
