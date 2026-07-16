<?php

namespace Marvel\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\CouponRequest;
use Marvel\Http\Requests\UpdateCouponRequest;
use Marvel\Database\Repositories\CouponRepository;
use Prettus\Validator\Exceptions\ValidatorException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Marvel\Database\Models\Coupon;
use Marvel\Enums\Permission;
use Marvel\Http\Resources\CouponResource;
use Marvel\Traits\ApiResponse;
use Svg\Tag\Rect;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * @OA\Schema(
 *     schema="Coupon",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="SAVE50"),
 *     @OA\Property(property="description", type="string", example="Get 50% off your first order"),
 *     @OA\Property(property="image", type="object"),
 *     @OA\Property(property="type", type="string", example="percentage"),
 *     @OA\Property(property="amount", type="number", format="float", example=50.00),
 *     @OA\Property(property="minimum_cart_amount", type="number", format="float", example=100.00),
 *     @OA\Property(property="active_from", type="string", format="date-time"),
 *     @OA\Property(property="expire_at", type="string", format="date-time"),
 *     @OA\Property(property="is_approve", type="boolean", example=true),
 *     @OA\Property(property="language", type="string", example="en"),
 *     @OA\Property(property="shop_id", type="integer")
 * )
 */
class CouponController extends CoreController
{
    use ApiResponse;
    public $repository;

    public function __construct(CouponRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware("permission:" . Permission::VIEW_COUPONS, ["only" => ["index", "show"]]);
        $this->middleware("permission:" . Permission::CREATE_COUPON, ["only" => ["store"]]);
        $this->middleware("permission:" . Permission::UPDATE_COUPON, ["only" => ["update"]]);
        $this->middleware("permission:" . Permission::DELETE_COUPON, ["only" => ["destroy"]]);
    }

    /**
     * @OA\Get(
     *     path="/coupons",
     *     operationId="getCoupons",
     *     tags={"Coupons"},
     *     summary="List all coupons",
     *     description="Retrieve a paginated list of coupons with optional shop and language filtering.",
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="shop_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="language", in="query", @OA\Schema(type="string", default="en")),
     *     @OA\Response(
     *         response=200,
     *         description="List of coupons",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Coupon")),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $limit = $request->limit ?? 15;
        $query = $this->fetchCoupons($request);
        $coupons = $query->paginate($limit)->withQueryString();
        $couponData = CouponResource::collection($coupons)->response()->getData(true);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, [
            "data" => $couponData['data'] ?? [],
            "page" => $couponData['meta']['current_page'] ?? 0,
            "current_page" => $couponData['meta']['current_page'] ?? 0,
            "from" => $couponData['meta']['from'] ?? 0,
            "to" => $couponData['meta']['to'] ?? 0,
            "last_page" => $couponData['meta']['last_page'] ?? 0,
            "path" => $couponData['meta']['path'] ?? "",
            "per_page" => $couponData['meta']['per_page'] ?? 0,
            "total" => $couponData['meta']['total'] ?? 0,
            "next_page_url" => $couponData['links']['next'] ?? "",
            "prev_page_url" => $couponData['links']['prev'] ?? "",
            "last_page_url" => $couponData['links']['last'] ?? "",
            "first_page_url" => $couponData['links']['first'] ?? "",
        ]);
    }
    public function fetchCoupons(Request $request)
    {
        $active = $request->active ?? null;
        $Inactive = $request->inactive ?? null;
        $search = $request->search ?? null;
        $order = $request->order;
        $sortedBy = $request->sortedBy ?? 'asc';
        $query = $this->repository->modelQuery();
        if ($active) {
            $query = $query->valid();
        }
        if ($Inactive) {
            $query = $query->invalid();
        }
        if ($search) {
            $query = $query->search('name', $search, app()->getLocale())
                ->orWhere('code', 'like', "%$search%");
        }
        if ($order && in_array($order, ['id', 'code', 'name', 'discount', 'discount_type', 'start_date', 'end_date', 'limiter', 'used', 'status', 'created_at', 'updated_at'])) {
            $query = $query->orderBy($order, $sortedBy === 'desc' ? 'desc' : 'asc');
        }
        return $query;
    }
    public function store(CouponRequest $request)
    {
        try {
            $coupon =  $this->repository->storeCoupon($request);
            return $this->apiResponse(CREATED_COUPON_SUCCESSFULLY, 201, true, CouponResource::make($coupon));
        } catch (MarvelException $e) {
            return $this->apiResponse(COULD_NOT_CREATE_THE_RESOURCE, 400, false);
        }
    }

    /**
     * @OA\Get(
     *     path="/coupons/{slug_or_id}",
     *     operationId="getCouponBySlugOrId",
     *     tags={"Coupons"},
     *     summary="Get single coupon",
     *     description="Retrieve details of a coupon by its code/slug or incremental ID.",
     *     @OA\Parameter(name="slug_or_id", in="path", required=true, description="Coupon code or ID", @OA\Schema(type="string")),
     *     @OA\Parameter(name="language", in="query", description="Language code", @OA\Schema(type="string", default="en")),
     *     @OA\Response(response=200, description="Coupon found", @OA\JsonContent(ref="#/components/schemas/Coupon")),
     *     @OA\Response(response=404, description="Coupon not found")
     * )
     */
    public function show(Request $request, $id)
    {
        try {

            $coupon =  $this->repository->where('id', $id)->orWhere('code', $id)->firstOrFail();
            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, CouponResource::make($coupon));
        } catch (Throwable $e) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
    }
    /**
     * @OA\Post(
     *     path="/coupons/verify",
     *     operationId="verifyCoupon",
     *     tags={"Coupons"},
     *     summary="Verify coupon code",
     *     description="Check if a coupon code is valid for the current sub_total.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "sub_total"},
     *             @OA\Property(property="code", type="string", example="SUMMER24"),
     *             @OA\Property(property="sub_total", type="number", example=100.00)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Coupon verification result"),
     *     @OA\Response(response=404, description="Coupon not found or invalid")
     * )
     */
    // public function verify(Request $request)
    // {
    //     $request->validate([
    //         'code' => 'required|string',
    //         'sub_total' => 'required|numeric',
    //     ]);
    //     try {
    //         return $this->repository->verifyCoupon($request);
    //     } catch (MarvelException $e) {
    //         throw new MarvelException(NOT_FOUND);
    //     }
    // }

    /**
     * @OA\Put(
     *     path="/coupons/{id}",
     *     operationId="updateCoupon",
     *     tags={"Coupons"},
     *     summary="Update coupon",
     *     description="Update details of an existing coupon. Accessible by Staff, Owners, or Admins.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="amount", type="number"),
     *             @OA\Property(property="expire_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Coupon updated", @OA\JsonContent(ref="#/components/schemas/Coupon")),
     *     @OA\Response(response=404, description="Coupon not found")
     * )
     */
    public function update(UpdateCouponRequest $request, $id)
    {
        try {
            $coupon = $this->repository->updateCoupon($id, $request);
            return $this->apiResponse(UPDATED_COUPON_SUCCESSFULLY, 200, true, CouponResource::make($coupon));
        } catch (MarvelException $th) {
            return $this->apiResponse(COULD_NOT_UPDATE_THE_RESOURCE, 400, false);
        }
    }

    /**
     * @OA\Delete(
     *     path="/coupons/{id}",
     *     operationId="deleteCoupon",
     *     tags={"Coupons"},
     *     summary="Delete coupon",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Coupon deleted successfully"),
     *     @OA\Response(response=404, description="Coupon not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $this->repository->findOrFail($id)->delete();
            return $this->apiResponse(DELETED_COUPON_SUCCESSFULLY, 200, true);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }


    public function addCouponToCart(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string|exists:coupons,code',
            ]);
            $coupon = $this->repository->addCouponToCart($request->code);
            return $this->apiResponse(COUPON_ADDED_TO_CART_SUCCESSFULLY, 200, true);
        } catch (MarvelException $e) {
            return $this->apiResponse(COULD_NOT_ADD_COUPON_TO_CART, 400, false);
        }
    }

    /**
     * @OA\Post(
     *     path="/approve-coupon",
     *     operationId="approveCoupon",
     *     tags={"Content Moderation"},
     *     summary="Approve Vendor Coupon",
     *     description="Approve a vendor-created coupon for public use. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id", type="integer", example=5, description="Coupon ID to approve")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Coupon approved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN"),
     *     @OA\Response(response=404, description="Coupon not found")
     * )
     */
    public function approveCoupon(Request $request)
    {
        try {
            $coupon = $this->repository->findOrFail($request->id);
            $coupon->update(['is_approve' => true]);
            return $this->apiResponse(UPDATED_COUPON_SUCCESSFULLY, 200, true, CouponResource::make($coupon));
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * @OA\Post(
     *     path="/disapprove-coupon",
     *     operationId="disapproveCoupon",
     *     tags={"Content Moderation"},
     *     summary="Disapprove Vendor Coupon",
     *     description="Reject/disapprove a vendor coupon. Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id", type="integer", example=5, description="Coupon ID to disapprove")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Coupon disapproved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN"),
     *     @OA\Response(response=404, description="Coupon not found")
     * )
     */
    public function disApproveCoupon(Request $request)
    {
        try {
            $coupon = $this->repository->findOrFail($request->id);
            $coupon->is_approve = false;
            $coupon->save();
            return $this->apiResponse(UPDATED_COUPON_SUCCESSFULLY, 200, true, CouponResource::make($coupon));
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }
}
