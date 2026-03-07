<?php

declare(strict_types=1);

$loader->load('container1.php');

$container->loadFromExtension('security', [
    'password_hashers' => [
        'JMS\FooBundle\Entity\User7' => [
            'algorithm' => 'bcrypt',
            'cost' => 15,
        ],
    ],
]);
