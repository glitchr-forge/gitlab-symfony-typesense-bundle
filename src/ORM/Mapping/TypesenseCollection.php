<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Mapping;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\ObjectManager;
use Typesense\Bundle\DBAL\Connection;
use Typesense\Bundle\ORM\Query;
use Typesense\Bundle\ORM\Query\Request;
use Typesense\Bundle\ORM\Query\Response;
use Typesense\Bundle\ORM\Transformer\Abstract\TransformerInterface;
use Typesense\Bundle\ORM\TypesenseManager;
use Typesense\Client;

class TypesenseCollection
{
    protected $connection;

    protected $metadata;
    protected $documents;

    public function __construct(TypesenseMetadata $metadata, Connection $connection)
    {
        $this->metadata   = $metadata;
        $this->connection = $connection;

        $this->documents  = new TypesenseDocuments($metadata, $connection);
    }

    public function name(): string { return $this->metadata->getName(); }
    public function metadata(): TypesenseMetadata { return $this->metadata; }
    public function transformer(): TransformerInterface { return $this->metadata->getTransformer(); }
    public function connection(): Connection { return $this->connection; }
    public function client(): ?Client { return $this->connection->getClient(); }
    public function documents(): TypesenseDocuments { return $this->documents; }

    public function supports($entity):bool
    {
        $entityClassname = ClassUtils::getClass($entity);
        return is_a($entity, $this->metadata->getClass(), true);
    }

    public function search(Request $query)
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        return $this->documents->search($query->getHeaders());
    }

    public function multiSearch(array $searchRequests, ?Request $commonSearchParams)
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        $searches = [];
        foreach ($searchRequests as $sr) {
            if (!$sr instanceof Query) {
                throw new \Exception('searchRequests must be an array  of Request objects');
            }
            if (!$sr->hasParameter('collection')) {
                throw new \Exception('Request must have the key : `collection` in order to perform multiSearch');
            }
            $searches[] = $sr->getHeaders();
        }

        return $this->client()->multiSearch->perform(
            [
                'searches' => $searches,
            ],
            $commonSearchParams ? $commonSearchParams->getHeaders() : []
        );
    }

    public function list()
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        return $this->client()->getCollections()->retrieve();
    }

    public function create()
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        $configuration = $this->metadata->getConfiguration();
        $configuration["fields"] = array_values(array_map(fn($f) => $f->toArray(), $configuration["fields"]));
        foreach($configuration["fields"] as &$field)
            $field["type"] = $this->metadata->getTransformer()->cast($field["type"]);

        $this->client()->getCollections()->create($configuration);
    }

    public function delete()
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        return $this->client()->getCollection($this->name())?->delete();
    }
}
