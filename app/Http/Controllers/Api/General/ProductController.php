<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductMiniResource;
use App\Http\Resources\Product\ProductResource;
use App\Services\General\ProductEngine\ProductStrategyResolver;
use App\Services\General\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Marvel\Database\Models\Category;
use Marvel\Http\Requests\ReviewCreateRequest;
use Marvel\Http\Requests\ReviewUpdateRequest;
use Marvel\Http\Resources\product\ProductCollectionMini;
use Marvel\Traits\ApiResponse;

class ProductController extends Controller
{
    use ApiResponse;

    private ProductService $productService;
    protected ProductStrategyResolver $productStrategyResolver;

    public function __construct(ProductService $productService, ProductStrategyResolver $productStrategyResolver)
    {
        $this->productService = $productService;
        $this->productStrategyResolver = $productStrategyResolver;
    }

    /**
     * List products with filtering, searching, and strategy-based display.
     *
     * Query Parameters:
     * - type (string): Product strategy type (e.g., 'index', 'best_product', 'new_arrivals').
     * - search (string): Full-text search term.
     * - limit (int): Results per page (max 100).
     * - order (string): Sort direction ('asc'|'desc').
     * - order_price (string): Sort by price ('asc'|'desc').
     * - category, brand, height, width, length, weight: Filter keys.
     * - rating, rating_min, rating_max: Rating filters.
     * - productsId (string): Comma-separated product IDs.
     * - categoriesId, brandsId, promotionsId, flashSalesId, bannersId, couponsId, slidersId: Relation ID filters.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type', 'index');
        $order = $request->query('order', 'desc');

        if (!empty($type)) {
            $handler = $this->productStrategyResolver->resolve($type);
            $data = $handler->getProducts($request);

            $productIds = $data instanceof LengthAwarePaginator
                ? $data->getCollection()->pluck('id')
                : $data->pluck('id');

            $filters = [];
            if ($productIds->isNotEmpty()) {
                $query = \Marvel\Database\Models\Product::query()->whereIn('id', $productIds);
                $filters = $this->productService->getDynamicFilters($query);
            }

            if ($data instanceof LengthAwarePaginator) {
                $collection = new ProductCollectionMini($data);
                $responseData = $collection->toArray($request);
            } else {
                $total = $data->count();
                $responseData = [
                    'data' => ProductMiniResource::collection($data),
                    'links' => $this->buildSimpleLinks($request, $total),
                ];
            }

            $responseData['filters'] = $filters;
            $responseData['categories'] = $this->getCollectionCategories($productIds);

            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $responseData);
        }

        $scoutQuery = $this->productService->buildScoutSearchQuery($request);

        if ($scoutQuery !== null) {
            $data = $scoutQuery->orderBy('id', $order)->paginate($this->productService->getLimit($request));
            $filters = $this->productService->getDynamicFilters(clone $scoutQuery);
        } else {
            $query = $this->productService->buildFilteredBaseQuery($request);
            $filters = $this->productService->getDynamicFilters(clone $query);
            $orderPrice = $request->query('order_price');
            if (in_array($orderPrice, ['asc', 'desc'])) {
                $query->orderBy('price', $orderPrice);
            }
            $data = $query->orderBy('id', $order)->paginate($this->productService->getLimit($request));
        }

        $productIds = $data->getCollection()->pluck('id');
        $collection = new ProductCollectionMini($data);
        $responseData = $collection->toArray($request);
        $responseData['filters'] = $filters;
        $responseData['categories'] = $this->getCollectionCategories($productIds);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $responseData);
    }

    /**
     * Build a simple pagination links array for non-paginated collections.
     */
    private function buildSimpleLinks(Request $request, int $total): array
    {
        return [
            'current_page' => 1,
            'from' => $total > 0 ? 1 : null,
            'to' => $total,
            'last_page' => 1,
            'path' => $request->url(),
            'per_page' => $total,
            'total' => $total,
            'next_page_url' => null,
            'prev_page_url' => null,
            'last_page_url' => $request->fullUrlWithQuery(['page' => 1]),
            'first_page_url' => $request->fullUrlWithQuery(['page' => 1]),
        ];
    }

    /**
     * Extract active sub-categories that have products in the given set of product IDs.
     *
     * @param \Illuminate\Support\Collection $productIds
     * @return array<int, array<string, mixed>>
     */
    private function getCollectionCategories($productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        return Category::query()
            ->active()
            ->whereNotNull('parent_id')
            ->whereHas('products', fn($q) => $q->whereIn('products.id', $productIds))
            ->get()
            ->map(fn($cat) => [
                'id'    => $cat->id,
                'name'  => $cat->getTranslation('name', app()->getLocale()),
                'slug'  => $cat->slug,
                'image' => [
                    'desktop' => $cat->getFirstMediaUrl('categories-desktop'),
                    'mobile'  => $cat->getFirstMediaUrl('categories-mobile'),
                ],
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get a single product by slug with related products and reviews.
     *
     * @param Request $request
     * @return JsonResponse 404 if not found.
     */
    public function getProductBySlug(Request $request): JsonResponse
    {
        $slug = trim((string) $request->route('slug'));
        $limit = $request->integer('limit', 10);
        $product = $this->productService->getProductBySlug($slug, $limit);

        if (!$product) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ProductResource::make($product));
    }

    /**
     * Add a product review.
     *
     * @param ReviewCreateRequest $request Validated review data (rating, comment, images).
     * @param int $id Product ID.
     * @return JsonResponse 404 if product not found.
     */
    public function addProductReview(ReviewCreateRequest $request, $id): JsonResponse
    {
        $review = $this->productService->addProductReview($request, $id);

        if (!$review) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

        return $this->apiResponse(REVIEW_CREATED_SUCCESSFULLY, 200, true);
    }

    /**
     * Update a product review. Only the review author can update their review.
     *
     * @param ReviewUpdateRequest $request Validated update data (rating, comment, images).
     * @param int $id Review ID.
     * @return JsonResponse 404 if review not found or not owned by user.
     */
    public function updateProductReview(ReviewUpdateRequest $request, $id): JsonResponse
    {
        $review = $this->productService->updateProductReview($request, $id);

        if (!$review) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

        return $this->apiResponse(REVIEW_UPDATED_SUCCESSFULLY, 200, true);
    }

    /**
     * Get best-selling products sorted by sold quantity.
     *
     * @param Request $request Accepts `limit` query param.
     */
    public function getBestProductSales(Request $request): JsonResponse
    {
        $products = $this->productService->getBestProductSales($request);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ProductMiniResource::collection($products));
    }

    /**
     * Get products where discount ends today or stock is low (1-9 remaining).
     *
     * @param Request $request Accepts `limit` query param.
     */
    public function getDiscountEndingTodayOrLowStockProducts(Request $request): JsonResponse
    {
        $products = $this->productService->getDiscountEndingTodayOrLowStockProducts($request);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ProductMiniResource::collection($products));
    }

    /**
     * Get newly arrived products (created within the last 15 days, no flash sale).
     *
     * @param Request $request Accepts `limit` query param.
     */
    public function getNewArrivals(Request $request): JsonResponse
    {
        $products = $this->productService->getNewArrivals($request);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ProductMiniResource::collection($products));
    }

    /**
     * Get all products that currently have an active discount.
     *
     * @param Request $request Accepts `limit` query param.
     */
    public function getAllDiscountProducts(Request $request): JsonResponse
    {
        $products = $this->productService->getAllDiscountProducts($request);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ProductMiniResource::collection($products));
    }

    /**
     * Get products that belong to top-level (parent) categories.
     *
     * @param Request $request Accepts `limit` query param.
     */
    public function getProductForParentCategory(Request $request): JsonResponse
    {
        $products = $this->productService->getProductForParentCategory($request);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ProductMiniResource::collection($products));
    }
}
