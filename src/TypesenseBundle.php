<?php

declare(strict_types=1);

namespace Typesense\Bundle;
use Base\DependencyInjection\Compiler\Pass\AnnotationPass;
use DoctrineExtensions\Query\Mysql\Field;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Typesense\Bundle\DependencyInjection\Compiler\ClientPass;
use Typesense\Bundle\DependencyInjection\Compiler\FinderPass;
use Typesense\Bundle\DependencyInjection\Compiler\TypesenseClientPass;

class TypesenseBundle extends Bundle
{
    public function boot() {

        $objectManager = $this->container->get('doctrine.orm.entity_manager');
        $objectManagerConfig  = $objectManager->getConfiguration();

        if (class_exists(Field::class)) {
            $objectManagerConfig
                ->addCustomNumericFunction("FIELD", Field::class);
        }
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $this->container = $container;

        $container->addCompilerPass(new ClientPass());
        $container->addCompilerPass(new FinderPass());
    }
}
