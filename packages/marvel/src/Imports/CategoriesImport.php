<?php

namespace Marvel\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Marvel\Imports\Sheets\CategoriesSheetImport;
use Marvel\Services\Import\CategoryImportService;

class CategoriesImport implements WithMultipleSheets
{
    protected CategoryImportService $service;

    public function __construct(CategoryImportService $service)
    {
        $this->service = $service;
    }

    public function sheets(): array
    {
        return [
            'categories' => new CategoriesSheetImport($this->service),
        ];
    }
}