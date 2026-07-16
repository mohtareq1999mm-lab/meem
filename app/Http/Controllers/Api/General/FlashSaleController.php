<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\FlashSale\FlashSaleResource;
use App\Http\Resources\Product\ProductMiniResource;
use App\Services\General\FlashSaleService;
use App\Services\General\ProductService;
use Illuminate\Http\Request;
use Marvel\Http\Resources\product\ProductCollection;
use Marvel\Traits\ApiResponse;

class FlashSaleController extends Controller
{
    use ApiResponse;

    private FlashSaleService $flashSaleService;

    public function __construct(FlashSaleService $flashSaleService)
    {
        $this->flashSaleService = $flashSaleService;
    }

    public function index(Request $request)
    {
        if ($slug = $request->query('slug')) {
            return $this->getFlashSaleBySlug($slug);
        }
        $flashSales = $this->flashSaleService->paginateFlashSales($request);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, FlashSaleResource::collection($flashSales));
    }

    public function getFlashSaleBySlug($slug)
    {
        $FlashSaleWithProducts  = $this->flashSaleService->getFlashSaleBySlug($slug);
        if (!$FlashSaleWithProducts) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, FlashSaleResource::make($FlashSaleWithProducts));
    }
    public function getFlashSalesAndHereProductsByQtySet(Request $request)
    {
        $flashSalesProduct = $this->flashSaleService->getFlashSalesAndHereProductsByQtySet($request);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ProductMiniResource::collection($flashSalesProduct));
    }

    public function getFlashSaleProductsEndingThisWeek(Request $request)
    {
        $products = $this->flashSaleService->getFlashSaleProductsEndingThisWeek($request);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ProductMiniResource::collection($products));
    }
    public function getFlashSaleProductsEndingToday(Request $request)
    {
        $products = $this->flashSaleService->getFlashSaleProductsEndingToday($request);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ProductMiniResource::collection($products));
    }
}
