<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use App\Services\General\SearchService;
use Illuminate\Http\Request;
use Marvel\Http\Resources\CategoryCollection;
use Marvel\Http\Resources\BrandResource;
use Marvel\Http\Resources\product\ProductCollection;
use Marvel\Http\Resources\ShopCollection;
use Marvel\Traits\ApiResponse;

class SearchController extends Controller
{
    use ApiResponse;
    private SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function index(Request $request)
    {
        $data =  $this->searchService->search($request);
        // $data['products'] = new ProductCollection($data['products']);
        // $data['shops'] = new ShopCollection($data['shops']);
        // $data['categories'] = new CategoryCollection($data['categories']);
        // $data['brands'] = BrandResource::collection($data['brands']);


        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $data);
    }
}
