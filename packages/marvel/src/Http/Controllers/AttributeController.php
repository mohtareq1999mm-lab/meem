<?php

namespace Marvel\Http\Controllers;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\AttributeRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Marvel\Database\Repositories\AttributeRepository;
use Marvel\Http\Resources\AttributeResource;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @OA\Tag(name="Attributes", description="Product attributes management [STORE_OWNER, SUPER_ADMIN]")
 *
 * @OA\Schema(
 *     schema="Attribute",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Size"),
 *     @OA\Property(property="slug", type="string", example="size"),
 *     @OA\Property(property="shop_id", type="integer", example=10),
 *     @OA\Property(property="language", type="string", example="en"),
 *     @OA\Property(property="values", type="array", @OA\Items(ref="#/components/schemas/AttributeValue")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="AttributeValue",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=5),
 *     @OA\Property(property="attribute_id", type="integer", example=1),
 *     @OA\Property(property="value", type="string", example="XL"),
 *     @OA\Property(property="meta", type="string", nullable=true)
 * )
 */
class AttributeController extends CoreController
{
    public $repository;

    public function __construct(AttributeRepository $repository)
    {
        $this->repository = $repository;
    }


    /**
     * @OA\Get(
     *     path="/attributes",
     *     operationId="getAttributes",
     *     tags={"Attributes"},
     *     summary="List Attributes",
     *     description="List attributes. customizable by Shop.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="shop_id", in="query", description="Filter by Shop ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Attributes retrieved",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Attribute"))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $language = $request->language ?? DEFAULT_LANGUAGE;
        $attributes = $this->repository->where('language', $language)->with(['values', 'shop'])->get();
        return AttributeResource::collection($attributes);
    }

    /**
     * @OA\Post(
     *     path="/attributes",
     *     operationId="createAttribute",
     *     tags={"Attributes"},
     *     summary="Create Attribute",
     *     description="Create a new attribute. Requires STORE_OWNER permission for the shop.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "shop_id"},
     *             @OA\Property(property="name", type="string", example="Color"),
     *             @OA\Property(property="shop_id", type="integer", example=10),
     *             @OA\Property(property="language", type="string", example="en"),
     *             @OA\Property(property="values", type="array", @OA\Items(type="object", @OA\Property(property="value", type="string"), @OA\Property(property="meta", type="string")))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Attribute created", @OA\JsonContent(ref="#/components/schemas/Attribute")),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(AttributeRequest $request)
    {
        try {
            if ($this->repository->hasPermission($request->user(), $request->shop_id)) {
                return $this->repository->storeAttribute($request);
            }
            throw new AuthorizationException(NOT_AUTHORIZED);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * @OA\Get(
     *     path="/attributes/{id}",
     *     operationId="getAttribute",
     *     tags={"Attributes"},
     *     summary="Get Attribute",
     *     description="Get attribute details by ID or Slug",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Attribute ID or Slug", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Attribute details", @OA\JsonContent(ref="#/components/schemas/Attribute")),
     *     @OA\Response(response=404, description="Attribute not found")
     * )
     */
    public function show(Request $request, $params)
    {

        try {
            $language = $request->language ?? DEFAULT_LANGUAGE;
            if (is_numeric($params)) {
                $params = (int) $params;
                $attribute = $this->repository->with('values')->where('id', $params)->firstOrFail();
                return new AttributeResource($attribute);
            }
            $attribute = $this->repository->with('values')->where('slug', $params)->where('language', $language)->firstOrFail();
            return new AttributeResource($attribute);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * @OA\Put(
     *     path="/attributes/{id}",
     *     operationId="updateAttribute",
     *     tags={"Attributes"},
     *     summary="Update Attribute",
     *     description="Update existing attribute. Requires permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Attribute ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Size"),
     *             @OA\Property(property="shop_id", type="integer"),
     *             @OA\Property(property="values", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Attribute updated", @OA\JsonContent(ref="#/components/schemas/Attribute")),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(AttributeRequest $request, $id)
    {
        try {
            $request->id = $id;
            return $this->updateAttribute($request);
        } catch (MarvelException $e) {
            throw new MarvelException(COULD_NOT_DELETE_THE_RESOURCE);
        }
    }

    public function updateAttribute(AttributeRequest $request)
    {

        if ($this->repository->hasPermission($request->user(), $request->shop_id)) {
            try {
                $attribute = $this->repository->with('values')->findOrFail($request->id);
            } catch (\Exception $e) {
                throw new HttpException(404, NOT_FOUND);
            }
            return $this->repository->updateAttribute($request, $attribute);
        }
        throw new AuthorizationException(NOT_AUTHORIZED);
    }

    /**
     * @OA\Delete(
     *     path="/attributes/{id}",
     *     operationId="deleteAttribute",
     *     tags={"Attributes"},
     *     summary="Delete Attribute",
     *     description="Delete an attribute. Requires permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Attribute ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Attribute deleted"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(Request $request, $id)
    {
        try {
            $request->id = $id;
            return $this->deleteAttribute($request);
        } catch (MarvelException $e) {
            throw new MarvelException(COULD_NOT_DELETE_THE_RESOURCE);
        }
    }

    public function deleteAttribute(Request $request)
    {
        try {
            $attribute = $this->repository->findOrFail($request->id);
        } catch (\Exception $e) {
            throw new HttpException(404, NOT_FOUND);
        }
        if ($this->repository->hasPermission($request->user(), $attribute->shop->id)) {
            $attribute->delete();
            return $attribute;
        }
        throw new AuthorizationException(NOT_AUTHORIZED);
    }

    public function exportAttributes(Request $request, $shop_id)
    {
        $filename = 'attributes-for-shop-id-' . $shop_id . '.csv';
        $headers = [
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Expires' => '0',
            'Pragma' => 'public'
        ];

        $list = $this->repository->where('shop_id', $shop_id)->with(['values'])->get()->toArray();

        if (!count($list)) {
            return response()->stream(function () {
            }, 200, $headers);
        }
        # add headers for each column in the CSV download
        array_unshift($list, array_keys($list[0]));

        $callback = function () use ($list) {
            $FH = fopen('php://output', 'w');
            foreach ($list as $key => $row) {
                if ($key === 0) {
                    $exclude = ['id', 'created_at', 'updated_at', 'slug', 'translated_languages'];
                    $row = array_diff($row, $exclude);
                }
                unset($row['id']);
                unset($row['updated_at']);
                unset($row['slug']);
                unset($row['created_at']);
                unset($row['translated_languages']);
                if (isset($row['values'])) {
                    $row['values'] = implode(',', Arr::pluck($row['values'], 'value'));
                }

                fputcsv($FH, $row);
            }
            fclose($FH);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importAttributes(Request $request)
    {
        $requestFile = $request->file();
        $user = $request->user();
        $shop_id = $request->shop_id;

        if (count($requestFile)) {
            if (isset($requestFile['csv'])) {
                $uploadedCsv = $requestFile['csv'];
            } else {
                $uploadedCsv = current($requestFile);
            }
        }

        if (!$this->repository->hasPermission($user, $shop_id)) {
            throw new MarvelException(NOT_AUTHORIZED);
        }
        if (isset($shop_id)) {
            $file = $uploadedCsv->storePubliclyAs('csv-files', 'attributes-' . $shop_id . '.' . $uploadedCsv->getClientOriginalExtension(), 'public');

            $attributes = $this->repository->csvToArray(storage_path() . '/app/public/' . $file);

            foreach ($attributes as $key => $attribute) {
                if (!isset($attribute['name'])) {
                    throw new MarvelException("MARVEL_ERROR.WRONG_CSV");
                }
                unset($attribute['id']);
                $attribute['shop_id'] = $shop_id;
                $values = [];
                if (isset($attribute['values'])) {
                    $values = explode(',', $attribute['values']);
                }
                unset($attribute['values']);
                $newAttribute = $this->repository->firstOrCreate($attribute);
                foreach ($values as $key => $value) {
                    $newAttribute->values()->create(['value' => $value]);
                }
            }
            return true;
        }
    }
}
