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

        if (isset($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (isset($this->filters['product_type'])) {
            $query->where('product_type', $this->filters['product_type']);
        }

        if (isset($this->filters['item_type']) && in_array($this->filters['item_type'], \Marvel\Enums\ItemType::getValues(), true)) {
            $query->where('item_type', $this->filters['item_type']);
        }

        if (isset($this->filters['category_id'])) {
            $query->whereHas('categories', fn($q) => $q->where('category_id', $this->filters['category_id']));
        }

        if (isset($this->filters['brand_id'])) {
            $query->whereHas('brands', fn($q) => $q->where('brand_id', $this->filters['brand_id']));
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
