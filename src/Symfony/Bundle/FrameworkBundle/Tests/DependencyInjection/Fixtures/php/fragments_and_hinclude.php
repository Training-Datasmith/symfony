<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'fragments' => [
        'enabled' => true,
        'hinclude_default_template' => 'global_hinclude_template',
    ],
]);
