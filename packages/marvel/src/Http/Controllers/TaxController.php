<?php

namespace Marvel\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Marvel\Http\Requests\CreateTaxRequest;
use Marvel\Http\Requests\UpdateTaxRequest;
use Marvel\Database\Repositories\TaxRepository;
use Marvel\Exceptions\MarvelException;
use Prettus\Validator\Exceptions\ValidatorException;

class TaxController extends CoreController
{
    public $repository;

    public function __construct(TaxRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @OA\Get(
     *     path="/taxes",
     *     operationId="listTaxes",
     *     tags={"Platform Configuration"},
     *     summary="List All Taxes",
     *     description="Get list of all tax rates. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Taxes retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN")
     * )
     */
    public function index(Request $request)
    {
        return $this->repository->all();
    }

    /**
     * @OA\Post(
     *     path="/taxes",
     *     operationId="createTax",
     *     tags={"Platform Configuration"},
     *     summary="Create Tax Rate",
     *     description="Create a new tax rate. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "rate", "country"},
     *             @OA\Property(property="name", type="string", example="VAT"),
     *             @OA\Property(property="rate", type="number", example=15.0),
     *             @OA\Property(property="country", type="string", example="US"),
     *             @OA\Property(property="state", type="string", example="CA"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="zip", type="string"),
     *             @OA\Property(property="is_global", type="boolean", example=false),
     *             @OA\Property(property="priority", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tax created successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(CreateTaxRequest $request)
    {
        $validateData = $request->validated();
        return $this->repository->create($validateData);
    }

    /**
     * @OA\Get(
     *     path="/taxes/{id}",
     *     operationId="getTax",
     *     tags={"Platform Configuration"},
     *     summary="Get Tax Details",
     *     description="Get a single tax rate by ID. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Tax ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tax retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Tax not found")
     * )
     */
    public function show($id)
    {
        try {
            return $this->repository->findOrFail($id);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * @OA\Put(
     *     path="/taxes/{id}",
     *     operationId="updateTax",
     *     tags={"Platform Configuration"},
     *     summary="Update Tax Rate",
     *     description="Update an existing tax rate. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Tax ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="rate", type="number"),
     *         @OA\Property(property="country", type="string")
     *     )),
     *     @OA\Response(response=200, description="Tax updated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN"),
     *     @OA\Response(response=404, description="Tax not found")
     * )
     */
    public function update(UpdateTaxRequest $request, $id)
    {
        try {
            $validatedData = $request->validated();
            return $this->repository->findOrFail($id)->update($validatedData);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * @OA\Delete(
     *     path="/taxes/{id}",
     *     operationId="deleteTax",
     *     tags={"Platform Configuration"},
     *     summary="Delete Tax Rate",
     *     description="Delete a tax rate. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Tax ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tax deleted successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN"),
     *     @OA\Response(response=404, description="Tax not found")
     * )
     */
    public function destroy($id)
    {
        try {
            return $this->repository->findOrFail($id)->delete();
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }
}
