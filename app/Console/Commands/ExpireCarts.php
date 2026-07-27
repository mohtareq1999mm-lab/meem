<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\General\CartInventoryService;

class ExpireCarts extends Command
{
    protected $signature = 'carts:expire';
    protected $description = 'Expire carts that have exceeded their reservation TTL';

    public function handle(CartInventoryService $cartInventoryService): int
    {
        $expiredCount = $cartInventoryService->expireCarts();

        $this->info("Expired {$expiredCount} cart(s).");

        return self::SUCCESS;
    }
}
