<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Transformer\Abstract;

use Doctrine\Persistence\ObjectManager;

interface TransformerInterface
{
    public const TYPE_COLLECTION   = 'collection';
    public const TYPE_DATETIME     = 'datetime';
    public const TYPE_OBJECT       = 'object';
    public const TYPE_INTEGER      = 'integer';
    public const TYPE_TEXT         = 'text';

    public const TYPE_STRING         = 'string';     // String values
    public const TYPE_STRING_ARRAY   = 'string[]';   // Array of strings
    public const TYPE_INT32          = 'int32';      // Integer values up to 2,147,483,647
    public const TYPE_INT32_ARRAY    = 'int32[]';    // Array of int32
    public const TYPE_INT64          = 'int64';      // Integer values larger than 2,147,483,647
    public const TYPE_INT64_ARRAY    = 'int64[]';    // Array of int64
    public const TYPE_FLOAT          = 'float';      // Floating point / decimal numbers
    public const TYPE_FLOAT_ARRAY    = 'float[]';    // Array of floating point / decimal numbers
    public const TYPE_BOOLEAN        = 'bool';       // true or false
    public const TYPE_BOOLEAN_ARRAY  = 'bool[]';     // Array of booleans
    public const TYPE_GEOPOINT       = 'geopoint';   // Latitude and longitude specified as [lat, lng]
    public const TYPE_GEOPOINT_ARRAY = 'geopoint[]'; // Arrays of Latitude and longitude specified as [[lat1, lng1], [lat2, lng2]]
    public const TYPE_STRING_PTR     = 'string*';    // Special type that automatically converts values to a string or string[].
    public const TYPE_AUTO           = 'auto';       // Special type that automatically attempts to infer the data type based on the documents added to the collection. See automatic schema detection.

    /**
     * Convert an object to a array of data indexable by typesense.
     *
     * @param object $entity the object to convert
     *
     * @return array the converted data
     */
    public function convert(object $entity): array;

    /**
     * Convert a value to an acceptable value for typesense.
     *
     * @param string $objectClass the object class name
     * @param string $properyName the property of the object
     * @param [type] $value the value to convert
     */
    public function get(string $objectClass, string $properyName, $value);

    /**
     * map a type to a typesense type field.
     */
    public function cast(string $type): string;

    public function getObjectManager(): ObjectManager;
}
