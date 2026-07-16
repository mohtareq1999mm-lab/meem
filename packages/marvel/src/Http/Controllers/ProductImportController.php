<?php

namespace Marvel\Http\Controllers;

use App\Http\Controllers\Controller;
use Marvel\Database\Models\Import;
use Marvel\Http\Requests\ProductImportRequest;
use Marvel\Jobs\ImportProductsJob;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Marvel\Enums\Permission;
use Marvel\Traits\ApiResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class ProductImportController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:' . Permission::CREATE_PRODUCT . '|' . Permission::SUPER_ADMIN);
    }

    protected function readSignalFile(int $importId, string $type): ?array
    {
        $path = storage_path("app/imports/{$type}_{$importId}.json");
        clearstatcache(true, $path);
        if (!file_exists($path)) {
            return null;
        }
        try {
            $contents = file_get_contents($path);
            return json_decode($contents, true) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function signalFileExists(int $importId, string $type): bool
    {
        $path = storage_path("app/imports/{$type}_{$importId}.json");
        clearstatcache(true, $path);
        return file_exists($path);
    }

    protected function writeSignalFile(int $importId, string $type, array $data = []): void
    {
        $dir = storage_path('app/imports');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        try {
            file_put_contents($dir . "/{$type}_{$importId}.json", json_encode($data), LOCK_EX);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function import(ProductImportRequest $request): JsonResponse
    {
        $file = $request->file('file');

        $filePath = $file->store('imports', 'public');

        $totalRows = $this->estimateRowCount($filePath);

        $import = Import::create([
            'type' => 'product',
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'status' => 'pending',
            'total_rows' => $totalRows,
            'created_by' => $request->user()->id,
        ]);

        $this->writeSignalFile($import->id, 'progress', [
            'processed_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
        ]);

        ImportProductsJob::dispatch($import->id);

        return $this->apiResponse(__('message.MESSAGE.IMPORT_STARTED_SUCCESSFULLY'), 202, true, [
            'import_id' => $import->id,
            'status' => $import->status,
        ]);
    }

    protected function estimateRowCount(string $filePath): int
    {
        try {
            $fullPath = Storage::disk('public')->path($filePath);
            if (!file_exists($fullPath)) {
                return 0;
            }

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($fullPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($fullPath);

            $total = 0;
            foreach ($spreadsheet->getSheetNames() as $name) {
                $sheet = $spreadsheet->getSheetByName($name);
                if ($sheet) {
                    $total += $sheet->getHighestDataRow();
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $reader);

            return $total;
        } catch (\Throwable $e) {
            report($e);
            return 0;
        }
    }

    public function status(int $id): JsonResponse
    {
        $import = Import::select(['id', 'status', 'total_rows', 'processed_rows', 'success_rows', 'failed_rows', 'errors'])->findOrFail($id);

        $cancelPending = $this->signalFileExists($id, 'cancel');
        $progressData = $this->readSignalFile($id, 'progress');

        $effectiveStatus = $cancelPending ? 'cancelling' : $import->status;

        if (in_array($import->status, ['completed', 'completed_with_errors'], true)) {
            $progress = 100.0;
        } elseif ($import->status === 'failed' || $import->status === 'cancelled') {
            $progress = $progressData['progress'] ?? 0.0;
        } elseif ($progressData && $import->status === 'processing' && !$cancelPending) {
            $progress = $progressData['progress'] ?? 99.0;
        } else {
            $progress = 0.0;
        }

        $processedRows = $progressData['processed_rows'] ?? $import->processed_rows;
        $successRows = $progressData['success_rows'] ?? $import->success_rows;
        $failedRows = $progressData['failed_rows'] ?? $import->failed_rows;

        return response()
            ->json([
                'status' => 200,
                'message' => __('message.MESSAGE.IMPORT_STATUS_FETCHED'),
                'success' => true,
                'data' => [
                    'id' => $import->id,
                    'status' => $effectiveStatus,
                    'total_rows' => $import->total_rows,
                    'processed_rows' => $processedRows,
                    'success_rows' => $successRows,
                    'failed_rows' => $failedRows,
                    'progress' => $progress,
                    'errors' => $import->errors,
                ],
            ])
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function downloadErrors(int $id): BinaryFileResponse|JsonResponse
    {
        $import = Import::findOrFail($id);

        if (empty($import->errors)) {
            return $this->apiResponse(__('message.MESSAGE.IMPORT_NO_ERRORS'), 404, false);
        }

        $filename = "failed_import_rows_{$id}.xlsx";

        $errors = collect($import->errors);

        $export = new class($errors) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            protected $errors;

            public function __construct($errors)
            {
                $this->errors = $errors;
            }

            public function collection()
            {
                return $this->errors->map(fn($e) => [
                    'sheet' => $e['sheet'] ?? '',
                    'row' => $e['row'] ?? '',
                    'sku' => $e['sku'] ?? '',
                    'error_message' => $e['error_message'] ?? '',
                ]);
            }

            public function headings(): array
            {
                return ['Sheet', 'Row', 'SKU', 'Error Message'];
            }
        };

        \Maatwebsite\Excel\Facades\Excel::store($export, $filename, 'local');

        return response()->download(
            storage_path("app/{$filename}"),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function cancel(int $id): JsonResponse
    {
        $import = Import::findOrFail($id);

        if (in_array($import->status, ['completed', 'completed_with_errors', 'failed', 'cancelled'], true)) {
            return $this->apiResponse(__('message.MESSAGE.IMPORT_CANNOT_CANCEL'), 409, false);
        }

        $this->writeSignalFile($import->id, 'cancel', ['cancelled_at' => now()->toIso8601String()]);

        try {
            Import::where('id', $import->id)->update([
                'status' => 'cancelled',
            ]);

            $import->refresh();
        } catch (QueryException $e) {
            report($e);
        }

        return $this->apiResponse(__('message.MESSAGE.IMPORT_CANCELLED_SUCCESSFULLY'), 200, true, [
            'import_id' => $import->id,
            'status' => 'cancelled',
        ]);
    }

    public function downloadSample(): BinaryFileResponse
    {
        $samplePath = base_path('packages/marvel/resources/products/product-import-sample.xlsx');

        if (!file_exists($samplePath)) {
            throw new FileNotFoundException($samplePath);
        }

        return response()->download(
            $samplePath,
            'product-import-sample.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
