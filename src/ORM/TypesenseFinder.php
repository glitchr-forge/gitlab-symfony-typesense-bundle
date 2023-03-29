<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM;

use Doctrine\Persistence\ObjectManager;
use Typesense\Bundle\Client\CollectionClient;
use Typesense\Bundle\Client\Metadata;
use Typesense\Bundle\DBAL\TypesenseManager;
use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;

class TypesenseFinder implements TypesenseFinderInterface
{
    protected $collection;
    protected $objectManager;

    public function __construct(TypesenseCollection $collection, ObjectManager $objectManager)
    {
        $this->collection = $collection;
        $this->objectManager = $objectManager;
    }

    public function metadata():TypesenseMetadata { return $this->collection->getMetadata(); }

    public function raw(Request $request): Response
    {
        return $this->search($request);
    }

    public function query(Request $request, bool $cacheable = false): Response
    {
        return $this->hydrate($this->raw($request), $cacheable);
    }

    public function facet(string $facetBy, ?Request $request = null): mixed
    {
        if ($request)
            $request = clone $request;

        $request ??= new Query();
        $request->addQueryBy($facetBy);
        $request->facetBy($facetBy);

        return $this->search($request)->getFacetCounts();
    }

    private function hydrate(Response $response, bool $cacheable = false): self
    {
        $ids             = [];
        $primaryKeyInfos = $this->getPrimaryKeyInfo();
        foreach ($response->getResults() ?? [] as $result) {
            $ids[] = $result['document'][$primaryKeyInfos['documentAttribute']];
        }

        if (!count($ids)) return $response;

        $classMetadata = $this->objectManager->getClassMetadata($this->collection->getMetadata()->entity);
        $response->setHydratedHits();

        return $response->setHydrated(true);
    }

    private function search(Request $request)
    {
        $classMetadata = $this->objectManager->getClassMetadata($this->collection->getMetadata()->entity);
        if(!$classMetadata->discriminatorColumn && $request->getParameter(Query::INSTANCE_OF))
            throw new \LogicException("Class \"".$this->collection->getMetadata()->entity."\" doesn't have discriminator values");

        $classNames = array_filter(
            explode(",", $request->getParameter(Query::INSTANCE_OF) ?? ""),
            fn($c) => !empty(trim($c ?? ""))
        );

        foreach($classNames as $className) {

            $relation = str_starts_with(trim($className), "^") ? ":!=" : ":=";
            $classMetadata = $this->objectManager->getClassMetadata(trim($className," ^"));

            $request->addFilterBy($classMetadata->discriminatorColumn["name"] . $relation . $classMetadata->discriminatorValue);
        }

        $result = $this->collection->search($this->collection->getMetadata()->name, $request);
        return new Response($result);
    }

    private function primary(): string
    {
        foreach ($this->collection->getMetadata()->fields as $name => $field) {

            if ($field['type'] === 'primary') {
                return $name;
            }
        }

        throw new \Exception(sprintf('No primary key found in collection %s', $this->collection->getMetadata()->name));
    }
}
