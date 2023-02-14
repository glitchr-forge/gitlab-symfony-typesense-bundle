<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class TypesenseExtension extends Extension
{
    /**
     * An array of collections as configured by the extension.
     *
     * @var array
     */
    private $collectionDefinitions = [];

    /**
     * An array of finder as configured by the extension.
     *
     * @var array
     */
    private $finderConfig = [];

    /**
     * An array of parameters to use as configured by the extension.
     *
     * @var array
     */
    private $parameters = [];

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function load(array $configs, ContainerBuilder $container)
    {
        // Format XML
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        // Configuration file: ./config/package/base.yaml
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);

        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->getRootNode()->getNode()->getName());

        $this->loadClient($config['server'], $container);

        $this->loadCollections($config['collections'], $container);
        $this->loadCollectionManager($container);
        $this->loadCollectionsFinder($container);

        $this->loadFinderServices($container);

        $this->loadTransformer($container);
        $this->configureController($container);
    }

    public function setConfiguration(ContainerBuilder $container, array $config, $globalKey = "")
    {
        foreach ($config as $key => $value) {

            if (!empty($globalKey)) $key = $globalKey . "." . $key;

            if (is_array($value)) $this->setConfiguration($container, $value, $key);
            else $container->setParameter($key, $value);
        }
    }

    /**
     * Loads the configured clients.
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    private function loadClient($config, ContainerBuilder $container)
    {
        $this->parameters['collection_prefix'] = $config['collection_prefix'] ?? '';
    }

    /**
     * Loads the configured collection.
     *
     * @param array            $collections An array of collection configurations
     * @param ContainerBuilder $container   A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException
     */
    private function loadCollections(array $collections, ContainerBuilder $container)
    {
        foreach ($collections as $name => $config) {
            $collectionName = $this->parameters['collection_prefix'] . ($config['collection_name'] ?? $name);

            $primaryKeyExists = false;

            foreach ($config['fields'] as $key => $fieldConfig) {

                $fieldConfig["name"] = $key;
                if (!isset($fieldConfig['type'])) {
                    throw new \Exception('typesense.collections.'.$name.'.'.$key.'.type must be set');
                }

                if ($fieldConfig['type'] === 'primary') {
                    $primaryKeyExists = true;
                }
                if (!isset($fieldConfig['entity_attribute'])) {
                    $config['fields'][$key]['entity_attribute'] = $key;
                }
            }

            if (!$primaryKeyExists) {

                $config['fields']['id'] = [
                    'name' => 'id',
                    'entity_id' => 'id',
                    'type' => 'primary'
                ];
            }

            if (isset($config['finders'])) {

                foreach ($config['finders'] as $finderName => $finderConfig) {

                    $finderName                      = $name.'.'.$finderName;
                    $finderConfig['collection_name'] = $collectionName;
                    $finderConfig['name']            = $name;
                    $finderConfig['finder_name']     = $finderName;


                    if (!isset($finderConfig['finder_parameters']['query_by'])) {
                        throw new \Exception('typesense.collections.'.$finderName.'.finder_parameters.query_by must be set');
                    }

                    $this->finderConfig[$finderName] = $finderConfig;
                }
            }

            $this->collectionDefinitions[$name] = [
                'typesense_name'        => $collectionName,
                'entity'                => $config['entity'],
                'name'                  => $name,
                'fields'                => $config['fields'],
                'default_sorting_field' => $config['default_sorting_field'],
                'token_separators'      => $config['token_separators'],
                'symbols_to_index'      => $config['symbols_to_index'],
            ];
        }

    }

    /**
     * Loads the collection manager.
     */
    private function loadCollectionManager(ContainerBuilder $container)
    {
        $managerDef = $container->getDefinition('typesense.collection_manager');
        $managerDef->replaceArgument(3, $this->collectionDefinitions);
    }

    /**
     * Loads the transformer.
     */
    private function loadTransformer(ContainerBuilder $container)
    {
        $managerDef = $container->getDefinition('typesense.transformer.doctrine_to_typesense');
        $managerDef->replaceArgument(1, $this->collectionDefinitions);
    }

    /**
     * Loads the configured index finders.
     */
    private function loadCollectionsFinder(ContainerBuilder $container)
    {
        foreach ($this->collectionDefinitions as $name => $config) {
            $collectionName = $config['name'];

            $finderId  = sprintf('typesense.finder.%s', $collectionName);
            $finderId  = sprintf('typesense.finder.%s', $name);
            $finderDef = new ChildDefinition('typesense.finder');
            $finderDef->replaceArgument(2, $config);

            $container->setDefinition($finderId, $finderDef);
        }
    }

    /**
     * Loads the configured Finder services.
     */
    private function loadFinderServices(ContainerBuilder $container)
    {
        foreach ($this->finderConfig as $name => $config) {

            $finderName     = $config['finder_name'];
            $collectionName = $config['name'];
            $finderId       = sprintf('typesense.finder.%s', $collectionName);

            if (isset($config['finder_service'])) {
                $finderId = $config['finder_service'];
            }

            $specificFinderId  = sprintf('typesense.specificfinder.%s', $finderName);
            $specificFinderDef = new ChildDefinition('typesense.specificfinder');
            $specificFinderDef->replaceArgument(0, new Reference($finderId));
            $specificFinderDef->replaceArgument(1, $config['finder_parameters']);

            $container->setDefinition($specificFinderId, $specificFinderDef);
        }
    }

    private function configureController(ContainerBuilder $container)
    {
        $finderServices = [];
        foreach ($this->finderConfig as $name => $config) {
            $finderName                  = $config['finder_name'];
            $finderId                    = sprintf('typesense.specificfinder.%s', $finderName);
            $finderServices[$finderName] = new Reference($finderId);

        }

        $controllerDef = $container->getDefinition('typesense.autocomplete_controller');
        $controllerDef->replaceArgument(0, $finderServices);
    }
}
