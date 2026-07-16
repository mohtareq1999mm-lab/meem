<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\Slider\SliderResource;
use App\Services\General\SliderService;
use Illuminate\Http\Request;
use Marvel\Traits\ApiResponse;

class SliderController extends Controller
{
    use ApiResponse;
    private SliderService $sliderService;

    public function __construct(SliderService $sliderService)
    {
        $this->sliderService = $sliderService;
    }

    public function index(Request $request)
    {
        if ($slug = $request->query('slug')) {
            return $this->getSliderBySlug($slug);
        }
        $sliders = $this->sliderService->getSliders($request);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, SliderResource::collection($sliders));
    }

    public function getSliderBySlug($slug)
    {
        $slider = $this->sliderService->getSliderBySlug($slug);
        if (!$slider) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, SliderResource::make($slider));
    }
}