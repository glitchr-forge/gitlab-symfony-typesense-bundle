<?php

declare(strict_types=1);

namespace Typesense\Bundle\DBAL;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Typesense\Bundle\DBAL\Configuration;
use Typesense\Bundle\DBAL\Driver;
use Typesense\Bundle\Exception\TypesenseException;
use Typesense\Bundle\ORM\CollectionFinder;
use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\TypesenseFinder;
use Typesense\Bundle\Transformer\DoctrineToTypesenseTransformer;
use Typesense\Aliases;
use Typesense\Bundle\Transformer\DoctrineTransformer;
use Typesense\Client;
use Typesense\Collection;
use Typesense\Document;
use Typesense\Documents;
use Typesense\Collections;
use Typesense\Debug;
use Typesense\Health;
use Typesense\Keys;
use Typesense\Metrics;
use Typesense\MultiSearch;
use Typesense\Operations;

class Connection
{
    protected $parameterBag;

    /**
     * @var string $name
     */
    protected $name;
    protected $driver;

    public function __construct(string $name, ParameterBagInterface $parameterBag)
    {
        $this->name = $name;
        $this->parameterBag = $parameterBag;
        $this->driver = new Driver($name);
    }

    public function getName(): string { return $this->name; }
    public function getClient(): ?Client
    {
        $params = $this->parameterBag->get("typesense.connections.".$this->name);
        return $this->getDriver()->connect($params);
    }
    public function getDriver(): Driver { return $this->driver; }

    public function isConnected(): bool { return $this->getClient() !== null; }

    public function getCollections(): ?Collections { return $this->getClient()?->getCollections(); }
    public function getCollection(string $name): Collection { return $this->getCollections()[$name]; }
    public function getDocuments(string $name): Documents { return $this->getCollection($name)->getDocuments(); }
    public function getDocument(string $name, $id): Document { return $this->getCollection($name)->getDocuments()[$id]; }

    public function getHealth(): bool { return $this->getClient()?->getHealth()?->retrieve()["ok"] ?? false; }
    public function getDebug(): ?array { return $this->getClient()?->getDebug()?->retrieve(); }
}
