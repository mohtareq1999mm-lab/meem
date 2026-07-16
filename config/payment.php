<?php

return [
    'default_gateway' => env('DEFAULT_PAYMENT_GATEWAY', 'myfatoorah'),
    'default_currency' => env('DEFAULT_CURRENCY', 'KWD'),
    'order_timeout_hours' => env('ORDER_TIMEOUT_HOURS', 72),

    'gateways' => [
        'myfatoorah' => [
            'class' => App\Services\Gateway\MyFatoorahGateway::class,
            'api_key' => env('MYFATOORAH_API_KEY'),
            'base_url' => env('MYFATOORAH_BASE_URL', 'https://apitest.myfatoorah.com/v2/'),
        ],
    ],

    'pay_at_cashier' => [
        'size' => 50,
        'format' => 'svg',
        'storage_disk' => 'public',
        'storage_path' => 'qr-codes',
    ],
];
