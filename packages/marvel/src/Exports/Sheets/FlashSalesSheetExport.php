<?php

namespace Marvel\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Marvel\Database\Models\Product;

class FlashSalesSheetExport implements FromCollection, WithTitle, WithHeadings
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'flash_sales';
    }

    public function collection()
    {
        $query = Product::query()->with('flash_sales');

        $products = $query->get();
        $rows = [];

        foreach ($products as $product) {
            foreach ($product->flash_sales as $flashSale) {
                $rows[] = [
                    'product_sku' => $product->sku,
                    'flash_sale_slug' => $flashSale->slug,
                ];
            }
        }

        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'product_sku',
            'flash_sale_slug',
        ];
    }
}
