<?php

declare(strict_types=1);

namespace Typesense\Bundle\Transformer;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\ObjectManagerInterface;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Typesense\Bundle\TypesenseInterface;

class DoctrineTransformer extends AbstractTransformer
{
    private $collectionDefinitions;
    private $entityToCollectionMapping;
    private $accessor;
    protected $objectManager;

    public function __construct(ObjectManagerInterface $objectManager, array $collectionDefinitions)
    {
        $this->objectManager = $objectManager;
        $this->collectionDefinitions = $this->extendsSubclasses($collectionDefinitions);

        $this->accessor = PropertyAccess::createPropertyAccessorBuilder()->enableMagicCall()->getPropertyAccessor();

        $this->entityToCollectionMapping = [];
        foreach ($this->collectionDefinitions as $collection => $collectionDefinition) {
            $this->entityToCollectionMapping[$collectionDefinition['entity']] = $collection;
        }
    }

    public function convert($entity): array
    {
        $entityClass = ClassUtils::getClass($entity);
        if (!$entity instanceof TypesenseInterface) {
            throw new \Exception("Class ".$entityClass." does not implement \"".TypesenseInterface::class."\"");
        }

        if (!isset($this->entityToCollectionMapping[$entityClass])) {
            throw new \Exception(sprintf('Class %s is not supported for Doctrine To Typesense Transformation', $entityClass));
        }

        $data = [];

        $fields = $this->collectionDefinitions[$this->entityToCollectionMapping[$entityClass]]['fields'];

        foreach ($fields as $key => $field) {
            try {
                if (array_key_exists("discriminator", $field)) {
                    $value = $this->objectManager->getClassMetadata(get_class($entity))->discriminatorValue;
                } else {
                    $value = $entity->__typesenseGetter($field['entity_attribute'] ?? $field["name"]?? $key, $field);
                }
            } catch (RuntimeException $exception) {
                $value = null;
            }

            $name = $field['name'] ?? $key;

            $data[$name] = $this->castValue(
                $entityClass,
                $name,
                $value
            );
        }

        return $data;
    }

    public function get(string $entityClass, string $propertyName, $value)
    {
        $collection = $this->entityToCollectionMapping[$entityClass];
        $this->collectionDefinitions[$collection]['fields'][$propertyName]["name"] ??= $propertyName;

        $key        = array_search(
            $propertyName,
            array_column(
                $this->collectionDefinitions[$collection]['fields'],
                'name'
            ),
            true
        );

        $collectionFieldsDefinitions = array_values($this->collectionDefinitions[$collection]['fields']);
        $originalType                = $collectionFieldsDefinitions[$key]['type'];
        $castedType                  = $this->cast($originalType);

        switch ($originalType.":".$castedType) {

            case self::TYPE_DATETIME.":".self::TYPE_INT_64:
                if ($value instanceof \DateTime) {
                    return $value->getTimestamp();
                }
                return null;

            case self::TYPE_OBJECT.":".self::TYPE_STRING:
                return $value->__toString();

            case self::TYPE_COLLECTION.":".self::TYPE_ARRAY_STRING:
                return array_filter(array_values(
                    $value->map(function ($v) {
                        return $v->__toString();
                    })->toArray()
                )) ?? [];

            case self::TYPE_STRING .":".self::TYPE_STRING:
            case self::TYPE_PRIMARY.":".self::TYPE_STRING:
                return (string) $value;

            default:
                return is_array($value) ? array_values(array_filter($value)) : $value;
        }
    }
}
