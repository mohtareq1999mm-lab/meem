<?php

namespace Marvel\Http\Controllers;

use App\Http\Resources\Pages\SectionResource as PagesSectionResource;
use App\Services\General\SectionTypeService;
use Illuminate\Http\Request;
use Marvel\Http\Requests\StoreSectionRequest;
use Marvel\Http\Requests\UpdateSectionRequest;
use Marvel\Models\Section;
use Marvel\Traits\ApiResponse;

class SectionController extends CoreController
{
    use ApiResponse;

    public function __construct(
        private SectionTypeService $sectionTypeService
    ) {}

    public function index()
    {
        $sections = Section::ordered()->get();
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, PagesSectionResource::collection($sections));
    }

    public function store(StoreSectionRequest $request)
    {
        try {
            $data = $request->validated();
            $setting = $data['setting'] ?? null;

            $section = Section::create($data);

            // if ($setting) {
            //     $sectionType = $this->sectionTypeService->getByType($section->type);
            //     if (!$sectionType) {
            //         $sectionType = $this->sectionTypeService->createType(['type' => $section->type]);
            //     }
            //     $this->sectionTypeService->upsertSettings($sectionType->id, $setting);
            // }

            return $this->apiResponse(SECTION_CREATED_SUCCESSFULLY, 200, true, PagesSectionResource::make($section));
        } catch (\Exception $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }

    public function show(Section $section)
    {
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, PagesSectionResource::make($section));
    }

    public function update(UpdateSectionRequest $request, Section $section)
    {
        try {
            $data = $request->validated();
            $setting = $data['setting'] ?? null;

            $section->update($data);

            // if ($setting) {
            //     $sectionType = $this->sectionTypeService->getByType($section->type);
            //     if (!$sectionType) {
            //         $sectionType = $this->sectionTypeService->createType(['type' => $section->type]);
            //     }
            //     $this->sectionTypeService->upsertSettings($sectionType->id, $setting);
            // }

            return $this->apiResponse(SECTION_UPDATED_SUCCESSFULLY, 200, true, PagesSectionResource::make($section));
        } catch (\Exception $e) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
    }

    public function destroy(Section $section)
    {
        try {
            $section->delete();
            return $this->apiResponse(SECTION_DELETED_SUCCESSFULLY, 200, true);
        } catch (\Exception $e) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
    }

    public function reorder(Request $request)
    {
        try {
            $request->validate([
                'sections'   => 'required|array',
                'sections.*' => 'required|integer|distinct|exists:sections,id',
            ]);
            Section::setNewOrder($request->sections);
            return $this->apiResponse(SECTIONS_REORDERED_SUCCESSFULLY, 200, true);
        } catch (\Exception $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }

    public function toggleStatus(Section $section)
    {
        $section->is_active = !$section->is_active;
        $section->save();
        return $this->apiResponse(UPDATE_DATA_SUCCESSFULLY, 200, true, PagesSectionResource::make($section));
    }

    public function getTypeSection()
    {
        $types = Section::get()->pluck('type')->unique();
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $types);
    }
}
