<?php

declare(strict_types=1);

if ('prod' !== $env) {
    return;
}

return [
    'acme' => [
        'color' => 'red',
    ],
];
