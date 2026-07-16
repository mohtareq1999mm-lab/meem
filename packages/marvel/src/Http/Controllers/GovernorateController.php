<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Marvel\Database\Repositories\GovernorateRepository;
use Marvel\Enums\Permission;
use Marvel\Http\Requests\BulkStatusRequest;
use Marvel\Http\Requests\GovernorateStoreRequest;
use Marvel\Http\Requests\GovernorateUpdateRequest;
use Marvel\Http\Resources\GovernorateResource;
use Marvel\Traits\ApiResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Governorates",
 *     description="Governorate management"
 * )
 */
class GovernorateController extends CoreController
{
    use ApiResponse;
    public function __construct(private readonly GovernorateRepository $repository)
    {
        $this->middleware("permission:" . Permission::VIEW_GOVERNORATE, ["only" => ["index", "show"]]);
        $this->middleware("permission:" . Permission::CREATE_GOVERNORATE, ["only" => ["store"]]);
        $this->middleware("permission:" . Permission::UPDATE_GOVERNORATE, ["only" => ["update"]]);
        $this->middleware("permission:" . Permission::DELETE_GOVERNORATE, ["only" => ["destroy"]]);
    }

    /**
     * @OA\Get(
     *     path="/api/governorates",
     *     tags={"Governorates"},
     *     summary="List governorates",
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="country_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $governorates = $this->repository->paginate(
            (int) $request->get('per_page', 15),
            $request->get('search'),
            $request->has('status') ? (bool) $request->get('status') : null,
            $request->get('country_id') ? (int) $request->get('country_id') : null
        );

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, GovernorateResource::collection($governorates));
    }

    /**
     * @OA\Post(
     *     path="/api/governorates",
     *     tags={"Governorates"},
     *     summary="Create governorate",
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(GovernorateStoreRequest $request): JsonResponse
    {
        $governorate = $this->repository->create($request->validated());
        return $this->apiResponse(GOVERNORATE_CREATED_SUCCESSFULLY, 201, true, GovernorateResource::make($governorate));
    }

    /**
     * @OA\Get(
     *     path="/api/governorates/{id}",
     *     tags={"Governorates"},
     *     summary="Get governorate",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $governorate = $this->repository->findById($id, ['country', 'cities', 'shippingPrice']);

        if (!$governorate) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, GovernorateResource::make($governorate));
    }

    /**
     * @OA\Put(
     *     path="/api/governorates/{id}",
     *     tags={"Governorates"},
     *     summary="Update governorate",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function update(GovernorateUpdateRequest $request, int $id): JsonResponse
    {
        $governorate = $this->repository->findById($id);

        if (!$governorate) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
        $governorate = $this->repository->update($governorate, $request->validated());
        return $this->apiResponse(GOVERNORATE_UPDATED_SUCCESSFULLY, 200, true, GovernorateResource::make($governorate));
    }

    /**
     * @OA\Delete(
     *     path="/api/governorates/{id}",
     *     tags={"Governorates"},
     *     summary="Delete governorate",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $governorate = $this->repository->findById($id);

        if (!$governorate) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

        $this->repository->delete($governorate);

        return $this->apiResponse(GOVERNORATE_DELETED_SUCCESSFULLY, 200, true);
    }

    public function cities(int $governorate): JsonResponse
    {
        $governorateModel = $this->repository->findById($governorate, ['cities']);
        if (!$governorateModel) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, GovernorateResource::make($governorateModel));
    }

    public function shippingPrice(int $governorate): JsonResponse
    {
        $governorateModel = $this->repository->findById($governorate, ['shippingPrice']);
        if (!$governorateModel) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, GovernorateResource::make($governorateModel));
    }

    public function bulkStatus(BulkStatusRequest $request): JsonResponse
    {
        $count = $this->repository->bulkStatus($request->input('ids', []), (bool) $request->input('status'));
        return $this->apiResponse(BULK_STATUS_UPDATED_SUCCESSFULLY, 200, true);
    }

    /**
     * @OA\Put(
     *     path="/api/governorates/{id}/fast-shipping",
     *     tags={"Governorates"},
     *     summary="Toggle fast shipping for a governorate",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="is_fast_shipping_enabled", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Fast shipping status updated")
     * )
     */
    public function toggleFastShipping(Request $request, int $id): JsonResponse
    {
        $governorate = $this->repository->findById($id);

        if (!$governorate) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

        $validated = $request->validate([
            'is_fast_shipping_enabled' => ['required', 'boolean'],
        ]);

        $governorate = $this->repository->update($governorate, $validated);

        return $this->apiResponse(GOVERNORATE_UPDATED_SUCCESSFULLY, 200, true, GovernorateResource::make($governorate));
    }
}