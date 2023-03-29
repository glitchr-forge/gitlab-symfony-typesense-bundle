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

class TypesenseMetadata
{
    public function getName(): string { return $this->name; }
    public function getClassNames(?string $connectionName = null) :array
    {
        $classNames = [];
        foreach ($this->getConnection($connectionName)->getCollections() as $name => $collection)
        {
            $collectionName = $collection->getMetadata()->getName();
            $classNames[$collectionName] = $collection->getMetadata()->getClassName();
        }

        return $classNames;
    }

    protected function addFieldIdentifiers(array $metadata)
    {
         foreach ($metadata as $name => $collectionDefinition) {

             //
             // Add primary keys
             foreach ($collectionDefinition['fields'] as $key => $_) {

                 $entityName = $collectionDefinition["entity"] ?? null;
                 if ($entityName && class_exists($entityName)) {

                     $classMetadata = $this->objectManager->getClassMetadata($entityName);

                     // Primary key identifier
                     foreach ($classMetadata->identifier as $identifier) {

                         $metadata[$name]["fields"][$key] ??= [];
                         if(empty($metadata[$name]["fields"][$key])) {
                             $metadata[$name]["fields"][$key]["name"] = $key;
                             $metadata[$name]["fields"][$key]["type"] = $classMetadata->getTypeOfField($key);
                             if ($classMetadata->hasField($key))
                                 $metadata[$name]["fields"][$key]["entity_attribute"] = $key;
                         }

                         $metadata[$name]["fields"][$key]["identifier"] = true;
                     }
                 }
             }
         }

         return $metadata;
    }

    protected function addDiscriminatorColumn()
    {
         $entityName = $collectionDefinition["entity"] ?? null;
        if ($entityName) {

            $classMetadata = $this->objectManager->getClassMetadata($entityName);
            $discriminatorColumn = $classMetadata->discriminatorColumn["name"];
            $discriminatorType   = $classMetadata->discriminatorColumn["type"] ?? "string";
            $discriminatorValue  = $classMetadata->discriminatorValue;

            // Discriminator
            if (!array_key_exists($discriminatorColumn, $metadata[$name]["fields"])) {

                 $metadata[$name]["fields"][$discriminatorColumn] = [];
                 if(empty($metadata[$name]["fields"][$discriminatorColumn])) {

                     $metadata[$name]["fields"][$discriminatorColumn]["name"] = $discriminatorColumn;
                     $metadata[$name]["fields"][$discriminatorColumn]["type"] = $discriminatorType;
                     $metadata[$name]["fields"][$discriminatorColumn]["facet"] ??= true;
                     $metadata[$name]["fields"][$discriminatorColumn]["entity_attribute"] = $discriminatorColumn;
                 }

                 $metadata[$name]["fields"][$discriminatorColumn]["discriminator"] = true;
            }

            foreach ($classMetadata->subClasses as $subclass) {

                $isDeclared = count(array_keys(array_column($metadata, 'entity'), $subclass)) > 0;
                if (!$isDeclared) {

                    $basename = explode("\\", $subclass);
                    $basename = strtolower($basename[count($basename)-1]);

                    $subname = array_flip($classMetadata->discriminatorMap)[$subclass];

                    $metadata[$subname] = [];
                    $metadata[$subname]["name"]   = $subname;
                    $metadata[$subname]["entity"] = $subclass;
                    $metadata[$subname]["fields"] = $metadata[$name]["fields"];

                    if(array_key_exists("default_sorting_field", $metadata[$name]))
                        $metadata[$subname]["default_sorting_field"] = $metadata[$name]["default_sorting_field"];
                    if(array_key_exists("token_separators", $metadata[$name]))
                        $metadata[$subname]["token_separators"] = $metadata[$name]["token_separators"];
                    if(array_key_exists("symbols_to_index", $metadata[$name]))
                        $metadata[$subname]["symbols_to_index"] = $metadata[$name]["symbols_to_index"];
                }
            }
        }

        return $metadata;
    }
}
