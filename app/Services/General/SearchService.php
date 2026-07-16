<?php

namespace App\Services\General;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Marvel\Database\Models\Brand;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Shop;

class SearchService
{

    public function search(Request $request)
    {

    }
    // public function search(Request $request): array
    // {
    //     $term = trim((string) $request->get('search', ''));
    //     $limit = $this->getLimit($request);

    //     $products = $this->buildProductQuery($request, $term, app()->getLocale())
    //         ->paginate($limit, ['*'], 'product_page');

    //     $shops = $this->buildShopQuery($term, app()->getLocale())
    //         ->paginate($limit, ['*'], 'shop_page');

    //     $categories = $this->buildCategoryQuery($term, app()->getLocale())
    //         ->paginate($limit, ['*'], 'category_page');

    //     $brands = $this->buildBrandQuery($term, app()->getLocale())
    //         ->paginate($limit, ['*'], 'brand_page');

    //     $data = [
    //         'products' => $products,
    //         'shops' => $shops,
    //         'categories' => $categories,
    //         'brands' => $brands,
    //     ];

    //     return $data;
    // }

    // private function buildProductQuery(Request $request, string $term, string $locale): Builder
    // {
    //     $query = Product::query()
    //         ->with(['shop:id,name', 'categories:id,name'])
    //         ->withAvg('reviews', 'rating')
    //         ->withCount('reviews');

    //     $query = $this->applyProductSearch($query, $term, app()->getLocale());

    //     return $query->orderByDesc('id');
    // }

    // private function applyProductSearch(Builder $query, string $term, string $locale)
    // {
    //     return $query->where(function (Builder $builder) use ($term, $locale) {
    //         $this->applyTranslatableLike($builder, 'name', $term, $locale);

    //         $builder->orWhere(function (Builder $sub) use ($term, $locale) {
    //             $this->applyTranslatableLike($sub, 'description', $term, $locale);
    //         });

    //         if (is_numeric($term)) {
    //             $builder->orWhere('price', $term)
    //                 ->orWhere('sold_quantity', $term);
    //         }


    //         $builder->orWhereHas('shop', function (Builder $shopQuery) use ($term, $locale) {
    //             $this->applyTranslatableLike($shopQuery, 'name', $term, $locale);
    //         });

    //         $builder->orWhereHas('categories', function (Builder $categoryQuery) use ($term, $locale) {
    //             $this->applyTranslatableLike($categoryQuery, 'name', $term, $locale);
    //         });
    //     });
    // }

    // private function buildShopQuery(string $term, string $locale): Builder
    // {
    //     $query = Shop::query()->withCount('categories');

    //     if ($term !== '') {
    //         $query->where(function (Builder $builder) use ($term, $locale) {
    //             $this->applyTranslatableLike($builder, 'name', $term, $locale);
    //             $builder->orWhere(function (Builder $sub) use ($term, $locale) {
    //                 $this->applyTranslatableLike($sub, 'description', $term, $locale);
    //             });
    //         });
    //     }

    //     return $query->orderByDesc('id');
    // }

    // private function buildCategoryQuery(string $term, string $locale): Builder
    // {
    //     $query = Category::query()->withCount('products');

    //     if ($term !== '') {
    //         $query->where(function (Builder $builder) use ($term, $locale) {
    //             $this->applyTranslatableLike($builder, 'name', $term, $locale);
    //             $builder->orWhere(function (Builder $sub) use ($term, $locale) {
    //                 $this->applyTranslatableLike($sub, 'details', $term, $locale);
    //             });
    //         });
    //     }

    //     return $query->orderByDesc('id');
    // }

    // private function buildBrandQuery(string $term, string $locale): Builder
    // {
    //     $query = Brand::query()->active();

    //     if ($term !== '') {
    //         $query->where(function (Builder $builder) use ($term, $locale) {
    //             $this->applyTranslatableLike($builder, 'name', $term, $locale);
    //             $builder->orWhere(function (Builder $sub) use ($term, $locale) {
    //                 $this->applyTranslatableLike($sub, 'details', $term, $locale);
    //             });
    //         });
    //     }

    //     return $query->orderByDesc('id');
    // }

    // private function applyTranslatableLike(Builder $query, string $field, string $term, string $locale): void
    // {
    //     $query->where($field . '->' . $locale, 'like', '%' . $term . '%')
    //         ->orWhere($field, 'like', '%' . $term . '%');
    // }

    // private function getLimit(Request $request): int
    // {
    //     $limit = (int) $request->get('limit', 15);
    //     if ($limit <= 0) {
    //         return 15;
    //     }

    //     return min($limit, 100);
    // }
}
