<?php

namespace Tests\Feature\Phase0;

use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Marvel\Jobs\ImportProductsJob;
use Tests\TestCase;

class DebugAttemptsProbeTest extends TestCase
{
    /** @test */
    public function probe_attempts_value_inside_job()
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($t) { $t->id(); $t->string('name')->nullable(); });
        }
        if (! Schema::hasTable('imports')) {
            Schema::create('imports', function ($t) {
                $t->id();
                $t->string('type')->default('product');
                $t->string('file_path');
                $t->string('file_name');
                $t->string('status')->default('pending');
                $t->integer('total_rows')->default(0);
                $t->integer('processed_rows')->default(0);
                $t->integer('success_rows')->default(0);
                $t->integer('failed_rows')->default(0);
                $t->json('errors')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
            });
        }

        Storage::fake('public');

        $importId = (int) DB::table('imports')->insertGetId([
            'type' => 'product',
            'file_path' => 'imports/probe.xlsx',
            'file_name' => 'probe.xlsx',
            'status' => 'processing',
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Maatwebsite\Excel\Facades\Excel::shouldReceive('import')
            ->andThrow(new \RuntimeException('probe-boom'));

        $mock = \Mockery::mock(QueueJobContract::class);
        $mock->shouldReceive('attempts')->andReturn(1);

        $job = new ImportProductsJob($importId);
        $job->setJob($mock);

        fwrite(STDERR, "\ntries=" . var_export($job->tries, true));
        fwrite(STDERR, ' attempts()=' . var_export($job->attempts(), true) . "\n");

        try {
            $job->handle();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'caught=' . get_class($e) . ':' . substr($e->getMessage(), 0, 60) . "\n");
        }

        fwrite(STDERR, 'status=' . DB::table('imports')->where('id', $importId)->value('status') . "\n");
        fwrite(STDERR, 'errors=' . substr((string) DB::table('imports')->where('id', $importId)->value('errors'), 0, 160) . "\n");

        $this->assertTrue(true);
    }
}
