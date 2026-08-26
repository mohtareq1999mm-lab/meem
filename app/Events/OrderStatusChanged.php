<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 0: emitted inside status-transition transactions; deferral keeps
 * notifications consistent with committed state.
 */
class OrderStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $order,
    ) {}
}
