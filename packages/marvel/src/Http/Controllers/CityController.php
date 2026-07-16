<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Marvel\Database\Repositories\CityRepository;
use Marvel\Enums\Permission;
use Marvel\Http\Requests\CityStoreRequest;
use Marvel\Http\Requests\CityUpdateRequest;
use Marvel\Http\Resources\CityResource;
use Marvel\Traits\ApiResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Cities",
 *     description="City management"
 * )
 */
class CityController extends CoreController
{
    use ApiResponse;
    public function __construct(private readonly CityRepository $repository)
    {
        $this->middleware("permission:" . Permission::VIEW_CITY, ["only" => ["index", "show"]]);
        $this->middleware("permission:" . Permission::CREATE_CITY, ["only" => ["store"]]);
        $this->middleware("permission:" . Permission::UPDATE_CITY, ["only" => ["update"]]);
        $this->middleware("permission:" . Permission::DELETE_CITY, ["only" => ["destroy"]]);
    }

    /**
     * @OA\Get(
     *     path="/api/cities",
     *     tags={"Cities"},
     *     summary="List cities",
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="governorate_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $cities = $this->repository->paginate(
            (int) $request->get('per_page', 15),
            $request->get('search'),
            $request->get('governorate_id') ? (int) $request->get('governorate_id') : null
        );

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, CityResource::collection($cities));
    }

    /**
     * @OA\Post(
     *     path="/api/cities",
     *     tags={"Cities"},
     *     summary="Create city",
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(CityStoreRequest $request): JsonResponse
    {
        $city = $this->repository->create($request->validated());
        return $this->apiResponse(CITY_CREATED_SUCCESSFULLY, 201, true, CityResource::make($city));
    }

    /**
     * @OA\Get(
     *     path="/api/cities/{id}",
     *     tags={"Cities"},
     *     summary="Get city",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $city = $this->repository->findById($id, ['governorate']);
        if (!$city) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, CityResource::make($city));
    }

    /**
     * @OA\Put(
     *     path="/api/cities/{id}",
     *     tags={"Cities"},
     *     summary="Update city",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function update(CityUpdateRequest $request, int $id): JsonResponse
    {
        $city = $this->repository->findById($id);

        if (!$city) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

            $city = $this->repository->update($city, $request->validated());
        

            return $this->apiResponse(CITY_UPDATED_SUCCESSFULLY, 200, true, CityResource::make($city)); 
    }

    /**
     * @OA\Delete(
     *     path="/api/cities/{id}",
     *     tags={"Cities"},
     *     summary="Delete city",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $city = $this->repository->findById($id);

        if (!$city) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

        $this->repository->delete($city);

        return $this->apiResponse(CITY_DELETED_SUCCESSFULLY, 200, true);
    }
}