<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(bind: ['$arg' => 'foo'])]
#[Autoconfigure(bind: ['$arg' => 'bar'])]
class AutoconfigureRepeatedBindings
{
}
