<?php

declare(strict_types=1);

namespace Typesense\Bundle\DBAL;

use Doctrine\ORM\ObjectManager;
use Doctrine\ORM\ObjectManagerInterface;
use Typesense\Bundle\Client\CollectionClient;
use Typesense\Bundle\Client\Connection;
use Typesense\Bundle\Exception\TypesenseException;
use Typesense\Bundle\Transformer\AbstractTransformer;
use Typesense\Client;

class Configuration
{
    protected string $secret;
    protected array $options;

    protected string $scheme;
    protected string $host;
    protected int $port;

    public function __toString(): string { return $this->getEndpoint(); }

    public function __construct(array $params = [], #[SensitiveParameter] string $secret, array $options = [])
    {
        $this->host   = $host;
        $this->scheme = $url["scheme"] ?? "http";

        $this->port   = $url["port"] ?? 8108;
        $this->port   = is_string($this->port) ? intval($this->port) : $this->port;

        $this->path = $url["path"] ?? "";

        $this->secret = $secret;
        $this->options = $options;
    }

    public function getSecret()
    {
        return $this->secret;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getNodes()
    {
        return ['host' => $this->host, 'port' => $this->port, 'protocol' => $this->scheme];
    }

    public function getEndpoint(string $path = "")
    {
        return sprintf('%s://%s:%d%s%s', $this->scheme, $this->host, $this->port, $this->collectionPrefix, $path);
    }
}
