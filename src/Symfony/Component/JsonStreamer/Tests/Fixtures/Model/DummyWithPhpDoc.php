<?php

declare(strict_types=1);

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithPhpDoc
{
    /**
     * @var array<DummyWithNameAttributes>
     */
    public mixed $arrayOfDummies = [];

    /**
     * @var list<mixed>
     */
    public array $array = [];
}
