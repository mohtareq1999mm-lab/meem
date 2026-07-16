<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\Coupons\CouponResource;
use App\Services\General\CouponService;
use Marvel\Traits\ApiResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    use ApiResponse;
    protected $couponService;
    public function __construct(CouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    public function index(Request $request)
    {
        $coupons = $this->couponService->getCoupons($request);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, CouponResource::collection($coupons));
    }

    public function applyCoupon(Request $request)
    {
        $code = $request->get('code');
        $result = $this->couponService->addCouponToCart($code);

        if ($result === null) {
            return $this->apiResponse(INVALID_COUPON_CODE_OR_COUPON_CANNOT_BE_APPLIED_OR_COUPON_USAGE_LIMIT_REACHED, 400, false);
        }

        if (isset($result['already_applied']) && $result['already_applied']) {
            return $this->apiResponse(COUPON_ALREADY_APPLIED, 200, true, $result);
        }

        return $this->apiResponse(COUPON_APPLIED_SUCCESSFULLY, 200, true, $result);
    }
}
