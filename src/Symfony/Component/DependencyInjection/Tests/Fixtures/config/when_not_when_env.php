<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;

return #[When(env: 'dev')] #[WhenNot(env: 'prod')] function () {
    throw new RuntimeException('This code should not be run.');
};
