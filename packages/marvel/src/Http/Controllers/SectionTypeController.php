<?php

namespace Marvel\Http\Controllers;

use App\Services\General\SectionTypeService;
use Illuminate\Http\Request;
use Marvel\Database\Models\SectionType;
use Marvel\Http\Requests\StoreSectionTypeRequest;
use Marvel\Http\Requests\UpdateSectionTypeRequest;
use Marvel\Traits\ApiResponse;

class SectionTypeController extends CoreController
{
    use ApiResponse;

    public function __construct(
        private SectionTypeService $sectionTypeService
    ) {}

    public function index()
    {
        $types = $this->sectionTypeService->getAll();
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $types);
    }

    public function store(StoreSectionTypeRequest $request)
    {
        try {
            $type = $this->sectionTypeService->createType($request->validated());
            return $this->apiResponse(TYPE_CREATED_SUCCESSFULLY, 200, true, $type);
        } catch (\Exception $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }

    public function show(SectionType $sectionType)
    {
        $grouped = $this->sectionTypeService->getSettingsGrouped($sectionType->type);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $grouped);
    }

    public function update(UpdateSectionTypeRequest $request, SectionType $sectionType)
    {
        try {
            $type = $this->sectionTypeService->updateType($sectionType->id, $request->validated());
            return $this->apiResponse(TYPE_UPDATED_SUCCESSFULLY, 200, true, $type);
        } catch (\Exception $e) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
    }

    public function destroy(SectionType $sectionType)
    {
        try {
            $this->sectionTypeService->deleteType($sectionType->id);
            return $this->apiResponse(TYPE_DELETED_SUCCESSFULLY, 200, true);
        } catch (\Exception $e) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
    }

    public function settings(string $type)
    {
        $sectionType = $this->sectionTypeService->getByType($type);
        if (!$sectionType) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
        $grouped = $this->sectionTypeService->getSettingsGrouped($type);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $grouped);
    }

    public function updateSettings(Request $request, string $type)
    {
        try {
            $request->validate([
                'front' => 'nullable|array',
                'back' => 'nullable|array',
            ]);

            $sectionType = $this->sectionTypeService->getByType($type);
            if (!$sectionType) {
                return $this->apiResponse(NOT_FOUND, 404, false);
            }

            $this->sectionTypeService->upsertSettings($sectionType->id, $request->only(['front', 'back']));

            $grouped = $this->sectionTypeService->getSettingsGrouped($type);
            return $this->apiResponse(SETTINGS_UPDATED_SUCCESSFULLY, 200, true, $grouped);
        } catch (\Exception $e) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
    }

    public function byType(string $type)
    {
        $sectionType = $this->sectionTypeService->getByType($type);
        if (!$sectionType) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
        $grouped = $this->sectionTypeService->getSettingsGrouped($type);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $grouped);
    }
}
