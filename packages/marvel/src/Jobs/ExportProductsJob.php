<?php

namespace Marvel\Jobs;

use Marvel\Exports\ProductsExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $export = new ProductsExport($this->filters);

        $filename = 'products-export-' . now()->format('Y-m-d-His') . '.xlsx';

        $export->store($filename, 'public');
    }
}
