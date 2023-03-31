<?php

declare(strict_types=1);

namespace Typesense\Bundle\ORM\Mapping;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Typesense\Bundle\Exception\TypesenseException;
use Typesense\Bundle\ORM\CollectionFinder;
use Typesense\Bundle\DBAL\Collections;
use Typesense\Bundle\DBAL\Documents;
use Typesense\Bundle\ORM\Transformer\Abstract\TransformerInterface;
use Typesense\Aliases;
use Typesense\Client;
use Typesense\Debug;
use Typesense\Health;
use Typesense\Keys;
use Typesense\Metrics;
use Typesense\MultiSearch;
use Typesense\Operations;

class TypesenseMetadata extends TypesenseMetadataInfo
{
    protected TransformerInterface $transformer;
    protected ObjectManager $objectManager;

    public function __construct(string $name, array $configuration, TransformerInterface $transformer)
    {
        $this->name                = $name;
        $this->class               = $configuration["class"] ?? null;
        $this->transformer         = $transformer;
        $this->objectManager       = $transformer->getObjectManager();

        $this->fields              = [];
        foreach($configuration["fields"] ?? [] as $fieldName => $field) {

            if($fieldName == "id") {
                throw new TypesenseException("Field 'id' cannot be used. It is a reserved string field.");
            }

            if($field instanceof TypesenseMetadataField) {
                $this->fields[$fieldName] = $field;
            }

            if (!array_key_exists($fieldName, $this->fields)) {

                $this->fields[$fieldName] = new TypesenseMetadataField();
                $this->fields[$fieldName]->name ??= $fieldName;
                $this->fields[$fieldName]->type ??= $field["type"] ?? "string";
                $this->fields[$fieldName]->property ??= $field["property"] ?? null;
                $this->fields[$fieldName]->facet = $field["facet"] ?? false;
                $this->fields[$fieldName]->discriminator = false;
                $this->fields[$fieldName]->identifier = false;
            }
        }

        $this->addIdentifierColumns();
        $this->addDiscriminatorColumn();

        $id = first($this->fields)->name;
        foreach($this->fields as $name => $field) {

           if ($field->identifier && $field->name != "id") {
               $id = $field->name;
               break;
           }
        }

        $this->defaultSortingField = $configuration["default_sorting_field"] ?? $id;
        $this->symbolsToIndex      = $configuration["symbols_to_index"] ?? [];
        $this->tokenSeparators     = $configuration["token_separators"] ?? [];
    }

    public function getName(): string { return $this->name; }
    public function getConfiguration(): array {

        $configuration = [];
        $configuration["name"]                  = $this->name;
        $configuration["fields"]                = $this->fields;
        $configuration["default_sorting_field"] = $this->defaultSortingField;
        $configuration["token_separators"]      = $this->tokenSeparators;
        $configuration["symbols_to_index"]      = $this->symbolsToIndex;

        return $configuration;
    }

    public function getObjectManager(): ObjectManager { return $this->objectManager; }

    public function getClass(): string { return $this->class; }
    public function getTransformer(): TransformerInterface { return $this->transformer; }

    protected function addIdentifierColumns()
    {
         $className = $this->class ?? null;
         if ($className && class_exists($className)) {

             $classMetadata = $this->objectManager->getClassMetadata($className);

             // identifier key identifier
             foreach ($classMetadata->identifier as $id => $identifier) {

                 if(array_key_exists($identifier, $this->fields)) {
                     $this->fields[$identifier]->identifier = true;
                     continue;
                 }

                 if($id == 0) {

                     $name = "id";
                     $this->fields[$name] = new TypesenseMetadataField();
                     $this->fields[$name]->name = "id";
                     $this->fields[$name]->type = "string";
                     $this->fields[$name]->identifier = true;

                     if ($classMetadata->hasField($identifier))
                         $this->fields[$name]->property = $identifier;
                 }

                 $name = ($id == 0) ? "primary" : "identifier[".$id."]";
                 $this->defaultSortingField ??= $name;

                 $this->fields[$name] = new TypesenseMetadataField();
                 $this->fields[$name]->name ??= $name;
                 $this->fields[$name]->type ??= $classMetadata->getTypeOfField($identifier);
                 $this->fields[$name]->identifier = true;

                 if ($classMetadata->hasField($identifier))
                     $this->fields[$name]->property = $identifier;
             }
         }

         return $this;
    }

    protected function addDiscriminatorColumn()
    {
         $className = $this->class ?? null;
         if ($className && class_exists($className)) {

             $classMetadata = $this->objectManager->getClassMetadata($className);

             $discriminatorColumn = $classMetadata->discriminatorColumn["name"];
             $discriminatorType = $classMetadata->discriminatorColumn["type"] ?? "string";
             $discriminatorValue = $classMetadata->discriminatorValue;

             if (!array_key_exists($discriminatorColumn, $this->fields))
                 $this->fields[$discriminatorColumn] = new TypesenseMetadataField();

             $this->fields[$discriminatorColumn]->name ??= $discriminatorColumn;
             $this->fields[$discriminatorColumn]->type ??= $discriminatorType;
             $this->fields[$discriminatorColumn]->property ??= $discriminatorColumn;
             $this->fields[$discriminatorColumn]->discriminator = true;
             $this->fields[$discriminatorColumn]->facet = true;
         }

        return $this;
    }

    public function getSubMetadata(): array
    {
         $metadata = [];
         $className = $this->class ?? null;
         if ($className) {

             $classMetadata = $this->objectManager->getClassMetadata($className);
             foreach ($classMetadata->subClasses as $subclass) {

                 $isDeclared = count(array_keys(array_column($metadata, 'class'), $subclass)) > 0;
                 if (!$isDeclared) {

                     $basename = explode("\\", $subclass);
                     $basename = strtolower($basename[count($basename) - 1]);

                     $subname = array_flip($classMetadata->discriminatorMap)[$subclass];

                     $configuration = [];
                     $configuration["name"] = $this->name."%".$subname;
                     $configuration["class"] = $subclass;
                     $configuration["fields"] = array_filter($this->fields, fn($k) => $k != "id", ARRAY_FILTER_USE_KEY);
                     $configuration["default_sorting_field"] = $this->defaultSortingField;
                     $configuration["token_separators"] = $this->tokenSeparators;
                     $configuration["symbols_to_index"] = $this->symbolsToIndex;

                     $metadata[] = new TypesenseMetadata($this->name."%".$subname, $configuration, $this->transformer);
                 }
             }
         }

        return $metadata;
    }
}
