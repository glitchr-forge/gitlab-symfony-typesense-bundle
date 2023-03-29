<?php

namespace Typesense\Bundle\DBAL;
use Doctrine\ORM\ObjectManagerInterface;
use Doctrine\Persistence\ObjectManager;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Typesense\Bundle\Client\Connection;
use Typesense\Bundle\ORM\CollectionFinder;
use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\TypesenseFinder;
use Typesense\Bundle\Transformer\DoctrineTransformer;

class TypesenseManager
{
    protected string $defaultConnection;
    protected array $connections = [];

    protected array $collections = [];
    protected array $finders = [];
    protected array $metadata = [];

    public function __construct(ObjectManager $objectManager, ?string $defaultConnection)
    {
        $this->objectManager = $objectManager;
        $this->defaultConnection = $defaultConnection;
    }

    //
    // Default connection
    protected function getDefaultConnection(): ?Connection { return $this->getConnection(); }
    protected function getDefaultConnectionName(): string  { return $this->defaultConnection; }

    //
    // Connection instances
    protected function getConnections(): array { return $this->connections; }
    protected function getConnection(?string $connectionName = null): ?Connection { return $this->connections[$connectionName ?? $this->getDefaultConnectionName()]; }
    protected function addConnection(Connection $connection)
    {
        $this->connections[$connection->getName()] = $connection;
        return $this;
    }

    //
    // Metadata instances
    public function getMetadata(string $collectionName): ?TypesenseMetadata { $this->getCollection($collectionName)?->getMetadata(); }
    protected function addMetadata(TypesenseMetadata $metadata): self
    {
        $this->metadata[$metadata->getName()] = $metadata;
        return $this;
    }

    //
    // Collection instances
    public function getCollection(string $collectionName) { return $this->collections[$collectionName]; }
    protected function addCollection(TypesenseCollection $collection): self
    {
        $this->collections[$collection->getName()] = $collection;
        return $this;
    }

    //
    // Additional instances for autowiring..
    public function getFinder (string $collectionName): ?TypesenseFinder { return $this->getCollection($connectionName)?->getFinder($collectionName); }
    protected function addFinder(TypesenseFinder $finder): self
    {
        $this->finders[$finder->getCollection()->getName()] = $finder;

        return $this;
    }
}
