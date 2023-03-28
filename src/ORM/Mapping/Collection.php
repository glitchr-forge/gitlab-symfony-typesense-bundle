<?php

declare(strict_types=1);

namespace Typesense\Bundle\Client;

use Typesense\Bundle\ORM\TypesenseQuery;
use Typesense\Bundle\Manager\TypesenseManager;

class Collection
{
    protected $client;
    protected $definition;

    public function __construct(TypesenseClient $client, array $definition)
    {
        $this->client = $client;

        $this->definition = $definition;
        $this->definition = $this->addDiscriminatorColumn($this->definition);
        $this->definition = $this->addFieldIdentifiers($this->definition);
    }

    protected function addFieldIdentifiers(array $collectionDefinitions)
    {
         foreach ($collectionDefinitions as $name => $collectionDefinition) {

             //
             // Add primary keys
             foreach ($collectionDefinition['fields'] as $key => $_) {

                 $entityName = $collectionDefinition["entity"] ?? null;
                 if ($entityName && class_exists($entityName)) {

                     $classMetadata = $this->objectManager->getClassMetadata($entityName);

                     // Primary key identifier
                     foreach ($classMetadata->identifier as $identifier) {

                         $collectionDefinitions[$name]["fields"][$key] ??= [];
                         if(empty($collectionDefinitions[$name]["fields"][$key])) {
                             $collectionDefinitions[$name]["fields"][$key]["name"] = $key;
                             $collectionDefinitions[$name]["fields"][$key]["type"] = $classMetadata->getTypeOfField($key);
                             if ($classMetadata->hasField($key))
                                 $collectionDefinitions[$name]["fields"][$key]["entity_attribute"] = $key;
                         }

                         $collectionDefinitions[$name]["fields"][$key]["identifier"] = true;
                     }
                 }
             }
         }

         return $collectionDefinitions;
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
            if (!array_key_exists($discriminatorColumn, $collectionDefinitions[$name]["fields"])) {

                 $collectionDefinitions[$name]["fields"][$discriminatorColumn] = [];
                 if(empty($collectionDefinitions[$name]["fields"][$discriminatorColumn])) {

                     $collectionDefinitions[$name]["fields"][$discriminatorColumn]["name"] = $discriminatorColumn;
                     $collectionDefinitions[$name]["fields"][$discriminatorColumn]["type"] = $discriminatorType;
                     $collectionDefinitions[$name]["fields"][$discriminatorColumn]["facet"] ??= true;
                     $collectionDefinitions[$name]["fields"][$discriminatorColumn]["entity_attribute"] = $discriminatorColumn;
                 }

                 $collectionDefinitions[$name]["fields"][$discriminatorColumn]["discriminator"] = true;
            }

            foreach ($classMetadata->subClasses as $subclass) {

                $isDeclared = count(array_keys(array_column($collectionDefinitions, 'entity'), $subclass)) > 0;
                if (!$isDeclared) {

                    $basename = explode("\\", $subclass);
                    $basename = strtolower($basename[count($basename)-1]);

                    $subname = array_flip($classMetadata->discriminatorMap)[$subclass];

                    $collectionDefinitions[$subname] = [];
                    $collectionDefinitions[$subname]["name"]   = $subname;
                    $collectionDefinitions[$subname]["entity"] = $subclass;
                    $collectionDefinitions[$subname]["fields"] = $collectionDefinitions[$name]["fields"];

                    if(array_key_exists("default_sorting_field", $collectionDefinitions[$name]))
                        $collectionDefinitions[$subname]["default_sorting_field"] = $collectionDefinitions[$name]["default_sorting_field"];
                    if(array_key_exists("token_separators", $collectionDefinitions[$name]))
                        $collectionDefinitions[$subname]["token_separators"] = $collectionDefinitions[$name]["token_separators"];
                    if(array_key_exists("symbols_to_index", $collectionDefinitions[$name]))
                        $collectionDefinitions[$subname]["symbols_to_index"] = $collectionDefinitions[$name]["symbols_to_index"];
                }
            }
        }

        return $collectionDefinitions;
    }

    public function search(TypesenseQuery $query)
    {
        if (!$this->client->isOperationnal()) {
            return null;
        }

        return $this->client->collections[$collectionName]->documents->search($query->getParameters());
    }

    public function multiSearch(array $searchRequests, ?TypesenseQuery $commonSearchParams)
    {
        if (!$this->client->isOperationnal()) {
            return null;
        }

        $searches = [];
        foreach ($searchRequests as $sr) {
            if (!$sr instanceof TypesenseQuery) {
                throw new \Exception('searchRequests must be an array  of TypesenseQuery objects');
            }
            if (!$sr->hasParameter('collection')) {
                throw new \Exception('TypesenseQuery must have the key : `collection` in order to perform multiSearch');
            }
            $searches[] = $sr->getParameters();
        }

        return $this->client->multiSearch->perform(
            [
                'searches' => $searches,
            ],
            $commonSearchParams ? $commonSearchParams->getParameters() : []
        );
    }

    public function list()
    {
        if (!$this->client->isOperationnal()) {
            return null;
        }

        return $this->client->collections->retrieve();
    }

    public function create($name, $fields, $defaultSortingField, array $tokenSeparators, array $symbolsToIndex)
    {
        if (!$this->client->isOperationnal()) {
            return null;
        }

        $collectionDefinition = [];
        $collectionDefinition["name"]                  = $name;
        $collectionDefinition["fields"]                = $fields;

        if ($defaultSortingField)
            $collectionDefinition["default_sorting_field"] = $defaultSortingField;

        if ($tokenSeparators)
            $collectionDefinition["token_separators"]      = $tokenSeparators;

        if ($symbolsToIndex)
            $collectionDefinition["symbols_to_index"]      = $symbolsToIndex;

        if($fields)
            $this->client->collections->create($collectionDefinition);
    }

    public function delete(string $name)
    {
        if (!$this->client->isOperationnal()) {
            return null;
        }

        return $this->client->collections[$name]->delete();
    }
}
