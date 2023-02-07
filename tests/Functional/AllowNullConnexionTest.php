<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Tests\Functional;

use Symfony\UX\Typesense\Client\CollectionClient;
use Symfony\UX\Typesense\Client\TypesenseClient;
use Symfony\UX\Typesense\Command\CreateCommand;
use Symfony\UX\Typesense\Command\ImportCommand;
use Symfony\UX\Typesense\Finder\CollectionFinder;
use Symfony\UX\Typesense\Finder\TypesenseQuery;
use Symfony\UX\Typesense\Manager\CollectionManager;
use Symfony\UX\Typesense\Manager\DocumentManager;
use Symfony\UX\Typesense\Tests\Functional\Entity\Author;
use Symfony\UX\Typesense\Tests\Functional\Entity\Book;
use Symfony\UX\Typesense\Transformer\DoctrineToTypesenseTransformer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * This test ensure that the commands works great with a
 * booted Typesense Server.
 */
class AllowNullConnexionTest extends KernelTestCase
{
    public const NB_BOOKS    = 5;
    public const BOOK_TITLES = [
        'Total Khéops',
        'Chourmo',
        'Solea',
        'La fabrique du monstre',
        'La chute du monstre',
    ];

    public function testCreateCommand()
    {
        $commandTester = $this->createCommandTester();
        $commandTester->execute(['-vvv']);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Deleting books', $output);
        self::assertStringContainsString('Creating books', $output);
    }

    /**
     * @depends testCreateCommand
     */
    public function testImportCommand()
    {
        $commandTester = $this->importCommandTester();
        $commandTester->execute([]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Import [books]', $output);
        self::assertStringContainsString('[OK] '.self::NB_BOOKS.' elements populated', $output);
    }

    /**
     * @depends testImportCommand
     */
    public function testSearchByAuthor()
    {
        $typeSenseClient       = new TypesenseClient(null);
        $collectionClient      = new CollectionClient($typeSenseClient);
        $book                  = new Book(1, 'test', new Author('John Doe', 'France'), new \DateTime());
        $em                    = $this->getMockedEntityManager([$book]);
        $collectionDefinitions = $this->getCollectionDefinitions(\get_class($book));
        $bookDefinition        = $collectionDefinitions['books'];

        $bookFinder = new CollectionFinder($collectionClient, $em, $bookDefinition);
        $results    = $bookFinder->rawQuery(new TypesenseQuery('John', 'author'))->getResults();
        self::assertNull($results);
    }

    private function createCommandTester(): CommandTester
    {
        $application = new Application();

        $application->setAutoExit(false);

        $book = $this->getMockBuilder('\App\Entity\Book')->getMock();
        // Author is required
        $author = $this->getMockBuilder('\App\Entity\Author')->getMock();

        $collectionDefinitions = $this->getCollectionDefinitions(\get_class($book));
        $typeSenseClient       = new TypesenseClient('null', 'null');
        $propertyAccessor      = PropertyAccess::createPropertyAccessor();
        $collectionClient      = new CollectionClient($typeSenseClient);
        $transformer           = new DoctrineToTypesenseTransformer($collectionDefinitions, $propertyAccessor);
        $collectionManager     = new CollectionManager($collectionClient, $transformer, $collectionDefinitions);

        $command = new CreateCommand($collectionManager);

        $application->add($command);

        return new CommandTester($application->find('typesense:create'));
    }

    private function importCommandTester(): CommandTester
    {
        $application = new Application();

        $application->setAutoExit(false);

        // Prepare all mocked objects required to run the command
        $books                 = $this->getMockedBooks();
        $collectionDefinitions = $this->getCollectionDefinitions(Book::class);
        $typeSenseClient       = new TypesenseClient('null', 'null');
        $propertyAccessor      = PropertyAccess::createPropertyAccessor();
        $collectionClient      = new CollectionClient($typeSenseClient);
        $transformer           = new DoctrineToTypesenseTransformer($collectionDefinitions, $propertyAccessor);
        $documentManager       = new DocumentManager($typeSenseClient);
        $collectionManager     = new CollectionManager($collectionClient, $transformer, $collectionDefinitions);
        $em                    = $this->getMockedEntityManager($books);

        $command = new ImportCommand($em, $collectionManager, $documentManager, $transformer);

        $application->add($command);

        return new CommandTester($application->find('typesense:import'));
    }

    private function getCollectionDefinitions($entityClass)
    {
        return [
            'books' => [
                'typesense_name' => 'books',
                'entity'         => $entityClass,
                'name'           => 'books',
                'fields'         => [
                    'id' => [
                        'name'             => 'id',
                        'type'             => 'primary',
                        'entity_attribute' => 'id',
                    ],
                    'sortable_id' => [
                        'entity_attribute' => 'id',
                        'name'             => 'sortable_id',
                        'type'             => 'int32',
                    ],
                    'title' => [
                        'name'             => 'title',
                        'type'             => 'string',
                        'entity_attribute' => 'title',
                    ],
                    'author' => [
                        'name'             => 'author',
                        'type'             => 'object',
                        'entity_attribute' => 'author',
                    ],
                    'michel' => [
                        'name'             => 'author_country',
                        'type'             => 'string',
                        'entity_attribute' => 'author.country',
                    ],
                    'publishedAt' => [
                        'name'             => 'published_at',
                        'type'             => 'datetime',
                        'optional'         => true,
                        'entity_attribute' => 'publishedAt',
                    ],
                ],
                'default_sorting_field' => 'sortable_id',
            ],
        ];
    }

    private function getMockedBooks()
    {
        $author = new Author('John Doe', 'France');
        $books  = [];

        for ($i = 0; $i < self::NB_BOOKS; ++$i) {
            $books[] = new Book($i, self::BOOK_TITLES[$i], $author, new \DateTime());
        }

        return $books;
    }

    private function getMockedEntityManager($books)
    {
        $em = $this->createMock(EntityManager::class);

        $connection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($connection);

        $configuration = $this->createMock(Configuration::class);
        $connection->method('getConfiguration')->willReturn($configuration);

        $query = $this->createMock(AbstractQuery::class);
        $em->method('createQuery')->willReturn($query);

        $query->method('getSingleScalarResult')->willReturn(self::NB_BOOKS);

        $query->method('toIterable')->willReturn($books);

        return $em;
    }
}
