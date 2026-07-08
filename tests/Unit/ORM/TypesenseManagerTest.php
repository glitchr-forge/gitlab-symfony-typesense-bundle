<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Unit\ORM;

use PHPUnit\Framework\TestCase;
use Typesense\Bundle\DBAL\Connection;
use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\Transformer\Abstract\AbstractTransformer;
use Typesense\Bundle\ORM\TypesenseFinder;
use Typesense\Bundle\ORM\TypesenseManager;

class TypesenseManagerTest extends TestCase
{
    private function makeManager(string $defaultConnection = 'default'): TypesenseManager
    {
        return new TypesenseManager($defaultConnection);
    }

    /**
     * The "multi server" feature: any number of named connections can be
     * registered, and getConnection(null) resolves to whichever one was
     * configured as the default — not just the first one added.
     */
    public function testMultipleConnectionsAreRegisteredByName(): void
    {
        $manager = $this->makeManager('secondary');

        $primary = $this->createMock(Connection::class);
        $primary->method('getName')->willReturn('primary');
        $secondary = $this->createMock(Connection::class);
        $secondary->method('getName')->willReturn('secondary');

        $manager->addConnection($primary)->addConnection($secondary);

        $this->assertSame(['primary' => $primary, 'secondary' => $secondary], $manager->getConnections());
        $this->assertSame($secondary, $manager->getConnection());
        $this->assertSame($secondary, $manager->getDefaultConnection());
        $this->assertSame($primary, $manager->getConnection('primary'));
        $this->assertSame('secondary', $manager->getDefaultConnectionName());
    }

    public function testAddCollectionAlsoRegistersDiscriminatorSubMetadataCollections(): void
    {
        $manager = $this->makeManager();

        $transformer = $this->createMock(AbstractTransformer::class);

        $subMetadata = $this->createMock(TypesenseMetadata::class);
        $subMetadata->method('getName')->willReturn('article__blog');
        $subMetadata->method('getTransformer')->willReturn($transformer);

        $metadata = $this->createMock(TypesenseMetadata::class);
        $metadata->method('getName')->willReturn('article');
        $metadata->method('getSubMetadata')->willReturn([$subMetadata]);
        $metadata->method('getTransformer')->willReturn($transformer);

        $connection = $this->createMock(Connection::class);

        $collection = $this->createMock(TypesenseCollection::class);
        $collection->method('name')->willReturn('article');
        $collection->method('metadata')->willReturn($metadata);
        $collection->method('connection')->willReturn($connection);

        $manager->addCollection($collection);

        $this->assertSame($collection, $manager->getCollection('article'));
        $this->assertInstanceOf(TypesenseCollection::class, $manager->getCollection('article__blog'));
        $this->assertNotSame($collection, $manager->getCollection('article__blog'));
    }

    public function testGetMetadataDelegatesToTheCollection(): void
    {
        $manager = $this->makeManager();

        $metadata = $this->createMock(TypesenseMetadata::class);
        $metadata->method('getName')->willReturn('article');
        $metadata->method('getSubMetadata')->willReturn([]);
        $metadata->method('getTransformer')->willReturn($this->createMock(AbstractTransformer::class));

        $collection = $this->createMock(TypesenseCollection::class);
        $collection->method('name')->willReturn('article');
        $collection->method('metadata')->willReturn($metadata);

        $manager->addCollection($collection);

        $this->assertSame($metadata, $manager->getMetadata('article'));
    }

    /**
     * getFinder() strips a discriminator suffix ("article__blog" ->
     * "article") — sub-collections don't get their own finder, they share
     * the root collection's.
     */
    public function testGetFinderStripsTheDiscriminatorSuffix(): void
    {
        $manager = $this->makeManager();

        $finder = $this->createMock(TypesenseFinder::class);
        $finder->method('name')->willReturn('article');

        $manager->addFinder($finder);

        $this->assertSame($finder, $manager->getFinder('article'));
        $this->assertSame($finder, $manager->getFinder('article__blog'));
    }
}
