<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 120, 300];
    public $timeout = 120;

    public function __construct(
        public Invoice $invoice,
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        try {
            Log::info('PDF generation placeholder for invoice ' . $this->invoice->invoice_number);

            $this->invoice->update([
                'status' => 'ready',
                'pdf_generated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->invoice->update([
                'status' => 'failed',
                'last_generation_error' => substr($e->getMessage(), 0, 1000),
                'generation_attempts' => $this->invoice->generation_attempts + 1,
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('PDF generation failed for invoice ' . $this->invoice->invoice_number, [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'order_id' => $this->invoice->order_id,
            'error' => $e->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
