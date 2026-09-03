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

// Dry run validation
echo "\n=== DRY RUN ===\n";
$dryRun = [];
foreach ($files as $type => $path) {
    $full = __DIR__ . '/' . $path;
    $spreadsheet = IOFactory::load($full);
    $sheet = $spreadsheet->getSheet(0);
    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    $headers = [];
    for ($col = 'A'; $col <= $highestCol; $col++) {
        $val = $sheet->getCell($col . '1')->getValue();
        if ($val) $headers[] = trim($val);
    }
    $rows = [];
    for ($row = 2; $row <= $highestRow; $row++) {
        $rowData = [];
        for ($col = 'A'; $col <= $highestCol; $col++) {
            $rowData[$headers[ord($col)-65]] = $sheet->getCell($col . $row)->getValue();
        }
        $rows[] = $rowData;
    }
    $dryRun[$type] = ['headers' => $headers, 'rows' => $rows, 'count' => count($rows)];
    echo "$type: " . count($rows) . " rows, headers: " . implode(', ', $headers) . "\n";
    foreach ($rows as $i => $r) {
        echo "  Row ".($i+2).": " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

// Check duplicates and existing
echo "\n=== DUPLICATE CHECK ===\n";
foreach (['brands', 'categories', 'products'] as $type) {
    $rows = $dryRun[$type]['rows'];
    $keys = [];
    $dups = [];
    foreach ($rows as $r) {
        $key = $type === 'brands' ? ($r['name_en'] ?? '') : ($type === 'categories' ? ($r['name_en'] ?? '') : ($r['sku'] ?? ''));
        if (isset($keys[$key])) $dups[] = $key;
        $keys[$key] = true;
    }
    echo "$type duplicates in Excel: " . (empty($dups) ? "none" : implode(', ', $dups)) . "\n";
}

// Before counts
echo "\n=== BEFORE COUNTS ===\n";
$before = [
    'categories' => DB::table('categories')->count(),
    'brands' => DB::table('brands')->count(),
    'products' => DB::table('products')->count(),
];
foreach ($before as $k=>$v) echo "$k: $v\n";

// Check existing SKUs
$existingSkus = DB::table('products')->whereIn('sku', array_column($dryRun['products']['rows'], 'sku'))->pluck('sku')->toArray();
echo "Existing SKUs that would be updated: " . implode(', ', $existingSkus) . "\n";
$newSkus = array_diff(array_column($dryRun['products']['rows'], 'sku'), $existingSkus);
echo "New SKUs: " . implode(', ', $newSkus) . "\n";

// Check categories and brands existence for product relations
$catSlugs = DB::table('categories')->pluck('slug')->toArray();
$brandSlugs = DB::table('brands')->pluck('slug')->toArray();
echo "Existing category slugs sample: " . implode(', ', array_slice($catSlugs, 0, 5)) . "\n";
echo "Existing brand slugs sample: " . implode(', ', array_slice($brandSlugs, 0, 5)) . "\n";

// Check product's category/brand slugs
$prodSheets = IOFactory::load(__DIR__ . '/' . $files['products']);
$catSheet = $prodSheets->getSheetByName('categories');
if ($catSheet) {
    echo "Product categories sheet:\n";
    for ($row=2; $row<= $catSheet->getHighestRow(); $row++) {
        $sku = $catSheet->getCell('A'.$row)->getValue();
        $slug = $catSheet->getCell('B'.$row)->getValue();
        $exists = in_array($slug, $catSlugs) ? "exists" : "MISSING";
        echo "  $sku -> $slug ($exists)\n";
    }
}
$brandSheet = $prodSheets->getSheetByName('brands');
if ($brandSheet) {
    echo "Product brands sheet:\n";
    for ($row=2; $row<= $brandSheet->getHighestRow(); $row++) {
        $sku = $brandSheet->getCell('A'.$row)->getValue();
        $slug = $brandSheet->getCell('B'.$row)->getValue();
        $exists = in_array($slug, $brandSlugs) ? "exists" : "MISSING";
        echo "  $sku -> $slug ($exists)\n";
    }
}

// Now do real import using existing services
echo "\n=== REAL IMPORT ===\n";

// Import brands
$brandService = app(BrandImportService::class);
$brandRows = $dryRun['brands']['rows'];
$brandBefore = $before['brands'];
foreach ($brandRows as $i => $row) {
    $rowIndex = $i+2;
    try {
        // Check if brand exists by slug (generated from name_en)
        $slug = \Illuminate\Support\Str::slug($row['name_en']);
        $existing = Brand::where('slug', $slug)->first();
        if ($existing) {
            echo "Brand $slug exists (ID {$existing->id}), will update\n";
            // Use service's method? Check BrandImportService
            // It has processBrandRow?
        }
        // Try to use the service directly
        // BrandImportService has method to process row?
        // Let's inspect: it likely has processBrandRow
        if (method_exists($brandService, 'processBrandRow')) {
            $brandService->processBrandRow($row, $rowIndex);
        } else {
            // Fallback: use BrandsSheetImport
            echo "No processBrandRow, trying direct create\n";
            $brand = Brand::where('slug', $slug)->first();
            if (!$brand) {
                Brand::create(['name' => ['en' => $row['name_en'], 'ar' => $row['name_ar']], 'slug' => $slug, 'details' => ['en' => $row['details_en'], 'ar' => $row['details_ar']], 'is_active' => $row['status'] == 1]);
                echo "Created brand $slug\n";
            }
        }
    } catch (Exception $e) {
        echo "Brand row $rowIndex failed: " . $e->getMessage() . "\n";
    }
}
echo "Brands after: " . DB::table('brands')->count() . " (before $brandBefore)\n";

// Import categories
$catService = app(CategoryImportService::class);
$catRows = $dryRun['categories']['rows'];
$catBefore = $before['categories'];
foreach ($catRows as $i => $row) {
    $rowIndex = $i+2;
    try {
        if (method_exists($catService, 'processCategoryRow')) {
            $catService->processCategoryRow($row, $rowIndex);
        } else {
            echo "No processCategoryRow\n";
        }
    } catch (Exception $e) {
        echo "Category row $rowIndex failed: " . $e->getMessage() . "\n";
    }
}
echo "Categories after: " . DB::table('categories')->count() . " (before $catBefore)\n";

// Import products via ProductImportService
$prodService = app(ProductImportService::class);
$prodRows = $dryRun['products']['rows'];
$prodBefore = $before['products'];
foreach ($prodRows as $i => $row) {
    $rowIndex = $i+2;
    try {
        $prodService->processProductRow($row, $rowIndex);
        echo "Product {$row['sku']} processed\n";
    } catch (Exception $e) {
        echo "Product row $rowIndex failed: " . $e->getMessage() . "\n";
    }
}
// Process variants, images, etc. via sheets
$spreadsheet = IOFactory::load(__DIR__ . '/' . $files['products']);
foreach (['product_variants', 'images', 'categories', 'brands', 'flash_sales', 'sliders', 'tags'] as $sheetName) {
    $sheet = $spreadsheet->getSheetByName($sheetName);
    if (!$sheet) continue;
    $headers = [];
    $highestCol = $sheet->getHighestColumn();
    for ($col='A'; $col <= $highestCol; $col++) {
        $val = $sheet->getCell($col.'1')->getValue();
        if ($val) $headers[] = trim($val);
    }
    for ($row=2; $row <= $sheet->getHighestRow(); $row++) {
        $rowData = [];
        for ($col='A'; $col <= $highestCol; $col++) {
            $idx = ord($col)-65;
            if (isset($headers[$idx])) {
                $rowData[$headers[$idx]] = $sheet->getCell($col.$row)->getValue();
            }
        }
        if (empty(array_filter($rowData))) continue;
        try {
            switch ($sheetName) {
                case 'product_variants':
                    $prodService->processVariantRow($rowData, $row);
                    echo "Variant {$rowData['variant_sku']} processed\n";
                    break;
                case 'images':
                    $prodService->processProductImage($rowData['product_sku'], $rowData['image']);
                    echo "Image for {$rowData['product_sku']} processed\n";
                    break;
                case 'categories':
                    $prodService->syncCategories($rowData['product_sku'], [$rowData['category_slug']]);
                    echo "Category {$rowData['category_slug']} synced for {$rowData['product_sku']}\n";
                    break;
                case 'brands':
                    $prodService->syncBrands($rowData['product_sku'], [$rowData['brand_slug']]);
                    echo "Brand {$rowData['brand_slug']} synced for {$rowData['product_sku']}\n";
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
                    break;
            }
        } catch (Exception $e) {
            echo "Sheet $sheetName row $row failed: " . $e->getMessage() . "\n";
        }
    }
}
$prodService->finalizeVariants();

echo "Products after: " . DB::table('products')->count() . " (before $prodBefore)\n";

// After counts
echo "\n=== AFTER COUNTS ===\n";
$after = [
    'categories' => DB::table('categories')->count(),
    'brands' => DB::table('brands')->count(),
    'products' => DB::table('products')->count(),
];
foreach ($after as $k=>$v) {
    echo "$k: $v (created: " . ($v - $before[$k]) . ")\n";
}

// Verify relationships
echo "\n=== RELATIONSHIP VERIFICATION ===\n";
foreach (['PRD-SAMPLE-001', 'PRD-SAMPLE-002', 'PRD-SAMPLE-003'] as $sku) {
    $product = Product::where('sku', $sku)->first();
    if (!$product) {
        echo "$sku: NOT FOUND\n";
        continue;
    }
    echo "$sku: ID {$product->id}, name " . json_encode($product->name) . ", slug {$product->slug}, price {$product->price}, quantity {$product->quantity}\n";
    $cats = $product->categories()->pluck('slug')->toArray();
    echo "  Categories: " . implode(', ', $cats) . "\n";
    $brands = $product->brands()->pluck('slug')->toArray();
    echo "  Brands: " . implode(', ', $brands) . "\n";
    echo "  Translations en: " . ($product->getTranslation('name', 'en') ?? 'N/A') . " ar: " . ($product->getTranslation('name', 'ar') ?? 'N/A') . "\n";
}

echo "\nDone\n";
