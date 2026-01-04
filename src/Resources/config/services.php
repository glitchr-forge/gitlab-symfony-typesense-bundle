<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Typesense\Bundle\ORM\TypesenseManager;
use Typesense\Bundle\ORM\TypesenseManagerInterface;
use Typesense\Bundle\DBAL\Connection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\TypesenseFinder;
use Typesense\Bundle\EventListener\TypesenseIndexer;
use Typesense\Bundle\Command\CreateCommand;
use Typesense\Bundle\Command\ListCommand;
use Typesense\Bundle\Command\HealthCommand;
use Typesense\Bundle\Command\ActionCommand;
use Typesense\Bundle\Command\UpdateCommand;
use Typesense\Bundle\ORM\Transformer\Abstract\AbstractTransformer;
use Typesense\Bundle\ORM\Transformer\EntityTransformer;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // defaults
    $services
        ->defaults()
        ->public(true);

    /*
     * Aliases
     */
    $services->alias('typesense_manager', TypesenseManager::class);
    $services->alias(TypesenseManagerInterface::class, 'typesense_manager');

    /*
     * TypesenseManager
     */
    $services
        ->set(TypesenseManager::class)
        ->public(true);

    /*
     * Abstract connection
     */
    $services
        ->set('typesense.connection', Connection::class)
        ->abstract()
        ->public(true)
        ->arg(0, null) // connection name
        ->arg(1, service('parameter_bag'));

    /*
     * Abstract metadata
     */
    $services
        ->set('typesense.metadata', TypesenseMetadata::class)
        ->abstract()
        ->public(true)
        ->arg(0, null)
        ->arg(1, null)
        ->arg(2, service('typesense.transformer.entity'));

    /*
     * Abstract collection
     */
    $services
        ->set('typesense.collection', TypesenseCollection::class)
        ->abstract()
        ->public(true)
        ->arg(0, null) // metadata
        ->arg(1, null); // collection

    /*
     * Abstract finder
     */
    $services
        ->set('typesense.finder', TypesenseFinder::class)
        ->abstract()
        ->arg(0, null) // collection
        ->arg(1, service('parameter_bag'));

    /*
     * Doctrine listener
     */
    $services
        ->set('typesense.listener.doctrine_indexer', TypesenseIndexer::class)
        ->tag('doctrine.event_listener', [
            'event' => 'postPersist',
            'priority' => -1,
            'connection' => 'default',
        ])
        ->tag('doctrine.event_listener', [
            'event' => 'postUpdate',
            'priority' => -1,
            'connection' => 'default',
        ])
        ->tag('doctrine.event_listener', [
            'event' => 'preRemove',
            'priority' => -1,
            'connection' => 'default',
        ])
        ->tag('doctrine.event_listener', [
            'event' => 'postRemove',
            'priority' => -1,
            'connection' => 'default',
        ])
        ->tag('doctrine.event_listener', [
            'event' => 'postFlush',
            'priority' => -1,
            'connection' => 'default',
        ])
        ->arg(0, service('typesense_manager'))
        ->arg(1, service('request_stack'))
        ->arg(2, service('parameter_bag'));

    /*
     * Console commands
     */
    $services
        ->set('typesense.command.create', CreateCommand::class)
        ->tag('console.command')
        ->arg(0, service('typesense_manager'));

    $services
        ->set('typesense.command.list', ListCommand::class)
        ->tag('console.command')
        ->arg(0, service('typesense_manager'));

    $services
        ->set('typesense.command.health', HealthCommand::class)
        ->tag('console.command')
        ->arg(0, service('typesense_manager'));

    $services
        ->set('typesense.command.action', ActionCommand::class)
        ->tag('console.command')
        ->arg(0, service('typesense_manager'));

    $services
        ->set('typesense.command.update', UpdateCommand::class)
        ->tag('console.command')
        ->arg(0, service('typesense_manager'));

    /*
     * Transformers
     */
    $services
        ->set('typesense.transformer', AbstractTransformer::class)
        ->abstract()
        ->public(true);

    $services
        ->set('typesense.transformer.entity', EntityTransformer::class)
        ->parent('typesense.transformer')
        ->public(true)
        ->arg(0, service('doctrine.orm.entity_manager'));
};