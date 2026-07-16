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
use Marvel\Enums\Permission;
use Marvel\Http\Resources\CouponResource;
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
    public $repository;

    public function __construct(CouponRepository $repository)
    {
        $this->repository = $repository;
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
        $language = $request->language ?? DEFAULT_LANGUAGE;
        $coupons = $this->fetchCoupons($request, $language)->paginate($limit)->withQueryString();
        return formatAPIResourcePaginate(CouponResource::collection($coupons)->response()->getData(true));
    }
    public function fetchCoupons(Request $request)
    {
        try {
            $language = $request->language ?? DEFAULT_LANGUAGE;
            $user = $request->user();
            $query = $this->repository->whereNotNull('id')->with('shop');
            if ($user) {
                switch (true) {
                    case $user->hasPermissionTo(Permission::SUPER_ADMIN):
                        $query->where('language', $language);
                        break;

                    case $user->hasPermissionTo(Permission::STORE_OWNER):
                        $this->repository->hasPermission($user, $request->shop_id)
                            ? $query->where('shop_id', $request->shop_id)
                            : $query->where('user_id', $user->id)->whereIn('shop_id', $user->shops->pluck('id'));
                        $query->where('language', $language);
                        break;

                    case $user->hasPermissionTo(Permission::STAFF):
                        $query->where('shop_id', $request->shop_id)->where('language', $language);
                        break;

                    default:
                        $query->where('language', $language);
                        break;
                }
            } else {
                if ($request->shop_id) {
                    $query->where('shop_id', $request->shop_id);
                }
                $query->where('language', $language);
            }
            return $query;
        } catch (MarvelException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG, $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/coupons",
     *     operationId="storeCoupon",
     *     tags={"Coupons"},
     *     summary="Create new coupon",
     *     description="Create a new coupon for a shop. Accessible by Store Owners and Super Admins.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "type", "amount", "shop_id"},
     *             @OA\Property(property="code", type="string", example="SUMMER24"),
     *             @OA\Property(property="type", type="string", enum={"percentage", "fixed", "free_shipping"}),
     *             @OA\Property(property="amount", type="number", example=20),
     *             @OA\Property(property="shop_id", type="integer", example=1),
     *             @OA\Property(property="minimum_cart_amount", type="number", example=100),
     *             @OA\Property(property="active_from", type="string", format="date-time"),
     *             @OA\Property(property="expire_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Coupon created", @OA\JsonContent(ref="#/components/schemas/Coupon")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(CouponRequest $request)
    {
        try {
            return $this->repository->storeCoupon($request);
        } catch (MarvelException $e) {
            throw new MarvelException(COULD_NOT_CREATE_THE_RESOURCE, $e->getMessage());
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
    public function show(Request $request, $params)
    {
        try {
            $language = $request->language ?? DEFAULT_LANGUAGE;
            try {
                if (is_numeric($params)) {
                    $params = (int) $params;
                    return $this->repository->where('id', $params)->firstOrFail();
                }
                return $this->repository->where('code', $params)->where('language', $language)->firstOrFail();
            } catch (Throwable $e) {
                throw new ModelNotFoundException(NOT_FOUND);
            }
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
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
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'sub_total' => 'required|numeric',
        ]);
        try {
            return $this->repository->verifyCoupon($request);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

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
            $request->id = $id;
            return $this->updateCoupon($request);
        } catch (MarvelException $th) {
            throw new MarvelException();
        }
    }

    /**
     * Undocumented function
     *
     * @param  $request
     * @return void
     */
    public function updateCoupon(Request $request)
    {
        $id = $request->id;
        $dataArray = $this->repository->getDataArray();

        try {
            $code = $this->repository->findOrFail($id);

            if ($request->has('language') && $request['language'] === DEFAULT_LANGUAGE) {
                $updatedCoupon = $request->only($dataArray);
                if (!$request->user()->hasPermissionTo(Permission::SUPER_ADMIN)) {
                    $updatedCoupon['is_approve'] = false;
                }
                $nonTranslatableKeys = ['language', 'image', 'description', 'id'];
                foreach ($nonTranslatableKeys as $key) {
                    if (isset($updatedCoupon[$key])) {
                        unset($updatedCoupon[$key]);
                    }
                }
                $this->repository->where('code', $code->code)->update($updatedCoupon);
            }

            return $this->repository->update($request->only($dataArray), $id);
        } catch (Exception $e) {
            throw new HttpException(404, NOT_FOUND);
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
            return $this->repository->findOrFail($id)->delete();
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
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
            if (!$request->user()->hasPermissionTo(Permission::SUPER_ADMIN)) {
                throw new MarvelException(NOT_AUTHORIZED);
            }
            $coupon = $this->repository->findOrFail($request->id);
            $coupon->update(['is_approve' => true]);
            return $coupon;
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
            if (!$request->user()->hasPermissionTo(Permission::SUPER_ADMIN)) {
                throw new MarvelException(NOT_AUTHORIZED);
            }
            $coupon = $this->repository->findOrFail($request->id);
            $coupon->is_approve = false;
            $coupon->save();
            return $coupon;
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }
}
