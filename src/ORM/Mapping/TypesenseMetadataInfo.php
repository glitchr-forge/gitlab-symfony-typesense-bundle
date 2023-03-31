<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Mapping;

use Doctrine\ORM\ObjectManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Typesense\Bundle\Exception\TypesenseException;
use Typesense\Bundle\ORM\CollectionFinder;
use Typesense\Bundle\DBAL\Collections;
use Typesense\Bundle\DBAL\Documents;
use Typesense\Bundle\Transformer\DoctrineToTypesenseTransformer;
use Typesense\Aliases;
use Typesense\Client;
use Typesense\Debug;
use Typesense\Health;
use Typesense\Keys;
use Typesense\Metrics;
use Typesense\MultiSearch;
use Typesense\Operations;

class TypesenseMetadataInfo
{
    /**
     * @var string
     */
    public string $name;

    /**
     * @var ?string
     */
    public ?string $class;

    /**
     * @var string
     */
    public array $fields;

    /**
     * @var ?string
     */
    public ?string $defaultSortingField = null;

    /**
     * @var array
     */
    public array $symbolsToIndex = [];

    /**
     * @var array
     */
    public $tokenSeparators = [];
}
