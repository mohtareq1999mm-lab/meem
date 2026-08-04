<?php

namespace App\Http\Controllers\Api\General;

use App\Enums\FrontendResource;
use App\Http\Controllers\Controller;
use App\Http\Resources\Brand\BrandProductResource;
use App\Http\Resources\Brand\BrandResource;
use App\Services\General\BrandService;
use App\Traits\HasCache;
use Illuminate\Http\Request;
use Marvel\Traits\ApiResponse;

class BrandController extends Controller
{
    use ApiResponse, HasCache;
    private BrandService $brandService;

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    public function index(Request $request)
    {
        if ($slug = $request->query('slug')) {
            return $this->getBrandBySlug($slug);
        }
        $brands =  $this->brandService->getBrands($request);
        $brandCache = $this->remember(FrontendResource::BRANDS->value, md5($request->fullUrl()), $brands);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true,  BrandResource::collection($brandCache));
    }

    public function getBrandBySlug($slug)
    {
        $brand =  $this->brandService->getBrandBySlug($slug);
        if (!$brand) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, BrandResource::make($brand));
    }

    public function getBrandsProductsByQtySet(Request $request)
    {
        $brandWithProducts =  $this->brandService->getBrandsProductsByQtySet($request);
        $brandWithProductsCache = $this->remember(FrontendResource::BRANDS_PRODUCTS->value, md5($request->fullUrl()), $brandWithProducts);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, BrandProductResource::collection($brandWithProductsCache));
    }
}