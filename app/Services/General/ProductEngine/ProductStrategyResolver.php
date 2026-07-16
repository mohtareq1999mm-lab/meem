<?php


namespace App\Services\General\ProductEngine;

use App\Services\General\ProductEngine\Strategies\AllProduct;
use App\Services\General\ProductEngine\Strategies\AllProductHasDiscount;
use App\Services\General\ProductEngine\Strategies\BestProduct;
use App\Services\General\ProductEngine\Strategies\NewArrivals;
use App\Services\General\ProductEngine\Strategies\ProductDiscountEndingTodayOrLowStock;
use App\Services\General\ProductEngine\Strategies\ProductForBrand;
use App\Services\General\ProductEngine\Strategies\ProductForParentCategory;
use App\Services\General\ProductEngine\Strategies\ProductHasFlashSale;
use App\Services\General\ProductEngine\Strategies\ProductHasFlashSaleEndThisWeek;
use App\Services\General\ProductEngine\Strategies\ProductHasFlashSaleEndToday;

class ProductStrategyResolver
{
    public function resolve($type)
    {
        return match ($type) {
            'index'                                             => app(AllProduct::class), // all products in the system
            'best_product_sales'                                => app(BestProduct::class), // pest product in the system
            'brands_product'                                    => app(ProductForBrand::class), // products for all active brand
            'new_arrivals'                                      => app(NewArrivals::class), // new arrivals products in the system
            'all_product_discounts'                             => app(AllProductHasDiscount::class), // all products that have discount in the system
            'product_discount_today_or_low_qty'                 => app(ProductDiscountEndingTodayOrLowStock::class), // products with discount ending today or low stock
            'flash_sales_product'                               => app(ProductHasFlashSale::class), // products that have flash sales is valid andreturn only product
            'flash_sales_end_today'                             => app(ProductHasFlashSaleEndToday::class), // products that have flash sales is valid and return product
            'product_for_parent_category'                       => app(ProductForParentCategory::class), // products for parent category
            'flash_sales_end_week'                              => app(ProductHasFlashSaleEndThisWeek::class), // products that have flash sales is valid and return  product
            default => throw new \InvalidArgumentException("Invalid product type: $type"),
        };
    }
}
