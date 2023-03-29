<?php

namespace Typesense\Bundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use Typesense\Bundle\DBAL\TypesenseManager;
use Typesense\Bundle\Typesense;

class MetadataPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(TypesenseManager::class)) {
            return;
        }

        $definition     = $container->findDefinition(TypesenseManager::class);
        $taggedServices = $container->findTaggedServiceIds("typesense.metadata");
        foreach ($taggedServices as $className => $tags) {

            $reference = new Reference($className);
            $definition->addMethodCall("addMetadata", [$reference]);
        }
    }
}
