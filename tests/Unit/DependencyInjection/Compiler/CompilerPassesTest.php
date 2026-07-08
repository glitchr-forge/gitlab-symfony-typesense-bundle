<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Typesense\Bundle\DependencyInjection\Compiler\CollectionPass;
use Typesense\Bundle\DependencyInjection\Compiler\ConnectionPass;
use Typesense\Bundle\DependencyInjection\Compiler\FinderPass;
use Typesense\Bundle\DependencyInjection\Compiler\MetadataPass;
use Typesense\Bundle\ORM\TypesenseManager;

/**
 * All four compiler passes follow the identical shape: collect every
 * service tagged X, wire it into TypesenseManager via one addX() method
 * call each. One test class covers all four rather than four near-
 * identical files.
 */
class CompilerPassesTest extends TestCase
{
    public static function passProvider(): array
    {
        return [
            'connections' => [ConnectionPass::class, 'typesense.connection', 'addConnection'],
            'collections' => [CollectionPass::class, 'typesense.collection', 'addCollection'],
            'metadata' => [MetadataPass::class, 'typesense.metadata', 'addMetadata'],
            'finders' => [FinderPass::class, 'typesense.finder', 'addFinder'],
        ];
    }

    public function testDoesNothingWhenTypesenseManagerIsNotRegistered(): void
    {
        foreach (self::passProvider() as [$passClass, $tag, $method]) {
            $container = new ContainerBuilder();
            $container->register('some.tagged.service', \stdClass::class)->addTag($tag);

            (new $passClass())->process($container);
            // No exception, and no definition was created for the absent manager.
            $this->assertFalse($container->has(TypesenseManager::class));
        }
    }

    public function testWiresEveryTaggedServiceOntoTheManager(): void
    {
        foreach (self::passProvider() as $name => [$passClass, $tag, $method]) {
            $container = new ContainerBuilder();
            $managerDefinition = $container->register(TypesenseManager::class, TypesenseManager::class);

            $container->register('tagged.a', \stdClass::class)->addTag($tag);
            $container->register('tagged.b', \stdClass::class)->addTag($tag);

            (new $passClass())->process($container);

            $calls = $managerDefinition->getMethodCalls();
            $this->assertCount(2, $calls, "$name: expected exactly one $method() call per tagged service");
            $this->assertSame($method, $calls[0][0]);
            $this->assertSame($method, $calls[1][0]);
        }
    }
}
