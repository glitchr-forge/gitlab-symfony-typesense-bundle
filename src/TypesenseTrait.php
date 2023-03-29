<?php

namespace Typesense\Bundle;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\PropertyAccess\PropertyAccess;

trait TypesenseTrait
{
    public function __typesenseGetter(string $propertyName, array $propertyInfo): mixed
    {
        $accessor = PropertyAccess::createPropertyAccessorBuilder()->enableMagicCall()->getPropertyAccessor();

        // Look for property value based on property path
        $value = $this;
        $propertyPath = explode(".", $propertyName);
        foreach ($propertyPath as $propertyName) {
            $value = $value instanceof Collection
                ? $value->Map(fn ($v) => $accessor->getValue($v, $propertyName))
                : $accessor->getValue($value, $propertyName);
        }

        // Turn collections into array
        $propertyName = implode(".", $propertyPath);
        if ($value instanceof Collection) {
            $value = $value->Map(function ($v) use ($propertyName) {
                if ($v instanceof TypesenseInterface) {
                    return $v->__typesense();
                }
                if (is_object($v) && method_exists($v, "__toString")) {
                    return $v->__toString();
                }

                return $v;
            })->toArray();
        }

        // Flatten array
        $arrayCast = str_ends_with($propertyInfo["type"], "[]") && !is_array($value);
        $v = $arrayCast ? [$value] : $value;
        if (is_array($v)) {
            $flattenArray = [];
            array_walk_recursive($v, function ($a) use (&$flattenArray) {
                $flattenArray[] = $a;
            });

            return $flattenArray;
        }

        return $v;
    }
}
