<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Marvel\Database\Repositories\BrandRepository;
use Marvel\Enums\Permission;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\BrandCreateRequest;
use Marvel\Http\Requests\BrandUpdateRequest;
use Marvel\Http\Resources\BrandResource;
use Marvel\Traits\ApiResponse;

class BrandController extends CoreController
{
    use ApiResponse;

    public $repository;

    public function __construct(BrandRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('permission:' . Permission::VIEW_BRANDS, ['only' => ['index']]);
        $this->middleware('permission:' . Permission::VIEW_BRANDS, ['only' => ['show']]);
        $this->middleware('permission:' . Permission::CREATE_BRAND, ['only' => ['store']]);
        $this->middleware('permission:' . Permission::UPDATE_BRAND, ['only' => ['update']]);
        $this->middleware('permission:' . Permission::DELETE_BRAND, ['only' => ['destroy']]);
        $this->middleware('permission:' . Permission::UPDATE_BRAND, ['only' => ['reorder']]);
    }

    public function index(Request $request)
    {
        $limit = $request->per_page ?? $request->limit ?? 15;
        $active = $request->active ?? null;
        $inactive = $request->inactive ?? null;
        $search = $request->search ?? null;
        $order = $request->order;
        $sortedBy = $request->sortedBy ?? 'asc';

        $brandsQuery = $this->repository;

        if ($active) {
            $brandsQuery = $brandsQuery->active();
        }
        if ($inactive) {
            $brandsQuery = $brandsQuery->inactive();
        }
        if ($search) {
            $brandsQuery = $brandsQuery->search('name', $search, app()->getLocale());
        }
        if ($order && in_array($order, ['id', 'name', 'slug', 'status', 'created_at', 'updated_at'])) {
            $brandsQuery = $brandsQuery->orderBy($order, $sortedBy === 'desc' ? 'desc' : 'asc');
        }

        $brands = $brandsQuery->ordered()->paginate($limit);
        $data = BrandResource::collection($brands)->response()->getData(true);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, [
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

    public function store(BrandCreateRequest $request)
    {
        try {
            $brand = $this->repository->saveBrand($request);
            $brand->load('products');
            return $this->apiResponse(BRAND_CREATED_SUCCESSFULLY, 200, true, BrandResource::make($brand));
        } catch (MarvelException $th) {
            throw new MarvelException(COULD_NOT_CREATE_THE_RESOURCE);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $brand = $this->repository->with('products')->where('id', $id)->firstOrFail();
            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, BrandResource::make($brand));
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    public function update(BrandUpdateRequest $request, $id)
    {
        try {
            $request->merge(['id' => $id]);
            $brand = $this->brandUpdate($request);
            $brand->load('products');
            return $this->apiResponse(BRAND_UPDATED_SUCCESSFULLY, 200, true, BrandResource::make($brand));
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    public function brandUpdate(BrandUpdateRequest $request)
    {
        $brand = $this->repository->findOrFail($request->id);
        return $this->repository->updateBrand($request, $brand);
    }

    public function destroy($id)
    {
        try {
            $this->repository->findOrFail($id)->delete();
            return $this->apiResponse(BRAND_DELETED_SUCCESSFULLY, 200, true);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    public function reorder(Request $request)
    {
        try {
            $request->validate([
                'brands' => 'required|array',
                'brands.*' => 'required|exists:brands,id',
            ]);
            $this->repository->reorder($request->brands);

            return $this->apiResponse(BRANDS_REORDERED_SUCCESSFULLY, 200, true);
        } catch (\Exception $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }
}
