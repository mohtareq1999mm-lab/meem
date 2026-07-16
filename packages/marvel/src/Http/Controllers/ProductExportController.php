<?php

namespace Marvel\Http\Controllers;

use App\Http\Controllers\Controller;
use Marvel\Exports\ProductsExport;
use Marvel\Http\Requests\ProductExportRequest;
use Carbon\Carbon;
use Marvel\Enums\Permission;
use Marvel\Traits\ApiResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductExportController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:' . Permission::VIEW_PRODUCTS . '|' . Permission::SUPER_ADMIN);
    }

    public function export(ProductExportRequest $request): BinaryFileResponse
    {
        $filters = $request->only(['status', 'product_type', 'category_id', 'brand_id']);

        $filename = 'products-export-' . Carbon::now()->format('Y-m-d-His') . '.xlsx';

        $export = new ProductsExport($filters);

        return $export->download($filename);
    }
}
