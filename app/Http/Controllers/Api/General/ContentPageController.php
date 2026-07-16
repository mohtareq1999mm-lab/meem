<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\Pages\ContentPageResource;
use Illuminate\Support\Facades\Cache;
use Marvel\Models\ContentPage;
use Marvel\Traits\ApiResponse;

class ContentPageController extends Controller
{
    use ApiResponse;
    public function index()
    {
        // $pages = Cache::remember('content_pages', 60 * 60 * 24, function () {
        //     return ContentPage::with([
        //         'sections' => function ($query) {
        //             $query->where('is_active', true);
        //         }
        //     ])->paginate(15);
        // });
        $pages = ContentPage::with([
            'sections' => function ($query) {
                $query->where('is_active', true);
            }
        ])->paginate(15);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ContentPageResource::collection($pages));
    }

    public function show($slug)
    {
        // $content_page = Cache::remember('content_page_' . $slug, 60 * 60 * 24, function () use ($slug) {
        //     return ContentPage::where('slug', $slug)->with('sections', function ($query) {
        //         $query->where('is_active', true);
        //     })->firstOrFail();
        // });
        $content_page = ContentPage::where('slug', $slug)->with('sections', function ($query) {
            $query->where('is_active', true);
        })->firstOrFail();
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ContentPageResource::make($content_page));
    }
}
