<?php

namespace Marvel\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Marvel\Database\Models\Product;

class TagsSheetExport implements FromCollection, WithTitle, WithHeadings
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'tags';
    }

    /**
     * product_sku + tag_slug pairs — the exact contract consumed by
     * Imports\Sheets\TagsSheetImport on re-import.
     */
    public function collection()
    {
        $query = Product::query()->with('tags');

        if (isset($this->filters['category_id'])) {
            $query->whereHas('categories', fn($q) => $q->where('categories.id', $this->filters['category_id']));
        }

        $products = $query->get();
        $rows = [];

        foreach ($products as $product) {
            foreach ($product->tags as $tag) {
                $rows[] = [
                    'product_sku' => $product->sku,
                    'tag_slug' => $tag->slug,
                ];
            }
        }

        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'product_sku',
            'tag_slug',
        ];
    }
}
