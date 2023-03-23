<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense;
use Base\DependencyInjection\Compiler\Pass\AnnotationPass;
use DoctrineExtensions\Query\Mysql\Field;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\UX\Typesense\DependencyInjection\Compiler\ClientPass;
use Symfony\UX\Typesense\DependencyInjection\Compiler\FinderPass;
use Symfony\UX\Typesense\DependencyInjection\Compiler\TypesenseClientPass;

class TypesenseBundle extends Bundle
{
    public function boot() {

        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManagerConfig  = $entityManager->getConfiguration();

        if (class_exists(Field::class)) {
            $entityManagerConfig
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
