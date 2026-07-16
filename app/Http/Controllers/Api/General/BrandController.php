<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\Brand\BrandResource;
use App\Http\Resources\Product\ProductMiniResource;
use App\Services\General\BrandService;
use Illuminate\Http\Request;
use Marvel\Traits\ApiResponse;

class BrandController extends Controller
{
    use ApiResponse;
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
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true,  BrandResource::collection($brands));
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

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ProductMiniResource::collection($brandWithProducts));
    }
}
