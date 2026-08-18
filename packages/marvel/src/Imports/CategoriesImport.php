<?php

namespace Marvel\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithTitle;
use Marvel\Services\Import\CategoryImportService;

class CategoriesImport implements ToCollection, WithTitle, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    protected CategoryImportService $service;

    public function __construct(CategoryImportService $service)
    {
        $this->service = $service;
    }

    public function title(): string
    {
        return 'categories';
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows): void
    {
        $this->service->processRows($rows);
    }
}