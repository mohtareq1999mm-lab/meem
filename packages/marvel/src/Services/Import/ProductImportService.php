<?php

namespace Marvel\Services\Import;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Marvel\Database\Models\Attribute;
use Marvel\Database\Models\AttributeProduct;
use Marvel\Database\Models\AttributeValue;
use Marvel\Database\Models\Brand;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\FlashSale;
use Marvel\Database\Models\Import;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\ProductVariant;
use Marvel\Database\Models\Slider;
use Marvel\Enums\DiscountType;
use Marvel\Enums\ProductType;
use Marvel\Exceptions\ImportCancelledException;
use Marvel\Services\Import\ImageHandlers\UrlImageHandler;
use Marvel\Services\Pricing\ProductPricingService;

class ProductImportService
{
    protected ?UrlImageHandler $urlHandler = null;

    protected ProductPricingService $pricingService;

    protected array $failedRows = [];

    protected int $successCount = 0;

    protected array $keptVariantIds = [];

    protected array $createdProductIds = [];

    protected ?int $importId = null;

    protected int $processedCount = 0;

    protected float $startedAt;

    protected float $lastTickTime;

    protected int $lastTickProcessedCount = 0;

    protected float $currentProgress = 0.0;

    protected const FLUSH_THRESHOLD = 10;

    protected const SIGNAL_DIR = 'imports';

    public function __construct(?int $importId = null, ?ProductPricingService $pricingService = null)
    {
        $this->urlHandler = new UrlImageHandler();
        $this->importId = $importId;
        $this->pricingService = $pricingService ?? app(ProductPricingService::class);
        $now = microtime(true);
        $this->startedAt = $now;
        $this->lastTickTime = $now;
        $this->ensureSignalDir();
    }

    protected function ensureSignalDir(): void
    {
        $dir = storage_path('app/' . self::SIGNAL_DIR);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    protected function signalPath(string $type): ?string
    {
        if ($this->importId === null) {
            return null;
        }
        return storage_path('app/' . self::SIGNAL_DIR) . '/' . $type . '_' . $this->importId . '.json';
    }

    protected function writeSignal(string $type, array $data = []): void
    {
        $path = $this->signalPath($type);
        if ($path === null) {
            return;
        }
        try {
            file_put_contents($path, json_encode($data));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function readSignal(string $type): ?array
    {
        $path = $this->signalPath($type);
        if ($path === null || !file_exists($path)) {
            return null;
        }
        try {
            $contents = file_get_contents($path);
            return json_decode($contents, true) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function removeSignal(string $type): void
    {
        $path = $this->signalPath($type);
        if ($path === null) {
            return;
        }
        try {
            if (file_exists($path)) {
                @unlink($path);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function getFailedRows(): array
    {
        return $this->failedRows;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    protected function flushProgress(): void
    {
        if ($this->importId === null) {
            return;
        }

        $this->processedCount = $this->successCount + count($this->failedRows);

        $rowsSinceLastTick = $this->processedCount - $this->lastTickProcessedCount;
        $timeSinceLastTick = microtime(true) - $this->lastTickTime;

        if ($rowsSinceLastTick >= 10 || $timeSinceLastTick >= 30) {
            $this->currentProgress = $this->calculateSmoothProgress();
            $this->lastTickProcessedCount = $this->processedCount;
            $this->lastTickTime = microtime(true);
        }

        $this->writeSignal('progress', [
            'processed_rows' => $this->processedCount,
            'success_rows' => $this->successCount,
            'failed_rows' => count($this->failedRows),
            'progress' => $this->currentProgress,
        ]);

        if ($this->isCancelled()) {
            $this->writeProgress(true);
            throw new ImportCancelledException();
        }

        if ($this->processedCount % self::FLUSH_THRESHOLD === 0) {
            $this->writeProgress();
        }
    }

    public function writeExplicitProgress(float $progress): void
    {
        if ($this->importId === null) {
            return;
        }
        $this->currentProgress = $progress;
        $this->lastTickProcessedCount = $this->successCount + count($this->failedRows);
        $this->lastTickTime = microtime(true);
        $this->writeSignal('progress', [
            'processed_rows' => $this->successCount + count($this->failedRows),
            'success_rows' => $this->successCount,
            'failed_rows' => count($this->failedRows),
            'progress' => $progress,
        ]);
    }

    protected function calculateSmoothProgress(): float
    {
        $processed = $this->successCount + count($this->failedRows);
        $elapsed = max(microtime(true) - $this->startedAt, 0);

        $timeBased = 99.0 * (1 - exp(-$elapsed / 60));
        $rowBased = 99.0 * (2 / M_PI) * atan($processed / 200);
        $progress = max($timeBased, $rowBased);

        return round(min($progress, 99.0), 2);
    }

    protected function writeProgress(bool $ignoreStatus = false): void
    {
        try {
            $query = Import::where('id', $this->importId);

            if (!$ignoreStatus) {
                $query->where('status', 'processing');
            }

            $query->update([
                'processed_rows' => $this->processedCount,
                'success_rows' => $this->successCount,
                'failed_rows' => count($this->failedRows),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function finalizeProgress(): void
    {
        if ($this->importId === null) {
            return;
        }

        $this->processedCount = $this->successCount + count($this->failedRows);

        if ($this->isCancelled()) {
            throw new ImportCancelledException();
        }

        Import::where('id', $this->importId)
            ->update([
                'processed_rows' => $this->processedCount,
                'success_rows' => $this->successCount,
                'failed_rows' => count($this->failedRows),
            ]);
    }

    public function getCreatedProductIds(): array
    {
        return $this->createdProductIds;
    }

    public function getKeptVariantIds(): array
    {
        return $this->keptVariantIds;
    }

    protected function isCancelled(): bool
    {
        if ($this->importId === null) {
            return false;
        }

        return file_exists(storage_path('app/' . self::SIGNAL_DIR) . '/cancel_' . $this->importId . '.json');
    }

    public function rollbackCreatedData(): void
    {
        foreach ($this->keptVariantIds as $productId => $variantIds) {
            AttributeProduct::whereIn('product_variant_id', $variantIds)->delete();
            ProductVariant::whereIn('id', $variantIds)->forceDelete();
        }

        if (!empty($this->createdProductIds)) {
            $createdProducts = Product::whereIn('id', $this->createdProductIds)->get();
            foreach ($createdProducts as $product) {
                $product->categories()->detach();
                $product->brands()->detach();
                $product->flash_sales()->detach();
                $product->sliders()->detach();
                try {
                    $product->clearMediaCollection('products');
                } catch (\Throwable $e) {
                    report($e);
                }
                $product->forceDelete();
            }
        }
    }

    public function processProductRow(array $row, int $rowIndex): void
    {
        try {
            DB::beginTransaction();

            $sku = $row['sku'] ?? null;
            $product = null;

            if (!empty($sku)) {
                $product = Product::where('sku', $sku)->first();
            }

            $data = $this->buildProductData($row);

            if (empty($sku)) {
                $data['sku'] = 'PRD-' . Str::uuid();
            } else {
                $data['sku'] = $sku;
            }

            if ($product) {
                
                $data['slug'] = $product->slug;
                $product->fill($data)->saveQuietly();
            } else {
                $data['slug'] = $this->generateSlug($row, $product->id ?? null);
                $product = new Product($data);
                $product->saveQuietly();
                $this->createdProductIds[] = $product->id;
            }

            if (!empty($sku) && $product->sku !== $sku) {
                $product->sku = $sku;
                $product->saveQuietly();
            }

            $pricing = $this->pricingService->calculateProductPricingFromData(
                $product->toArray(),
                $product->getActiveFlashSale()
            );
            $product->fill([
                'price_after_discount' => $pricing['price_after_discount'] ?? null,
                'price_after_flash_sale' => $pricing['price_after_flash_sale'] ?? null,
            ])->saveQuietly();

            DB::commit();
            $this->successCount++;
        } catch (Exception $e) {
            DB::rollBack();
            $this->failedRows[] = [
                'sheet' => 'products',
                'row' => $rowIndex,
                'sku' => $row['sku'] ?? 'N/A',
                'error_message' => $e->getMessage(),
            ];

        }

        $this->flushProgress();
    }

    public function processVariantRow(array $row, int $rowIndex): void
    {
        $productSku = $row['product_sku'] ?? null;
        if (empty($productSku)) {
            return;
        }

        $product = Product::where('sku', $productSku)->first();
        if (!$product) {
            $this->failedRows[] = [
                'sheet' => 'product_variants',
                'row' => $rowIndex,
                'sku' => $productSku,
                'error_message' => "Product with SKU '{$productSku}' not found",
            ];
            $this->flushProgress();
            return;
        }

        try {
            DB::beginTransaction();

            $variant = $this->findVariantByFields($product->id, $row);

            $variantData = [
                'product_id' => $product->id,
                'sku' => $row['variant_sku'] ?? null,
                'price' => (float) ($row['price'] ?? 0),
                'sale_price' => isset($row['sale_price']) && $row['sale_price'] !== '' ? (float) $row['sale_price'] : null,
                'stock_quantity' => (int) ($row['quantity'] ?? 0),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'in_stock' => $this->parseBoolean($row['in_stock'] ?? true),
                'height' => $row['height'] ?? null,
                'width' => $row['width'] ?? null,
                'length' => $row['length'] ?? null,
                'weight' => $row['weight'] ?? null,
            ];

            if ($variant) {
                $variant->fill($variantData)->saveQuietly();
                $variant->attributeProducts()->delete();
            } else {
                $variant = new ProductVariant($variantData);
                $variant->saveQuietly();
            }

            $this->attachVariantAttributes($variant, $row);

            $this->keptVariantIds[$product->id][] = $variant->id;

            DB::commit();

            $product->product_type = ProductType::VARIABLE;
            $product->saveQuietly();

            $this->successCount++;
        } catch (Exception $e) {
            DB::rollBack();
            $this->failedRows[] = [
                'sheet' => 'product_variants',
                'row' => $rowIndex,
                'sku' => $productSku,
                'error_message' => $e->getMessage(),
            ];

        }

        $this->flushProgress();
    }

    protected function findVariantByFields(int $productId, array $row): ?ProductVariant
    {
        $query = ProductVariant::where('product_id', $productId)
            ->where('price', (float) ($row['price'] ?? 0));

        foreach (['height', 'width', 'length', 'weight'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $query->where($field, $row[$field]);
            } else {
                $query->whereNull($field);
            }
        }

        if (isset($row['sale_price']) && $row['sale_price'] !== '') {
            $query->where('sale_price', (float) $row['sale_price']);
        } else {
            $query->whereNull('sale_price');
        }

        return $query->first();
    }

    public function finalizeVariants(): void
    {
        foreach ($this->keptVariantIds as $productId => $variantIds) {
            ProductVariant::where('product_id', $productId)
                ->whereNotIn('id', $variantIds)
                ->delete();
        }
    }

    protected function attachVariantAttributes(ProductVariant $variant, array $row): void
    {
        $attributesString = $row['attributes'] ?? '';

        if (empty(trim($attributesString))) {
            return;
        }

        $groups = explode('-', $attributesString);

        foreach ($groups as $group) {
            $group = trim($group);
            if (empty($group)) {
                continue;
            }

            $parts = explode(':', $group, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $namePart = trim($parts[0]);
            $valuePart = trim($parts[1]);

            if (empty($namePart) || empty($valuePart)) {
                continue;
            }

            $nameLanguages = explode('|', $namePart, 2);
            $valueLanguages = explode('|', $valuePart, 2);

            $enName = trim($nameLanguages[0]);
            $arName = trim($nameLanguages[1] ?? '');
            $enValue = trim($valueLanguages[0]);
            $arValue = trim($valueLanguages[1] ?? '');

            if (empty($enName)) {
                continue;
            }

            $attribute = Attribute::where('name->en', $enName)
                ->when($arName, fn($q) => $q->where('name->ar', $arName))
                ->first();

            if (!$attribute) {
                $name = ['en' => $enName];
                if ($arName) {
                    $name['ar'] = $arName;
                }
                $attribute = Attribute::create(['name' => $name]);
            }

            $attributeValue = AttributeValue::where('attribute_id', $attribute->id)
                ->where('value->en', $enValue)
                ->when($arValue, fn($q) => $q->where('value->ar', $arValue))
                ->first();

            if (!$attributeValue) {
                $value = ['en' => $enValue];
                if ($arValue) {
                    $value['ar'] = $arValue;
                }
                $attributeValue = AttributeValue::create([
                    'attribute_id' => $attribute->id,
                    'value' => $value,
                ]);
            }

            AttributeProduct::firstOrCreate([
                'product_variant_id' => $variant->id,
                'attribute_value_id' => $attributeValue->id,
            ]);
        }
    }

    public function processProductImage(string $productSku, string $imageUrl): void
    {

        $product = Product::where('sku', $productSku)->first();
        if (!$product) {

            return;
        }

        $imageUrl = trim($imageUrl);
        if (empty($imageUrl)) {
            return;
        }

        try {
            if ($this->urlHandler && $this->urlHandler->isValidUrl($imageUrl)) {
                $downloaded = $this->urlHandler->download($imageUrl);
                if ($downloaded) {
                    $this->urlHandler->attachToModel($product, $downloaded, 'products');
                    $this->urlHandler->cleanup($downloaded);
                }
            } elseif (file_exists($imageUrl)) {
                $product->addMedia($imageUrl)
                    ->toMediaCollection('products');
            }
        } catch (Exception $e) {

        }
    }

    public function syncCategories(string $productSku, array $categorySlugs): void
    {
        $product = Product::where('sku', $productSku)->first();
        if (!$product) {
            return;
        }

        $categoryIds = Category::whereIn('slug', $categorySlugs)->pluck('id')->toArray();
        if (!empty($categoryIds)) {
            $product->categories()->sync($categoryIds);
        }
    }

    public function syncBrands(string $productSku, array $brandSlugs): void
    {
        $product = Product::where('sku', $productSku)->first();
        if (!$product) {
            return;
        }

        $brandIds = Brand::whereIn('slug', $brandSlugs)->pluck('id')->toArray();
        if (!empty($brandIds)) {
            $product->brands()->sync($brandIds);
        }
    }

    public function syncFlashSales(string $productSku, array $flashSaleSlugs): void
    {
        $product = Product::where('sku', $productSku)->first();
        if (!$product) {
            return;
        }

        $flashSaleIds = FlashSale::whereIn('slug', $flashSaleSlugs)->pluck('id')->toArray();
        if (!empty($flashSaleIds)) {
            $product->flash_sales()->sync($flashSaleIds);
        }
    }

    public function syncSliders(string $productSku, array $sliderSlugs): void
    {
        $product = Product::where('sku', $productSku)->first();
        if (!$product) {
            return;
        }

        $sliderIds = Slider::whereIn('slug', $sliderSlugs)->pluck('id')->toArray();
        if (!empty($sliderIds)) {
            $product->sliders()->sync($sliderIds);
        }
    }

    protected function buildProductData(array $row): array
    {
        $data = [];

        $name = [];
        if (!empty($row['name_en'])) {
            $name['en'] = $row['name_en'];
        }
        if (!empty($row['name_ar'])) {
            $name['ar'] = $row['name_ar'];
        }
        if (!empty($name)) {
            $data['name'] = $name;
        }

        $description = [];
        if (!empty($row['description_en'])) {
            $description['en'] = $row['description_en'];
        }
        if (!empty($row['description_ar'])) {
            $description['ar'] = $row['description_ar'];
        }
        if (!empty($description)) {
            $data['description'] = $description;
        }

        if (isset($row['price'])) {
            $data['price'] = (float) $row['price'];
        }

        if (isset($row['product_type'])) {
            $data['product_type'] = in_array($row['product_type'], ProductType::getValues())
                ? $row['product_type']
                : ProductType::SIMPLE;
        }

        if (isset($row['quantity'])) {
            $data['stock_quantity'] = (int) $row['quantity'];
            $data['quantity'] = (int) $row['quantity'];
        }

        if (isset($row['status'])) {
            $data['status'] = $this->parseBoolean($row['status']);
        }

        if (isset($row['in_stock'])) {
            $data['in_stock'] = $this->parseBoolean($row['in_stock']);
        }

        if (isset($row['has_discount'])) {
            $data['has_discount'] = $this->parseBoolean($row['has_discount']);
        }

        if (isset($row['discount_type'])) {
            $data['discount_type'] = in_array($row['discount_type'], DiscountType::getValues())
                ? $row['discount_type']
                : DiscountType::PERCENTAGE;
        }

        if (isset($row['discount_amount'])) {
            $data['discount_amount'] = (float) $row['discount_amount'];
        }

        if (!empty($row['start_date'])) {
            $data['start_date'] = Carbon::parse($row['start_date'])->format('Y-m-d');
        }

        if (!empty($row['end_date'])) {
            $data['end_date'] = Carbon::parse($row['end_date'])->format('Y-m-d');
        }

        $dimensionFields = ['height', 'width', 'length', 'weight'];
        foreach ($dimensionFields as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $data[$field] = (string) $row[$field];
            }
        }

        if (isset($row['pieces'])) {
            $data['pieces'] = (int) $row['pieces'];
        }

        if (isset($row['has_flash_sale'])) {
            $data['has_flash_sale'] = $this->parseBoolean($row['has_flash_sale']);
        }

        return $data;
    }

    protected function generateSlug(array $row, ?int $existingId = null): string
    {
        $baseSlug = Str::slug($row['name_en'] ?? $row['sku'] ?? 'product-' . Str::random(6));
        $slug = $baseSlug;
        $count = 1;

        while (Product::where('slug', $slug)->when($existingId, fn($q, $id) => $q->where('id', '!=', $id))->exists()) {
            $slug = $baseSlug . '-' . $count++;
        }

        return $slug;
    }

    protected function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'publish', 'approved']);
        }
        return false;
    }
}
