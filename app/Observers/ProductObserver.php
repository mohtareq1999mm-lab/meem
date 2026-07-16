<?php

namespace App\Observers;

use App\Jobs\LogActivityJob;
use Illuminate\Support\Facades\Auth;
use Marvel\Database\Models\Product;

class ProductObserver
{
    public function created(Product $product): void
    {
        LogActivityJob::dispatch(
            get_class($product),
            $product->id,
            Auth::id(),
            'created',
            'products',
            __('activity.product_created'),
        );
    }

    public function updated(Product $product): void
    {
        $dirty = $product->getDirty();
        unset($dirty['updated_at']);

        if (empty($dirty)) {
            return;
        }

        $statusChanged = array_key_exists('status', $dirty);
        $hasOtherChanges = count($dirty) > ($statusChanged ? 1 : 0);

        if ($statusChanged) {
            $oldStatus = $product->getOriginal('status');
            $newStatus = $product->status;
            $description = $newStatus
                ? __('activity.product_activated')
                : __('activity.product_deactivated');
            $description = $description ?: ($newStatus ? 'Product activated' : 'Product deactivated');

            LogActivityJob::dispatch(
                get_class($product),
                $product->id,
                Auth::id(),
                'statusChanged',
                'products',
                $description,
                ['old' => ['status' => (string) $oldStatus], 'new' => ['status' => (string) $newStatus]],
            );
        }

        if ($hasOtherChanges) {
            $oldValues = [];
            $newValues = [];
            foreach ($dirty as $key => $newValue) {
                if ($key === 'status') continue;
                $oldValues[$key] = $product->getOriginal($key);
                $newValues[$key] = $newValue;
            }

            LogActivityJob::dispatch(
                get_class($product),
                $product->id,
                Auth::id(),
                'updated',
                'products',
                __('activity.product_updated'),
                ['old' => $oldValues, 'new' => $newValues],
            );
        }
    }

    public function deleted(Product $product): void
    {
        LogActivityJob::dispatch(
            get_class($product),
            $product->id,
            Auth::id(),
            'deleted',
            'products',
            __('activity.product_deleted'),
        );
    }

    public function restored(Product $product): void
    {
        LogActivityJob::dispatch(
            get_class($product),
            $product->id,
            Auth::id(),
            'restored',
            'products',
            __('activity.product_restored'),
        );
    }

    public function forceDeleted(Product $product): void
    {
        LogActivityJob::dispatch(
            get_class($product),
            $product->id,
            Auth::id(),
            'forceDeleted',
            'products',
            __('activity.product_force_deleted'),
        );
    }
}
