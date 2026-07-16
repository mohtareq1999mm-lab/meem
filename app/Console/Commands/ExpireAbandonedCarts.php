<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\General\CartInventoryService;

class ExpireAbandonedCarts extends Command
{
    protected $signature = 'cart:expire';
    protected $description = 'Expire abandoned carts and restore reserved stock';

    public function handle(CartInventoryService $cartInventoryService): int
    {
        $expiredCount = $cartInventoryService->expireCarts();
        $this->info("Expired {$expiredCount} abandoned cart(s).");

        return self::SUCCESS;
    }
}
