<?php

declare(strict_types=1);

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class SelfReferencingDummyDict
{
    /**
     * @var array<string, self>
     */
    public array $items = [];
}
