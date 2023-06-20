<?php

declare(strict_types=1);

namespace Typesense\Bundle;

use DoctrineExtensions\Query\Mysql\Field;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Typesense\Bundle\DependencyInjection\Compiler\CollectionPass;
use Typesense\Bundle\DependencyInjection\Compiler\ConnectionPass;
use Typesense\Bundle\DependencyInjection\Compiler\FinderPass;
use Typesense\Bundle\DependencyInjection\Compiler\MetadataPass;

/**
 *
 */
class TypesenseBundle extends Bundle
{
    public function boot(): void
    {
        if ($this->container->has('doctrine.orm.entity_manager')) {
            $objectManager = $this->container->get('doctrine.orm.entity_manager');
            $objectManagerConfig = $objectManager->getConfiguration();

            if (class_exists(Field::class)) {
                $objectManagerConfig
                    ->addCustomNumericFunction('FIELD', Field::class);
            }
        }
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->container = $container;

        $container->addCompilerPass(new ConnectionPass());

        $container->addCompilerPass(new MetadataPass());
        $container->addCompilerPass(new FinderPass());
        $container->addCompilerPass(new CollectionPass());
    }
}
