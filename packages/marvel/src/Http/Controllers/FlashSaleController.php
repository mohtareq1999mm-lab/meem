<?php

namespace Marvel\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Models\FlashSale;
use Marvel\Database\Models\Product;
use Marvel\Database\Repositories\FlashSaleRepository;
use Marvel\Enums\Permission;
use Marvel\Events\FlashSaleProcessed;
use Marvel\Exceptions\MarvelException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Marvel\Http\Requests\CreateFlashSaleRequest;
use Marvel\Http\Requests\UpdateFlashSaleRequest;
use Marvel\Http\Resources\FlashSaleResource;
use Marvel\Traits\ApiResponse;
use Prettus\Validator\Exceptions\ValidatorException;
use Marvel\Database\Repositories\ProductRepository;

/**
 * @OA\Tag(name="Flash Sales", description="Public and admin flash sale management")
 *
 * @OA\Schema(
 *     schema="FlashSale",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Summer Sale"),
 *     @OA\Property(property="slug", type="string", example="summer-sale"),
 *     @OA\Property(property="description", type="string", example="Up to 50% off on summer collection"),
 *     @OA\Property(property="image", type="object"),
 *     @OA\Property(property="start_date", type="string", format="date-time"),
 *     @OA\Property(property="end_date", type="string", format="date-time"),
 *     @OA\Property(property="type", type="string", enum={"fixed", "percentage"}, example="percentage"),
 *     @OA\Property(property="rate", type="number", example=50.00),
 *     @OA\Property(property="language", type="string", example="en"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class FlashSaleController extends CoreController
{
    use ApiResponse;
    public $repository;

    public $productRepository;

    public function __construct(FlashSaleRepository $repository, ProductRepository $productRepository)
    {
        $this->repository = $repository;
        $this->productRepository = $productRepository;
        $this->middleware("permission:" . Permission::VIEW_FlASH_SALE, ["only" => ["index", "show"]]);
        $this->middleware("permission:" . Permission::CREATE_FlASH_SALE, ["only" => ["store"]]);
        $this->middleware("permission:" . Permission::UPDATE_FlASH_SALE, ["only" => ["update", "reorder"]]);
        $this->middleware("permission:" . Permission::DELETE_FlASH_SALE, ["only" => ["destroy"]]);
    }

    /**
     * @OA\Get(
     *     path="/flash-sale",
     *     operationId="getFlashSales",
     *     tags={"Flash Sales"},
     *     summary="List Flash Sales",
     *     description="Retrieve a paginated list of flash sales.",
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="language", in="query", required=false, @OA\Schema(type="string", default="en")),
     *     @OA\Parameter(name="request_from", in="query", required=false, @OA\Schema(type="string", enum={"vendor"})),
     *     @OA\Response(
     *         response=200,
     *         description="Flash sales retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/FlashSale")),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->per_page ?? $request->limit ?? 10;
            $flashSales =  $this->fetchFlashSales($request)->paginate($limit)->withQueryString();
            $flashSaleData = FlashSaleResource::collection($flashSales)->response()->getData(true);
            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, [
                "data" => $flashSaleData['data'] ?? [],
                "page" => $flashSaleData['meta']['current_page'] ?? 0,
                "current_page" => $flashSaleData['meta']['current_page'] ?? 0,
                "from" => $flashSaleData['meta']['from'] ?? 0,
                "to" => $flashSaleData['meta']['to'] ?? 0,
                "last_page" => $flashSaleData['meta']['last_page'] ?? 0,
                "path" => $flashSaleData['meta']['path'] ?? "",
                "per_page" => $flashSaleData['meta']['per_page'] ?? 0,
                "total" => $flashSaleData['meta']['total'] ?? 0,
                "next_page_url" => $flashSaleData['links']['next'] ?? "",
                "prev_page_url" => $flashSaleData['links']['prev'] ?? "",
                "last_page_url" => $flashSaleData['links']['last'] ?? "",
                "first_page_url" => $flashSaleData['links']['first'] ?? "",
            ]);
        } catch (MarvelException $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }

    public function fetchFlashSales(Request $request)
    {
        $active = $request->active ?? null;
        $inactive = $request->inactive ?? null;
        $search = $request->search ?? null;
        $order = $request->order;
        $sortedBy = $request->sortedBy ?? 'asc';
        $query = $this->repository->modelQuery();
        if ($active) {
            $query = $query->valid();
        }
        if ($inactive) {
            $query = $query->invalid();
        }
        if ($search) {
            $query = $query->search('title', $search, app()->getLocale());
        }
        if ($order && in_array($order, ['id', 'title', 'slug', 'type', 'discount', 'status', 'start_date', 'end_date', 'created_at', 'updated_at'])) {
            $query = $query->orderBy($order, $sortedBy === 'desc' ? 'desc' : 'asc');
        }
        return $query;
    }

    /**
     * Store a newly created faq in storage.
     *
     * @param CreateFlashSaleRequest $request
     * @return mixed
     * @throws ValidatorException
     */
    public function store(CreateFlashSaleRequest $request)
    {
        try {
            $flashSale =  $this->repository->storeFlashSale($request);
            $flashSale->load('products');
            return $this->apiResponse(CREATE_FLASH_SALE_SUCCESSFULLY, 200, true, FlashSaleResource::make($flashSale));
        } catch (MarvelException $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }

    /**
     * @OA\Get(
     *     path="/flash-sale/{slug}",
     *     operationId="getFlashSaleBySlug",
     *     tags={"Flash Sales"},
     *     summary="Get Single Flash Sale",
     *     description="Retrieve details of a flash sale by its slug.",
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="language", in="query", required=false, @OA\Schema(type="string", default="en")),
     *     @OA\Response(
     *         response=200,
     *         description="Flash sale details retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/FlashSale")
     *     ),
     *     @OA\Response(response=404, description="Flash sale not found")
     * )
     */
    public function show(Request $request, $id)
    {
        try {

            //            $language = $request->language ?? DEFAULT_LANGUAGE;
            $flash_sale = $this->repository
                ->with('products')
                ->where('id', '=', $id)
                ->first();
            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, FlashSaleResource::make($flash_sale));
        } catch (MarvelException $e) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
    }


    /**
     * Update the specified flash sale
     *
     * @param UpdateFlashSaleRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateFlashSaleRequest $request, $id)
    {
        try {
            $request->merge(['id' => $id]);
            $flashSale =  $this->updateFlashSale($request);
            $flashSale->load('products');
            return $this->apiResponse(UPDATE_FLASH_SALE_SUCCESSFULLY, 200, true, FlashSaleResource::make($flashSale));
        } catch (MarvelException $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }

    /**
     * updateFlashSale
     *
     * @param Request $request
     * @return void
     */
    public function updateFlashSale(Request $request)
    {
        // $flash_sale_id = $this->repository->findOrFail($request['id']);
        // return $this->repository->updateFlashSale($request, $flash_sale_id);

        $id = $request->id;

        return $this->repository->updateFlashSale($request, $id);
    }

    /**
     * Remove the specified flash sale
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function reorder(Request $request)
    {
        try {
            $request->validate([
                'flash_sales' => 'required|array',
                'flash_sales.*' => 'required|exists:flash_sales,id',
            ]);
            $this->repository->reorder($request->flash_sales);

            return $this->apiResponse(FLASH_SALE_REORDERED_SUCCESSFULLY, 200, true);
        } catch (HttpException $e) {
            return $this->apiResponse(SOMETHING_WENT_WRONG, 500, false);
        }
    }

    public function destroy($id, Request $request)
    {
        $request->merge(['id' => $id]);
        if ($this->deleteFlashSale($request)) {
            return $this->apiResponse(DELETE_FLASH_SALE_SUCCESSFULLY, 200, true);
        }
        return $this->apiResponse(NOT_FOUND, 200, true);
    }

    public function deleteFlashSale(Request $request)
    {
        try {
            $user = $request->user();
            $flashSale = $this->repository->findOrFail($request->id);
            $flashSale->delete();
            return true;
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND, $e->getMessage());
        }
    }

    /**
     * getFlashSaleInfoByProductID
     *
     * @param Request $request
     * @return void
     */
    public function getFlashSaleInfoByProductID(Request $request)
    {
        try {
            $flash_sale_info = [];
            $product = Product::find($request->id);

            if ($product) {
                $flash_sale_info = $product->flash_sales;
            }

            return $flash_sale_info;
        } catch (MarvelException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG, $e->getMessage());
        }
    }

    /**
     * getProductsByFlashSale
     *
     * @param Request $request
     * @return void
     */
    public function getProductsByFlashSale(Request $request)
    {
        $limit = $request->per_page ?? $request->limit ?? 10;
        return $this->fetchProductsByFlashSale($request)->paginate($limit)->withQueryString();
    }

    /**
     * fetchProductsByFlashSale
     *
     * @param Request $request
     * @return object
     */
    public function fetchProductsByFlashSale(Request $request)
    {
        $flashSale = $this->repository->where('slug', '=', $request->slug)->firstOrFail();

        $order = $request->order;
        $sortedBy = $request->sortedBy ?? 'asc';

        $query = $flashSale->products();

        $sortableFields = ['id', 'title', 'slug', 'price', 'sale_price', 'quantity', 'created_at', 'updated_at'];

        if ($order && in_array($order, $sortableFields)) {
            $query = $query->orderBy($order, $sortedBy === 'desc' ? 'desc' : 'asc');
        }

        return $query;
    }
}
