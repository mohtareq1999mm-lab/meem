<?php


namespace Marvel\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Models\Tag;
use Marvel\Database\Repositories\TagRepository;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\TagCreateRequest;
use Marvel\Http\Requests\TagUpdateRequest;
use Marvel\Http\Resources\TagResource;
use Prettus\Validator\Exceptions\ValidatorException;

/**
 * @OA\Tag(name="Tags", description="Product tags management - organize products with tags")
 *
 * @OA\Schema(
 *     schema="Tag",
 *     type="object",
 *     description="Product tag details",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Organic"),
 *     @OA\Property(property="slug", type="string", example="organic"),
 *     @OA\Property(property="details", type="string", nullable=true, example="Fresh organic products"),
 *     @OA\Property(property="image", type="object", nullable=true),
 *     @OA\Property(property="icon", type="string", nullable=true),
 *     @OA\Property(property="language", type="string", example="en"),
 *     @OA\Property(property="translated_languages", type="array", @OA\Items(type="string"), example={"en"}),
 *     @OA\Property(property="type", ref="#/components/schemas/Type")
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedTags",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Tag")),
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=50),
 *     @OA\Property(property="last_page", type="integer", example=4)
 * )
 */
class TagController extends CoreController
{
    public $repository;

    public function __construct(TagRepository $repository)
    {
        $this->repository = $repository;
    }
    /**
     * @OA\Get(
     *     path="/tags",
     *     operationId="listTags",
     *     tags={"Tags"},
     *     summary="List all product tags",
     *     description="Retrieve a paginated list of product tags.",
     *     @OA\Parameter(name="language", in="query", description="Language code", required=false, @OA\Schema(type="string", default="en", example="en")),
     *     @OA\Parameter(name="limit", in="query", description="Items per page", required=false, @OA\Schema(type="integer", default=15, example=15)),
     *     @OA\Response(response=200, description="Tags retrieved successfully", @OA\JsonContent(ref="#/components/schemas/PaginatedTags"))
     * )
     */
    public function index(Request $request)
    {
        $language = $request->language ?? DEFAULT_LANGUAGE;
        $limit = $request->limit ? $request->limit : 15;
        $tags = $this->repository->where('language', $language)->with(['type'])->paginate($limit);
        $data = TagResource::collection($tags)->response()->getData(true);
        return formatAPIResourcePaginate($data);
    }

    /**
     * @OA\Post(
     *     path="/tags",
     *     operationId="createTag",
     *     tags={"Tags"},
     *     summary="Create a new tag",
     *     description="Create a new tag. Requires admin permissions.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "type_id"},
     *             @OA\Property(property="name", type="string", example="Organic"),
     *             @OA\Property(property="type_id", type="integer", example=1),
     *             @OA\Property(property="details", type="string", example="Organic products"),
     *             @OA\Property(property="icon", type="string", example="OrganicIcon")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tag created successfully", @OA\JsonContent(ref="#/components/schemas/Tag")),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(TagCreateRequest $request)
    {
        try {
            $validatedData = $request->validated();
            $validatedData['slug'] = $this->repository->makeSlug($request);
            return $this->repository->create($validatedData);
        } catch (MarvelException $th) {
            throw new MarvelException(COULD_NOT_CREATE_THE_RESOURCE);
        }
    }

    /**
     * @OA\Get(
     *     path="/tags/{slug}",
     *     operationId="getTag",
     *     tags={"Tags"},
     *     summary="Get a single product tag",
     *     description="Retrieve detailed information about a tag by slug or ID.",
     *     @OA\Parameter(name="slug", in="path", description="Tag slug or ID", required=true, @OA\Schema(type="string", example="organic")),
     *     @OA\Parameter(name="language", in="query", description="Language code", required=false, @OA\Schema(type="string", default="en")),
     *     @OA\Response(response=200, description="Tag retrieved successfully", @OA\JsonContent(ref="#/components/schemas/Tag")),
     *     @OA\Response(response=404, description="Tag not found")
     * )
     */
    public function show(Request $request, $params)
    {

        try {
            $language = $request->language ?? DEFAULT_LANGUAGE;
            if (is_numeric($params)) {
                $params = (int) $params;
                $tag = $this->repository->where('id', $params)->with(['type'])->firstOrFail();
                return new TagResource($tag);
            }
            $tag = $this->repository->where('slug', $params)->where('language', $language)->with(['type'])->firstOrFail();
            return new TagResource($tag);
        } catch (MarvelException $th) {
            throw new MarvelException(COULD_NOT_CREATE_THE_RESOURCE);
        }
    }

    /**
     * @OA\Put(
     *     path="/tags/{id}",
     *     operationId="updateTag",
     *     tags={"Tags"},
     *     summary="Update a tag",
     *     description="Update tag details. Requires admin permissions.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", description="Tag ID", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Tag")),
     *     @OA\Response(response=200, description="Tag updated successfully", @OA\JsonContent(ref="#/components/schemas/Tag")),
     *     @OA\Response(response=404, description="Tag not found")
     * )
     */
    public function update(TagUpdateRequest $request, $id)
    {
        try {
            $request['id'] = $id;
            return $this->tagUpdate($request);
        } catch (MarvelException $th) {
            throw new MarvelException(COULD_NOT_CREATE_THE_RESOURCE);
        }
    }

    public function tagUpdate(Request $request)
    {
        try {
            $tag = $this->repository->findOrFail($request->id);
            return $this->repository->updateTag($request, $tag);
        } catch (MarvelException $th) {
            throw new MarvelException(COULD_NOT_CREATE_THE_RESOURCE);
        }
    }

    /**
     * @OA\Delete(
     *     path="/tags/{id}",
     *     operationId="deleteTag",
     *     tags={"Tags"},
     *     summary="Delete a tag",
     *     description="Delete a product tag. Requires admin permissions.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", description="Tag ID to delete", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Tag deleted successfully", @OA\JsonContent(type="boolean", example=true)),
     *     @OA\Response(response=404, description="Tag not found")
     * )
     */
    public function destroy($id)
    {
        try {
            return $this->repository->findOrFail($id)->delete();
        } catch (MarvelException $th) {
            throw new MarvelException(COULD_NOT_CREATE_THE_RESOURCE);
        }
    }
}
