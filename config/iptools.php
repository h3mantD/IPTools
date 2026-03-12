<?php

declare(strict_types=1);

return [
    'storage' => [
        'connection' => env('IPTOOLS_DB_CONNECTION'),
        'table' => env('IPTOOLS_RANGES_TABLE', 'ip_ranges'),
    ],
];
