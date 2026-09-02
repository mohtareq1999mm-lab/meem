<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Import Sample Files
    |--------------------------------------------------------------------------
    |
    | Paths to the sample Excel files for import templates.
    | These files are used by the downloadSample endpoints.
    |
    */
    'import' => [
        'samples' => [
            'product' => storage_path('packages/marvel/resources/product/products_export_2026-09-01_scraped.xlsx'),
            'category' => storage_path('packages/marvel/resources/category/niceone_categories.xlsx'),
            'brand' => storage_path('packages/marvel/resources/brands/brand-import-sample.xlsx'),
        ],
    ],
];
