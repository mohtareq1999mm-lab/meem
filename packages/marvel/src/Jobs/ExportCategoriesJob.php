<?php

namespace Marvel\Jobs;

use App\Events\FileOperationEvent;
use App\Traits\BroadcastsFileOperationProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Marvel\Database\Models\Import;
use Marvel\Exports\CategoriesExport;
use Throwable;

class ExportCategoriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use BroadcastsFileOperationProgress;

    public int $tries = 3;

    public int $timeout = 1200;

    public array $backoff = [60, 120, 240];

    protected int $importId;

    public function __construct(int $importId)
    {
        $this->importId = $importId;
        $this->onQueue('meem-medium');
    }

    public function handle(): void
    {
        $import = Import::findOrFail($this->importId);

        if (in_array($import->status, ['completed', 'completed_with_errors', 'failed', 'cancelled'], true)) {
            return;
        }

        $updated = Import::where('id', $import->id)->whereIn('status', ['pending', 'processing'])->update([
            'status' => 'processing',
            'processed_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
        ]);
        $import->refresh();
        if ($updated === 0 && $import->isTerminal()) {
            return;
        }

        try {
            $export = new CategoriesExport();

            $rowCount = $export->collection()->count();

            $filename = 'categories-export-' . $import->id . '-' . now()->format('Y-m-d-His') . '.xlsx';

            $export->store($filename, 'imports');

            if (!\Illuminate\Support\Facades\Storage::disk('imports')->exists($filename)) {
                throw new \RuntimeException('Export file was not created');
            }

            $import->update([
                'status' => 'completed',
                'file_path' => $filename,
                'file_name' => $filename,
                'total_rows' => $rowCount,
                'processed_rows' => $rowCount,
                'success_rows' => $rowCount,
                'failed_rows' => 0,
                'errors' => [],
            ]);

            $this->broadcastFileOperationTerminal(
                FileOperationEvent::CATEGORY_EXPORT_COMPLETED,
                'category-export',
                $this->importId,
                'completed',
                false,
                [
                    'progress' => 100.0,
                    'total_rows' => $rowCount,
                    'processed_rows' => $rowCount,
                    'success_rows' => $rowCount,
                    'failed_rows' => 0,
                ]
            );
        } catch (Throwable $e) {
            try {
                if (isset($filename) && $filename !== null && \Illuminate\Support\Facades\Storage::disk('imports')->exists($filename)) {
                    \Illuminate\Support\Facades\Storage::disk('imports')->delete($filename);
                }
            } catch (Throwable $cleanup) { report($cleanup); }
            $import->update(['status' => 'failed']);

            $this->broadcastFileOperationTerminal(
                FileOperationEvent::CATEGORY_EXPORT_FAILED,
                'category-export',
                $this->importId,
                'failed',
                true
            );

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $import = Import::find($this->importId);

        if ($import && $import->status === 'processing') {
            $import->update(['status' => 'failed']);

            $this->broadcastFileOperationTerminal(
                FileOperationEvent::CATEGORY_EXPORT_FAILED,
                'category-export',
                $this->importId,
                'failed',
                true
            );
        }
    }
}