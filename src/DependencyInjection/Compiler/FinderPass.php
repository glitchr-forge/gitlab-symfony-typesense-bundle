<?php

namespace Typesense\Bundle\DependencyInjection\Compiler;

use Base\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Registry;
use Typesense\Bundle\ORM\TypesenseManager;

class FinderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(TypesenseManager::class)) {
            return;
        }

        $definition     = $container->findDefinition(TypesenseManager::class);
        $taggedServices = $container->findTaggedServiceIds("typesense.finder");
        foreach ($taggedServices as $serviceName => $tags) {

            $reference = new Reference($serviceName);
            $definition->addMethodCall("addFinder", [$reference]);
        }
    }
}
