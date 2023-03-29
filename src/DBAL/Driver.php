<?php

declare(strict_types=1);

namespace Typesense\Bundle\DBAL;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\ORM\ObjectManager;
use Doctrine\ORM\ObjectManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Typesense\Bundle\Client\CollectionClient;
use Typesense\Bundle\Client\Connection;
use Typesense\Bundle\Exception\TypesenseException;
use Typesense\Bundle\Transformer\AbstractTransformer;
use Typesense\Client;
use Typesense\Exceptions\ConfigError;

class Driver
{
    public const NODES = "nodes";
    public const API_KEY = "api_key";
    public const CONNECTION_TIMOUT_SECONDS = "connection_timeout_seconds";

    private ?Client $client = null;
    private Configuration $configuration;

    public function __construct(#[SentitiveParameter] array $params)
    {
        $this->configuration = $this->prepare($params);
    }

    public function prepare(array $params): Configuration
    {
        if (!$this->configuration) {

            // API Key extraction
            $secret = $params["secret"];
            if (!$secret) return [null, [], []];

            // Parsing URL: return array
            $params = parse_url($params["url"] ?? "");
            $params["scheme"] ??= "http";
            $params["host"]   ??= "localhost";
            $params["port"]   ??= 8108;

            // Options
            $options = $params["options"] ?? [];

            $this->configuration = new Configuration($url, $secret, $options);
        }

        return $this->configuration;
    }

    public function connect(): ?Client
    {
        if (!$this->client) {

            if($this->configuration->getSecret() == null) return null;

            $options = $this->configuration->getOptions();
            $options[self::NODES]   = $this->configuration->getNodes();
            $options[self::API_KEY] = $this->configuration->getSecret();

            try { $this->client = new Client($options); }
            catch (ConfigError $e) { return null; }
        }

        return $this->client;
    }
}
