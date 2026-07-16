<?php

namespace Marvel\Http\Controllers;

use Marvel\Database\Repositories\SliderRepository;
use Marvel\Enums\Permission;
use Marvel\Http\Requests\SliderCreateRequest;
use Marvel\Http\Requests\SliderUpdateRequest;
use Marvel\Http\Resources\SliderResource;
use Marvel\Traits\ApiResponse;
use Illuminate\Http\Request;

use const Dom\NOT_FOUND_ERR;

class SliderController   extends CoreController
{
    use ApiResponse;
    public $repository;
    public function __construct(SliderRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware("permission:".Permission::VIEW_SLIDER)->only(["index","show"]);
        $this->middleware("permission:".Permission::CREATE_SLIDER)->only("store");
        $this->middleware("permission:".Permission::UPDATE_SLIDER)->only("update");
        $this->middleware("permission:".Permission::DELETE_SLIDER)->only("destroy");
    }

    public function index(Request $request)
    {
        $sliders = $this->repository->getSliders($request);
        $data = SliderResource::collection($sliders)->response()->getData(true);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY,200, true, [
            "data" => $data['data'] ?? [],
            "page" => $data['meta']['current_page'] ?? 0,
            "current_page" => $data['meta']['current_page'] ?? 0,
            "from" => $data['meta']['from'] ?? 0,
            "to" => $data['meta']['to'] ?? 0,
            "last_page" => $data['meta']['last_page'] ?? 0,
            "path" => $data['meta']['path'] ?? "",
            "per_page" => $data['meta']['per_page'] ?? 0,
            "total" => $data['meta']['total'] ?? 0,
            "next_page_url" => $data['links']['next'] ?? "",
            "prev_page_url" => $data['links']['prev'] ?? "",
            "last_page_url" => $data['links']['last'] ?? "",
            "first_page_url" => $data['links']['first'] ?? "",
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SliderCreateRequest $request)
    {
        try{
            $slider = $this->repository->createSlider($request);
            $slider->load('products');
            return $this->apiResponse(SLIDER_CREATED_SUCCESSFULLY,200, true, SliderResource::make($slider));
        }catch(\Exception $e){
            return $this->apiResponse(SOMETHING_WENT_WRONG,500, false);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try{
            $slider = $this->repository->findOrFail($id);
            $slider->load('products');
            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY,200, true, SliderResource::make($slider));
        }catch(\Exception $e){
            return $this->apiResponse(NOT_FOUND,404, false);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SliderUpdateRequest $request, string $id)
    {
        try{
            $slider = $this->repository->updateSlider($request, $id);
            $slider->load('products');
            return $this->apiResponse(SLIDER_UPDATED_SUCCESSFULLY,200, true, SliderResource::make($slider));
        }catch(\Exception $e){
            return $this->apiResponse(NOT_FOUND,404, false);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            $slider = $this->repository->findOrFail($id);
            $slider->delete();
            return $this->apiResponse(SLIDER_DELETED_SUCCESSFULLY,200, true);
        }catch(\Exception $e){
            return $this->apiResponse(NOT_FOUND,404, false, null);
        }
    }

     public function changeStatus(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:sliders,id',
        ]);
        $slider = $this->repository->changeStatus($request->id);
        if(!$slider){
            return $this->apiResponse(SOMETHING_WENT_WRONG,500, false);
        }
        $slider->load('products');
        return $this->apiResponse(SLIDER_STATUS_CHANGED,200, true, SliderResource::make($slider));
    }


    public function reorder(Request $request)
    {
        try {
            $request->validate([
                'sliders' => 'required|array',
                'sliders.*' => 'required|exists:sliders,id',
            ]);
            $this->repository->reorder($request->sliders);

            return $this->apiResponse(SLIDERS_REORDERED_SUCCESSFULLY, 200, true);
        } catch (\Exception $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }
}
