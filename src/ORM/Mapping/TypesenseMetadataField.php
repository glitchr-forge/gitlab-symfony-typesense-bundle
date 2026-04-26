<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Mapping;

class TypesenseMetadataField
{
    public string $name;

    public string $type;

    public bool $identifier;

    public bool $facet;

    /**
     * @var ?string
     */
    public ?string $property;

    public bool $discriminator;

    public function __construct()
    {
        $this->facet = false;
        $this->discriminator = false;
        $this->identifier = false;
    }

    public function toArray(): array
    {
        $configuration = [];
        $configuration['name'] = $this->name;
        $configuration['type'] = $this->type;
        $configuration['property'] = $this->property;
        $configuration['identifier'] = $this->identifier;
        $configuration['discriminator'] = $this->discriminator;
        $configuration['facet'] = $this->facet;

        return $configuration;
    }
}
