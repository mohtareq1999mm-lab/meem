<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Attribute;
use Marvel\Database\Models\AttributeProduct;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\ProductVariant;
use Marvel\Enums\ProductType;
use Marvel\Services\Pricing\ProductPricingService;

class ProductVariantSeeder extends Seeder
{
    public function run(): void
    {
        $pricingService = app(ProductPricingService::class);
        $attributes = Attribute::with('values')->get();

        $colorValues = $this->valuesForAttribute($attributes, 'Color');
        $sizeValues = $this->valuesForAttribute($attributes, 'Size');

        // Only pick variable-type products that have stock
        $products = Product::where('product_type', ProductType::VARIABLE)
            ->where('stock_quantity', '>', 0)
            ->inRandomOrder()
            ->take(15)
            ->with('flash_sales')
            ->get();

        if ($products->isEmpty()) {
            $this->command?->warn('No variable-type products found. Skipping variant creation.');
            return;
        }

        foreach ($products as $index => $product) {
            $basePrice = (float) $product->price;
            $flashSale = $product->has_flash_sale ? $pricingService->resolveActiveFlashSale($product) : null;

            $colorIdx = $colorValues->isNotEmpty() ? $index % $colorValues->count() : null;
            $sizeIdx = $sizeValues->isNotEmpty() ? $index % $sizeValues->count() : null;

            $variants = [
                [
                    'price' => $basePrice,
                    'stock_quantity' => max(5, (int) ($product->stock_quantity * 0.6)),
                    'height' => $product->height,
                    'width' => $product->width,
                    'length' => $product->length,
                    'weight' => $product->weight,
                    'attributes' => [
                        $colorValues->get($colorIdx) ?? null,
                        $sizeValues->get($sizeIdx) ?? null,
                    ],
                ],
                [
                    'price' => round($basePrice * 1.15, 2),
                    'stock_quantity' => max(3, (int) ($product->stock_quantity * 0.4)),
                    'height' => $product->height,
                    'width' => $product->width,
                    'length' => $product->length,
                    'weight' => $product->weight,
                    'attributes' => [
                        $colorValues->get(($colorIdx + 1) % $colorValues->count()) ?? null,
                        $sizeValues->get(($sizeIdx + 1) % $sizeValues->count()) ?? null,
                    ],
                ],
            ];

            foreach ($variants as $variantData) {
                $attributeValues = array_values(array_filter($variantData['attributes']));

                $variant = ProductVariant::create([
                    'price' => $variantData['price'],
                    'sale_price' => $pricingService->calculateVariantSalePrice($product, $variantData, $flashSale),
                    'stock_quantity' => $variantData['stock_quantity'],
                    'reserved_quantity' => 0,
                    'sold_quantity' => 0,
                    'height' => $variantData['height'],
                    'width' => $variantData['width'],
                    'length' => $variantData['length'],
                    'weight' => $variantData['weight'],
                    'product_id' => $product->id,
                ]);

                foreach ($attributeValues as $attributeValue) {
                    AttributeProduct::create([
                        'product_variant_id' => $variant->id,
                        'attribute_value_id' => $attributeValue->id,
                    ]);
                }
            }

            // Ensure product_type is set to variable after creating variants
            if ($product->product_type !== ProductType::VARIABLE) {
                $product->update(['product_type' => ProductType::VARIABLE]);
            }
        }

        $this->command?->info('ProductVariantSeeder completed. Created variants for ' . $products->count() . ' products.');
    }

    private function valuesForAttribute($attributes, string $attributeName)
    {
        $attribute = $attributes->first(function ($item) use ($attributeName) {
            return strtolower((string) $item->getTranslation('name', 'en')) === strtolower($attributeName);
        });

        return $attribute ? collect($attribute->values->values()) : collect();
    }
}
