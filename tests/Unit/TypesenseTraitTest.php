<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Typesense\Bundle\Tests\Fixtures\FixtureArticle;
use Typesense\Bundle\Tests\Fixtures\FixtureAuthor;
use Typesense\Bundle\Tests\Fixtures\FixtureTag;

class TypesenseTraitTest extends TestCase
{
    public function testReadsAPlainScalarProperty(): void
    {
        $article = new FixtureArticle();
        $article->title = 'Hello world';

        $this->assertSame('Hello world', $article->__typesenseGetter('title', ['type' => 'string']));
    }

    public function testReadsANestedPropertyPath(): void
    {
        $article = new FixtureArticle();
        $article->author = new FixtureAuthor('Ada Lovelace');

        $this->assertSame('Ada Lovelace', $article->__typesenseGetter('author.name', ['type' => 'string']));
    }

    /**
     * A Collection value is Map()'d — each element goes through
     * __typesense() (if it implements TypesenseInterface) or __toString(),
     * then the whole thing is flattened into a plain array (string[]).
     */
    public function testCollectionPropertyMapsEachElementAndFlattens(): void
    {
        $article = new FixtureArticle();
        $article->tags->add(new FixtureTag('php'));
        $article->tags->add(new FixtureTag('symfony'));

        $result = $article->__typesenseGetter('tags', ['type' => 'string[]']);

        $this->assertSame(['php', 'symfony'], $result);
    }

    /**
     * A scalar value against an array-typed field ("string[]") is wrapped
     * in an array rather than left bare — the flatten step always produces
     * an array shape matching the field's declared arrayness.
     */
    public function testScalarValueIsWrappedIntoAnArrayWhenTheFieldTypeIsAnArrayType(): void
    {
        $article = new FixtureArticle();
        $article->title = 'Hello world';

        $result = $article->__typesenseGetter('title', ['type' => 'string[]']);

        $this->assertSame(['Hello world'], $result);
    }

    public function testEmptyCollectionMapsToAnEmptyArray(): void
    {
        $article = new FixtureArticle();

        $this->assertSame([], $article->__typesenseGetter('tags', ['type' => 'string[]']));
    }
}
