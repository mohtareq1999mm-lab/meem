<?php

namespace App\Services\General;

use App\Traits\HasChannelFilter;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\FlashSale;
use Marvel\Database\Models\Product;

class FlashSaleService
{
    use HasChannelFilter;

    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function paginateFlashSales($request)
    {
        $limit = $request->get('limit', 10);
        $start_date = $request->query('start_date');
        $end_date = $request->query('end_date');
        $flashSalesId = $request->query('flashSalesId');
        $order = $request->query('order', 'desc');

        $query = FlashSale::query()->valid()
            ->when($start_date, function ($query) use ($start_date) {
                $query->where('created_at', '>=', $start_date);
            })
            ->when($end_date, function ($query) use ($end_date) {
                $query->where('created_at', '<=', $end_date);
            });

        if (!empty($flashSalesId)) {
            $ids = is_array($flashSalesId) ? $flashSalesId : explode(',', $flashSalesId);
            $ids = array_filter($ids, 'is_numeric');
            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        return $query->orderBy('id', $order)->paginate($limit);
    }

    public function getFlashSaleBySlug($slug)
    {
        $FlashSale = FlashSale::search('slug', $slug, app()->getLocale())->first();
        if ($FlashSale) {
            $FlashSale->load(['products' => fn($q) => $this->applyChannelHomeFilter($q)->with(['media'])->withAvg(['reviews' => fn($q) => $q->approved()], 'rating')]);
            $this->productService->enrichCollectionWithPricing($FlashSale->products);
        }
        return $FlashSale;
    }
    public function getFlashSalesAndHereProductsByQtySet($request)
    {
        $qty = $request->query('limit', 5);
        $start_date = $request->query('start_date', '');
        $end_date = $request->query('end_date', '');

        $flashSales = FlashSale::query()->valid()
            ->when($start_date, function ($query) use ($start_date) {
                $query->where('created_at', '>=', $start_date);
            })
            ->when($end_date, function ($query) use ($end_date) {
                $query->where('created_at', '<=', $end_date);
            })
            ->with([
                'products' => function ($query) use ($qty) {
                    $this->applyChannelHomeFilter($query);
                    $query->with(['media'])->withAvg(['reviews' => fn($q) => $q->approved()], 'rating')->limit($qty);
                }
            ])->get()
            ->pluck('products')
            ->flatten();

        return $this->productService->enrichCollectionWithPricing($flashSales);
    }
    public function getFlashSaleProductsEndingThisWeek($request)
    {
        $limit = $request->query('limit', 10);
        $weekEnd = now()->endOfWeek();

        $products = Product::query()
            ->with(['categories', 'variations', 'brands', 'media', 'flash_sales' => fn($q) => $q->valid()])
            ->withAvg(['reviews' => fn($q) => $q->approved()], 'rating')
            ->select([
                'id', 'name', 'slug', 'price', 'quantity',
                'has_discount', 'discount_type', 'discount_amount', 'discount_status',
                'start_date', 'end_date',
            ])
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
            ->limit($limit)
            ->get();

        return $this->productService->enrichCollectionWithPricing($products)->values();
    }
    public function getFlashSaleProductsEndingToday($request)
    {
        $limit = $request->query('limit', 10);

        $products = Product::query()
            ->with(['categories', 'variations', 'brands', 'media', 'flash_sales' => fn($q) => $q->valid()])
            ->withAvg(['reviews' => fn($q) => $q->approved()], 'rating')
            ->select([
                'id', 'name', 'slug', 'price', 'quantity',
                'has_discount', 'discount_type', 'discount_amount', 'discount_status',
                'start_date', 'end_date',
            ])
            ->whereNull('deleted_at')
            ->where('status', true)
            ->where('has_flash_sale', true)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('flash_sale_products')
                    ->join('flash_sales', 'flash_sale_products.flash_sale_id', '=', 'flash_sales.id')
                    ->whereColumn('flash_sale_products.product_id', 'products.id')
                    ->whereNull('flash_sales.deleted_at')
                    ->where('flash_sales.status', true)
                    ->whereNotNull('flash_sales.end_date')
                    ->whereDate('flash_sales.end_date', today());
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->productService->enrichCollectionWithPricing($products)->values();
    }
    private function moneyValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
