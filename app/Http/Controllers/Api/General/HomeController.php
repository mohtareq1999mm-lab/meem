<?php

namespace App\Http\Controllers\Api\General;

use App\Enums\FrontendResource;
use App\Http\Controllers\Controller;
use App\Services\General\HomeService;
use App\Traits\HasCache;
use Illuminate\Http\Request;
use Marvel\Traits\ApiResponse;

class HomeController extends Controller
{
    use ApiResponse, HasCache;

    private HomeService $homeService;

    public function __construct(HomeService $homeService)
    {
        $this->homeService = $homeService;
    }

    public function index(Request $request)
    {
        $parentCategoryId = $request->integer('parent_category_id');
        $parentCategoryId = $parentCategoryId > 0 ? $parentCategoryId : null;

        $sections = $this->resolveSections($request);

        $data = $this->homeService->getHomeData($parentCategoryId, $sections);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $data);
    }

    public function navData(Request $request)
    {
        $level = $request->integer('level');
        $level = $level > 0 ? $level : null;

        $data = $this->homeService->getNavData($level);
        $dataCache = $this->remember(FrontendResource::CATEGORIES->value, md5($request->fullUrl()), $data);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $dataCache);
    }

    /**
     * @return list<string>|null
     */
    private function resolveSections(Request $request): ?array
    {
        $raw = $request->get('sections', $request->get('keys'));

        if ($raw === null || $raw === '') {
            return null;
        }

        $sections = is_array($raw)
            ? $raw
            : preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_intersect($sections, HomeService::availableSections()));
    }
}