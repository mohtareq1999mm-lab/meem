<?php

namespace App\Services\Digital;

use App\Events\DigitalProductsDelivered;
use App\Models\DigitalAsset;
use App\Models\DigitalEntitlement;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Order;
use Throwable;

class DigitalFulfillmentService
{
    /**
     * Fulfil every DIGITAL order line of an order exactly once.
     *
     * Idempotency: UNIQUE(order_product_id) + firstOrCreate — running this
     * twice (event replay, queue retry) can never duplicate entitlements.
     */
    public function fulfillOrder(Order $order): void
    {
        if (!config('digital.enabled', true)) {
            return;
        }

        $order->loadMissing('orderItems.product');

        $digitalItems = $order->orderItems->filter(
            fn ($item) => ($item->item_type ?? \Marvel\Enums\ItemType::PHYSICAL) === \Marvel\Enums\ItemType::DIGITAL
        );

        if ($digitalItems->isEmpty()) {
            return;
        }

        $deliveredEntitlements = collect();

        DB::transaction(function () use ($digitalItems, $order, &$deliveredEntitlements) {
            foreach ($digitalItems as $item) {
                $entitlement = DigitalEntitlement::firstOrCreate(
                    ['order_product_id' => $item->id],
                    [
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'status' => DigitalEntitlement::STATUS_PENDING,
                        'download_limit' => max(1, (int) config('digital.download_limit', 5)),
                    ]
                );

                // Grant every active asset of the purchased product.
                $assetIds = DigitalAsset::query()
                    ->where('product_id', $item->product_id)
                    ->pluck('id');

                $entitlement->assets()->syncWithoutDetaching($assetIds);

                if ($entitlement->status === DigitalEntitlement::STATUS_PENDING) {
                    $entitlement->update([
                        'status' => DigitalEntitlement::STATUS_DELIVERED,
                        'delivered_at' => now(),
                    ]);
                }

                $deliveredEntitlements->push($entitlement);
            }
        });

        try {
            DigitalProductsDelivered::dispatch($order->fresh(), $deliveredEntitlements);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Revoke access for a delivered digital entitlement (D7). The next
     * signed download attempt fails the status check immediately.
     */
    public function revoke(DigitalEntitlement $entitlement): void
    {
        if ($entitlement->status !== DigitalEntitlement::STATUS_REVOKED) {
            $entitlement->update([
                'status' => DigitalEntitlement::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);
        }
    }
}
