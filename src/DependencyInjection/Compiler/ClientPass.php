<?php

namespace Typesense\Bundle\DependencyInjection\Compiler;

use Base\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Registry;
use Typesense\Bundle\Manager\TypesenseManager;
use Typesense\Bundle\Typesense;

class ClientPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(TypesenseManager::class)) {
            return;
        }

        $definition     = $container->findDefinition(TypesenseManager::class);
        $taggedServices = $container->findTaggedServiceIds("typesense.client");
        foreach ($taggedServices as $className => $tags) {

            $reference = new Reference($className);

            $definition->addMethodCall("addClient", [$reference]);
        }
    }
}
