<?php


namespace Marvel\Http\Controllers;

use App\Services\General\CategoryHierarchyService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Category;
use Marvel\Database\Repositories\CategoryRepository;
use Marvel\Enums\Permission;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\CategoryCreateRequest;
use Marvel\Http\Requests\CategoryUpdateRequest;
use Marvel\Http\Resources\CategoryResource;
use Marvel\Traits\ApiResponse;


/**
 * @OA\Tag(name="Categories", description="Product category management - hierarchical categories with parent/child relationships")
 *
 * @OA\Schema(
 *     schema="CategorySummary",
 *     type="object",
 *     description="Category summary for listings",
 *     @OA\Property(property="id", type="integer", example=3),
 *     @OA\Property(property="name", type="string", example="Men"),
 *     @OA\Property(property="slug", type="string", example="men"),
 *     @OA\Property(property="icon", type="string", example="Wallet"),
 *     @OA\Property(property="details", type="string", example="A wonderful serenity has taken possession of my entire soul."),
 *     @OA\Property(property="parent", type="integer", nullable=true, example=null),
 *     @OA\Property(property="products_count", type="integer", example=25),
 *     @OA\Property(property="language", type="string", example="en"),
 *     @OA\Property(
 *         property="image",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=28),
 *             @OA\Property(property="original", type="string", example="https://chawkbazarlaravel.s3.ap-southeast-1.amazonaws.com/28/men.png"),
 *             @OA\Property(property="thumbnail", type="string", example="https://chawkbazarlaravel.s3.ap-southeast-1.amazonaws.com/28/conversions/men-thumbnail.jpg")
 *         )
 *     ),
 *     @OA\Property(
 *         property="banner_image",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="original", type="string"),
 *             @OA\Property(property="thumbnail", type="string")
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Category",
 *     allOf={@OA\Schema(ref="#/components/schemas/CategorySummary")},
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="type",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Clothing"),
 *         @OA\Property(property="slug", type="string", example="clothing")
 *     ),
 *     @OA\Property(
 *         property="parentCategory",
 *         type="object",
 *         nullable=true,
 *         ref="#/components/schemas/CategorySummary"
 *     ),
 *     @OA\Property(
 *         property="children",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/CategorySummary")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedCategories",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CategorySummary")),
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=8)
 * )
 */
class CategoryController extends CoreController
{
    use ApiResponse;
    public $repository;

    public function __construct(CategoryRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware("permission:" . Permission::VIEW_CATEGORIES, ["only" => ["index", "show", "fetchFeaturedCategories"]]);
        $this->middleware("permission:" . Permission::CREATE_CATEGORY, ["only" => ["store"]]);
        $this->middleware("permission:" . Permission::UPDATE_CATEGORY, ["only" => ["update"]]);
        $this->middleware("permission:" . Permission::DELETE_CATEGORY, ["only" => ["destroy"]]);
        $this->middleware("permission:" . Permission::UPDATE_CATEGORY, ["only" => ["addOrRemoveCategoryFromFeature"]]);
    }

    // /**
    //  * Display a listing of the resource.
    //  *
    //  * @param Request $request
    //  * @return Collection|Category[]
    //  */
//     public function fetchOnlyParent(Request $request)
//     {
//         $limit = $request->limit ? $request->limit : 15;
//         $categories = $this->repository->withCount(['products'])
// //            ->with(['type', 'parent', 'children'])
//             ->with(['children'])
//             ->where('parent', null)->paginate($limit);

    //             return $this->apiResponse("Categories fetched successfully", 200, true, CategoryResource::collection($categories));
//     }

    // /**
    //  * Display a listing of the resource.
    //  *
    //  * @param Request $request
    //  * @return Collection|Category[]
    //  */
    // public function fetchCategoryRecursively(Request $request)
    // {
    //     $limit = $request->limit ?   $request->limit : 15;
    //     return $this->repository->withCount(['products'])->with(['parent', 'subCategories'])->where('parent', null)->paginate($limit);
    // }
    /**
     * @OA\Get(
     *     path="/categories",
     *     operationId="listCategories",
     *     tags={"Categories"},
     *     summary="List all categories",
     *     description="Retrieve a paginated list of categories with optional filtering. Returns categories with their type, parent, children relationships and products count.",
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of categories per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, example=15)
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="Language code for translations",
     *         required=false,
     *         @OA\Schema(type="string", default="en", example="en")
     *     ),
     *     @OA\Parameter(
     *         name="parent",
     *         in="query",
     *         description="Filter by parent category. Use 'null' to get only root categories.",
     *         required=false,
     *         @OA\Schema(type="string", example="null")
     *     ),
     *     @OA\Parameter(
     *         name="self",
     *         in="query",
     *         description="Exclude category by ID (useful when editing to exclude self from parent dropdown)",
     *         required=false,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categories retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/PaginatedCategories")
     *     )
     * )
     */
    public function index(Request $request)
    {
        $parent = $request->parent ?? null;
        $selfId = $request->exceptSelf ?? null;
        $limit = $request->per_page ?? $request->limit ?? 15;
        $active = $request->active ?? null;
        $Inactive = $request->inactive ?? null;
        $search = $request->search ?? null;
        $featureCategory = $request->input('feature-category');
        $order = $request->order;
        $sortedBy = $request->sortedBy ?? 'asc';
        $categoriesQuery = $this->repository
            ->withCount(['products']);

        if ($featureCategory) {
            $categoriesQuery = $categoriesQuery->where('is_featured', true);
        }
        if ($order && in_array($order, ['id', 'name', 'slug', 'products_count', 'created_at', 'updated_at', 'level'])) {
            $categoriesQuery = $categoriesQuery->orderBy($order, $sortedBy === 'desc' ? 'desc' : 'asc');
        }
        if ($parent) {
            $categoriesQuery = $categoriesQuery->whereNull('parent_id');
        }
        if ($selfId) {
            $categoriesQuery = $categoriesQuery->where('id', '!=', $selfId);
        }
        if ($active) {
            $categoriesQuery = $categoriesQuery->active();
        }
        if ($Inactive) {
            $categoriesQuery = $categoriesQuery->inactive();
        }
        if ($search) {
            $categoriesQuery = $categoriesQuery->search('name', $search, app()->getLocale());
        }

        $categories = $categoriesQuery->paginate($limit);
        $data = CategoryResource::collection($categories)->response()->getData(true);
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

    /**
     * @OA\Post(
     *     path="/categories",
     *     operationId="createCategory",
     *     tags={"Categories"},
     *     summary="Create a new category",
     *     description="Create a new product category. Requires admin permissions.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="Accessories"),
     *             @OA\Property(property="slug", type="string", example="accessories"),
     *             @OA\Property(property="icon", type="string", example="Accessories"),
     *             @OA\Property(property="details", type="string", example="Browse our wide range of accessories."),
     *             @OA\Property(property="parent", type="integer", nullable=true, example=null, description="Parent category ID for nested categories"),
     *             @OA\Property(property="type_id", type="integer", example=1),
     *             @OA\Property(property="image", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="banner_image", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="language", type="string", example="en")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Category")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(CategoryCreateRequest $request)
    {
        try {
            $category = $this->repository->saveCategory($request);
            $category->load('products');
            return $this->apiResponse(CATEGORY_CREATED_SUCCESSFULLY, 200, true, CategoryResource::make($category));
        } catch (MarvelException $th) {
            throw new MarvelException(COULD_NOT_CREATE_THE_RESOURCE);
        }
    }

    /**
     * @OA\Get(
     *     path="/categories/{slug}",
     *     operationId="getCategory",
     *     tags={"Categories"},
     *     summary="Get a single category",
     *     description="Retrieve detailed category information by slug or ID. Includes type, parent category, and children.",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Category slug or ID",
     *         required=true,
     *         @OA\Schema(type="string", example="men")
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="Language code for translations",
     *         required=false,
     *         @OA\Schema(type="string", default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Category")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        try {
            $category = $this->repository->with(['parent', 'products'])
                ->withCount('products')
                ->where('id', $id)->firstOrFail();
            app(CategoryHierarchyService::class)->loadDirectChildren($category, true);
            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, CategoryResource::make($category));
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * @OA\Put(
     *     path="/categories/{id}",
     *     operationId="updateCategory",
     *     tags={"Categories"},
     *     summary="Update a category",
     *     description="Update an existing category. Requires admin permissions.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Men's Fashion"),
     *             @OA\Property(property="details", type="string", example="Updated description for men's fashion category."),
     *             @OA\Property(property="icon", type="string"),
     *             @OA\Property(property="parent", type="integer", nullable=true),
     *             @OA\Property(property="image", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="banner_image", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Category")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(CategoryUpdateRequest $request, $id)
    {
        try {
            $request->merge(['id' => $id]);
            $category = $this->categoryUpdate($request);
            return $this->apiResponse(CATEGORY_UPDATED_SUCCESSFULLY, 200, true, CategoryResource::make($category));
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }


    public function categoryUpdate(CategoryUpdateRequest $request): Category
    {
        $category = $this->repository->findOrFail($request->id);
        $category = $this->repository->updateCategory($request, $category);
        $category->load('products');
        return $category;
    }

    /**
     * @OA\Delete(
     *     path="/categories/{id}",
     *     operationId="deleteCategory",
     *     tags={"Categories"},
     *     summary="Delete a category",
     *     description="Delete a category. Requires admin permissions. Note: Deleting a parent category may affect child categories.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted successfully",
     *         @OA\JsonContent(type="boolean", example=true)
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $this->repository->findOrFail($id)->delete();
            return $this->apiResponse(CATEGORY_DELETED_SUCCESSFULLY, 200, true);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new MarvelException(NOT_FOUND);
        } catch (\Illuminate\Database\QueryException $e) {
            throw new MarvelException(CANNOT_DELETE_CATEGORY_WITH_ASSOCIATED_RESOURCES);
        }
    }

    /**
     * @OA\Get(
     *     path="/featured-categories",
     *     operationId="getFeaturedCategories",
     *     tags={"Categories"},
     *     summary="Get featured categories",
     *     description="Retrieve featured categories with their top products. Returns 3 categories by default. ChawkBazar specific endpoint.",
     *     @OA\Response(
     *         response=200,
     *         description="Featured categories retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 allOf={@OA\Schema(ref="#/components/schemas/CategorySummary")},
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/ProductSummary")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function fetchFeaturedCategories(Request $request)
    {
        $limit = isset($request->limit) ? $request->limit : 3;
        //                return $this->repository->with(['products'])->take($limit)->get()->map(function ($category) {
        //                    $category->setRelation('products', $category->products->withCount('orders')->sortBy('orders_count', "desc")->take(3));
        //                    return $category;
        //                });
        $categories = $this->repository->with(['products'])
            ->withCount('products')
            ->orderByDesc('products_count')
            ->limit($limit)
            ->get();
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, CategoryResource::collection($categories));
    }

    public function addOrRemoveCategoryFromFeature(Request $request)
    {
        $request->validate([
           "id"=>'required|integer|exists:categories,id',
        ]);

        $category = Category::find($request->id);
        $category->is_featured = !$category->is_featured;
        $category->save();

        return $this->apiResponse(CATEGORY_FEATURE_TOGGLED_SUCCESSFULLY, 200, true);
    }
}
