<?php

return [
    // single: one independent pharmacy installation; saas: host/domain routed DB-per-tenant.
    'mode' => env('TENANCY_MODE', 'single'),
    'central_connection' => 'central',
    'tenant_connection' => 'tenant',
    'allow_unmapped_domains' => env('TENANCY_ALLOW_UNMAPPED_DOMAINS', false),
    'central_domains' => array_values(array_filter(array_map('trim', explode(',', env('TENANCY_CENTRAL_DOMAINS', 'localhost,127.0.0.1'))))),
];
