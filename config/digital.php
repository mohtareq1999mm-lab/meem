<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Digital Product System
    |--------------------------------------------------------------------------
    |
    | Kill-switch for the digital product subsystem. When disabled, digital
    | fulfillment listeners exit early and download endpoints refuse access.
    |
    */

    'enabled' => env('DIGITAL_PRODUCTS_ENABLED', true),

    /*
    | Allowed upload types. PDF only for the MVP.
    */
    'allowed_mimes' => ['pdf'],
    'allowed_mime_types' => ['application/pdf', 'application/x-pdf'],
    'max_upload_kb' => env('DIGITAL_MAX_UPLOAD_KB', 20480),

    /*
    | Default maximum number of downloads per purchased digital entitlement.
    | Admin-overridable per entitlement via the download_limit column.
    */
    'download_limit' => (int) env('DIGITAL_DOWNLOAD_LIMIT', 5),

    /*
    | Signed download URL lifetime in minutes.
    */
    'signed_url_ttl_minutes' => (int) env('DIGITAL_SIGNED_URL_TTL', 30),
];
