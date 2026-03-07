<?php

declare(strict_types=1);

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

final class DummyWithSelfReferencingDummy
{
    public ClassicDummy $otherDummy;
    public ?SelfReferencingDummyWithOtherDummy $selfReferencing = null;
}
