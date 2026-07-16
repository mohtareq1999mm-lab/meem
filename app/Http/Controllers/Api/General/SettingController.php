<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use App\Services\General\SettingService;
use Marvel\Http\Resources\SettingResource;
use Marvel\Traits\ApiResponse;

class SettingController extends Controller
{
    use ApiResponse;
    private SettingService $settingService;
    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function index()
    {
        $setting = $this->settingService->getSetting();
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY,200,true,SettingResource::make($setting));
    }
}
