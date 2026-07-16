<?php

namespace App\Observers;

use App\Jobs\LogActivityJob;
use Illuminate\Support\Facades\Auth;
use Marvel\Database\Models\FlashSale;

class FlashSaleObserver
{
    public function created(FlashSale $flashSale): void
    {
        LogActivityJob::dispatch(
            get_class($flashSale),
            $flashSale->id,
            Auth::id(),
            'created',
            'flash_sales',
            __('activity.flash_sale_created'),
        );
    }

    public function updated(FlashSale $flashSale): void
    {
        $dirty = $flashSale->getDirty();
        unset($dirty['updated_at']);

        if (empty($dirty)) {
            return;
        }

        $statusChanged = array_key_exists('status', $dirty);
        $hasOtherChanges = count($dirty) > ($statusChanged ? 1 : 0);

        if ($statusChanged) {
            $oldStatus = $flashSale->getOriginal('status');
            $newStatus = $flashSale->status;
            $description = $newStatus
                ? __('activity.flash_sale_activated')
                : __('activity.flash_sale_deactivated');
            $description = $description ?: ($newStatus ? 'Flash sale activated' : 'Flash sale deactivated');

            LogActivityJob::dispatch(
                get_class($flashSale),
                $flashSale->id,
                Auth::id(),
                'statusChanged',
                'flash_sales',
                $description,
                ['old' => ['status' => (string) $oldStatus], 'new' => ['status' => (string) $newStatus]],
            );
        }

        if ($hasOtherChanges) {
            $oldValues = [];
            $newValues = [];
            foreach ($dirty as $key => $newValue) {
                if ($key === 'status') continue;
                $oldValues[$key] = $flashSale->getOriginal($key);
                $newValues[$key] = $newValue;
            }

            LogActivityJob::dispatch(
                get_class($flashSale),
                $flashSale->id,
                Auth::id(),
                'updated',
                'flash_sales',
                __('activity.flash_sale_updated'),
                ['old' => $oldValues, 'new' => $newValues],
            );
        }
    }

    public function deleted(FlashSale $flashSale): void
    {
        LogActivityJob::dispatch(
            get_class($flashSale),
            $flashSale->id,
            Auth::id(),
            'deleted',
            'flash_sales',
            __('activity.flash_sale_deleted'),
        );
    }

    public function restored(FlashSale $flashSale): void
    {
        LogActivityJob::dispatch(
            get_class($flashSale),
            $flashSale->id,
            Auth::id(),
            'restored',
            'flash_sales',
            __('activity.flash_sale_restored'),
        );
    }

    public function forceDeleted(FlashSale $flashSale): void
    {
        LogActivityJob::dispatch(
            get_class($flashSale),
            $flashSale->id,
            Auth::id(),
            'forceDeleted',
            'flash_sales',
            __('activity.flash_sale_force_deleted'),
        );
    }
}
