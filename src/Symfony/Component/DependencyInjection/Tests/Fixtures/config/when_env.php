<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Attribute\When;

return #[When(env: 'prod')] function () {
    throw new RuntimeException('This code should not be run.');
};
