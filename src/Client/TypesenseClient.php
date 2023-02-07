<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Client;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\UX\Typesense\Exception\TypesenseException;
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
    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    public function prepare(?string $connectionName = null /* to be used later */): array
    {
        $apiKey = $this->parameterBag->get("typesense.server.secret");

        if(!$apiKey) {

            if(is_cli()) throw new TypesenseException("Typesense API Key missing");
            return [];
        }

        $host = $this->parameterBag->get("typesense.server.host");
        $urlParsed = parse_url($host);

        $host     = $urlParsed["host"] ?? $host ?? "localhost";
        $port     = $urlParsed["port"] ?? $this->parameterBag->get("typesense.server.port") ?? 8108;
        $protocol = $urlParsed["scheme"] ?? ($this->parameterBag->get("typesense.server.use_https") ? "https" : "http");

        $node = ['host' => $host, 'port' => $port, 'protocol' => $protocol];
        $options = $this->parameterBag->get("typesense.server.options") ?? [];

        return [$apiKey, $node, $options];
    }

    private ?Client $client = null;
    public function getClient(): ?Client
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client;
    }

    protected ?string $clientUrl = null;
    public function getClientUrl(): ?string {

        if(!$this->client)
            $this->client = $this->connect();

        return $this->clientUrl;
    }

    public function connect(?string $connectionName = null): ?Client
    {
        if(!$this->client) {

            list($apiKey, $node, $options) = $this->prepare($connectionName);

            $this->client = new Client(array_merge($options, ["nodes" => [$node], "api_key" => $apiKey]));
            if ($this->client)
                $this->clientUrl = $node["protocol"]."://".$node["host"].":".$node["port"];
        }

        return $this->client;
    }

    /**
     * This allow to be use to use new Typesense\Client functions
     * before we update this client.
     */
    public function __call($name, $arguments)
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->{$name}(...$arguments);
    }

    public function __get($name)
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->{$name};
    }

    public function isOperationnal(): bool
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client !== null;
    }
}