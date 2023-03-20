<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Tests\Unit\DependencyInjection;

use Symfony\UX\Typesense\DependencyInjection\TypesenseExtension;
// use FOS\ElasticaBundle\Doctrine\MongoDBPagerProvider;
// use FOS\ElasticaBundle\Doctrine\ORMPagerProvider;
// use FOS\ElasticaBundle\Doctrine\PHPCRPagerProvider;
// use FOS\ElasticaBundle\Doctrine\RegisterListenersService;
// use FOS\ElasticaBundle\Persister\InPlacePagerPersister;
// use FOS\ElasticaBundle\Persister\Listener\FilterObjectsListener;
// use FOS\ElasticaBundle\Persister\PagerPersisterRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
// use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
// use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
class TypesenseExtensionTest extends TestCase
{
    public function testTypesenseClientDefinition()
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->registerExtension($extension = new TypesenseExtension());
        $containerBuilder->setParameter('kernel.debug', true);

        $loader = new YamlFileLoader($containerBuilder, new FileLocator(__DIR__.'/fixtures'));
        $loader->load('typesense.yaml');

        $extensionConfig = $containerBuilder->getExtensionConfig($extension->getAlias());
        $extension->load($extensionConfig, $containerBuilder);

        $this->assertTrue($containerBuilder->hasDefinition('typesense.client'));

        $clientDefinition = $containerBuilder->findDefinition('typesense.client');

        $this->assertSame('http://localhost:8108', $clientDefinition->getArgument(0));
        $this->assertSame('typesense', $clientDefinition->getArgument(1));
    }

    public function testFinderServiceDefinition()
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->registerExtension($extension = new TypesenseExtension());
        $containerBuilder->setParameter('kernel.debug', true);

        $loader = new YamlFileLoader($containerBuilder, new FileLocator(__DIR__.'/fixtures'));
        $loader->load('typesense.yml');

        $extensionConfig = $containerBuilder->getExtensionConfig($extension->getAlias());
        $extension->load($extensionConfig, $containerBuilder);

        $this->assertTrue($containerBuilder->hasDefinition('typesense.finder'));
        $this->assertTrue($containerBuilder->hasDefinition('typesense.finder.books'));

        $finderBooksDefinition = $containerBuilder->findDefinition('typesense.finder.books');
        $finderBooksDefinitionArguments = $finderBooksDefinition->getArguments();
        $arguments = array_pop($finderBooksDefinitionArguments);

        $this->assertSame('books', $arguments['typesense_name']);
        $this->assertSame('books', $arguments['name']);
    }

    public function testFinderServiceDefinitionWithCollectionPrefix()
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->registerExtension($extension = new TypesenseExtension());
        $containerBuilder->setParameter('kernel.debug', true);

        $loader = new YamlFileLoader($containerBuilder, new FileLocator(__DIR__.'/fixtures'));
        $loader->load('typesense_collection_prefix.yml');

        $extensionConfig = $containerBuilder->getExtensionConfig($extension->getAlias());
        $extension->load($extensionConfig, $containerBuilder);

        $this->assertTrue($containerBuilder->hasDefinition('typesense.finder'));
        $this->assertTrue($containerBuilder->hasDefinition('typesense.finder.books'));

        $finderBooksDefinition = $containerBuilder->findDefinition('typesense.finder.books');
        $finderBooksDefinitionArguments = $finderBooksDefinition->getArguments();
        $arguments = array_pop($finderBooksDefinitionArguments);

        $this->assertSame('prefix_books', $arguments['typesense_name']);
        $this->assertSame('books', $arguments['name']);
    }
}
