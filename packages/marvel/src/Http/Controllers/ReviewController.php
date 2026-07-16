<?php


namespace Marvel\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Review;
use Marvel\Database\Repositories\ReviewRepository;
use Marvel\Database\Repositories\SettingsRepository;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\FeedbackCreateRequest;
use Marvel\Http\Requests\ReviewCreateRequest;
use Marvel\Http\Requests\ReviewUpdateRequest;
use Prettus\Validator\Exceptions\ValidatorException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
    public $repository;
    public $settingsRepository;

    public function __construct(ReviewRepository $repository, SettingsRepository $settingsRepository)
    {
        $this->repository = $repository;
        $this->settingsRepository = $settingsRepository;
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
        $limit = $request->limit ? $request->limit : 15;
        if (isset($request['product_id']) && !empty($request['product_id'])) {
            if (null !== $request->user()) {
                $request->user()->id; // need another way to force login
            }
            return $this->repository->where('product_id', $request['product_id'])->paginate($limit);
        }
        return $this->repository->paginate($limit);
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
        $setting = $this->settingsRepository->first();
        $product_id = $request['product_id'];
        $order_id = $request['order_id'];
        try {
            $hasProductInOrder = Order::where('id', $order_id)->whereHas('products', function ($q) use ($product_id) {
                $q->where('product_id', $product_id);
            })->exists();

            if (false === $hasProductInOrder) {
                throw new ModelNotFoundException(NOT_FOUND);
            }

            $user_id = $request->user()->id;
            $request['user_id'] = $user_id;

            // check if the review is following conventional system.
            if (!empty($setting->options['reviewSystem']['value']) && $setting->options['reviewSystem']['value'] === 'review_single_time') {

                // find out if any review exists or not
                if (isset($request['variation_option_id']) && !empty($request['variation_option_id'])) {
                    $review = $this->repository->where('user_id', $user_id)->where('order_id', $order_id)->where('product_id', $product_id)->where('shop_id', $request['shop_id'])->where('variation_option_id', $request['variation_option_id'])->get();
                } else {
                    $review = $this->repository->where('user_id', $user_id)->where('order_id', $order_id)->where('product_id', $product_id)->where('shop_id', $request['shop_id'])->get();
                }

                if (count($review)) {
                    throw new HttpException(400, ALREADY_GIVEN_REVIEW_FOR_THIS_PRODUCT);
                }
            }

            return $this->repository->storeReview($request);
        } catch (MarvelException $e) {
            throw new MarvelException(ALREADY_GIVEN_REVIEW_FOR_THIS_PRODUCT);
        }
    }

    /**
     * @OA\Get(
     *     path="/reviews/{id}",
     *     operationId="getReview",
     *     tags={"Reviews"},
     *     summary="Get Review Details",
     *     description="Get a singel review details",
     *     @OA\Parameter(name="id", in="path", required=true, description="Review ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review details", @OA\JsonContent(ref="#/components/schemas/Review")),
     *     @OA\Response(response=404, description="Not found")
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
            return $this->updateReview($request);
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
            return $this->repository->findOrFail($id)->delete();
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }
}
