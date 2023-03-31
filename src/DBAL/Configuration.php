<?php

declare(strict_types=1);

namespace Typesense\Bundle\DBAL;

use Doctrine\ORM\ObjectManager;
use Doctrine\ORM\ObjectManagerInterface;
use Typesense\Bundle\Client\CollectionClient;
use Typesense\Bundle\DBAL\Connection;
use Typesense\Bundle\Exception\TypesenseException;
use Typesense\Bundle\Transformer\AbstractTransformer;
use Typesense\Client;

class Configuration
{
    protected ?string $secret;
    protected array $options;

    protected string $scheme;
    protected string $host;
    protected string $path;

    protected int $port;

    public function __toString(): string { return $this->getEndpoint(); }

    public function __construct(#[SensitiveParameter] ?string $secret, #[SensitiveParameter] array $params, array $options = [])
    {
        $this->scheme = $url["scheme"] ?? "http";
        $this->host   = $url["host"] ?? "localhost";

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

    public function getNode()
    {
        return ['host' => $this->host, 'port' => $this->port, 'protocol' => $this->scheme];
    }

    public function getEndpoint(string $path = "")
    {
        return sprintf('%s://%s:%d%s%s', $this->scheme, $this->host, $this->port, $this->collectionPrefix, $path);
    }
}
