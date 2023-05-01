<?php

declare(strict_types=1);

namespace Typesense\Bundle\DBAL;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Typesense\Client;
use Typesense\Collection;
use Typesense\Collections;
use Typesense\Document;
use Typesense\Documents;

/**
 *
 */
class Connection
{
    protected ParameterBagInterface $parameterBag;

    protected string $name;

    protected Driver $driver;

    public function __construct(string $name, ParameterBagInterface $parameterBag)
    {
        $this->name = $name;
        $this->parameterBag = $parameterBag;
        $this->driver = new Driver($name);
    }

    /**
     * @param $array
     * @param $divider_char
     * @return array|false|mixed
     */
    private function inflate($array, $divider_char = '.')
    {
        if (!is_array($array)) {
            return false;
        }

        $split = '/' . preg_quote($divider_char, '/') . '/';

        $ret = [];
        foreach ($array as $key => $val) {
            $parts = preg_split($split, $key, -1, PREG_SPLIT_NO_EMPTY);
            $leafpart = array_pop($parts);
            $parent = &$ret;
            foreach ($parts as $part) {
                if (!isset($parent[$part])) {
                    $parent[$part] = [];
                } elseif (!is_array($parent[$part])) {
                    $parent[$part] = [];
                }

                $parent = &$parent[$part];
            }

            if (empty($parent[$leafpart])) {
                $parent[$leafpart] = $val;
            }
        }

        return $ret;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClient(): ?Client
    {
        $params = array_filter($this->parameterBag->all(), fn($k) => str_starts_with($k, 'typesense.connections.' . $this->name), ARRAY_FILTER_USE_KEY);
        $params = $this->inflate($params)['typesense']['connections'][$this->name] ?? [];

        return $this->getDriver()->connect($params);
    }

    public function getDriver(): Driver
    {
        return $this->driver;
    }

    public function getStatus(): ?string
    {
        return $this->driver->getConfigError()?->getMessage() ?? null;
    }

    public function getStatusCode(): ?int
    {
        return $this->driver->getConfigError()?->getCode() ?? null;
    }

    public function isConnected(): bool
    {
        return null !== $this->getClient();
    }

    public function getCollections(): ?Collections
    {
        return $this->getClient()?->getCollections();
    }

    public function getCollection(string $name): Collection
    {
        return $this->getCollections()[$name];
    }

    public function getDocuments(string $name): Documents
    {
        return $this->getCollection($name)->getDocuments();
    }

    /**
     * @param string $name
     * @param $id
     * @return Document
     */
    public function getDocument(string $name, $id): Document
    {
        return $this->getCollection($name)->getDocuments()[$id];
    }

    public function getHealth(): bool
    {
        return $this->getClient()?->getHealth()?->retrieve()['ok'] ?? false;
    }

    public function getDebug(): ?array
    {
        return $this->getClient()?->getDebug()?->retrieve();
    }
}
