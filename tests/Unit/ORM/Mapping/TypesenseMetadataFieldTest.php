<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Unit\ORM\Mapping;

use PHPUnit\Framework\TestCase;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadataField;

class TypesenseMetadataFieldTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $field = new TypesenseMetadataField();

        $this->assertFalse($field->facet);
        $this->assertFalse($field->discriminator);
        $this->assertFalse($field->identifier);
    }

    public function testToArrayExposesAllProperties(): void
    {
        $field = new TypesenseMetadataField();
        $field->name = 'title';
        $field->type = 'string';
        $field->property = 'title';
        $field->identifier = true;

        $this->assertSame([
            'name' => 'title',
            'type' => 'string',
            'property' => 'title',
            'identifier' => true,
            'discriminator' => false,
            'facet' => false,
        ], $field->toArray());
    }

    /**
     * $property is a nullable typed property with no constructor default —
     * PHP's null-coalescing operators (??, ??=) are special-cased to treat
     * an uninitialized typed property as unset rather than throwing, so
     * toArray() must be safe to call even before ->property is ever
     * assigned (TypesenseMetadata's own constructor relies on exactly this
     * via `$field->property ??= ...`).
     */
    public function testToArrayIsSafeWithoutEverSettingProperty(): void
    {
        $field = new TypesenseMetadataField();
        $field->name = 'title';
        $field->type = 'string';

        $this->assertNull($field->toArray()['property']);
    }
}
