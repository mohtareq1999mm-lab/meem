<?php

namespace Marvel\Jobs;

use App\Events\FileOperationEvent;
use App\Traits\BroadcastsFileOperationProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Marvel\Database\Models\Import;
use Marvel\Exceptions\ImportCancelledException;
use Marvel\Imports\BrandsImport;
use Marvel\Services\Import\BrandImportService;
use Throwable;

class ImportBrandsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use BroadcastsFileOperationProgress;

    public int $tries = 3;

    public int $timeout = 1500;

    public array $backoff = [60, 120, 240];

    protected int $importId;

    public function __construct(int $importId)
    {
        $this->importId = $importId;
        $this->onQueue('meem-bulk');
    }

    protected function removeSignalFile(string $type): void
    {
        $path = storage_path("app/imports/{$type}_{$this->importId}.json");

        if (file_exists($path)) {
            @unlink($path);
        }
    }

    protected function cancelSignalFileExists(): bool
    {
        $path = storage_path("app/imports/cancel_{$this->importId}.json");
        clearstatcache(true, $path);

        return file_exists($path);
    }

    protected function cleanSignals(): void
    {
        $this->removeSignalFile('cancel');
        $this->removeSignalFile('progress');
    }

    public function handle(): void
    {
        $import = Import::select(['id', 'status', 'file_path', 'file_name'])->findOrFail($this->importId);

        if ($import->status === 'cancelled' || $this->cancelSignalFileExists()) {
            Storage::disk('public')->delete($import->file_path);
            $this->removeSignalFile('cancel');

            return;
        }

        if (in_array($import->status, ['completed', 'completed_with_errors', 'failed'], true)) {
            return;
        }

        $import->update([
            'status' => 'processing',
            'processed_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
        ]);

        $filePath = Storage::disk('public')->path($import->file_path);

        $service = new BrandImportService($this->importId);
        $service->writeExplicitProgress(1.0);

        $totalRows = $this->countRows();

        if ($import->total_rows !== $totalRows) {
            $import->update(['total_rows' => $totalRows]);
        }

        $service->writeExplicitProgress(2.0);

        try {
            $importObj = new BrandsImport($service);

            $readerType = \Maatwebsite\Excel\Excel::XLSX;
            $extension = strtolower(pathinfo($import->file_name, PATHINFO_EXTENSION));

            if ($extension === 'xls') {
                $readerType = \Maatwebsite\Excel\Excel::XLS;
            } elseif ($extension === 'ods') {
                $readerType = \Maatwebsite\Excel\Excel::ODS;
            }

            Excel::import($importObj, $filePath, null, $readerType);

            $service->writeExplicitProgress(99.0);

            $service->finalizeProgress();

            $failedRows = $service->getFailedRows();
            $successCount = $service->getSuccessCount();

            $status = 'completed';

            if (!empty($failedRows) && $successCount > 0) {
                $status = 'completed_with_errors';
            } elseif ($successCount === 0) {
                $status = 'failed';
            }

            $import->update([
                'status' => $status,
                'total_rows' => $successCount + count($failedRows),
                'processed_rows' => $successCount + count($failedRows),
                'success_rows' => $successCount,
                'failed_rows' => count($failedRows),
                'errors' => $failedRows,
            ]);

            $this->broadcastFileOperationTerminal(
                FileOperationEvent::BRAND_IMPORT_PROGRESS,
                'brand-import',
                $this->importId,
                $status,
                !empty($failedRows),
                [
                    'progress' => 100.0,
                    'total_rows' => $successCount + count($failedRows),
                    'processed_rows' => $successCount + count($failedRows),
                    'success_rows' => $successCount,
                    'failed_rows' => count($failedRows),
                ]
            );

            Storage::disk('public')->delete($import->file_path);
            $this->removeSignalFile('progress');
        } catch (ImportCancelledException $e) {
            $service->rollbackCreatedData();
            Storage::disk('public')->delete($import->file_path);
            $this->cleanSignals();

            $import->update([
                'status' => 'cancelled',
                'total_rows' => $service->getSuccessCount() + count($service->getFailedRows()),
                'processed_rows' => $service->getSuccessCount() + count($service->getFailedRows()),
                'success_rows' => $service->getSuccessCount(),
                'failed_rows' => count($service->getFailedRows()),
                'errors' => $service->getFailedRows(),
            ]);

            $this->broadcastFileOperationTerminal(
                FileOperationEvent::BRAND_IMPORT_PROGRESS,
                'brand-import',
                $this->importId,
                'cancelled',
                !empty($service->getFailedRows()),
                [
                    'progress' => 100.0,
                    'total_rows' => $service->getSuccessCount() + count($service->getFailedRows()),
                    'processed_rows' => $service->getSuccessCount() + count($service->getFailedRows()),
                    'success_rows' => $service->getSuccessCount(),
                    'failed_rows' => count($service->getFailedRows()),
                ]
            );
        } catch (Throwable $e) {
            // P10: intermediate attempts must stay retryable (see ImportProductsJob).
            if ($this->attempts() >= $this->tries) {
                $import->update([
                    'status' => 'failed',
                    'errors' => [[
                        'sheet' => 'system',
                        'row' => 0,
                        'name_en' => '',
                        'name_ar' => '',
                        'error_message' => $e->getMessage(),
                    ]],
                ]);

                $this->broadcastFileOperationTerminal(
                    FileOperationEvent::BRAND_IMPORT_PROGRESS,
                    'brand-import',
                    $this->importId,
                    'failed',
                    true
                );
            } else {
                $import->update([
                    'errors' => array_merge($import->errors ?? [], [[
                        'sheet' => 'system',
                        'row' => 0,
                        'name_en' => '',
                        'name_ar' => '',
                        'error_message' => 'Attempt ' . $this->attempts() . ': ' . $e->getMessage(),
                    ]]),
                ]);
            }

            throw $e;
        }
    }

    protected function countRows(): int
    {
        try {
            $import = Import::find($this->importId);

            if (!$import) {
                return 0;
            }

            $filePath = Storage::disk('public')->path($import->file_path);

            if (!file_exists($filePath)) {
                return 0;
            }

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);

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
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function failed(Throwable $exception): void
    {
        $import = Import::find($this->importId);

        if ($import && $import->status === 'processing') {
            $import->update(['status' => 'failed']);

            $this->broadcastFileOperationTerminal(
                FileOperationEvent::BRAND_IMPORT_PROGRESS,
                'brand-import',
                $this->importId,
                'failed',
                true
            );
        }
    }
}
