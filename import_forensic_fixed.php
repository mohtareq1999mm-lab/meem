<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Marvel\Services\Import\CategoryImportService;
use Marvel\Services\Import\BrandImportService;
use Marvel\Services\Import\ProductImportService;
use Illuminate\Support\Collection;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\Brand;
use Marvel\Database\Models\Product;

echo "=== DATABASE ===\n";
$config = config('database.connections.' . config('database.default'));
echo "Driver: " . config('database.default') . "\n";
echo "Host: " . ($config['host'] ?? 'N/A') . "\n";
echo "Port: " . ($config['port'] ?? 'N/A') . "\n";
echo "Database: " . ($config['database'] ?? 'N/A') . "\n";
echo "Username: " . ($config['username'] ?? 'N/A') . "\n";
echo "Env: " . app()->environment() . "\n";

$files = [
    'brands' => 'packages/marvel/resources/brands/brand-import-sample.xlsx',
    'categories' => 'packages/marvel/resources/categories/category-import-sample.xlsx',
    'products' => 'packages/marvel/resources/products/product-import-sample.xlsx',
];

echo "\n=== BEFORE COUNTS ===\n";
$before = [
    'categories' => DB::table('categories')->count(),
    'brands' => DB::table('brands')->count(),
    'products' => DB::table('products')->count(),
];
foreach ($before as $k=>$v) echo "$k: $v\n";

// Test Brand import via service
echo "\n=== BRAND IMPORT (real service) ===\n";
$brandFile = __DIR__ . '/' . $files['brands'];
$spreadsheet = IOFactory::load($brandFile);
$sheet = $spreadsheet->getSheet(0);
$headers = [];
$highestCol = $sheet->getHighestColumn();
for ($col='A'; $col <= $highestCol; $col++) {
    $val = $sheet->getCell($col.'1')->getValue();
    if ($val) $headers[] = trim($val);
}
$rows = [];
for ($row=2; $row <= $sheet->getHighestRow(); $row++) {
    $rowData = [];
    for ($col='A'; $col <= $highestCol; $col++) {
        $idx = ord($col)-65;
        if (isset($headers[$idx])) {
            $rowData[$headers[$idx]] = $sheet->getCell($col.$row)->getValue();
        }
    }
    if (!empty(array_filter($rowData, fn($v) => $v !== null && $v !== ''))) {
        $rows[] = $rowData;
    }
}
echo "Brand headers: " . implode(', ', $headers) . "\n";
echo "Brand rows: " . count($rows) . "\n";
foreach ($rows as $i=>$r) echo "  Row ".($i+2).": " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";

$brandService = app(BrandImportService::class);
try {
    $brandService->processRows(new Collection($rows));
    echo "Brand service successCount: " . $brandService->getSuccessCount() . "\n";
    echo "Brand failedRows: " . json_encode($brandService->getFailedRows(), JSON_UNESCAPED_UNICODE) . "\n";
    echo "Brands after: " . DB::table('brands')->count() . "\n";
} catch (Exception $e) {
    echo "Brand import failed: " . $e->getMessage() . "\n";
}

// Test Category import
echo "\n=== CATEGORY IMPORT (real service) ===\n";
$catFile = __DIR__ . '/' . $files['categories'];
$spreadsheet = IOFactory::load($catFile);
$sheet = $spreadsheet->getSheet(0);
$headers = [];
$highestCol = $sheet->getHighestColumn();
for ($col='A'; $col <= $highestCol; $col++) {
    $val = $sheet->getCell($col.'1')->getValue();
    if ($val) $headers[] = trim($val);
}
$rows = [];
for ($row=2; $row <= $sheet->getHighestRow(); $row++) {
    $rowData = [];
    for ($col='A'; $col <= $highestCol; $col++) {
        $idx = ord($col)-65;
        if (isset($headers[$idx])) {
            $rowData[$headers[$idx]] = $sheet->getCell($col.$row)->getValue();
        }
    }
    if (!empty(array_filter($rowData, fn($v) => $v !== null && $v !== ''))) {
        $rows[] = $rowData;
    }
}
echo "Category headers: " . implode(', ', $headers) . "\n";
echo "Category rows: " . count($rows) . "\n";
foreach ($rows as $i=>$r) echo "  Row ".($i+2).": " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";

$catService = app(CategoryImportService::class);
try {
    $catService->processRows(new Collection($rows));
    echo "Category successCount: " . $catService->getSuccessCount() . "\n";
    echo "Category failedRows: " . json_encode($catService->getFailedRows(), JSON_UNESCAPED_UNICODE) . "\n";
    echo "Categories after: " . DB::table('categories')->count() . "\n";
} catch (Exception $e) {
    echo "Category import failed: " . $e->getMessage() . "\n";
}

// Test Product import
echo "\n=== PRODUCT IMPORT (real service) ===\n";
$prodFile = __DIR__ . '/' . $files['products'];
$spreadsheet = IOFactory::load($prodFile);
$prodSheet = $spreadsheet->getSheetByName('products');
$headers = [];
$highestCol = $prodSheet->getHighestColumn();
for ($col='A'; $col <= $highestCol; $col++) {
    $val = $prodSheet->getCell($col.'1')->getValue();
    if ($val) $headers[] = trim($val);
}
$prodRows = [];
for ($row=2; $row <= $prodSheet->getHighestRow(); $row++) {
    $rowData = [];
    for ($col='A'; $col <= $highestCol; $col++) {
        $idx = ord($col)-65;
        if (isset($headers[$idx])) {
            $rowData[$headers[$idx]] = $prodSheet->getCell($col.$row)->getValue();
        }
    }
    if (!empty(array_filter($rowData, fn($v) => $v !== null && $v !== ''))) {
        $prodRows[] = $rowData;
    }
}
echo "Product headers: " . implode(', ', $headers) . "\n";
echo "Product rows: " . count($prodRows) . "\n";
foreach ($prodRows as $i=>$r) echo "  Row ".($i+2).": sku={$r['sku']}, name_en={$r['name_en']}, price={$r['price']}\n";

$prodService = app(ProductImportService::class);
foreach ($prodRows as $i=>$row) {
    $rowIndex = $i+2;
    try {
        $prodService->processProductRow($row, $rowIndex);
        echo "Product {$row['sku']} processed, successCount: " . $prodService->getSuccessCount() . "\n";
    } catch (Exception $e) {
        echo "Product row $rowIndex failed: " . $e->getMessage() . "\n";
    }
}
echo "Product failedRows: " . json_encode($prodService->getFailedRows(), JSON_UNESCAPED_UNICODE) . "\n";
echo "Products after product rows: " . DB::table('products')->count() . "\n";

// Now process other sheets
$sheetNames = ['product_variants', 'images', 'categories', 'brands', 'flash_sales', 'sliders', 'tags'];
foreach ($sheetNames as $sheetName) {
    $sheet = $spreadsheet->getSheetByName($sheetName);
    if (!$sheet) {
        echo "Sheet $sheetName not found, skipping\n";
        continue;
    }
    $headers = [];
    $highestCol = $sheet->getHighestColumn();
    for ($col='A'; $col <= $highestCol; $col++) {
        $val = $sheet->getCell($col.'1')->getValue();
        if ($val) $headers[] = trim($val);
    }
    echo "\nSheet $sheetName headers: " . implode(', ', $headers) . "\n";
    for ($row=2; $row <= $sheet->getHighestRow(); $row++) {
        $rowData = [];
        for ($col='A'; $col <= $highestCol; $col++) {
            $idx = ord($col)-65;
            if (isset($headers[$idx])) {
                $rowData[$headers[$idx]] = $sheet->getCell($col.$row)->getValue();
            }
        }
        if (empty(array_filter($rowData, fn($v) => $v !== null && $v !== ''))) continue;
        try {
            switch ($sheetName) {
                case 'product_variants':
                    $prodService->processVariantRow($rowData, $row);
                    echo "Variant {$rowData['variant_sku']} for {$rowData['product_sku']} processed\n";
                    break;
                case 'images':
                    $prodService->processProductImage($rowData['product_sku'], $rowData['image']);
                    echo "Image for {$rowData['product_sku']}: {$rowData['image']} processed\n";
                    break;
                case 'categories':
                    $prodService->syncCategories($rowData['product_sku'], [$rowData['category_slug']]);
                    echo "Category {$rowData['category_slug']} for {$rowData['product_sku']} synced\n";
                    break;
                case 'brands':
                    $prodService->syncBrands($rowData['product_sku'], [$rowData['brand_slug']]);
                    echo "Brand {$rowData['brand_slug']} for {$rowData['product_sku']} synced\n";
                    break;
                case 'flash_sales':
                    if (!empty($rowData['product_sku']) && !empty($rowData['flash_sale_slug'])) {
                        $prodService->syncFlashSales($rowData['product_sku'], [$rowData['flash_sale_slug']]);
                    }
                    break;
                case 'sliders':
                    if (!empty($rowData['product_sku']) && !empty($rowData['slider_slug'])) {
                        $prodService->syncSliders($rowData['product_sku'], [$rowData['slider_slug']]);
                    }
                    break;
                case 'tags':
                    if (!empty($rowData['product_sku']) && !empty($rowData['tag_slug'])) {
                        $prodService->syncTags($rowData['product_sku'], [$rowData['tag_slug']]);
                    }
                    echo "Tag {$rowData['tag_slug']} for {$rowData['product_sku']} synced\n";
                    break;
            }
        } catch (Exception $e) {
            echo "Sheet $sheetName row $row failed: " . $e->getMessage() . "\n";
        }
    }
}
$prodService->finalizeVariants();
echo "Product failedRows after all sheets: " . json_encode($prodService->getFailedRows(), JSON_UNESCAPED_UNICODE) . "\n";
echo "Products final: " . DB::table('products')->count() . "\n";

echo "\n=== AFTER COUNTS ===\n";
$after = [
    'categories' => DB::table('categories')->count(),
    'brands' => DB::table('brands')->count(),
    'products' => DB::table('products')->count(),
    'product_variants' => DB::table('product_variants')->count(),
];
foreach ($after as $k=>$v) {
    echo "$k: $v (delta: " . ($v - $before[$k]) . ")\n";
}

echo "\n=== VERIFICATION ===\n";
foreach (['PRD-SAMPLE-001', 'PRD-SAMPLE-002', 'PRD-SAMPLE-003'] as $sku) {
    $product = Product::where('sku', $sku)->first();
    if (!$product) {
        echo "$sku: NOT FOUND\n";
        continue;
    }
    echo "$sku: ID {$product->id}, name " . json_encode($product->name) . ", slug {$product->slug}, price {$product->price}, sku {$product->sku}\n";
    echo "  Categories: " . implode(', ', $product->categories()->pluck('slug')->toArray()) . "\n";
    echo "  Brands: " . implode(', ', $product->brands()->pluck('slug')->toArray()) . "\n";
    echo "  Variants: " . $product->variants()->count() . "\n";
    echo "  Media: " . $product->media()->count() . "\n";
}

echo "\nDone\n";
