<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM;

use Doctrine\Persistence\ObjectManager;
use Psr\cache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Typesense\Bundle\ORM\Query\Query;
use Typesense\Bundle\ORM\Query\Request;
use Typesense\Bundle\ORM\Query\Response;
use Typesense\Bundle\ORM\Transformer\Abstract\TransformerInterface;
use Typesense\Bundle\ORM\TypesenseManager;
use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;

class TypesenseFinder implements TypesenseFinderInterface
{
    protected $cache;
    protected $collection;
    protected $objectManager;

    public function __construct(TypesenseCollection $collection)
    {
        $this->collection = $collection;
        $this->cache = new Psr16Cache(new FilesystemAdapter("typesense"));

        $this->objectManager = $collection->metadata()->getObjectManager();
    }

    public function name(): string { Return $this->metadata()->name; }
    public function metadata():TypesenseMetadata { return $this->collection->metadata(); }
    public function collection():TypesenseCollection { return $this->collection; }

    public function raw(Request $request, bool $cacheable = false): Response
    {
        $key = str_replace("\\", "__", static::class)."_".sha1(serialize($request));
        if($cacheable && $this->cache->has($key)) return $this->cache->get($key);

        $search = $this->search($request);
        if($cacheable) $this->cache->set($key, $search);

        return $search;
    }

    public function query(Request $request, bool $cacheable = false): Response
    {
        return $this->hydrate($this->raw($request, $cacheable), $cacheable);
    }

    public function facet(string $facetBy, ?Request $request = null): mixed
    {
        if ($request)
            $request = clone $request;

        $request ??= new Query($facetBy);
        $request->facetBy($facetBy);

        $response = $this->search($request);
        return $response->getFacetCounts();
    }

    private function hydrate(Response $response, bool $cacheable = false): Response
    {
        $ids             = [];
        $primaryKey = $this->identifier();
        $primaryField = $this->metadata()->fields[$primaryKey];
        foreach ($response->getResults() ?? [] as $result) {
            $ids[] = $result['document'][$primaryKey];
        }

        if (!count($ids)) return $response;

        $classMetadata = $this->objectManager->getClassMetadata($this->collection->metadata()->class);
        $response->setHydratedHits($this->objectManager
            ->createQueryBuilder()
                ->select('e')
                ->from($this->metadata()->class, "e")
                ->where("e.".$primaryField->property ." IN (:ids)")
                ->orderBy("FIELD(e.".$primaryField->property.", ".implode(', ', $ids).")")
                ->setParameter("ids", $ids)
                ->setCacheable($cacheable)
            ->getQuery()
                ->useQueryCache($cacheable)
                ->setCacheRegion($classMetadata->cache["region"] ?? null)
            ->getResult()
        );

        return $response->setHydrated(true);
    }

    private function search(Request $request)
    {
        $classMetadata = $this->objectManager->getClassMetadata($this->collection->metadata()->class);
        if(!$classMetadata->discriminatorColumn && $request->getHeader(Query::INSTANCE_OF))
            throw new \LogicException("Class \"".$this->collection->metadata()->class."\" doesn't have discriminator values");

        $classNames = array_filter(
            explode(",", $request->getHeader(Query::INSTANCE_OF) ?? ""),
            fn($c) => !empty(trim($c ?? ""))
        );

        foreach($classNames as $className) {

            $relation = str_starts_with(trim($className), "^") ? ":!=" : ":=";
            $classMetadata = $this->objectManager->getClassMetadata(trim($className," ^"));

            $request->addFilterBy($classMetadata->discriminatorColumn["name"] . $relation . $classMetadata->discriminatorValue);
        }

        $result = $this->collection->search($request);
        return new Response($result);
    }

    private function identifier(): string
    {
        foreach ($this->collection->metadata()->fields as $name => $field) {

            if ($field->identifier ?? false) {
                return $name;
            }
        }

        throw new \Exception(sprintf('No identifier key found in collection %s', $this->collection->metadata()->name));
    }
}
