<?php

namespace Typesense\Bundle\DBAL;
use Doctrine\ORM\ObjectManagerInterface;
use DoctrineExtensions\Query\Mysql\Field;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Typesense\Bundle\Client\Connection;
use Typesense\Bundle\ORM\CollectionFinder;
use Typesense\Bundle\Transformer\DoctrineToTypesenseTransformer;

class TypesenseManager
{
    protected array $clients = [];
    protected $parameterBag;

    protected $documentManagers;
    protected $collectionManagers;

    public function __construct(ParameterBagInterface $parameterBag, ObjectManagerInterface $objectManager)
    {
        $this->parameterBag = $parameterBag;
    }

    public function addClient(Connection $client)
    {
        $this->clients[$client->getConnectionName()] = $client;
    }

    public function getManagedClassNames(?string $connectionName = null) :array
    {
        $connectionName ??= $this->getDefaultConnectionName();
        return $this->getCollectionManager($connectionName)?->getManagedClassNames() ?? [];
    }

    public function getCollectionManager(?string $connectionName = null) : ?CollectionManager
    {
        $connectionName ??= $this->getDefaultConnectionName();
        return $this->collectionManagers[$connectionName] ?? null;
    }

    public function getDocumentManager(?string $connectionName = null) : ?DocumentManager
    {
        $connectionName ??= $this->getDefaultConnectionName();
        return $this->documentManagers[$connectionName] ?? null;
    }

    public function addFinder(string $connectionName, CollectionFinder $collectionFinder)
    {
        $client = $this->getConnection($connectionName);
        $client->addFinder($collectionFinder);
    }

    public function getDefaultConnectionName() { return $this->parameterBag->get("typesense.default_connection"); }
    public function getDefaultConnection(): ?Connection { return $this->getConnection(); }
    public function getConnections(?string $connectionName = null): array
    {
        return $this->clients;
    }
    public function getConnection(?string $connectionName = null): ?Connection
    {
        $connectionName ??= $this->getDefaultConnectionName();
        return $this->clients[$connectionName];
    }

    public function getFinders(?string $connectionName = null): ?array { return $this->getConnection($connectionName)?->getFinders(); }
    public function getFinder(string $collectionName, ?string $connectionName = null): ?CollectionFinder
    {
        return $this->getConnection($connectionName)?->getFinder($collectionName);
    }
}
