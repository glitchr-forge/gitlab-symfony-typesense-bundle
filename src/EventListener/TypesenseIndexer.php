<?php

declare(strict_types=1);

namespace Typesense\Bundle\EventListener;

use Typesense\Bundle\DBAL\Collections;
use Typesense\Bundle\DBAL\Documents;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\TypesenseManager;
use Typesense\Bundle\Transformer\DoctrineToTypesenseTransformer;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Http\Client\Exception\NetworkException;

class TypesenseIndexer
{
    private $managedClassNames;
    private $objetsIdThatCanBeDeletedByObjectHash = [];

    private $documentsToPersist                     = [];
    private $documentsToUpdate                    = [];
    private $documentsToDelete                    = [];

    protected $typesenseManager;

    public function __construct(TypesenseManager $typesenseManager) {

        $this->typesenseManager = $typesenseManager;
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        foreach($this->typesenseManager->getCollections() as $collectionName => $collection) {

            if (!$collection->supports($entity)) {
                continue;
            }

            $data = $collection->transformer()->convert($entity);
            $this->documentsToPersist[] = [$collection, $data];
        }

    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        foreach($this->typesenseManager->getCollections() as $collectionName => $collection) {

             if (!$collection->supports($entity)) {
                continue;
             }

            $this->checkPrimaryKeyExists($collection->metadata());
            $data = $collection->transformer()->convert($entity);

            $primaryField = array_search_by($collection->metadata()->fields, "identifier", true);
            $entityId = $data[$primaryField["name"] ?? "id"] ?? null;
            if ($entityId) {

                $this->documentsToUpdate[] = [$collection, $entityId, $data];
            }
        }
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        foreach($this->typesenseManager->getCollections() as $collectionName => $collection) {

             if (!$collection->supports($entity)) {
                continue;
            }

            $data = $collection->transformer()->convert($entity);

            $this->objetsIdThatCanBeDeletedByObjectHash[spl_object_hash($entity)] = $data['id'];
        }
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        $entityHash = spl_object_hash($entity);

        foreach($this->typesenseManager->getCollections() as $collectionName => $collection) {

            if (!isset($this->objetsIdThatCanBeDeletedByObjectHash[$entityHash])) {
                return;
            }

            $this->documentsToDelete[] = [$collection, $this->objetsIdThatCanBeDeletedByObjectHash[$entityHash]];
        }
    }

    public function postFlush()
    {
        try {

            $this->persistDocuments();
            $this->updateDocuments();
            $this->deleteDocuments();

        } catch(NetworkException $e) { }

        $this->resetDocuments();
    }

    private function persistDocuments()
    {
        foreach ($this->documentsToPersist as [$collection, $data]) {

            $collection->documents()->create($data);
        }
    }

    private function updateDocuments()
    {
        foreach ($this->documentsToUpdate as [$collection, $entityId, $data]) {

            try { $collection->documents()->delete($entityId); }
            catch(\Typesense\Exceptions\ObjectNotFound $e) { }

            $collection->documents()->create($data);
        }
    }

    private function deleteDocuments()
    {
        foreach ($this->documentsToDelete as [$collection, $entityId]) {

            $collection->documents()->delete($entityId);
        }
    }

    private function resetDocuments()
    {
        $this->documentsToPersist = [];
        $this->documentsToUpdate = [];
        $this->documentsToDelete = [];
    }

    private function checkPrimaryKeyExists(TypesenseMetadata $metadata)
    {
        foreach ($metadata->fields as $field) {
            if ($field->identifier) {
                return;
            }
        }

        throw new \Exception(sprintf('Primary key info have not been found for Typesense collection %s', $collectionConfig['name']));
    }
}
