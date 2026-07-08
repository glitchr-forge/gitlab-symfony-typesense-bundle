<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Unit\ORM\Query;

use PHPUnit\Framework\TestCase;
use Typesense\Bundle\ORM\Query\Request;

class RequestTest extends TestCase
{
    public function testConstructorSetsQueryByAndTerm(): void
    {
        $request = new Request('title', 'hello world');

        $this->assertSame('title', $request->getHeader(Request::QUERY_BY));
        $this->assertSame('hello world', $request->getHeader(Request::TERM));
    }

    public function testConstructorAcceptsAnArrayOfFields(): void
    {
        $request = new Request(['title', 'content']);

        $this->assertSame('title, content', $request->getHeader(Request::QUERY_BY));
        $this->assertSame(['title', ' content'], $request->getFields());
        $this->assertSame(2, $request->getNumFields());
    }

    public function testTermDefaultsToAnEmptyString(): void
    {
        $request = new Request('title');

        $this->assertSame('', $request->getHeader(Request::TERM));
    }

    public function testHasHeader(): void
    {
        $request = new Request('title');

        $this->assertTrue($request->hasHeader(Request::QUERY_BY));
        $this->assertFalse($request->hasHeader('bogus'));
    }

    public function testFluentSettersReturnSelfAndOverwrite(): void
    {
        $request = (new Request('title'))->term('hello')->q('world')->queryBy('content');

        $this->assertSame('content', $request->getHeader(Request::QUERY_BY));
        // q() and term() both write to the same TERM header — last call wins.
        $this->assertSame('world', $request->getHeader(Request::TERM));
    }

    public function testAddQueryByAppendsToTheExistingList(): void
    {
        $request = new Request('title');
        $request->addQueryBy('content');

        $this->assertSame('title, content', $request->getHeader(Request::QUERY_BY));
    }

    public function testAddQueryByOnAnEmptyQueryBySetsItDirectly(): void
    {
        // Constructing with an empty string still calls addHeader(QUERY_BY, '')
        // via the constructor, so QUERY_BY is always "set" once constructed;
        // addQueryBy() only special-cases a falsy *value*, not an unset key.
        $request = new Request('');
        $request->addQueryBy('title');

        $this->assertSame('title', $request->getHeader(Request::QUERY_BY));
    }
}
