<?php

declare(strict_types=1);

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithList
{
    /** @var list<ClassicDummy> */
    public array $dummies;

    public string $customProperty;
}
