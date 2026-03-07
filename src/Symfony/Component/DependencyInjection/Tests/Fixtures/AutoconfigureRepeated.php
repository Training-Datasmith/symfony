<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true, shared: false)]
#[Autoconfigure(lazy: true)]
class AutoconfigureRepeated
{
}
