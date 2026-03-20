<?php

declare(strict_types=1);

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

final class SelfReferencingDummyWithOtherDummy
{
    public ClassicDummy $otherDummy;
    public ?self $self = null;
}
