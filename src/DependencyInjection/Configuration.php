<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    private $treeBuilder;
    public function getTreeBuilder(): TreeBuilder
    {
        return $this->treeBuilder;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $this->treeBuilder = new TreeBuilder('typesense');
        $this->treeBuilder->getRootNode()
            ->children()
                ->arrayNode('server')
                    ->info('Typesense server information')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('secret')->defaultNull()->end()
                        ->scalarNode('host')->defaultValue("localhost")->end()
                        ->scalarNode('port')->defaultValue(8108)->end()
                        ->scalarNode('use_https')->defaultFalse()->end()
                        ->arrayNode('options')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('collection_prefix')->end()
                    ->end()
                ->end()
                ->arrayNode('collections')
                    ->info('Collection definition')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('collection_name')->end()
                            ->scalarNode('entity')->end()
                            ->arrayNode('fields')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('entity_attribute')->end()
                                        ->scalarNode('name')->end()
                                        ->scalarNode('type')->end()
                                        ->booleanNode('facet')->end()
                                        ->booleanNode('optional')->end()
                                        ->booleanNode('infix')->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('default_sorting_field')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('finders')
                                ->info('Entity specific finders declaration')
                                ->useAttributeAsKey('name')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('finder_service')->end()
                                        ->arrayNode('finder_parameters')
                                            ->scalarPrototype()->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('token_separators')
                                ->defaultValue([])
                                ->scalarPrototype()->end()
                            ->end()
                            ->arrayNode('symbols_to_index')
                                ->defaultValue([])
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $this->treeBuilder;
    }
}
