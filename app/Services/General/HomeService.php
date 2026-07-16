<?php

namespace App\Services\General;

use App\Traits\HasChannelFilter;
use App\Http\Resources\Banner\BannerResource;
use App\Http\Resources\Brand\BrandResource;
use App\Http\Resources\Category\CategoryHomeResource;
use App\Http\Resources\Category\CategoryNavbarResource;
use App\Http\Resources\Coupons\CouponResource;
use App\Http\Resources\FlashSale\FlashSaleResource;
use App\Http\Resources\Product\ProductMiniResource;
use App\Http\Resources\Slider\SliderResource;
use App\Services\General\ProductService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Banner;
use Marvel\Database\Models\Brand;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\Coupon;
use Marvel\Database\Models\FlashSale;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Slider;

class HomeService
{
    use HasChannelFilter;

    public function __construct(
        private readonly CategoryHierarchyService $hierarchyService,
        private readonly ProductService $productService,
    ) {}

    public function getNavData(?int $level = null)
    {
        $cacheKey = $level !== null ? "home-nav-bar:level:{$level}" : "home-nav-bar";

        return Cache::remember($cacheKey, 120, function () {
            return CategoryNavbarResource::collection($this->getCategoryWithChildren());
        });
    }
    public function getHomeData(?int $parentCategoryId = null, ?array $sections = null)
    {
        $parentCategoryId = $parentCategoryId ?: 1;

        $categoryTree = Cache::remember("home_data:parent:{$parentCategoryId}:category-tree", 120, function () use ($parentCategoryId) {
            return $this->getCategoryTree($parentCategoryId);
        });

        $categoriesWithChildren = Cache::remember("home_data:parent:{$parentCategoryId}:categories-with-children", 120, function () {
            return $this->getCategories();
        });

        $data = [

            'sliders' => Cache::remember("home-active-sliders", 120, function () {
                return SliderResource::collection($this->getActiveSliders());
            }),
            'dailyOffers' => Cache::remember("home-flash-sales", 120, function () {
                return FlashSaleResource::collection($this->getFlashSalesForOneDay(9));
            }),
            'bestCategories' => Cache::remember("home-best-categories", 120, function () use ($categoriesWithChildren) {
                return CategoryHomeResource::collection($categoriesWithChildren);
            }),
            'discountProductsEndToday' => Cache::remember("home-discount-products-end-today", 120, function () {
                return ProductMiniResource::collection($this->getDiscountEndingTodayOrLowStockProducts());
            }),
            'banners' => Cache::remember("home-active-banners", 120, function () {
                return BannerResource::collection($this->getActiveBanners());
            }),
            'brands' => Cache::remember("home-brands", 120, function () {
                return BrandResource::collection($this->getBrands());
            }),

            'parent_categories' => Cache::remember("home-parent-categories", 120, function () use ($categoryTree) {
                return CategoryHomeResource::collection($categoryTree);
            }),

            'coupons' => Cache::remember('home-latest-coupons', 120, function () {
                return CouponResource::collection($this->getLatestValidCoupons(5));
            }),
            'flashSaleProducts' => Cache::remember("home-flash-sale-products", 120, function () {
                return ProductMiniResource::collection($this->getFlashSaleProductsEndingThisWeek());
            }),
            'parentCategories' => Cache::remember("home-weekly-parent-categories", 120, function () use ($categoryTree) {
                return CategoryHomeResource::collection($categoryTree);
            }),
            'weeklyProducts' => Cache::remember("home-weekly-products", 120, function () use ($categoryTree) {
                return ProductMiniResource::collection($this->getWeeklyCategoryProducts($categoryTree));
            }),
            'allDiscountProducts' => Cache::remember("home-all-discount-products", 120, function () {
                return ProductMiniResource::collection($this->getAllDiscountProducts());
            }),
            'newArrivals' => Cache::remember("home-flash-sales-after-9", 120, function () {
                return ProductMiniResource::collection($this->getNewArrivals(10));
            }),

        ];

        if ($sections === null || $sections === []) {
            return $data;
        }

        $requested = array_values(array_intersect(array_keys($data), $sections));

        return array_intersect_key($data, array_flip($requested));
    }

    public function getLatestValidCoupons(int $limit = 5): Collection
    {
        return Coupon::query()
            ->valid()
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public static function availableSections(): array
    {
        return [
            'nav-bar',
            'active_sliders',
            'active_banners',
            'brands',
            'best_categories',
            'parent_categories',
            'discount_products_end_today',
            'flash_sales',
            'flash_sale_products',
            'weekly_parent_categories',
            'weekly_products',
            'all_discount_products',
            'flash_sales_after_9',
            'coupons',
        ];
    }

    public function getActiveSliders(): Collection
    {
        return Slider::active()->ordered()->get();
    }

    public function getActiveBanners(): Collection
    {
        return Banner::active()->ordered()->get();
    }

    public function getBrands(): Collection
    {
        return Brand::query()
            ->active()
            ->select(['id', 'name', 'slug', 'details', 'status'])
            ->orderByDesc('id')
            ->get();
    }

    public function getFlashSales(int $limit, $after = null): Collection
    {

        return FlashSale::query()->valid()
            ->when($after, function ($query, $after) {
                $query->where('id', '>', $after);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
    public function getFlashSalesForOneDay(int $limit): Collection
    {
        return FlashSale::query()->valid()
            ->where('start_date', '=', today())
            ->where('end_date', '=', today())
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function getDiscountEndingTodayOrLowStockProducts(): Collection
    {
        $products = Product::query()
            ->when(true, fn($q) => $this->applyChannelHomeFilter($q))
            ->select([
                'id',
                'name',
                'slug',
                'price',
                'quantity',
                'has_discount',
                'discount_type',
                'discount_amount',
                'discount_status',
                'start_date',
                'end_date',
            ])
            ->with(['reviews', 'media', 'flash_sales' => fn($q) => $q->valid()])
            ->withAvg(['reviews' => fn($q) => $q->approved()], 'rating')
            ->whereNull('deleted_at')
            ->where('status', true)
            ->where('has_discount', true)
            ->where('has_flash_sale', false)
            ->where(function ($query) {
                $query->whereDate('end_date', today())
                    ->orWhereBetween('quantity', [1, 9]);
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return $this->productService->enrichCollectionWithPricing($products)
            ->filter(fn(Product $product) => (bool) $product->discount_active)
            ->values();
    }

    public function getNewArrivals(int $limit = 10): Collection
    {
        $products = Product::query()
            ->when(true, fn($q) => $this->applyChannelHomeFilter($q))
            ->select([
                'id',
                'name',
                'slug',
                'price',
                'quantity',
                'has_discount',
                'discount_type',
                'discount_amount',
                'discount_status',
                'start_date',
                'end_date',
            ])
            ->with(['reviews', 'media', 'flash_sales' => fn($q) => $q->valid()])
            ->withAvg(['reviews' => fn($q) => $q->approved()], 'rating')
            ->whereNull('deleted_at')
            ->where('status', true)
            ->where('has_flash_sale', false)
            ->whereDate('created_at', '>=', now()->subDays(15))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->productService->enrichCollectionWithPricing($products)->values();
    }
    public function getFlashSaleProductsEndingThisWeek(): Collection
    {
        $weekEnd = now()->endOfWeek();

        $products = Product::query()
            ->when(true, fn($q) => $this->applyChannelHomeFilter($q))
            ->select([
                'id',
                'name',
                'slug',
                'price',
                'quantity',
                'has_discount',
                'discount_type',
                'discount_amount',
                'discount_status',
                'start_date',
                'end_date',
            ])
            ->with(['reviews', 'media', 'flash_sales' => fn($q) => $q->valid()])
            ->withAvg(['reviews' => fn($q) => $q->approved()], 'rating')
            ->whereNull('deleted_at')
            ->where('status', true)
            ->where('has_flash_sale', true)
            ->whereExists(function ($query) use ($weekEnd) {
                $query->select(DB::raw(1))
                    ->from('flash_sale_products')
                    ->join('flash_sales', 'flash_sale_products.flash_sale_id', '=', 'flash_sales.id')
                    ->whereColumn('flash_sale_products.product_id', 'products.id')
                    ->whereNull('flash_sales.deleted_at')
                    ->where('flash_sales.status', true)
                    ->whereNotNull('flash_sales.end_date')
                    ->whereBetween('flash_sales.end_date', [today(), $weekEnd]);
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return $this->productService->enrichCollectionWithPricing($products)->values();
    }

    public function getWeeklyCategoryProducts(Collection $categoryTree, int $productLimit = 10): Collection
    {
        $categoryIds = $categoryTree->pluck('id')->all();

        $products = Product::query()
            ->when(true, fn($q) => $this->applyChannelHomeFilter($q))
            ->select([
                'id',
                'name',
                'slug',
                'price',
                'quantity',
                'has_discount',
                'discount_type',
                'discount_amount',
                'discount_status',
                'start_date',
                'end_date',
            ])
            ->with(['reviews', 'media', 'flash_sales' => fn($q) => $q->valid()])
            ->withAvg(['reviews' => fn($q) => $q->approved()], 'rating')
            ->whereNull('deleted_at')
            ->where('status', true)
            ->where('has_discount', true)
            ->whereExists(function ($query) use ($categoryIds) {
                $query->select(DB::raw(1))
                    ->from('category_product')
                    ->whereColumn('category_product.product_id', 'products.id')
                    ->whereIn('category_product.category_id', $categoryIds);
            })
            ->orderByDesc('id')
            ->limit($productLimit)
            ->get();

        return $this->productService->enrichCollectionWithPricing($products)
            ->filter(fn(Product $product) => (bool) $product->discount_active)
            ->values();
    }

    public function getAllDiscountProducts(): Collection
    {
        $products = Product::query()
            ->when(true, fn($q) => $this->applyChannelHomeFilter($q))
            ->select([
                'id',
                'name',
                'slug',
                'price',
                'quantity',
                'has_discount',
                'discount_type',
                'discount_amount',
                'discount_status',
                'start_date',
                'end_date',
            ])
            ->with(['reviews', 'media', 'flash_sales' => fn($q) => $q->valid()])
            ->withAvg(['reviews' => fn($q) => $q->approved()], 'rating')
            ->whereNull('deleted_at')
            ->where('status', true)
            ->where('has_discount', true)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return $this->productService->enrichCollectionWithPricing($products)
            ->filter(fn(Product $product) => (bool) $product->discount_active)
            ->values();
    }

    private function getCategoryTree($id = 1): Collection
    {
        $parent = Category::query()->active()
            ->whereNull('parent_id')
            ->where('id', '=', $id)
            ->withCount('products')
            ->first();

        if (!$parent) {
            return collect();
        }

        return $parent->children()
            ->active()
            ->withCount('products')
            ->orderBy('id')
            ->get();
    }


    private function getCategories(): Collection
    {
        $categories = Category::query()->active()
            ->withCount('products')
            ->orderByDesc('products_count')
            ->limit(20)
            ->get();

        return $categories;
    }


    private function getCategoryWithChildren(): Collection
    {
        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->withCount('products')
            ->with(['children' => function ($query) {
                $query->active()->withCount('products')->with(['children' => function ($q) {
                    $q->active()->withCount('products');
                }]);
            }])
            ->orderByDesc('products_count')
            ->get();
    }

    public static function clearCache(): void
    {
        $keys = [
            'home-flash-sales',
            'home-discount-products-end-today',
            'home-flash-sale-products',
            'home-weekly-products',
            'home-all-discount-products',
            'home-flash-sales-after-9',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    private function moneyValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
