<?php

declare(strict_types=1);

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithNestedList
{
    /** @var list<DummyWithList> */
    public array $dummies;

    public string $stringProperty;
}
