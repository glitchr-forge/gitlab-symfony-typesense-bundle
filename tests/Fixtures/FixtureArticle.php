<?php

declare(strict_types=1);

namespace Typesense\Bundle\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Typesense\Bundle\TypesenseInterface;
use Typesense\Bundle\TypesenseTrait;

class FixtureArticle implements TypesenseInterface
{
    use TypesenseTrait;

    public int $id;
    public string $title;
    public ?FixtureAuthor $author = null;
    public ArrayCollection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function __typesense(): ?string
    {
        return $this->title;
    }
}

class FixtureAuthor
{
    public function __construct(private string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

class FixtureTag implements TypesenseInterface
{
    use TypesenseTrait;

    public function __construct(private string $slug)
    {
    }

    public function __typesense(): ?string
    {
        return $this->slug;
    }
}
