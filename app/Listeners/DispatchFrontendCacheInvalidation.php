<?php

declare(strict_types=1);

namespace App\Listeners;

use App\DTOs\FrontendCachePayload;
use App\Events\FrontendCacheInvalidation;
use App\Jobs\SendFrontendWebhookJob;

class DispatchFrontendCacheInvalidation
{
    public function handle(FrontendCacheInvalidation $event): void
    {
        $payload = new FrontendCachePayload(
            resource: $event->resource,
        );

        SendFrontendWebhookJob::dispatchSync($payload);
    }
}
