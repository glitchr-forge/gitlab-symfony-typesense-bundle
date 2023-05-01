<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Transformer;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\Transformer\Abstract\AbstractTransformer;
use Typesense\Bundle\TypesenseInterface;

class EntityTransformer extends AbstractTransformer
{
    public function convert(object $entity): array
    {
        $entityClass = ClassUtils::getClass($entity);
        if (!$entity instanceof TypesenseInterface) {
            throw new \Exception('Class '.$entityClass.' does not implement "'.TypesenseInterface::class.'"');
        }

        if (!$this->getMapping($entityClass) instanceof TypesenseMetadata) {
            throw new \Exception(sprintf('Class %s is not supported for Doctrine To Typesense Transformation', $entityClass));
        }

        $data = [];

        $fields = $this->getMapping($entityClass)->fields;
        foreach ($fields as $key => $field) {
            try {
                if ($field->discriminator) {
                    $value = $this->objectManager->getClassMetadata(get_class($entity))->discriminatorValue;
                } else {
                    $value = $entity->__typesenseGetter($field->property ?? $field->name ?? $key, $field->toArray());
                }
            } catch (RuntimeException $exception) {
                $value = null;
            }

            $name = $field->name ?? $key;

            $data[$name] = $this->get($entityClass, $name, $value);
        }

        return $data;
    }

    public function get(string $objectClass, string $propertyName, $value)
    {
        $metadata = $this->getMapping($objectClass);
        $metadata->fields[$propertyName]->name ??= $propertyName;

        $key = array_search(
            $propertyName,
            array_column(
                $metadata->fields,
                'name'
            ),
            true
        );

        $fields = array_values($metadata->fields);
        $originalType = $fields[$key]->type;
        $castedType = $this->cast($originalType);

        switch ($originalType.':'.$castedType) {
            case self::TYPE_DATETIME.':'.self::TYPE_INT64:
                if ($value instanceof \DateTime) {
                    return $value->getTimestamp();
                }

                return 0;

            case self::TYPE_OBJECT.':'.self::TYPE_STRING:
                return $value->__toString();

            case self::TYPE_COLLECTION.':'.self::TYPE_STRING_ARRAY:
                return array_filter(array_values(
                    $value->map(function ($v) {
                        return $v->__toString();
                    })->toArray()
                )) ?? [];

            case self::TYPE_STRING.':'.self::TYPE_STRING:
            case self::TYPE_TEXT.':'.self::TYPE_STRING:
                return (string) $value;

            default:
                return is_array($value) ? array_values(array_filter($value)) : $value;
        }
    }
}
