<?php

namespace Marvel\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Marvel\Database\Models\Product;

class SlidersSheetExport implements FromCollection, WithTitle, WithHeadings
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'sliders';
    }

    public function collection()
    {
        $query = Product::query()->with('sliders');

        $products = $query->get();
        $rows = [];

        foreach ($products as $product) {
            foreach ($product->sliders as $slider) {
                $rows[] = [
                    'product_sku' => $product->sku,
                    'slider_slug' => $slider->slug,
                ];
            }
        }

        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'product_sku',
            'slider_slug',
        ];
    }
}
