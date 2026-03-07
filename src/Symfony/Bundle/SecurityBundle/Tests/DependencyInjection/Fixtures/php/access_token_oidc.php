<?php

declare(strict_types=1);

$container->loadFromExtension('security', [
    'providers' => [
        'default' => [
            'memory' => null,
        ],
    ],
    'firewalls' => [
        'firewall1' => [
            'provider' => 'default',
            'access_token' => [
                'token_handler' => [
                    'oidc' => [
                        'algorithms' => ['RS256'],
                        'issuers' => ['https://www.example.com'],
                        'audience' => 'audience',
                        'keyset' => '{"keys":[{"kty":"RSA","n":"abc","e":"AQAB"}]}',
                    ],
                ],
            ],
        ],
    ],
]);
