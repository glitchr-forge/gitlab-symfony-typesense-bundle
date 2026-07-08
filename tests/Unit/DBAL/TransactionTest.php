<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Unit\DBAL;

use PHPUnit\Framework\TestCase;
use Typesense\Bundle\DBAL\Transaction;
use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseDocuments;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadataField;
use Typesense\Bundle\ORM\Transformer\Abstract\AbstractTransformer;

class TransactionTest extends TestCase
{
    private function makeCollectionWithDocuments(): array
    {
        $idField = new TypesenseMetadataField();
        $idField->name = 'id';
        $idField->identifier = true;

        $metadata = $this->createMock(TypesenseMetadata::class);
        $metadata->fields = ['id' => $idField];

        $documents = $this->createMock(TypesenseDocuments::class);

        $collection = $this->createMock(TypesenseCollection::class);
        $collection->method('metadata')->willReturn($metadata);
        $collection->method('documents')->willReturn($documents);

        return [$collection, $documents, $metadata];
    }

    public function testPersistConvertsTheObjectThenDeletesAndCreates(): void
    {
        [$collection, $documents] = $this->makeCollectionWithDocuments();

        $mock = ['id' => 42, 'title' => 'Hello'];
        $transformer = $this->createMock(AbstractTransformer::class);
        $transformer->method('convert')->willReturn($mock);
        $collection->method('transformer')->willReturn($transformer);

        $documents->expects($this->once())->method('delete')->with('42');
        $documents->expects($this->once())->method('create')->with($mock, []);

        $transaction = new Transaction($collection, Transaction::PERSIST, (object) ['id' => 42, 'title' => 'Hello']);
        $transaction->commit();
    }

    /**
     * A pre-existing "not found" error on the delete-before-create step
     * (there's nothing to delete the first time a document is indexed) is
     * expected and swallowed — commit() must still proceed to create().
     */
    public function testPersistToleratesDeleteFailingBecauseNothingExistedYet(): void
    {
        [$collection, $documents] = $this->makeCollectionWithDocuments();

        $mock = ['id' => 42];
        $transformer = $this->createMock(AbstractTransformer::class);
        $transformer->method('convert')->willReturn($mock);
        $collection->method('transformer')->willReturn($transformer);

        $documents->method('delete')->willThrowException(new \Typesense\Exceptions\ObjectNotFound());
        $documents->expects($this->once())->method('create')->with($mock, []);

        $transaction = new Transaction($collection, Transaction::PERSIST, (object) ['id' => 42]);
        $transaction->commit();
    }

    public function testRemoveByIdOnlyDeletesAndNeverConverts(): void
    {
        [$collection, $documents] = $this->makeCollectionWithDocuments();
        $collection->expects($this->never())->method('transformer');

        $documents->expects($this->once())->method('delete')->with('42');
        $documents->expects($this->never())->method('create');

        $transaction = new Transaction($collection, Transaction::REMOVE, '42');
        $transaction->commit();
    }

    public function testCommitIsIdempotent(): void
    {
        [$collection, $documents] = $this->makeCollectionWithDocuments();

        $documents->expects($this->once())->method('delete')->with('42');

        $transaction = new Transaction($collection, Transaction::REMOVE, '42');
        $transaction->commit();
        $transaction->commit();
    }

    /**
     * Regression: the REMOVE case's "throw new Exception('Unsupported
     * action')" used to sit unreachably after a `break;` — any action
     * string outside PERSIST/UPDATE/REMOVE silently did nothing instead of
     * raising. Now a real default: case.
     */
    public function testCommitThrowsOnAnUnsupportedAction(): void
    {
        [$collection] = $this->makeCollectionWithDocuments();

        $transaction = new Transaction($collection, 'ACTION_BOGUS', '42');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported action');

        $transaction->commit();
    }

    public function testActionReturnsTheConfiguredAction(): void
    {
        [$collection] = $this->makeCollectionWithDocuments();

        $transaction = new Transaction($collection, Transaction::REMOVE, '42');

        $this->assertSame(Transaction::REMOVE, $transaction->action());
    }

    public function testRollBackIsNotImplemented(): void
    {
        [$collection] = $this->makeCollectionWithDocuments();
        $transaction = new Transaction($collection, Transaction::REMOVE, '42');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not implemented');

        $transaction->rollBack();
    }
}
