<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Marvel\Services\Import\BrandImportService;
use Maatwebsite\Excel\Facades\Excel;

echo "Loading brand-import-sample.xlsx...\n";

$filePath = __DIR__ . '/packages/marvel/resources/brands/brand-import-sample.xlsx';

if (!file_exists($filePath)) {
    die("ERROR: File not found: {$filePath}\n");
}

echo "File exists: {$filePath}\n";

try {
    $service = new BrandImportService();

    // Load Excel file
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
    $spreadsheet = $reader->load($filePath);
    $sheet = $spreadsheet->getSheet(0);
    $rows = $sheet->toArray();

    // Remove header row
    array_shift($rows);

    // Convert to collection with headers
    $headers = ['name_en', 'name_ar', 'details_en', 'details_ar', 'status', 'image_desktop_url', 'image_mobile_url'];
    $collection = collect($rows)->map(function($row) use ($headers) {
        return array_combine($headers, $row);
    });

    echo "Processing " . $collection->count() . " brand rows...\n\n";

    $service->processRows($collection);

    echo "\n=== IMPORT RESULTS ===\n";
    echo "Success Count: " . (new \ReflectionProperty($service, 'successCount'))->getValue($service) . "\n";

    $failedRows = (new \ReflectionProperty($service, 'failedRows'))->getValue($service);
    echo "Failed Count: " . count($failedRows) . "\n";

    if (!empty($failedRows)) {
        echo "\nFailed Rows:\n";
        foreach ($failedRows as $fail) {
            $row = $fail['excel_row'] ?? 'unknown';
            $error = $fail['error'] ?? json_encode($fail);
            echo "  - Row {$row}: {$error}\n";
        }
    }

    echo "\n=== DATABASE CHECK ===\n";
    $brands = \Marvel\Database\Models\Brand::whereIn('slug', ['acme-audio', 'nordic-home'])->get();
    foreach ($brands as $brand) {
        $nameData = is_string($brand->name) ? json_decode($brand->name, true) : $brand->name;
        $nameEn = $nameData['en'] ?? 'N/A';
        echo "Brand: {$nameEn} (slug: {$brand->slug})\n";
        echo "  - Has desktop image: " . ($brand->hasMedia('brands-desktop') ? 'YES' : 'NO') . "\n";
        echo "  - Has mobile image: " . ($brand->hasMedia('brands-mobile') ? 'YES' : 'NO') . "\n";
    }

    echo "\n✓ Import completed successfully\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
