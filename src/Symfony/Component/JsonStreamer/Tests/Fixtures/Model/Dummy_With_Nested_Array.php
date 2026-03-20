<?php

declare(strict_types=1);

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithNestedArray
{
    /** @var DummyWithArray[] */
    public array $dummies;

    public string $stringProperty;
}
