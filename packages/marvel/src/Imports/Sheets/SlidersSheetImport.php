<?php

namespace Marvel\Imports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithTitle;
use Marvel\Services\Import\ProductImportService;

class SlidersSheetImport implements ToCollection, WithTitle, WithHeadingRow, SkipsEmptyRows
{
    protected ProductImportService $service;

    public function __construct(ProductImportService $service)
    {
        $this->service = $service;
    }

    public function title(): string
    {
        return 'sliders';
    }

    public function collection(Collection $rows): void
    {
        $grouped = $rows->groupBy(fn($row) => $row['product_sku'] ?? '');

        foreach ($grouped as $sku => $sliderRows) {
            if (empty($sku)) {
                continue;
            }
            $slugs = $sliderRows->pluck('slider_slug')->filter()->toArray();
            $this->service->syncSliders($sku, $slugs);
        }
    }
}
