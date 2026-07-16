<?php

namespace Marvel\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Marvel\Database\Models\Product;

class CategoriesSheetExport implements FromCollection, WithTitle, WithHeadings
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'categories';
    }

    public function collection()
    {
        $query = Product::query()->with('categories');

        if (isset($this->filters['category_id'])) {
            $query->whereHas('categories', fn($q) => $q->where('category_id', $this->filters['category_id']));
        }

        $products = $query->get();
        $rows = [];

        foreach ($products as $product) {
            foreach ($product->categories as $category) {
                $rows[] = [
                    'product_sku' => $product->sku,
                    'category_slug' => $category->slug,
                ];
            }
        }

        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'product_sku',
            'category_slug',
        ];
    }
}
