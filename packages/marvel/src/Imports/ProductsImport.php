<?php

namespace Marvel\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Marvel\Imports\Sheets\BrandsSheetImport;
use Marvel\Imports\Sheets\CategoriesSheetImport;
use Marvel\Imports\Sheets\FlashSalesSheetImport;
use Marvel\Imports\Sheets\ImagesSheetImport;
use Marvel\Imports\Sheets\ProductsSheetImport;
use Marvel\Imports\Sheets\ProductVariantsSheetImport;
use Marvel\Imports\Sheets\SlidersSheetImport;
use Marvel\Services\Import\ProductImportService;

class ProductsImport implements WithMultipleSheets
{
    protected ProductImportService $service;

    public function __construct(ProductImportService $service)
    {
        $this->service = $service;
    }

    public function sheets(): array
    {
        return [
            'products' => new ProductsSheetImport($this->service),
            'product_variants' => new ProductVariantsSheetImport($this->service),
            'images' => new ImagesSheetImport($this->service),
            'categories' => new CategoriesSheetImport($this->service),
            'brands' => new BrandsSheetImport($this->service),
            'flash_sales' => new FlashSalesSheetImport($this->service),
            'sliders' => new SlidersSheetImport($this->service),
        ];
    }
}
