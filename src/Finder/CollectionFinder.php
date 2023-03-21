<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Finder;

use App\Entity\Marketplace\Sales\Fee;
use Symfony\UX\Typesense\Client\CollectionClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

class CollectionFinder implements CollectionFinderInterface
{
    private $collectionDefinition;
    private $collectionClient;
    private $em;

    public function __construct(CollectionClient $collectionClient, EntityManagerInterface $em, array $collectionDefinition)
    {
        $this->collectionDefinition = $collectionDefinition;
        $this->collectionClient = $collectionClient;
        $this->em               = $em;
    }

    public function rawQuery(TypesenseQuery $query): TypesenseResponse
    {
        return $this->search($query);
    }

    public function query(TypesenseQuery $query, bool $cacheable = false): TypesenseResponse
    {
        $queryResponse = $this->search($query);

        return $this->hydrate($queryResponse, $cacheable);
    }

    private function hydrate($results, bool $cacheable = false)
    {
        $ids             = [];
        $primaryKeyInfos = $this->getPrimaryKeyInfo();
        foreach ($results->getResults() as $result) {
            $ids[] = $result['document'][$primaryKeyInfos['documentAttribute']];
        }

        if (!count($ids)) return $results;

        $results = $this->em
            ->createQueryBuilder()

            ->select('e')
            ->from($this->collectionDefinition['entity'], "e")

            ->where($primaryKeyInfos["entityAttribute"] ." IN (:ids)")
            ->orderBy("FIELD(e.".$primaryKeyInfos["entityAttribute"].", ".implode(', ', $ids))
            ->setParameter("ids", $ids)
            ->setCacheable($cacheable)
            ->getQuery()->getResult();

        dump($results);
exit(1);
        $results->setHydratedHits($hydratedResults);
        $results->setHydrated(true);

        return $results;
    }

    private function search(TypesenseQuery $query)
    {
        $classMetadata = $this->em->getClassMetadata($this->collectionDefinition['entity']);
        if(!$classMetadata->discriminatorColumn && $query->getParameter("discriminate_by"))
            throw new \LogicException("Class \"".$this->collectionDefinition['entity']."\" doesn't have discriminator values");

        $classNames = array_filter(
            explode(",", $query->getParameter("discriminate_by") ?? ""),
            fn($c) => !empty(trim($c ?? ""))
        );

        foreach($classNames as $className) {

            $relation = str_starts_with(trim($className), "^") ? ":!=" : ":=";
            $classMetadata = $this->em->getClassMetadata(trim($className," ^"));

            $query->addFilterBy($classMetadata->discriminatorColumn["name"] . $relation . $classMetadata->discriminatorValue);
        }

        $result = $this->collectionClient->search($this->collectionDefinition['typesense_name'], $query);

        return new TypesenseResponse($result);
    }

    private function getPrimaryKeyInfo()
    {
        foreach ($this->collectionDefinition['fields'] as $name => $config) {
            if ($config['type'] === 'primary') {
                return ['entityAttribute' => $config['entity_attribute'], 'documentAttribute' => $config['name']];
            }
        }

        throw new \Exception(sprintf('Primary key info have not been found for Typesense collection %s', $this->collectionDefinition['typesense_name']));
    }
}
