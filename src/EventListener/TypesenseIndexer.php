<?php

declare(strict_types=1);

namespace Typesense\Bundle\EventListener;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Typesense\Bundle\DBAL\Collections;
use Typesense\Bundle\DBAL\Documents;
use Typesense\Bundle\DBAL\Transaction;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\TypesenseManager;
use Typesense\Bundle\Transformer\DoctrineToTypesenseTransformer;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Http\Client\Exception\NetworkException;
use Typesense\Bundle\TypesenseInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Psr\cache\CacheInterface;

class TypesenseIndexer
{
    protected array $transactions = [];

    protected $typesenseManager;
    protected $propertyAccessor;
    protected $cache;

    public function __construct(TypesenseManager $typesenseManager, string $cacheDir, ?CacheInterface $cache = null) {

        $this->typesenseManager = $typesenseManager;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter("typesense", 0, $cacheDir));
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if(!$object instanceof TypesenseInterface) return;

        foreach($this->typesenseManager->getCollections() as $collectionName => $collection) {

            if (!$collection->supports($object)) {
                continue;
            }

            $this->transactions[] = new Transaction($collection, Transaction::PERSIST, $object);
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if(!$object instanceof TypesenseInterface) return;

        foreach($this->typesenseManager->getCollections() as $collectionName => $collection) {

             if (!$collection->supports($object)) {
                continue;
             }

            $this->transactions[] = (new Transaction($collection, Transaction::UPDATE, $object));
        }
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if(!$object instanceof TypesenseInterface) return;

        $this->objectIds[spl_object_hash($object)] = (string) $object->getId();
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if(!$object instanceof TypesenseInterface) return;

        $objectHash = spl_object_hash($object);
        if (!isset($this->objectIds[$objectHash])) {
            return;
        }

        foreach($this->typesenseManager->getCollections() as $collectionName => $collection) {

            if (!$collection->supports($object)) {
                continue;
            }

            if(array_key_exists($objectHash, $this->objectIds))
                $this->transactions[] = new Transaction($collection, Transaction::REMOVE, $this->objectIds[$objectHash]);
        }
    }

    public function postFlush()
    {
        foreach($this->transactions as $transaction) {
         
            try { $transaction->commit(); }
            catch(NetworkException $e) { }
        }

        if($this->transactions) $this->cache->clear();
    }}
