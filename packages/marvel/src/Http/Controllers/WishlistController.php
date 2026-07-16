<?php


namespace Marvel\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Marvel\Database\Models\Product;
use Illuminate\Support\Facades\Auth;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\WishlistCreateRequest;
use Marvel\Database\Repositories\WishlistRepository;
use Marvel\Http\Resources\ProductResource;
use Marvel\Http\Resources\WishlistResource;
use Marvel\Traits\ApiResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @OA\Tag(name="Wishlist", description="User Wishlist management")
 */
class WishlistController extends CoreController
{
    use ApiResponse;
    public $repository;

    public function __construct(WishlistRepository $repository)
    {
        $this->repository = $repository;
    }


    /**
     * @OA\Get(
     *     path="/wishlists",
     *     operationId="getWishlists",
     *     tags={"Wishlist"},
     *     summary="List Wishlist Products",
     *     description="Get a paginated list of products in the current user's wishlist.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Wishlist products retrieved",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $limit = $request->limit ? $request->limit : 15;
        $wishlist = $this->repository->get();

        $productIds = $wishlist->pluck('product_id');
        $variantIds = $wishlist->pluck('product_variant_id')->filter();
        $products = Product::whereIn('id', $productIds)
            ->with([
                'variations' => function ($query) use ($variantIds) {
                    $query->whereIn('id', $variantIds);
                },
                'variations.attributeProducts.attributeValue.attribute',
            ])
            ->paginate($limit);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, WishlistResource::collection($products));
    }

    /**
     * @OA\Post(
     *     path="/wishlists",
     *     operationId="addToWishlist",
     *     tags={"Wishlist"},
     *     summary="Add Product to Wishlist",
     *     description="Add a single product to the current user's wishlist.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id"},
     *             @OA\Property(property="product_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Product added successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(WishlistCreateRequest $request)
    {
        try {
            $wishlist = $this->repository->storeWishlist($request);
            return $this->apiResponse(ADDED_TO_WISHLIST_SUCCESSFULLY, 200, true, $wishlist);
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * @OA\Post(
     *     path="/wishlists/toggle",
     *     operationId="toggleWishlist",
     *     tags={"Wishlist"},
     *     summary="Toggle Wishlist Item",
     *     description="Add or remove a product from the current user's wishlist.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id"},
     *             @OA\Property(property="product_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Wishlist toggled successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function toggle(WishlistCreateRequest $request)
    {
        try {
            $result = $this->repository->toggleWishlist($request);
            return $this->apiResponse($result ? ADDED_TO_WISHLIST_SUCCESSFULLY : REMOVED_FROM_WISHLIST_SUCCESSFULLY, 200, true);
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * @OA\Delete(
     *     path="/wishlists/{id}",
     *     operationId="removeFromWishlist",
     *     tags={"Wishlist"},
     *     summary="Remove Product from Wishlist",
     *     description="Remove a specific product from the current user's wishlist.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Product ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product removed successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function destroy(Request $request, $id)
    {
        try {
            $request->merge(['id' => $id]);
            if ( $request->query('variant_id')) {
                $request->merge(['variant_id' => $request->query('variant_id')]);
            }
            $deletedWishlist = $this->delete($request);
            return $this->apiResponse(REMOVED_FROM_WISHLIST_SUCCESSFULLY, 200, true, $deletedWishlist);
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    public function delete(Request $request)
    {
        try {
            if (!$request->user()) {
                throw new AuthorizationException(NOT_AUTHORIZED);
            }
            $product = Product::where('id', $request->id)->first();
            if (!$product) {
                throw new MarvelException(NOT_FOUND);
            }
            $wishlist = $this->repository
                ->where('product_id', $product->id)
                ->where('user_id', auth()->id())
                ->when($request->product_variant_id, function ($query) use ($request) {
                    $query->where('product_variant_id', $request->variant_id);
                }, function ($query) {
                    $query->whereNull('product_variant_id');
                })
                ->first();
            if (!empty($wishlist)) {
                return $wishlist->delete();
            }
            throw new MarvelException(SOMETHING_WENT_WRONG);
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * Check in wishlist product for authenticated user
     *
     * @param int $product_id
     * @return JsonResponse
     */
    public function in_wishlist(Request $request, $product_id): JsonResponse
    {
        $request->merge(['product_id' => $product_id]);

        return response()->json([
            'data' => $this->inWishlist($request),
        ]);
    }

    public function inWishlist(Request $request)
    {
        if (auth()->user() && !empty($this->repository->where('product_id', $request->product_id)->where('user_id', auth()->user()->id)->first())) {
            return true;
        }
        return false;
    }
}
