<?php

declare(strict_types=1);

namespace Typesense\Bundle\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Http\Client\Exception\NetworkException;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Typesense\Bundle\DBAL\Transaction;
use Typesense\Bundle\Exception\TypesenseException;
use Typesense\Bundle\ORM\TypesenseManager;
use Typesense\Bundle\TypesenseInterface;

class TypesenseIndexer
{
    protected array $transactions = [];

    protected $requestStack;
    protected $typesenseManager;
    protected $propertyAccessor;
    protected $cache;

    public function __construct(TypesenseManager $typesenseManager, RequestStack $requestStack, ParameterBagInterface $parameterBag, ?CacheInterface $cache = null)
    {
        $this->typesenseManager = $typesenseManager;
        $this->requestStack = $requestStack;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter('typesense', 0, $parameterBag->get('kernel.cache_dir')));
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if (!$object instanceof TypesenseInterface) {
            return;
        }

        foreach ($this->typesenseManager->getCollections() as $collectionName => $collection) {
            if (!$collection->supports($object)) {
                continue;
            }

            $this->transactions[] = new Transaction($collection, Transaction::PERSIST, $object);
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if (!$object instanceof TypesenseInterface) {
            return;
        }

        foreach ($this->typesenseManager->getCollections() as $collectionName => $collection) {
            if (!$collection->supports($object)) {
                continue;
            }

            $this->transactions[] = (new Transaction($collection, Transaction::UPDATE, $object));
        }
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if (!$object instanceof TypesenseInterface) {
            return;
        }

        $this->objectIds[spl_object_hash($object)] = (string) $object->getId();
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if (!$object instanceof TypesenseInterface) {
            return;
        }

        $objectHash = spl_object_hash($object);
        if (!isset($this->objectIds[$objectHash])) {
            return;
        }

        foreach ($this->typesenseManager->getCollections() as $collectionName => $collection) {
            if (!$collection->supports($object)) {
                continue;
            }

            if (array_key_exists($objectHash, $this->objectIds)) {
                $this->transactions[] = new Transaction($collection, Transaction::REMOVE, $this->objectIds[$objectHash]);
            }
        }
    }

    protected $first = true;

    public function postFlush(PostFlushEventArgs $args)
    {
        foreach ($this->transactions as $transaction) {
            try {
                $transaction->commit();
            } catch (TypesenseException | NetworkException $e) {
                if ($this->first) {
                    $flashBag = $this->requestStack->getCurrentRequest()?->getSession()?->getFlashBag();
                    $flashBag->add('warning', 'Typesense ' . $e->getCode() . ': ' . $e->getMessage());
                    $this->first = false;
                }
            }
        }

        if ($this->transactions) {
            $this->cache->clear();
        }
    }
}
