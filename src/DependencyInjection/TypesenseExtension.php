<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\DependencyInjection;

use Base\Service\Model\IconProvider\AbstractIconAdapter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\UX\Typesense\Client\TypesenseClient;

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

    private array $connections = [];

    public function load(array $configs, ContainerBuilder $container)
    {
        // Format XML
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        // Configuration file: ./config/package/base.yaml
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);

        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->getRootNode()->getNode()->getName());

        $defaultConnection = $config["default_connection"];
        foreach($config["connections"] as $connectionName => $configuration) {

            $this
                ->initialize($connectionName)

                ->loadCollections($connectionName, $configuration['collections'], $container)
                ->loadCollectionsFinder($connectionName, $container)
                ->loadClient($connectionName, $configuration, $container)

                ->loadFinderServices($connectionName, $container)

                ->configureController($connectionName, $container);
        }
    }

    public function initialize(string $connectionName)
    {
        $this->collectionDefinitions[$connectionName] = [];
        $this->finderConfig[$connectionName] = [];
        $this->parameters[$connectionName] = [];

        return $this;
    }

    public function setConfiguration(ContainerBuilder $container, array $config, $globalKey = "")
    {
        foreach ($config as $key => $value) {
            if (!empty($globalKey)) {
                $key = $globalKey . "." . $key;
            }

            if (is_array($value)) {
                $this->setConfiguration($container, $value, $key);
            } else {
                $container->setParameter($key, $value);
            }
        }

        return $this;
    }

    /**
     * Loads the configured clients.
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    private function loadClient(string $connectionName, array $config, ContainerBuilder $container)
    {
        $clientId  = sprintf('typesense.client.%s', $connectionName);
        $clientDef = new ChildDefinition('typesense.client');
        $clientDef->replaceArgument(0, $connectionName);
        $clientDef->replaceArgument(1, $this->collectionDefinitions[$connectionName]);

        $container->setDefinition($clientId, $clientDef);
        $clientDef->addTag("typesense.client");

        return $this;
    }

    /**
     * Loads the configured collection.
     *
     * @param array            $collections An array of collection configurations
     * @param ContainerBuilder $container   A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException
     */
    private function loadCollections(string $connectionName, array $collections, ContainerBuilder $container)
    {
        $this->parameters[$connectionName]['collection_prefix'] = $config['collection_prefix'] ?? '';

        foreach ($collections as $name => $config) {

            $collectionName = $this->parameters[$connectionName]['collection_prefix'] . ($config['collection_name'] ?? $name);

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
                    'entity_attribute' => 'id',
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

                    $this->finderConfig[$connectionName][$finderName] = $finderConfig;
                }
            }

            $this->collectionDefinitions[$connectionName][$name] = [
                'typesense_name'        => $collectionName,
                'entity'                => $config['entity'],
                'name'                  => $name,
                'fields'                => $config['fields'],
                'default_sorting_field' => $config['default_sorting_field'],
                'token_separators'      => $config['token_separators'],
                'symbols_to_index'      => $config['symbols_to_index'],
            ];
        }

        return $this;
    }

    /**
     * Loads the configured index finders.
     */
    private function loadCollectionsFinder(string $connectionName, ContainerBuilder $container)
    {
        foreach ($this->collectionDefinitions[$connectionName] as $name => $config) {

            $collectionName = $config['name'];

            $finderId  = sprintf('typesense.finder.%s.%s', $connectionName, $collectionName);
            $finderDef = new ChildDefinition('typesense.finder');
            $finderDef->replaceArgument(2, $config);

            $finderDef->addTag("typesense.finder");

            $container->setDefinition($finderId, $finderDef);
        }

        return $this;
    }

    /**
     * Loads the configured Finder services.
     */
    private function loadFinderServices(string $connectionName, ContainerBuilder $container)
    {
        foreach ($this->finderConfig[$connectionName] as $name => $config) {
            $finderName     = $config['finder_name'];
            $collectionName = $config['name'];
            $finderId       = sprintf('typesense.finder.%s.%s', $connectionName, $collectionName);

            if (isset($config['finder_service'])) {
                $finderId = $config['finder_service'];
            }

            $specificFinderId  = sprintf('typesense.specific_finder.%s.%s', $connectionName, $finderName);
            $specificFinderDef = new ChildDefinition('typesense.specific_finder');
            $specificFinderDef->replaceArgument(0, new Reference($finderId));
            $specificFinderDef->replaceArgument(1, $config['finder_parameters']);

            $container->setDefinition($specificFinderId, $specificFinderDef);
        }

        return $this;
    }

    private function configureController(string $connectionName, ContainerBuilder $container)
    {
        $finderServices = [];
        foreach ($this->finderConfig[$connectionName] as $name => $config) {
            $finderName                  = $config['finder_name'];
            $finderId                    = sprintf('typesense.specific_finder.%s.%s', $connectionName, $finderName);
            $finderServices[$finderName] = new Reference($finderId);
        }

        $controllerDef = $container->getDefinition('typesense.autocomplete_controller');
        $controllerDef->replaceArgument(0, $finderServices);

        return $this;
    }
}
