<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ChangeLog;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
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
            // First attempt as-is
            ChangeLog::create($this->auditData);
        } catch (Throwable $e) {
            // Log the actual error BEFORE attempting fallback
            Log::warning('Audit log creation failed on attempt ' . $this->attempts(), [
                'audit_data' => $this->auditData,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_class' => get_class($e),
            ]);
            // If we hit a FK violation on user_id, retry once with system user id
            $isFkViolation = ($e instanceof QueryException) && (string) $e->getCode() === '23503';
            $message = $e->getMessage();
            $targetsUserFk = is_string($message) && (str_contains($message,
                'change_logs_user_id_fkey') || str_contains($message, 'user_id'));

            $systemUserId = (int) config('audit.system_user_id', 1);
            $canRetryWithSystem = $isFkViolation && $targetsUserFk && isset($this->auditData['user_id']) && (int) $this->auditData['user_id'] !== $systemUserId;

            if ($canRetryWithSystem) {
                try {
                    $fallback = $this->auditData;
                    $fallback['user_id'] = $systemUserId;
                    ChangeLog::create($fallback);
                    Log::warning('Audit log inserted with system user due to FK violation', [
                        'original_user_id' => $this->auditData['user_id'],
                        'system_user_id' => $systemUserId,
                    ]);

                    return;
                } catch (Throwable $e2) {
                    Log::error('Failed fallback insert for audit log', [
                        'audit_data' => $this->auditData,
                        'error' => $e2->getMessage(),
                        'exception' => $e2,
                    ]);
                    throw $e2;
                }
            }

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
