<?php

return [
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'verify_ssl' => (bool) env('MIDTRANS_VERIFY_SSL', true),
    'ca_cert' => env('MIDTRANS_CA_CERT') ?: (file_exists(base_path('vendor/midtrans/midtrans-php/data/cacert.pem'))
        ? base_path('vendor/midtrans/midtrans-php/data/cacert.pem')
        : null),
];
