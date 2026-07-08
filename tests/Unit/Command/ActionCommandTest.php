<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Unit\Command;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Typesense\Bundle\Command\ActionCommand;
use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseDocuments;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\TypesenseFinder;
use Typesense\Bundle\ORM\TypesenseManager;

/**
 * Configuration::setSQLLogger() was removed in Doctrine DBAL 4.x. ActionCommand
 * called it unconditionally before its bulk export query, so `typesense:action
 * upsert` (the only command that populates Typesense collections) fatals with
 * "Call to undefined method ...::setSQLLogger()" on DBAL 4.x before touching a
 * single document. Found live: all 5 mapped collections on a real deployment
 * had the correct schema but 0 indexed documents, because the reindex command
 * had never been able to complete since this DBAL version was in place.
 *
 * Uses a REAL Doctrine\DBAL\Configuration instance rather than mocking it —
 * this project's installed DBAL genuinely doesn't have setSQLLogger(), so the
 * test exercises the exact same method_exists() branch production hits.
 */
class ActionCommandTest extends TestCase
{
    public function testUpsertDoesNotFatalWhenSetSQLLoggerIsUnavailable(): void
    {
        $this->assertFalse(
            method_exists(Configuration::class, 'setSQLLogger'),
            'This test assumes the installed DBAL has already removed setSQLLogger(); '
            . 'if this fails, DBAL was downgraded and no longer reproduces the bug.'
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection->method('getConfiguration')->willReturn(new Configuration());
        $entityManager->method('getConnection')->willReturn($connection);

        $countQuery = $this->createMock(\Doctrine\ORM\Query::class);
        $countQuery->method('getSingleScalarResult')->willReturn(0);

        $selectQuery = $this->createMock(\Doctrine\ORM\Query::class);
        $selectQuery->method('toIterable')->willReturn([]);

        $entityManager->method('createQuery')->willReturnCallback(
            fn(string $dql) => str_starts_with($dql, 'select COUNT') ? $countQuery : $selectQuery
        );

        $metadata = $this->createMock(TypesenseMetadata::class);
        $metadata->method('getObjectManager')->willReturn($entityManager);
        $metadata->method('getClass')->willReturn(\stdClass::class);

        $documents = $this->createMock(TypesenseDocuments::class);
        $documents->method('import')->willReturn([]);

        $collection = $this->createMock(TypesenseCollection::class);
        $collection->method('metadata')->willReturn($metadata);
        $collection->method('documents')->willReturn($documents);

        $cache = $this->createMock(CacheInterface::class);
        $finder = $this->createMock(TypesenseFinder::class);
        $finder->method('cache')->willReturn($cache);

        $manager = $this->createMock(TypesenseManager::class);
        $manager->method('getCollections')->willReturn(['fixture' => $collection]);
        $manager->method('getFinder')->with('fixture')->willReturn($finder);

        $command = new ActionCommand($manager);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['action' => 'upsert']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('0 element', $tester->getDisplay());
    }
}
