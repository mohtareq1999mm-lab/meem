<?php

namespace App\Listeners;

use App\Events\PaymentSucceeded;
use App\Services\Invoice\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class GenerateInvoiceListener implements ShouldQueue
{
    public $queue = 'high';

    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        try {
            $this->invoiceService->generateFromOrder($event->order);
        } catch (\Throwable $e) {
            Log::error('Failed to generate invoice for order ' . $event->order?->id . ': ' . $e->getMessage());
            report($e);
        }
    }
}
