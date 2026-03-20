<?php

declare(strict_types=1);

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class SelfReferencingDummyList
{
    /**
     * @var self[]
     */
    public array $items = [];
}
