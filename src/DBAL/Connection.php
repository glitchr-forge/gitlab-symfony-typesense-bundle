<?php

declare(strict_types=1);

namespace Typesense\Bundle\Client;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Typesense\Bundle\DBAL\Configuration;
use Typesense\Bundle\DBAL\Driver;
use Typesense\Bundle\Exception\TypesenseException;
use Typesense\Bundle\ORM\CollectionFinder;
use Typesense\Bundle\DBAL\Documents;
use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\TypesenseFinder;
use Typesense\Bundle\Transformer\DoctrineToTypesenseTransformer;
use Typesense\Aliases;
use Typesense\Bundle\Transformer\DoctrineTransformer;
use Typesense\Client;
use Typesense\Collections;
use Typesense\Debug;
use Typesense\Health;
use Typesense\Keys;
use Typesense\Metrics;
use Typesense\MultiSearch;
use Typesense\Operations;

class Connection
{
    /**
     * @var string $name
     */
    protected $name;
    protected $driver;

    public function __construct(string $name, array $configuration)
    {
        $this->name = $name;
        $this->driver = new Driver($configuration);
    }

    public function getClient(): ?Client { return $this->getDriver()->connect(); }
    public function getDriver(): Driver { return $this->driver; }

    public function isConnected(): bool { return $this->getClient() !== null; }

    public function getCollections(): ?Collections { return $this->getClient()?->getCollections(); }
    public function getHealth(): bool { return $this->getClient()?->getKeys(); }
    public function getDebug(): bool { return $this->getClient()?->getDebug(); }
}
