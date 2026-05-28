<?php

return [
    'app' => [
        'base_url' => 'http://bumbonel.local',
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'bumbonel',
        'user' => 'bumbonel',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'products' => [
        'file' => __DIR__ . '/data/products.csv',
    ],
    'notifications' => [
        'enabled' => false,
        'to' => ['orders@example.com'],
        'from' => 'Bumbonel <orders@example.com>',
        'reply_to' => 'orders@example.com',
    ],
    'paynet' => [
        'environment' => 'test', // test or prod
        'merchant_code' => 'your-merchant-code',
        'merchant_secret_key' => 'your-merchant-secret-key',
        'merchant_user' => 'your-merchant-user',
        'merchant_user_password' => 'your-merchant-user-password',
        'expiry_hours' => 4,
        'adapting_hours' => 1,
        'endpoints' => [
            'test' => [
                'ui_url' => 'https://test.paynet.md/acquiring/setecom',
                'ui_server_url' => 'https://test.paynet.md/acquiring/getecom',
                'api_url' => 'https://test.paynet.md:4446',
            ],
            'prod' => [
                'ui_url' => 'https://paynet.md/acquiring/setecom',
                'ui_server_url' => 'https://paynet.md/acquiring/getecom',
                'api_url' => 'https://paynet.md:4446',
            ],
        ],
    ],
];
