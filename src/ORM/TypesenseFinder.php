<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM;

use App\Entity\Marketplace\Sales\Fee;
use Typesense\Bundle\Client\CollectionClient;
use Doctrine\ORM\ObjectManagerInterface;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Typesense\Bundle\DBAL\TypesenseManager;

class TypesenseFinder implements CollectionFinderInterface
{
    private $collectionDefinition;
    private $collectionClient;
    private $objectManager;

    public function getName():string { return $this->collectionDefinition["name"]; }
    public function __construct(CollectionClient $collection, ObjectManagerInterface $objectManager, array $collectionDefinition)
    {
        $this->collectionDefinition = $collectionDefinition;
        $this->collectionClient     = $collectionClient;
        $this->objectManager        = $objectManager;
    }

    public function getDefinition(): array { return $this->collectionDefinition; }
    public function rawQuery(TypesenseQuery $query): TypesenseResponse
    {
        return $this->search($query);
    }

    public function query(TypesenseQuery $query, bool $cacheable = false): TypesenseResponse
    {
        $queryResponse = $this->search($query);
        return $this->hydrate($queryResponse, $cacheable);
    }

    public function facet(string $facetBy, ?TypesenseQuery $query = null): mixed
    {
        if($query) $query = clone $query;
        $query ??= new TypesenseQuery();

        $query->addQueryBy($facetBy);
        $query->facetBy($facetBy);

        return $this->search($query)->getFacetCounts();
    }

    private function hydrate(Response $response, bool $cacheable = false): self
    {
        $ids             = [];
        $primaryKeyInfos = $this->getPrimaryKeyInfo();
        foreach ($response->getResults() ?? [] as $result) {
            $ids[] = $result['document'][$primaryKeyInfos['documentAttribute']];
        }

        if (!count($ids)) return $response;

        $classMetadata = $this->objectManager->getClassMetadata($this->collectionDefinition['entity']);
        $response->setHydratedHits();

        return $response->setHydrated(true);
    }

    private function search(TypesenseQuery $query)
    {
        $classMetadata = $this->objectManager->getClassMetadata($this->collectionDefinition['entity']);
        if(!$classMetadata->discriminatorColumn && $query->getParameter("discriminate_by"))
            throw new \LogicException("Class \"".$this->collectionDefinition['entity']."\" doesn't have discriminator values");

        $classNames = array_filter(
            explode(",", $query->getParameter("discriminate_by") ?? ""),
            fn($c) => !empty(trim($c ?? ""))
        );

        foreach($classNames as $className) {

            $relation = str_starts_with(trim($className), "^") ? ":!=" : ":=";
            $classMetadata = $this->objectManager->getClassMetadata(trim($className," ^"));

            $query->addFilterBy($classMetadata->discriminatorColumn["name"] . $relation . $classMetadata->discriminatorValue);
        }

        $result = $this->collectionClient->search($this->collectionDefinition['name'], $query);
        return new TypesenseResponse($result);
    }

    private function getPrimaryKeyInfo()
    {
        foreach ($this->collectionDefinition['fields'] as $name => $config) {
            if ($config['type'] === 'primary') {
                return ['entityAttribute' => $config['entity_attribute'], 'documentAttribute' => $config['name']];
            }
        }

        throw new \Exception(sprintf('Primary key info have not been found for Typesense collection %s', $this->collectionDefinition['name']));
    }
}
