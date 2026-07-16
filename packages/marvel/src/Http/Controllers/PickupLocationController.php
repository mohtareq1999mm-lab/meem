<?php

namespace Marvel\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Marvel\Enums\Permission;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\StorePickupLocationRequest;
use Marvel\Http\Requests\UpdatePickupLocationRequest;
use Marvel\Database\Repositories\PickupLocationRepository;
use Marvel\Http\Resources\PickupLocationResource;
use Marvel\Traits\ApiResponse;

class PickupLocationController extends CoreController
{
    use ApiResponse;

    public $repository;

    public function __construct(PickupLocationRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('permission:' . Permission::VIEW_PICKUP_LOCATIONS, ['only' => ['index']]);
        $this->middleware('permission:' . Permission::VIEW_PICKUP_LOCATIONS, ['only' => ['show']]);
        $this->middleware('permission:' . Permission::CREATE_PICKUP_LOCATION, ['only' => ['store']]);
        $this->middleware('permission:' . Permission::UPDATE_PICKUP_LOCATION, ['only' => ['update']]);
        $this->middleware('permission:' . Permission::DELETE_PICKUP_LOCATION, ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $limit = $request->per_page ?? $request->limit ?? 15;
        $search = $request->search ?? null;
        $active = $request->active ?? null;
        $inactive = $request->inactive ?? null;

        $query = $this->repository->orderBy('display_order')->orderBy('id');

        if ($active === 'true' || $active === '1') {
            $query = $query->active();
        }
        if ($inactive === 'true' || $inactive === '1') {
            $query = $query->inactive();
        }
        if ($search) {
            $query = $query->where('store_name', 'like', "%{$search}%");
        }

        $pickupLocations = $query->paginate($limit);
        $data = PickupLocationResource::collection($pickupLocations)->response()->getData(true);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, [
            'data' => $data['data'] ?? [],
            'page' => $data['meta']['current_page'] ?? 0,
            'current_page' => $data['meta']['current_page'] ?? 0,
            'from' => $data['meta']['from'] ?? 0,
            'to' => $data['meta']['to'] ?? 0,
            'last_page' => $data['meta']['last_page'] ?? 0,
            'path' => $data['meta']['path'] ?? '',
            'per_page' => $data['meta']['per_page'] ?? 0,
            'total' => $data['meta']['total'] ?? 0,
            'next_page_url' => $data['links']['next'] ?? '',
            'prev_page_url' => $data['links']['prev'] ?? '',
            'last_page_url' => $data['links']['last'] ?? '',
            'first_page_url' => $data['links']['first'] ?? '',
        ]);
    }

    public function store(StorePickupLocationRequest $request)
    {
        try {
            $pickupLocation = $this->repository->create($request->validated());
            return $this->apiResponse(PICKUP_LOCATION_CREATED_SUCCESSFULLY, 200, true, PickupLocationResource::make($pickupLocation));
        } catch (Exception $e) {
            throw new MarvelException(COULD_NOT_CREATE_THE_RESOURCE);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $pickupLocation = $this->repository->findOrFail($id);
            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, PickupLocationResource::make($pickupLocation));
        } catch (Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    public function update(UpdatePickupLocationRequest $request, $id)
    {
        try {
            $pickupLocation = $this->repository->findOrFail($id);
            $pickupLocation->update($request->validated());
            return $this->apiResponse(PICKUP_LOCATION_UPDATED_SUCCESSFULLY, 200, true, PickupLocationResource::make($pickupLocation));
        } catch (Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    public function destroy($id)
    {
        try {
            $this->repository->findOrFail($id)->delete();
            return $this->apiResponse(PICKUP_LOCATION_DELETED_SUCCESSFULLY, 200, true);
        } catch (Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }
}
