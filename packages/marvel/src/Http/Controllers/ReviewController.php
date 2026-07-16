<?php


namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Marvel\Database\Repositories\ReviewRepository;
use Marvel\Enums\Permission;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\ReviewCreateRequest;
use Marvel\Http\Requests\ReviewUpdateRequest;
use Marvel\Http\Resources\ReviewResource;
use Marvel\Traits\ApiResponse;

/**
 * @OA\Tag(name="Reviews", description="Product Reviews [CUSTOMER, PUBLIC]")
 *
 * @OA\Schema(
 *     schema="Review",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="rating", type="integer", example=5),
 *     @OA\Property(property="comment", type="string", example="Great product!"),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=10),
 *     @OA\Property(property="shop_id", type="integer", example=2),
 *     @OA\Property(property="feedbacks", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="images", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 */
class ReviewController extends CoreController
{
    use ApiResponse;
    public $repository;

    public function __construct(ReviewRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('permission:' . Permission::APPROVE_REVIEWS, ['only' => ['toggleApproveReview']]);
        $this->middleware('permission:' . Permission::DELETE_REVIEWS, ['only' => ['destroy']]);
    }


    /**
     * @OA\Get(
     *     path="/reviews",
     *     operationId="getReviews",
     *     tags={"Reviews"},
     *     summary="List Reviews",
     *     description="List reviews for a product.",
     *     @OA\Parameter(name="product_id", in="query", required=false, description="Filter by Product ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="limit", in="query", description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Reviews retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Review")),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);
        $limit = $request->limit ? $request->limit : 15;

        $data =  $this->repository->where('product_id', $request['product_id'])->paginate($limit);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ReviewResource::collection($data));
    }

    /**
     * @OA\Post(
     *     path="/reviews",
     *     operationId="createReview",
     *     tags={"Reviews"},
     *     summary="Create Review",
     *     description="Submit a review for a product. Requires CUSTOMER permission.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rating", "product_id", "shop_id", "order_id"},
     *             @OA\Property(property="rating", type="integer", example=5),
     *             @OA\Property(property="comment", type="string", example="Great!"),
     *             @OA\Property(property="product_id", type="integer", example=10),
     *             @OA\Property(property="shop_id", type="integer", example=2),
     *             @OA\Property(property="order_id", type="integer", example=100),
     *             @OA\Property(property="photos", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Review created", @OA\JsonContent(ref="#/components/schemas/Review")),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=400, description="Bad Request or Already Reviewed")
     * )
     */
    public function store(ReviewCreateRequest $request)
    {
        try {
            $review = $this->repository->storeReview($request);
            return $this->apiResponse(REVIEW_CREATED_SUCCESSFULLY, 200, true,  ReviewResource::make($review));
        } catch (MarvelException $e) {
            throw new MarvelException(ALREADY_GIVEN_REVIEW_FOR_THIS_PRODUCT);
        }
    }


    public function show($id)
    {
        try {
            $review = $this->repository->findOrFail($id);
            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, ReviewResource::make($review));
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * @OA\Put(
     *     path="/reviews/{id}",
     *     operationId="updateReview",
     *     tags={"Reviews"},
     *     summary="Update Review",
     *     description="Update a review. Requires permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Review ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *              @OA\Property(property="rating", type="integer"),
     *              @OA\Property(property="comment", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Review updated", @OA\JsonContent(ref="#/components/schemas/Review")),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(ReviewUpdateRequest $request, $id)
    {
        $request->merge(["id" => $id]);
        try {
            $review = $this->updateReview($request);
            return $this->apiResponse(REVIEW_UPDATED_SUCCESSFULLY, 200, true, ReviewResource::make($review));
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    public function updateReview(ReviewUpdateRequest $request)
    {
        $id = $request->id;
        return $this->repository->updateReview($request, $id);
    }

    /**
     * @OA\Delete(
     *     path="/reviews/{id}",
     *     operationId="deleteReview",
     *     tags={"Reviews"},
     *     summary="Delete Review",
     *     description="Delete a review. Requires permission.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Review ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review deleted"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy($id)
    {
        try {
            $review = $this->repository->findOrFail($id);
            $review->delete();
            return $this->apiResponse(REVIEW_DELETED_SUCCESSFULLY, 200, true);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    public function toggleApproveReview($id)
    {
        try {
            $review = $this->repository->toggleApprove($id);
            return $this->apiResponse(REVIEW_UPDATED_SUCCESSFULLY, 200, true, ReviewResource::make($review));
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }
}
