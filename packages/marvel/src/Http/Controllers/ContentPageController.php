<?php

namespace Marvel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Pages\ContentPageResource;
use App\Http\Resources\Pages\SectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Marvel\Http\Requests\StoreContentPageRequest;
use Marvel\Http\Requests\UpdateContentPageRequest;
use Marvel\Http\Requests\AttachSectionsRequest;
use Marvel\Models\ContentPage;
use Marvel\Traits\ApiResponse;

class ContentPageController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $pages = ContentPage::with('sections')->paginate(15);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ContentPageResource::collection($pages));
    }

    public function show(ContentPage $content_page)
    {
        $content_page->load('sections');
        return   $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ContentPageResource::make($content_page));
    }

    public function store(StoreContentPageRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->only(['title']);
            $data['slug'] = Str::slug($data['title']['en']);
            $page = ContentPage::create($data + ['is_active' => true]);

            return $this->apiResponse(CREATE_DATA_SUCCESSFULLY, 201, true, ContentPageResource::make($page));
        });
    }

    public function update(UpdateContentPageRequest $request, ContentPage $content_page)
    {
        return DB::transaction(function () use ($request, $content_page) {
            $content_page->update($request->only(['title', 'is_active' ]));
            $content_page->load('sections');

            return  $this->apiResponse(UPDATE_DATA_SUCCESSFULLY, 200, true, ContentPageResource::make($content_page));
        });
    }

    /**
     * Attach existing sections to the page by IDs provided in request.sections
     */
    public function attachSections(AttachSectionsRequest $request, ContentPage $content_page)
    {
        return DB::transaction(function () use ($request, $content_page) {
            $sectionIds = $request->input('sections', []);

            // if empty array provided, delete the content page as requested
            if (empty($sectionIds)) {
                $content_page->sections()->update(['content_page_id' => null]);
                return $this->apiResponse(DELETE_DATA_SUCCESSFULLY, 200, true);
            }

            $attached = $content_page->attachSectionsByIds($sectionIds);
            $content_page->load('sections');
            return $this->apiResponse(UPDATE_DATA_SUCCESSFULLY, 200, true, ContentPageResource::make($content_page));
        });
    }

    public function destroy(ContentPage $content_page): JsonResponse
    {
        $content_page->delete();
        return $this->apiResponse(DELETE_DATA_SUCCESSFULLY, 200, true);
    }

    public function toggleActive(ContentPage $content_page): JsonResponse
    {
        $content_page->is_active = !$content_page->is_active;
        $content_page->save();
        return $this->apiResponse(UPDATE_DATA_SUCCESSFULLY, 200, true, ContentPageResource::make($content_page));
    }
}
