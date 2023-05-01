<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Transformer\Abstract;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;

abstract class AbstractTransformer implements TransformerInterface
{
    protected PropertyAccessorInterface $accessor;
    protected ObjectManager $objectManager;

    protected array $mapping;

    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
        $this->mapping = [];

        $this->accessor = PropertyAccess::createPropertyAccessorBuilder()->enableMagicCall()->getPropertyAccessor();
    }

    public function getObjectManager(): ObjectManager
    {
        return $this->objectManager;
    }

    public function getMapping($className): ?TypesenseMetadata
    {
        return $this->mapping[$className] ?? null;
    }

    public function addMapping(TypesenseMetadata $metadata)
    {
        $this->mapping[$metadata->class] = $metadata;

        return $this;
    }

    /**
     * Convert an object to a array of data indexable by typesense.
     *
     * @param object $entity the object to convert
     *
     * @return array the converted data
     */
    abstract public function convert(object $entity): array;

    /**
     * Convert a value to an acceptable value for typesense.
     *
     * @param string $objectClass the object class name
     * @param string $propertyName the property of the object
     * @param [type] $value the value to convert
     */
    abstract public function get(string $objectClass, string $propertyName, $value);

    /**
     * map a type to a typesense type field.
     */
    public function cast(string $type): string
    {
        if (self::TYPE_COLLECTION === $type) {
            return self::TYPE_STRING_ARRAY;
        }
        if (self::TYPE_DATETIME === $type) {
            return self::TYPE_INT64;
        }
        if (self::TYPE_INTEGER === $type) {
            return self::TYPE_INT32;
        }
        if (self::TYPE_OBJECT === $type) {
            return self::TYPE_STRING;
        }
        if (self::TYPE_TEXT === $type) {
            return self::TYPE_STRING;
        }

        return $type;
    }
}
