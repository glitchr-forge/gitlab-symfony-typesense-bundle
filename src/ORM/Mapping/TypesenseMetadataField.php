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

class TypesenseMetadataField
{
    /**
     * @var string
     */
    public string $name;

    /**
     * @var string
     */
    public string $type;

    /**
     * @var bool
     */
    public bool $identifier;

    /**
     * @var bool
     */
    public bool $facet;

    /**
     * @var ?string
     */
    public ?string $property;

    /**
     * @var bool
     */
    public bool $discriminator;

    public function __construct()
    {
        $this->facet = false;
        $this->discriminator = false;
        $this->identifier = false;
    }
    public function toArray():array {

        $configuration = [];
        $configuration["name"]          = $this->name;
        $configuration["type"]          = $this->type;
        $configuration["property"]      = $this->property;
        $configuration["identifier"]    = $this->identifier;
        $configuration["discriminator"] = $this->discriminator;
        $configuration["facet"]         = $this->facet;

        return $configuration;
    }
}
