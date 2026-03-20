<?php

declare(strict_types=1);

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

/**
 * @template T of object
 */
class DummyWithGenerics
{
    /**
     * @var list<T>
     */
    public array $dummies = [];
}
