<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Client;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\UX\Typesense\Exception\TypesenseException;
use Symfony\UX\Typesense\Finder\CollectionFinder;
use Symfony\UX\Typesense\Traits\DiscriminatorTrait;
use Typesense\Aliases;
use Typesense\Client;
use Typesense\Collections;
use Typesense\Debug;
use Typesense\Health;
use Typesense\Keys;
use Typesense\Metrics;
use Typesense\MultiSearch;
use Typesense\Operations;

class TypesenseClient
{
    use DiscriminatorTrait;

    /**
     * @var string $connectionName
     */
    protected $connectionName;
    /**
     * @var ParameterBagInterface $parameterBag
     */
    protected $parameterBag;

    protected $collectionDefinitions;
    protected $collectionClient;

    protected $entityManager;

    protected array $finders = [];

    public function __construct(string $connectionName, array $collectionDefinitions, ParameterBagInterface $parameterBag, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        $this->connectionName = $connectionName;
        $this->collectionDefinitions = $this->extendsSubclasses($collectionDefinitions);

        $this->collectionClient = new CollectionClient($this);
        $this->parameterBag = $parameterBag;
    }

    public function getCollectionDefinitions() { return $this->collectionDefinitions; }
    public function getCollectionClient() { return $this->collectionClient; }

    public function getConnectionName()
    {
        return $this->connectionName;
    }

    public function addFinder(CollectionFinder $collectionFinder)
    {
        $this->finders[$collectionFinder->getName()] = $collectionFinder;
        return $this;
    }

    public function getFinders(): array { return $this->finders; }
    public function getFinder(string $collectionName): ?CollectionFinder
    {
        return $this->finders[$collectionName] ?? null;
    }

    public function prepare(): array
    {
        $connectionName = $this->connectionName;
        $apiKey = $this->parameterBag->get("typesense.connections.".$connectionName.".secret");

        if (!$apiKey) {
            if (is_cli()) {
                throw new TypesenseException("Typesense API Key missing");
            }
            return [null, [], []];
        }

        $host = $this->parameterBag->get("typesense.connections.".$connectionName.".host");
        $urlParsed = parse_url($host);

        $host     = $urlParsed["host"] ?? $host ?? "localhost";
        $port     = $urlParsed["port"] ?? $this->parameterBag->get("typesense.connections.".$connectionName.".port") ?? 8108;
        $protocol = $urlParsed["scheme"] ?? ($this->parameterBag->get("typesense.connections.".$connectionName.".use_https") ? "https" : "http");

        $node = ['host' => $host, 'port' => $port, 'protocol' => $protocol];
        $options = $this->parameterBag->get("typesense.connections.".$connectionName.".options") ?? [];

        return [$apiKey, $node, $options];
    }

    private ?Client $client = null;
    public function getClient(): ?Client
    {
        if (!$this->client) {
            $this->client = $this->connect();
        }

        return $this->client;
    }

    protected ?string $clientUrl = null;
    public function getClientUrl(): ?string
    {
        if (!$this->client) {
            $this->client = $this->connect();
        }

        return $this->clientUrl;
    }

    public function connect(): ?Client
    {
        if (!$this->client) {
            list($apiKey, $node, $options) = $this->prepare();
            if($apiKey == null) return null;

            $this->client = new Client(array_merge($options, ["nodes" => [$node], "api_key" => $apiKey]));
            if ($this->client) {
                $this->clientUrl = $node["protocol"]."://".$node["host"].":".$node["port"];
            }
        }

        return $this->client;
    }

    /**
     * This allow to be use to use new Typesense\Client functions
     * before we update this client.
     */
    public function __call($name, $arguments)
    {
        if (!$this->client) {
            $this->client = $this->connect();
        }

        return $this->client?->{$name}(...$arguments);
    }

    public function __get($name)
    {
        if (!$this->client) {
            $this->client = $this->connect();
        }

        return $this->client?->{$name};
    }

    public function isOperationnal(): bool
    {
        if (!$this->client) {
            $this->client = $this->connect();
        }

        return $this->client !== null;
    }
}
