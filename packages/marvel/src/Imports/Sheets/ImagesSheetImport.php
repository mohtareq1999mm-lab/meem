<?php

namespace Marvel\Imports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithTitle;
use Marvel\Services\Import\ProductImportService;

class ImagesSheetImport implements ToCollection, WithTitle, WithHeadingRow, SkipsEmptyRows
{
    protected ProductImportService $service;

    public function __construct(ProductImportService $service)
    {
        $this->service = $service;
    }

    public function title(): string
    {
        return 'images';
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $sku = $row['product_sku'] ?? '';

            if (empty($sku)) {
                continue;
            }

            $images = [];

            if (!empty($row['image'])) {
                $images[] = $row['image'];
            } elseif (!empty($row['images'])) {
                $items = explode('|', $row['images']);
                foreach ($items as $item) {
                    $item = trim($item);
                    if (!empty($item)) {
                        $images[] = $item;
                    }
                }
            }

            foreach ($images as $imageUrl) {
                $this->service->processProductImage($sku, $imageUrl);
            }
        }
    }
}
