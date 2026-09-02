<?php

namespace Marvel\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Marvel\Imports\Sheets\BrandsSheetImport;
use Marvel\Services\Import\BrandImportService;

class BrandsImport implements WithMultipleSheets
{
    protected BrandImportService $service;

    public function __construct(BrandImportService $service)
    {
        $this->service = $service;
    }

    public function sheets(): array
    {
        return [
            'brands' => new BrandsSheetImport($this->service),
        ];
    }
}
