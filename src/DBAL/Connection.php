<?php

declare(strict_types=1);

namespace Typesense\Bundle\Client;

use Doctrine\ORM\ObjectManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Typesense\Bundle\Exception\TypesenseException;
use Typesense\Bundle\ORM\CollectionFinder;
use Typesense\Bundle\Manager\CollectionManager;
use Typesense\Bundle\Manager\DocumentManager;
use Typesense\Bundle\Transformer\DoctrineToTypesenseTransformer;
use Typesense\Aliases;
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
     * @var string $connectionName
     */
    protected $connectionName;

    /**
     * @var ParameterBagInterface $parameterBag
     */
    protected $parameterBag;

    /**
     * @var ObjectManagerInterface $objectManager
     */
    protected $objectManager;
    protected $transformer;

    public function getDoctrineTransformer(?string $connectionName = null) :?DoctrineToTypesenseTransformer
    {
        $connectionName ??= $this->getDefaultConnectionName();
        return $this->doctrineTransformers[$connectionName] ?? null;
    }
    protected array $finders = []; // Create on-demand during compilation pass
    protected array $collectionClients = []; // For a given connection, this array contains the list of collection

    public function __construct(string $connectionName, array $collectionDefinitions, ParameterBagInterface $parameterBag, ObjectManagerInterface $objectManager)
    {
        $this->connectionName = $connectionName;
        $this->objectManager = $objectManager;
        $this->parameterBag = $parameterBag;

        // Create collection clients
        $this->collectionClients = [];
        foreach($collectionDefinitions as $collectionDefinition) {
            $this->collectionClients[] = new CollectionClient($this, $collectionDefinition);
        }

        // Prepare transformer and managers
        $this->transformer = new DoctrineToTypesenseTransformer($this);
        $this->documentManager = new DocumentManager($this);
        $this->collectionManager = new CollectionManager($this);
    }

    public function getCollection() { return $this->collectionClients; }
    public function getCollectionDefinitions() { return array_map(fn($c) => $c->getDefinition(), $this->collectionClients); }

    public function getConnectionName() { return $this->connectionName; }

    public function getFinders(): array { return $this->finders; }
    public function getFinder(string $collectionName): ?CollectionFinder
    {
        return $this->finders[$collectionName] ?? null;
    }

    public function addFinder(CollectionFinder $collectionFinder)
    {
        $this->finders[$collectionFinder->getName()] = $collectionFinder;
        return $this;
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

    private ?Client $client = null;
    public function client(): ?Client
    {
        if(!$this->client) {
            $this->client = $this->connect();
        }

        return $this->client;
    }

    protected ?string $clientUrl = null;
    public function clientUrl(): ?string
    {
        if(!$this->client) {
            $this->client = $this->connect();
        }

        return $this->clientUrl;
    }

    public function collection($name): ?CollectionClient
    {
        if (!$this->client) {
            $this->client = $this->connect();
        }

        return $this->client?->collectionClients[$name] ?? null;
    }

    public function isConnected(): bool
    {
        if (!$this->client) {
            $this->client = $this->connect();
        }

        return $this->client !== null;
    }
}
