<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\EventListener;

use Symfony\UX\Typesense\Manager\CollectionManager;
use Symfony\UX\Typesense\Manager\DocumentManager;
use Symfony\UX\Typesense\Manager\TypesenseManager;
use Symfony\UX\Typesense\Transformer\DoctrineToTypesenseTransformer;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class TypesenseIndexer
{
    private $managedClassNames;
    private $objetsIdThatCanBeDeletedByObjectHash = [];

    private $documentsToIndex                     = [];
    private $documentsToUpdate                    = [];
    private $documentsToDelete                    = [];

    public function __construct(TypesenseManager $typesenseManager) {

        $this->typesenseManager = $typesenseManager;
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        foreach($this->typesenseManager->getConnections() as $connectionName => $client) {

            $entity = $args->getObject();
            if ($this->entityIsNotManaged($entity, $connectionName)) {
                return;
            }

            $collections = $this->getCollectionNames($entity, $connectionName);
            $data = $this->typesenseManager->getDoctrineTransformer($connectionName)->convert($entity);

            foreach ($collections as $collection) {
                $this->documentsToIndex[] = [$collection, $data];
            }
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
         foreach($this->typesenseManager->getConnections() as $connectionName => $client) {

             $entity = $args->getObject();

             if ($this->entityIsNotManaged($entity, $connectionName)) {
                 return;
             }

             $collections = $this->getCollectionNames($entity, $connectionName);
             foreach ($collections as $collection) {

                 $collectionConfig = $this->typesenseManager->getCollectionManager($connectionName)->getCollectionDefinitions()[$collection];
                 $this->checkPrimaryKeyExists($collectionConfig);

                 $data = $this->typesenseManager->getDoctrineTransformer($connectionName)->convert($entity);

                 $primaryField = array_search_by($collectionConfig["fields"], "type", "primary");
                 $entityId = $data[$primaryField["name"] ?? "id"] ?? null;
                 if ($entityId) {

                     $this->documentsToUpdate[] = [$collection, $entityId, $data];
                 }
             }
         }
    }

    private function checkPrimaryKeyExists($collectionConfig)
    {
        foreach ($collectionConfig['fields'] as $config) {
            if ($config['type'] === 'primary') {
                return;
            }
        }

        throw new \Exception(sprintf('Primary key info have not been found for Typesense collection %s', $collectionConfig['typesense_name']));
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        foreach($this->typesenseManager->getConnections() as $connectionName => $client) {

            $entity = $args->getObject();

            if ($this->entityIsNotManaged($entity, $connectionName)) {
                return;
            }

            $data = $this->typesenseManager->getDoctrineTransformer($connectionName)->convert($entity);

            $this->objetsIdThatCanBeDeletedByObjectHash[spl_object_hash($entity)] = $data['id'];
        }
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        foreach($this->typesenseManager->getConnections() as $connectionName => $client) {

            $entity = $args->getObject();

            $entityHash = spl_object_hash($entity);

            if (!isset($this->objetsIdThatCanBeDeletedByObjectHash[$entityHash])) {
                return;
            }

            $collections = $this->getCollectionNames($entity, $connectionName);
            foreach ($collections as $collection) {
                $this->documentsToDelete[] = [$collection, $this->objetsIdThatCanBeDeletedByObjectHash[$entityHash]];
            }
        }
    }

    public function postFlush()
    {
        $this->indexDocuments();
        $this->updateDocuments();
        $this->deleteDocuments();

        $this->resetDocuments();
    }

    private function indexDocuments()
    {
        foreach ($this->documentsToIndex as $documentToIndex) {
            $this->documentManager->index(...$documentToIndex);
        }
    }

    private function updateDocuments()
    {
        foreach ($this->documentsToUpdate as $documentToUpdate) {
            try {
                $this->documentManager->delete($documentToUpdate[0], $documentToUpdate[1]);
            } catch(\Typesense\Exceptions\ObjectNotFound $e) {
            }

            $this->documentManager->index($documentToUpdate[0], $documentToUpdate[2]);
        }
    }

    private function deleteDocuments()
    {
        foreach ($this->documentsToDelete as $documentToDelete) {
            $this->documentManager->delete(...$documentToDelete);
        }
    }

    private function resetDocuments()
    {
        $this->documentsToIndex  = [];
        $this->documentsToUpdate = [];
        $this->documentsToDelete = [];
    }

    private function entityIsNotManaged($entity, ?string $connectionName = null)
    {
        $entityClassname = ClassUtils::getClass($entity);
        return !in_array($entityClassname, array_values($this->typesenseManager->getManagedClassNames($connectionName)), true);
    }

    private function getCollectionNames($entity, ?string $connectionName = null): array
    {
        $entityClassname = ClassUtils::getClass($entity);
        $connectionName = $this->typesenseManager->getConnectionName($connectionName);

        $collectionNames = [];
        foreach ($this->typesenseManager->getManagedClassNames($connectionName) as $key => $managedClassName) {
            if (is_instanceof($entityClassname, $managedClassName)) {
                $collectionNames[] = $key;
            }
        }

        return $collectionNames;
    }
}
