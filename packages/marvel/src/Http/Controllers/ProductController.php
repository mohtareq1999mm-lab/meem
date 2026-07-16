<?php

namespace Marvel\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Marvel\Database\Models\Type;
use Illuminate\Http\JsonResponse;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Wishlist;
use Marvel\Database\Models\Variation;
use Marvel\Exceptions\MarvelException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Marvel\Database\Models\Author;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\Manufacturer;
use Marvel\Http\Requests\ProductCreateRequest;
use Marvel\Http\Requests\ProductUpdateRequest;
use Marvel\Database\Repositories\ProductRepository;
use Marvel\Database\Repositories\SettingsRepository;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Marvel\Database\Models\Settings;
use Marvel\Database\Models\Tag;
use Marvel\Exceptions\MarvelNotFoundException;
use \OpenAI;
use Marvel\Enums\Permission;
use Marvel\Http\Resources\GetSingleProductResource;
use Marvel\Http\Resources\ProductResource;

/**
 * @OA\Tag(name="Products", description="Product catalog endpoints - browse, search, and manage products")
 *
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     description="Full product details for single product view",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Hoppister Tops"),
 *     @OA\Property(property="slug", type="string", example="hoppister-tops"),
 *     @OA\Property(property="description", type="string", example="Fendi began life in 1925 as a fur and leather speciality store in Rome."),
 *     @OA\Property(property="type_id", type="integer", example=13),
 *     @OA\Property(property="price", type="number", format="float", nullable=true, example=350.00),
 *     @OA\Property(property="sale_price", type="number", format="float", nullable=true, example=300.00),
 *     @OA\Property(property="min_price", type="number", format="float", example=20.00),
 *     @OA\Property(property="max_price", type="number", format="float", example=25.00),
 *     @OA\Property(property="quantity", type="integer", example=1000),
 *     @OA\Property(property="in_stock", type="boolean", example=true),
 *     @OA\Property(property="is_taxable", type="boolean", example=false),
 *     @OA\Property(property="status", type="string", enum={"draft", "publish", "approved", "rejected", "under_review"}, example="publish"),
 *     @OA\Property(property="product_type", type="string", enum={"simple", "variable"}, example="variable"),
 *     @OA\Property(property="unit", type="string", example="1 pc"),
 *     @OA\Property(property="sku", type="string", nullable=true, example="SKU-12345"),
 *     @OA\Property(property="shop_id", type="integer", example=2),
 *     @OA\Property(property="height", type="string", nullable=true),
 *     @OA\Property(property="width", type="string", nullable=true),
 *     @OA\Property(property="length", type="string", nullable=true),
 *     @OA\Property(property="is_digital", type="boolean", example=false),
 *     @OA\Property(property="is_external", type="boolean", example=false),
 *     @OA\Property(property="is_rental", type="boolean", example=false),
 *     @OA\Property(property="ratings", type="number", format="float", example=4.5),
 *     @OA\Property(property="total_reviews", type="integer", example=25),
 *     @OA\Property(property="in_wishlist", type="boolean", example=false),
 *     @OA\Property(property="language", type="string", example="en"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="image", type="object", @OA\Property(property="id", type="integer"), @OA\Property(property="original", type="string"), @OA\Property(property="thumbnail", type="string")),
 *     @OA\Property(property="gallery", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="categories", type="array", @OA\Items(type="object", @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"), @OA\Property(property="slug", type="string"))),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="shop", type="object", @OA\Property(property="id", type="integer", example=2), @OA\Property(property="name", type="string", example="Urban Threads Emporium"), @OA\Property(property="slug", type="string")),
 *     @OA\Property(property="type", type="object", @OA\Property(property="id", type="integer", example=13), @OA\Property(property="name", type="string", example="Clothing"), @OA\Property(property="slug", type="string")),
 *     @OA\Property(property="related_products", type="array", @OA\Items(ref="#/components/schemas/ProductSummary"))
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedProducts",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ProductSummary")),
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=50),
 *     @OA\Property(property="last_page", type="integer", example=5)
 * )
 */
class ProductController extends CoreController
{
    public $repository;

    public $settings;

    public function __construct(ProductRepository $repository, SettingsRepository $settings)
    {
        $this->repository = $repository;
        $this->settings = $settings;
    }


    /**
     * @OA\Get(
     *     path="/products",
     *     operationId="getProducts",
     *     tags={"Products"},
     *     summary="List all products",
     *     description="Retrieve a paginated list of products with optional filtering by category, shop, type, price range, and more.",
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of products per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, minimum=1, maximum=100, example=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="Language code for product translations",
     *         required=false,
     *         @OA\Schema(type="string", default="en", example="en")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term to filter products by name or description",
     *         required=false,
     *         @OA\Schema(type="string", example="shirt")
     *     ),
     *     @OA\Parameter(
     *         name="shop_id",
     *         in="query",
     *         description="Filter by shop ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="type_id",
     *         in="query",
     *         description="Filter by type/collection ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=13)
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category slug",
     *         required=false,
     *         @OA\Schema(type="string", example="men")
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=20.00)
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=500.00)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by product status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"publish", "draft", "approved", "rejected", "under_review"}, example="publish")
     *     ),
     *     @OA\Parameter(
     *         name="orderBy",
     *         in="query",
     *         description="Field to order by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"created_at", "name", "price", "min_price", "max_price"}, example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sortedBy",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc", example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/PaginatedProducts")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function index(Request $request)
    {
        $limit = $request->limit ? $request->limit : 15;
        $products = $this->fetchProducts($request)->paginate($limit)->withQueryString();
        $data = ProductResource::collection($products)->response()->getData(true);
        return formatAPIResourcePaginate($data);
    }



    /**
     * fetchProducts
     *
     * @param  mixed $request
     * @return object
     */
    public function fetchProducts(Request $request)
    {
        $unavailableProducts = [];
        $language = $request->language ? $request->language : DEFAULT_LANGUAGE;

        $products_query = $this->repository->where('language', $language);

        if (isset($request->date_range)) {
            $dateRange = explode('//', $request->date_range);
            $unavailableProducts = $this->repository->getUnavailableProducts($dateRange[0], $dateRange[1]);
        }
        if (in_array('variation_options.digital_files', explode(';', $request->with)) || in_array('digital_files', explode(';', $request->with))) {
            throw new AuthorizationException(NOT_AUTHORIZED);
        }
        $products_query = $products_query->whereNotIn('id', $unavailableProducts);

        if ($request->flash_sale_builder) {
            $products_query = $this->repository->processFlashSaleProducts($request, $products_query);
        }

        return $products_query;
    }



    /**
     * @OA\Post(
     *     path="/products",
     *     operationId="createProduct",
     *     tags={"Products"},
     *     summary="Create a new product",
     *     description="Create a new product. Requires STAFF or STORE_OWNER permissions.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "type_id", "shop_id", "product_type", "unit"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="Dido Pilot Glass"),
     *             @OA\Property(property="slug", type="string", nullable=true, example="dido-pilot-glass"),
     *             @OA\Property(property="description", type="string", maxLength=10000, example="Polarized sunglasses reduce glare reflected off of roads, bodies of water, snow and other horizontal surfaces."),
     *             @OA\Property(property="type_id", type="integer", example=15),
     *             @OA\Property(property="shop_id", type="integer", example=2),
     *             @OA\Property(property="price", type="number", format="float", nullable=true, example=350.00),
     *             @OA\Property(property="sale_price", type="number", format="float", nullable=true, example=300.00, description="Must be less than or equal to price"),
     *             @OA\Property(property="quantity", type="integer", nullable=true, example=500),
     *             @OA\Property(property="sku", type="string", example="SKU-GLASS-001"),
     *             @OA\Property(property="unit", type="string", example="1 pc"),
     *             @OA\Property(property="product_type", type="string", enum={"simple", "variable"}, example="simple"),
     *             @OA\Property(property="status", type="string", enum={"draft", "publish", "approved", "rejected", "under_review", "unpublish"}, example="publish"),
     *             @OA\Property(property="in_stock", type="boolean", example=true),
     *             @OA\Property(property="is_taxable", type="boolean", example=false),
     *             @OA\Property(property="is_digital", type="boolean", example=false),
     *             @OA\Property(property="is_external", type="boolean", example=false),
     *             @OA\Property(property="is_rental", type="boolean", example=false),
     *             @OA\Property(property="height", type="string", nullable=true),
     *             @OA\Property(property="width", type="string", nullable=true),
     *             @OA\Property(property="length", type="string", nullable=true),
     *             @OA\Property(property="external_product_url", type="string", nullable=true),
     *             @OA\Property(property="external_product_button_text", type="string", nullable=true),
     *             @OA\Property(property="categories", type="array", @OA\Items(type="integer"), example={3, 6}),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="integer"), example={1, 2}),
     *             @OA\Property(property="image", type="object", @OA\Property(property="id", type="integer"), @OA\Property(property="original", type="string"), @OA\Property(property="thumbnail", type="string")),
     *             @OA\Property(property="gallery", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="language", type="string", example="en")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - insufficient permissions"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function store(ProductCreateRequest $request)
    {
        return $this->ProductStore($request);
    }



    /**
     * Store a newly created resource in storage by GQL.
     *
     * @param Request $request
     * @return mixed
     */
    public function ProductStore(Request $request)
    {
        try {
            // inform_purchased_customer
            $setting = $this->settings->first();
            if ($this->repository->hasPermission($request->user(), $request->shop_id)) {
                return $this->repository->storeProduct($request, $setting);
            } else {
                throw new AuthorizationException(NOT_AUTHORIZED);
            }
        } catch (MarvelException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG, $e->getMessage());
        }
    }



    /**
     * @OA\Get(
     *     path="/products/{slug}",
     *     operationId="getProduct",
     *     tags={"Products"},
     *     summary="Get a single product",
     *     description="Retrieve detailed product information by slug or ID. Includes related products.",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Product slug or ID",
     *         required=true,
     *         @OA\Schema(type="string", example="hoppister-tops")
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="Language code for translations",
     *         required=false,
     *         @OA\Schema(type="string", default="en", example="en")
     *     ),
     *     @OA\Parameter(
     *         name="with",
     *         in="query",
     *         description="Relationships to include (separated by semicolon)",
     *         required=false,
     *         @OA\Schema(type="string", example="categories;tags;shop;type")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="MARVEL_ERROR.NOT_FOUND")
     *         )
     *     )
     * )
     */
    public function show(Request $request, $slug)
    {
        $request->merge(['slug' => $slug]);
        try {
            $product = $this->fetchSingleProduct($request);
            return new GetSingleProductResource($product);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }



    /**
     * Display the specified resource.
     *
     * @param $slug
     * @return JsonResponse
     */
    public function fetchSingleProduct(Request $request)
    {
        try {
            $slug = $request->slug;
            $language = $request->language ?? DEFAULT_LANGUAGE;
            $user = $request->user();
            $limit = isset($request->limit) ? $request->limit : 10;
            $product = $this->repository->where('language', $language)->where('slug', $slug)->orWhere('id', $slug)->firstOrFail();
            if (
                in_array('variation_options.digital_file', explode(';', $request->with)) || in_array('digital_file', explode(';', $request->with))
            ) {
                if (!$this->repository->hasPermission($user, $product->shop_id)) {
                    throw new AuthorizationException(NOT_AUTHORIZED);
                }
            }
            $related_products = $this->repository->fetchRelated($slug, $limit, $language);
            $product->setRelation('related_products', $related_products);

            return $product;
        } catch (Exception $e) {
            throw new MarvelNotFoundException();
        }
    }


    /**
     * @OA\Put(
     *     path="/products/{id}",
     *     operationId="updateProduct",
     *     tags={"Products"},
     *     summary="Update a product",
     *     description="Update an existing product. Requires STAFF or STORE_OWNER permissions for the shop.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Updated Hoppister Tops"),
     *             @OA\Property(property="description", type="string", example="Updated description for this product."),
     *             @OA\Property(property="price", type="number", format="float", example=399.00),
     *             @OA\Property(property="sale_price", type="number", format="float", example=350.00),
     *             @OA\Property(property="quantity", type="integer", example=1500),
     *             @OA\Property(property="status", type="string", enum={"draft", "publish"}, example="publish"),
     *             @OA\Property(property="categories", type="array", @OA\Items(type="integer"), example={3, 6}),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - you don't have permission to update this product"),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(ProductUpdateRequest $request, $id)
    {
        try {
            $request->id = $id;
            return $this->updateProduct($request);
        } catch (MarvelException $e) {
            throw new MarvelException(COULD_NOT_UPDATE_THE_RESOURCE);
        }
    }


    /**
     * updateProduct
     *
     * @param  Request $request
     * @return array
     */
    public function updateProduct(Request $request)
    {
        $setting = $this->settings->first();
        if ($this->repository->hasPermission($request->user(), $request->shop_id)) {
            $id = $request->id;
            return $this->repository->updateProduct($request, $id, $setting);
        } else {
            throw new AuthorizationException(NOT_AUTHORIZED);
        }
    }


    /**
     * @OA\Delete(
     *     path="/products/{id}",
     *     operationId="deleteProduct",
     *     tags={"Products"},
     *     summary="Delete a product",
     *     description="Soft delete a product. Requires STAFF or STORE_OWNER permissions for the shop.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - you don't have permission to delete this product"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function destroy(Request $request, $id)
    {
        $request->id = $id;
        return $this->destroyProduct($request);
    }


    /**
     * destroyProduct
     *
     * @param  Request $request
     * @return void
     */
    public function destroyProduct(Request $request)
    {
        try {
            $product = $this->repository->findOrFail($request->id);
            if ($this->repository->hasPermission($request->user(), $product->shop_id)) {
                $product->delete();
                return $product;
            }
            throw new AuthorizationException(NOT_AUTHORIZED);
        } catch (MarvelException $e) {
            throw new MarvelException($e->getMessage());
        }
    }



    /**
     * relatedProducts
     *
     * @param  Request $request
     * @return void
     */
    public function relatedProducts(Request $request)
    {
        $limit = isset($request->limit) ? $request->limit : 10;
        $slug = $request->slug;
        $language = $request->language ?? DEFAULT_LANGUAGE;
        return $this->repository->fetchRelated($slug, $limit, $language);
    }



    /**
     * exportProducts
     *
     * @param  Request $request
     * @param  mixed $shop_id
     * @return void
     */
    public function exportProducts(Request $request, $shop_id)
    {

        $filename = 'products-for-shop-id-' . $shop_id . '.csv';
        $headers = [
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Expires' => '0',
            'Pragma' => 'public'
        ];

        $list = $this->repository->with([
            'categories',
            'tags',
        ])->where('shop_id', $shop_id)->get()->toArray();

        if (!count($list)) {
            return response()->stream(function () {
                //
            }, 200, $headers);
        }
        # add headers for each column in the CSV download
        array_unshift($list, array_keys($list[0]));

        $callback = function () use ($list) {
            $FH = fopen('php://output', 'w');
            foreach ($list as $key => $row) {
                if ($key === 0) {
                    $exclude = ['id', 'slug', 'deleted_at', 'created_at', 'updated_at', 'shipping_class_id', 'ratings', 'total_reviews', 'my_review', 'in_wishlist', 'rating_count', 'translated_languages', 'sold', 'blocked_dates'];
                    $row = array_diff($row, $exclude);
                }
                unset($row['id']);
                unset($row['deleted_at']);
                unset($row['shipping_class_id']);
                unset($row['updated_at']);
                unset($row['created_at']);
                unset($row['slug']);
                unset($row['ratings']);
                unset($row['total_reviews']);
                unset($row['my_review']);
                unset($row['in_wishlist']);
                unset($row['rating_count']);
                unset($row['translated_languages']);
                unset($row['sold']);
                unset($row['blocked_dates']);

                if (isset($row['image'])) {
                    $row['image'] = json_encode($row['image']);
                }
                if (isset($row['gallery'])) {
                    $row['gallery'] = json_encode($row['gallery']);
                }
                if (isset($row['blocked_dates'])) {
                    $row['blocked_dates'] = json_encode($row['blocked_dates']);
                }
                if (isset($row['video'])) {
                    $row['video'] = json_encode($row['video']);
                }
                if (isset($row['categories'])) {
                    $categories = collect($row['categories'])->pluck('id')->toArray();
                    $row['categories'] = json_encode($categories);
                }
                if (isset($row['tags'])) {
                    $tagIds = collect($row['tags'])->pluck('pivot.tag_id')->toArray();
                    $row['tags'] = json_encode($tagIds);
                }
                fputcsv($FH, $row);
            }
            fclose($FH);
        };

        return response()->stream($callback, 200, $headers);
    }



    /**
     * exportVariableOptions
     *
     * @param  Request $request
     * @param  mixed $shop_id
     * @return void
     */
    public function exportVariableOptions(Request $request, $shop_id)
    {
        $filename = 'variable-options-' . Str::random(5) . '.csv';
        $headers = [
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Expires' => '0',
            'Pragma' => 'public'
        ];

        $products = $this->repository->where('shop_id', $shop_id)->get();

        $list = Variation::WhereIn('product_id', $products->pluck('id'))->get()->toArray();

        if (!count($list)) {
            return response()->stream(function () {
                //
            }, 200, $headers);
        }
        # add headers for each column in the CSV download
        array_unshift($list, array_keys($list[0]));

        $callback = function () use ($list) {
            $FH = fopen('php://output', 'w');
            foreach ($list as $key => $row) {
                if ($key === 0) {
                    $exclude = ['id', 'created_at', 'updated_at', 'translated_languages'];
                    $row = array_diff($row, $exclude);
                }
                unset($row['id']);
                unset($row['updated_at']);
                unset($row['created_at']);
                unset($row['translated_languages']);
                if (isset($row['options'])) {
                    $row['options'] = json_encode($row['options']);
                }
                if (isset($row['blocked_dates'])) {
                    $row['blocked_dates'] = json_encode($row['blocked_dates']);
                }
                fputcsv($FH, $row);
            }
            fclose($FH);
        };

        return response()->stream($callback, 200, $headers);
    }




    /**
     * importProducts
     *
     * @param  Request $request
     * @return bool
     */
    public function importProducts(Request $request)
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
            throw new AuthorizationException(NOT_AUTHORIZED);
        }
        if (isset($shop_id)) {
            $file = $uploadedCsv->storePubliclyAs('csv-files', 'products-' . $shop_id . '.' . $uploadedCsv->getClientOriginalExtension(), 'public');

            $products = $this->repository->csvToArray(storage_path() . '/app/public/' . $file);

            foreach ($products as $key => $product) {
                if (!isset($product['type_id'])) {
                    throw new MarvelException("MARVEL_ERROR.WRONG_CSV");
                }
                unset($product['id']);
                $product['shop_id'] = $shop_id;
                $product['image'] = json_decode($product['image'], true);
                $product['gallery'] = json_decode($product['gallery'], true);
                $product['video'] = json_decode($product['video'], true);
                $categoriesId = json_decode($product['categories'], true);
                $tagsId = json_decode($product['tags'], true);
                try {
                    $type = Type::findOrFail($product['type_id']);
                    $authorCacheKey = $product['author_id'] . '_author_id';
                    $manufacturerCacheKey = $product['manufacturer_id'] . '_manufacturer_id';
                    $product['author_id'] = Cache::remember($authorCacheKey, 30, fn() => Author::find($product['author_id'])?->id);
                    $product['manufacturer_id'] = Cache::remember($manufacturerCacheKey, 30, fn() => Manufacturer::find($product['manufacturer_id'])?->id);
                    $dataArray = $this->repository->getProductDataArray();
                    $productArray = array_intersect_key($product, array_flip($dataArray));
                    if (isset($type->id)) {
                        $newProduct = Product::FirstOrCreate($productArray);
                        $categoryCacheKey = $product['categories'] . '_categories';
                        $tagCacheKey = $product['tags'] . '_tags';
                        $categories = Cache::remember($categoryCacheKey, 30, fn() => Category::whereIn('id', $categoriesId)->get());
                        $tags = Cache::remember($tagCacheKey, 30, fn() => Tag::whereIn('id', $tagsId)->get());
                        if (!empty($categories)) {
                            $newProduct->categories()->attach($categories);
                        }
                        if (!empty($tags)) {
                            $newProduct->tags()->attach($tags);
                        }
                    }
                } catch (Exception $e) {
                    //
                }
            }
            return true;
        }
    }



    /**
     * importVariationOptions
     *
     * @param  Request $request
     * @return bool
     */
    public function importVariationOptions(Request $request)
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
        } else {
            throw new MarvelException(CSV_NOT_FOUND);
        }

        if (!$this->repository->hasPermission($user, $shop_id)) {
            throw new AuthorizationException(NOT_AUTHORIZED);
        }
        if (isset($user->id)) {
            $file = $uploadedCsv->storePubliclyAs('csv-files', 'variation-options-' . Str::random(5) . '.' . $uploadedCsv->getClientOriginalExtension(), 'public');

            $attributes = $this->repository->csvToArray(storage_path() . '/app/public/' . $file);

            foreach ($attributes as $key => $attribute) {
                if (!isset($attribute['title']) || !isset($attribute['price'])) {
                    throw new MarvelException("MARVEL_ERROR.WRONG_CSV");
                }
                unset($attribute['id']);
                $attribute['options'] = json_decode($attribute['options'], true);
                try {
                    $product = Type::findOrFail($attribute['product_id']);
                    if (isset($product->id)) {
                        Variation::firstOrCreate($attribute);
                    }
                } catch (Exception $e) {
                    //
                }
            }
            return true;
        }
    }



    /**
     * fetchDigitalFilesForProduct
     *
     * @param  Request $request
     * @return void
     */
    public function fetchDigitalFilesForProduct(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $product = $this->repository->with(['digital_file'])->findOrFail($request->parent_id);
            if ($this->repository->hasPermission($user, $product->shop_id)) {
                return $product->digital_file;
            }
        }
    }



    /**
     * fetchDigitalFilesForVariation
     *
     * @param  Request $request
     * @return void
     */
    public function fetchDigitalFilesForVariation(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $variation_option = Variation::with(['digital_file', 'product'])->findOrFail($request->parent_id);
            if ($this->repository->hasPermission($user, $variation_option->product->shop_id)) {
                return $variation_option->digital_file;
            }
        }
    }



    /**
     * @OA\Get(
     *     path="/best-selling-products",
     *     operationId="fetchBestSellingProducts",
     *     tags={"Products"},
     *     summary="Get best selling products",
     *     description="Retrieve products sorted by total sold quantity. Useful for showcasing top performers.",
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of products to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, minimum=1, maximum=100, example=10)
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="Language code for product translations",
     *         required=false,
     *         @OA\Schema(type="string", default="en", example="en")
     *     ),
     *     @OA\Parameter(
     *         name="type_slug",
     *         in="query",
     *         description="Filter by type/collection slug",
     *         required=false,
     *         @OA\Schema(type="string", example="clothing")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Best selling products retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/ProductSummary")
     *         )
     *     )
     * )
     */
    public function bestSellingProducts(Request $request)
    {
        return $this->repository->getBestSellingProducts($request);
    }



    /**
     * @OA\Get(
     *     path="/popular-products",
     *     operationId="fetchPopularProducts",
     *     tags={"Products"},
     *     summary="Get popular products",
     *     description="Retrieve products sorted by order count (popularity). Supports filtering by shop, type, and date range.",
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of products to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, minimum=1, maximum=100, example=10)
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="Language code for product translations",
     *         required=false,
     *         @OA\Schema(type="string", default="en", example="en")
     *     ),
     *     @OA\Parameter(
     *         name="shop_id",
     *         in="query",
     *         description="Filter by shop ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="type_id",
     *         in="query",
     *         description="Filter by type/collection ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=13)
     *     ),
     *     @OA\Parameter(
     *         name="type_slug",
     *         in="query",
     *         description="Filter by type/collection slug (alternative to type_id)",
     *         required=false,
     *         @OA\Schema(type="string", example="clothing")
     *     ),
     *     @OA\Parameter(
     *         name="range",
     *         in="query",
     *         description="Number of days to look back for popularity calculation",
     *         required=false,
     *         @OA\Schema(type="integer", example=30)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Popular products retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/ProductSummary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Type not found (if type_slug is provided but not found)"
     *     )
     * )
     */
    public function popularProducts(Request $request)
    {
        $limit = $request->limit ? $request->limit : 10;
        $language = $request->language ?? DEFAULT_LANGUAGE;
        $range = !empty($request->range) && $request->range !== 'undefined' ? $request->range : '';
        $type_id = $request->type_id ? $request->type_id : '';
        if (isset($request->type_slug) && empty($type_id)) {
            try {
                $type = Type::where('slug', $request->type_slug)->where('language', $language)->firstOrFail();
                $type_id = $type->id;
            } catch (MarvelException $e) {
                throw new MarvelException(NOT_FOUND);
            }
        }
        $products_query = $this->repository->withCount('orders')->with(['type', 'shop'])->orderBy('orders_count', 'desc')->where('language', $language);
        if (isset($request->shop_id)) {
            $products_query = $products_query->where('shop_id', "=", $request->shop_id);
        }
        if ($range) {
            $products_query = $products_query->whereDate('created_at', '>', Carbon::now()->subDays($range));
        }
        if ($type_id) {
            $products_query = $products_query->where('type_id', '=', $type_id);
        }
        return $products_query->take($limit)->get();
    }



    /**
     * @OA\Get(
     *     path="/products/calculate-rental-price",
     *     operationId="calculateRentalPrice",
     *     tags={"Products"},
     *     summary="Calculate rental price for a product",
     *     description="Calculates the total rental price based on duration, quantity, and selected features. Also checks availability for the given date range.",
     *     @OA\Parameter(name="product_id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="variation_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from", in="query", required=true, @OA\Schema(type="string", format="date", example="2023-12-01")),
     *     @OA\Parameter(name="to", in="query", required=true, @OA\Schema(type="string", format="date", example="2023-12-05")),
     *     @OA\Parameter(name="quantity", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="persons", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="dropoff_location_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="pickup_location_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Calculated rental price",
     *         @OA\JsonContent(
     *             @OA\Property(property="price", type="number", format="float", example=150.00),
     *             @OA\Property(property="total", type="number", format="float", example=150.00)
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid product or date range"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function calculateRentalPrice(Request $request)
    {
        $isAvailable = true;
        $product_id = $request->product_id;
        try {
            $product = Product::findOrFail($product_id);
        } catch (MarvelException $th) {
            throw new MarvelException(NOT_FOUND);
        }
        if (!$product->is_rental) {
            throw new MarvelException(NOT_A_RENTAL_PRODUCT);
        }
        $variation_id = $request->variation_id;
        $quantity = $request->quantity;
        $persons = $request->persons;
        $dropoff_location_id = $request->dropoff_location_id;
        $pickup_location_id = $request->pickup_location_id;
        $deposits = $request->deposits;
        $features = $request->features;
        $from = $request->from;
        $to = $request->to;
        if ($variation_id) {
            $blockedDates = $this->repository->fetchBlockedDatesForAVariationInRange($from, $to, $variation_id);
            $isAvailable = $this->repository->isVariationAvailableAt($from, $to, $variation_id, $blockedDates, $quantity);
            if (!$isAvailable) {
                throw new marvelException(NOT_AVAILABLE_FOR_BOOKING);
            }
        } else {
            $blockedDates = $this->repository->fetchBlockedDatesForAProductInRange($from, $to, $product_id);
            $isAvailable = $this->repository->isProductAvailableAt($from, $to, $product_id, $blockedDates, $quantity);
            if (!$isAvailable) {
                throw new marvelException(NOT_AVAILABLE_FOR_BOOKING);
            }
        }

        $from = Carbon::parse($from);
        $to = Carbon::parse($to);

        $bookedDay = $from->diffInDays($to);

        return $this->repository->calculatePrice($bookedDay, $product_id, $variation_id, $quantity, $persons, $dropoff_location_id, $pickup_location_id, $deposits, $features);
    }



    /**
     * myWishlists
     *
     * @param  Request $request
     * @return void
     */
    public function myWishlists(Request $request)
    {
        $limit = $request->limit ? $request->limit : 10;
        return $this->fetchWishlists($request)->paginate($limit);
    }



    /**
     * fetchWishlists
     *
     * @param  Request $request
     * @return object
     */
    public function fetchWishlists(Request $request)
    {
        $user = $request->user();
        $wishlist = Wishlist::where('user_id', $user->id)->pluck('product_id');
        return $this->repository->whereIn('id', $wishlist);
    }


    /**
     * @OA\Get(
     *     path="/draft-products",
     *     operationId="getDraftedProducts",
     *     tags={"Products"},
     *     summary="List products in draft status",
     *     description="Returns a paginated list of drafted products belonging to the user's shop(s).",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="shop_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="language", in="query", required=false, @OA\Schema(type="string", default="en")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of drafted products",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function draftedProducts(Request $request)
    {
        $limit = $request->limit ? $request->limit : 15;

        return $this->fetchDraftedProducts($request)->paginate($limit);
    }

    /**
     * fetchDraftedProducts
     *
     * @param  Request $request
     * @return mixed
     */
    public function fetchDraftedProducts(Request $request)
    {
        $user = $request->user() ?? null;
        ;
        $language = $request->language ? $request->language : DEFAULT_LANGUAGE;

        $products_query = $this->repository->with(['type', 'shop'])->where('language', $language);

        switch ($user) {
            case $user->hasPermissionTo(Permission::SUPER_ADMIN):
                return $products_query->whereIn('shop_id', $user->shops->pluck('id'));
                break;

            case $user->hasPermissionTo(Permission::STORE_OWNER):
                if (isset($request->shop_id)) {
                    return $products_query->where('shop_id', '=', $request->shop_id);
                } else {
                    return $products_query->whereIn('shop_id', $user->shops->pluck('id'));
                }
                break;

            case $user->hasPermissionTo(Permission::STAFF):
                if (isset($request->shop_id)) {
                    return $products_query->where('shop_id', '=', $request->shop_id);
                } else {
                    return $products_query->where('shop_id', $user->managed_shop->id);
                }
                break;
        }

        return $products_query;
    }

    /**
     * @OA\Get(
     *     path="/product-stock",
     *     operationId="getProductStock",
     *     tags={"Products"},
     *     summary="List products with low stock",
     *     description="Returns a paginated list of products with inventory less than 10 belonging to the user's shop(s).",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="shop_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="language", in="query", required=false, @OA\Schema(type="string", default="en")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of products with low stock",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function productStock(Request $request)
    {
        $limit = $request->limit ? $request->limit : 15;

        return $this->fetchProductStock($request)->paginate($limit);
    }

    /**
     * productStock
     *
     * @param  Request $request
     * @return mixed
     */
    public function fetchProductStock(Request $request)
    {
        $user = $request->user();
        $language = $request->language ? $request->language : DEFAULT_LANGUAGE;

        $products_query = $this->repository->with(['type', 'shop'])->where('language', $language)->where('quantity', '<', 10);

        switch ($user) {
            case $user->hasPermissionTo(Permission::SUPER_ADMIN):
                if (isset($request->shop_id)) {
                    return $products_query->where('shop_id', '=', $request->shop_id);
                } else {
                    return $products_query;
                }
                break;

            case $user->hasPermissionTo(Permission::STORE_OWNER):
                if (isset($request->shop_id)) {
                    // shop specific
                    return $products_query->where('shop_id', '=', $request->shop_id);
                } else {
                    // overall shops
                    return $products_query->whereIn('shop_id', $user->shops->pluck('id'));
                }
                break;

            case $user->hasPermissionTo(Permission::STAFF):
                if (isset($request->shop_id)) {
                    return $products_query->where('shop_id', '=', $request->shop_id);
                } else {
                    return $products_query->where('shop_id', '=', null);
                }
                break;

            default:
                return $products_query->where('shop_id', '=', null);

                break;
        }

        return $products_query;
    }
}
