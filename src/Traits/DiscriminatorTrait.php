<?php

namespace Symfony\UX\Typesense\Traits;

trait DiscriminatorTrait
{
    public function extendsSubclasses(array $collectionDefinitions)
    {
        foreach ($collectionDefinitions as $name => $def) {

            $entityName = $def["entity"] ?? null;
            if($entityName) {

                $classMetadata = $this->entityManager->getClassMetadata($entityName);
                $discriminatorColumn = $classMetadata->discriminatorColumn["name"];
                $discriminatorValue  = $classMetadata->discriminatorValue;

                if(!array_key_exists($discriminatorColumn, $collectionDefinitions[$name]["fields"])) {

                    $collectionDefinitions[$name]["fields"][$discriminatorColumn] = [
                        "name" => $discriminatorColumn,
                        "discriminator" => true,
                        "type" => "string",
                        "facet" => true,
                        "entity_attribute" => $discriminatorColumn
                    ];
                }

                foreach($classMetadata->subClasses as $subclass) {

                    $isDeclared = count(array_keys(array_column($collectionDefinitions, 'entity'), $subclass)) > 0;
                    if(!$isDeclared) {

                        $basename = explode("\\", $subclass);
                        $basename = strtolower($basename[count($basename)-1]);

                        $subname = array_flip($classMetadata->discriminatorMap)[$subclass];
                        $collectionDefinitions[$subname] = [
                            'typesense_name'        => $subname,
                            'entity'                => $subclass,
                            'name'                  => $subname,
                            'fields'                => $collectionDefinitions[$name]["fields"],
                            'default_sorting_field' => $collectionDefinitions[$name]["default_sorting_field"],
                            'token_separators'      => $collectionDefinitions[$name]["token_separators"],
                            'symbols_to_index'      => $collectionDefinitions[$name]["symbols_to_index"],
                        ];
                    }
                }
            }
        }

        return $collectionDefinitions;
    }
}