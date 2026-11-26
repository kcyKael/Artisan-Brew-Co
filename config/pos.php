<?php

return [
    'brand_name' => env('POS_BRAND_NAME', 'Artisan Brew Co.'),
    'brand_short' => env('POS_BRAND_SHORT', 'ABC'),
    'timezone' => env('POS_DISPLAY_TIMEZONE', 'Asia/Manila'),
    'currency_code' => 'PHP',
    'currency_symbol' => '₱',
    'max_upload_kb' => 3072,
    'media_disk' => 'local',
    'media_root' => 'media',
    'receipt_footer' => 'Thank you for brewing with Artisan Brew Co.',
];
