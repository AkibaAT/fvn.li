<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ChangeLog;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAuditLog implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly array $auditData
    ) {
        // Set queue name from config
        $this->onQueue(config('audit.queue_name', 'audit'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Create the audit log entry
            ChangeLog::create($this->auditData);
        } catch (Throwable $e) {
            Log::error('Failed to process audit log', [
                'audit_data' => $this->auditData,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Audit log job failed permanently', [
            'audit_data' => $this->auditData,
            'error' => $exception?->getMessage(),
            'exception' => $exception,
        ]);
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): DateTime
    {
        return now()->addMinutes(10);
    }
}
