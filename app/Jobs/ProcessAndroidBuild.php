<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AndroidBuild;
use App\Services\AndroidBuildService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAndroidBuild implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 3600; // 1 hour

    // The queue name is set in the dispatch method

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AndroidBuild $build
    ) {
        Log::info('ProcessAndroidBuild job created', [
            'build_id' => $this->build->id,
            'queue_connection' => config('queue.default'),
            'serialized' => $this->build->getAttributes(),
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(AndroidBuildService $androidBuildService): void
    {
        try {
            Log::info('Starting Android build process', [
                'build_id' => $this->build->id,
                'game_id' => $this->build->game_id,
                'game_version_id' => $this->build->game_version_id,
            ]);

            // Process the build
            $androidBuildService->processBuild($this->build);

            Log::info('Android build process completed successfully', [
                'build_id' => $this->build->id,
            ]);
        } catch (Exception $e) {
            Log::error('Android build process failed', [
                'build_id' => $this->build->id,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            // Update build status if not already updated
            if ($this->build->status !== 'failed') {
                $this->build->status = 'failed';
                $this->build->error_message = $e->getMessage();
                $this->build->save();
            }

            // Rethrow the exception to mark the job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Android build job failed', [
            'build_id' => $this->build->id,
            'error' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        // Update build status if not already updated
        if ($this->build->status !== 'failed') {
            $this->build->status = 'failed';
            $this->build->error_message = $exception->getMessage();
            $this->build->save();
        }
    }
}
