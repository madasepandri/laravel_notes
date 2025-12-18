<?php

return [
    'organization_registration_fee' => (int) env('ORG_REG_FEE', 100_000),
    'currency' => env('BILLING_CURRENCY', 'IDR'),
];

