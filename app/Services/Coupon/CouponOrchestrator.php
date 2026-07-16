<?php

namespace App\Services\Coupon;

use Illuminate\Support\Collection;
use Marvel\Database\Models\Coupon;
use Marvel\Database\Models\User;

class CouponOrchestrator
{
    public static function validateByCode(string $code, ?User $user = null, ?Collection $items = null): array
    {
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return self::invalid('not_found', __('coupon.not_found'));
        }

        return self::validate($coupon, $user, $items);
    }

    public static function validate(Coupon $coupon, ?User $user = null, ?Collection $items = null): array
    {
        if ($user) {
            $assignmentResult = CouponAssignmentValidator::validate($coupon, $user);

            if (!$assignmentResult['valid']) {
                return [
                    'valid' => false,
                    'reason' => $assignmentResult['reason'],
                    'message' => $assignmentResult['message'],
                    'coupon' => null,
                ];
            }

            if ($assignmentResult['has_assignments']) {
                $validation = CouponValidator::validate($coupon, null, $items);
            } else {
                $validation = CouponValidator::validate($coupon, $user, $items);
            }
        } else {
            $validation = CouponValidator::validate($coupon, null, $items);
        }

        if (!$validation['valid']) {
            return $validation;
        }

        return self::valid($coupon);
    }

    private static function valid(Coupon $coupon): array
    {
        return [
            'valid' => true,
            'reason' => null,
            'message' => null,
            'coupon' => $coupon,
        ];
    }

    private static function invalid(string $reason, string $message): array
    {
        return [
            'valid' => false,
            'reason' => $reason,
            'message' => $message,
            'coupon' => null,
        ];
    }
}
