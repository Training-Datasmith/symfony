<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Utils;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class NotAService
{
}
