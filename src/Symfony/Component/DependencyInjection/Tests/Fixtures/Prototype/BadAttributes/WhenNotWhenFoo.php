<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadAttributes;

use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;

#[When(env: 'dev')]
#[WhenNot(env: 'test')]
class WhenNotWhenFoo
{
}
