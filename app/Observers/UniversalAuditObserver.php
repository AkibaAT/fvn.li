<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ProcessAuditLog;
use App\Models\ChangeLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class UniversalAuditObserver
{
    /**
     * Flag to prevent recursive audit logging
     */
    private static bool $isProcessing = false;

    /**
     * Handle the model "created" event.
     */
    public function created(Model $model): void
    {
        $this->logModelEvent('created', $model);
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated(Model $model): void
    {
        // Only log if there are actual changes
        if ($this->hasSignificantChanges($model)) {
            $this->logModelEvent('updated', $model);
        }
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->logModelEvent('deleted', $model);
    }

    /**
     * Handle the model "restored" event.
     */
    public function restored(Model $model): void
    {
        if ($this->shouldLogEvent('restored')) {
            $this->logModelEvent('restored', $model);
        }
    }

    /**
     * Log a model event
     */
    private function logModelEvent(string $event, Model $model): void
    {
        // Prevent recursive logging
        if (self::$isProcessing) {
            return;
        }

        // Check if audit logging is enabled
        if (! Config::get('audit.enabled', true)) {
            return;
        }

        // Check if this model should be excluded (includes ChangeLog)
        if ($this->shouldExcludeModel($model)) {
            return;
        }

        // Check if this event should be excluded
        if (! $this->shouldLogEvent($event)) {
            return;
        }

        // Set processing flag to prevent recursion
        self::$isProcessing = true;

        try {
            $auditData = $this->buildAuditData($event, $model);

            // Check payload size to prevent memory exhaustion
            $serializedSize = strlen(serialize($auditData));
            $maxSize = Config::get('audit.performance.skip_large_payloads', true) ? 65536 : PHP_INT_MAX; // 64KB

            if ($serializedSize > $maxSize) {
                Log::warning('Skipping audit log due to large payload size', [
                    'model' => get_class($model),
                    'event' => $event,
                    'size_bytes' => $serializedSize,
                    'max_size' => $maxSize,
                ]);
                // Don't return here - let finally block handle cleanup
                self::$isProcessing = false;

                return;
            }

            // Process synchronously or asynchronously based on config
            $asyncEnabled = Config::get('audit.async', true);
            $asyncWithFallback = Config::get('audit.async_with_fallback', true);

            Log::debug('Audit processing config', [
                'async_enabled' => $asyncEnabled,
                'async_with_fallback' => $asyncWithFallback,
                'entity_type' => get_class($model),
                'entity_id' => $model->getKey(),
                'event' => $event,
            ]);

            if ($asyncEnabled) {
                if ($asyncWithFallback) {
                    // For production reliability, we need to test if queue workers are actually running
                    // Since Laravel queue dispatch rarely throws exceptions, we test the connection directly

                    $queueConnection = Config::get('audit.queue_connection', 'audit');
                    $useAsync = false;

                    try {
                        // Test Redis connection for the default queue
                        $redis = app('redis')->connection('default');
                        $redis->ping(); // This will throw if Redis is not available

                        // Check if there are any workers processing this queue by checking queue size growth
                        $queueName = Config::get('audit.queue_name', 'audit');
                        $initialSize = $redis->llen("queues:{$queueName}");

                        Log::debug('Queue health check', [
                            'connection' => $queueConnection,
                            'queue_size' => $initialSize,
                        ]);

                        // If queue is too large, it means workers aren't keeping up - fallback to sync
                        if ($initialSize > 100) {
                            Log::warning('Queue backlog too large, using sync processing', [
                                'queue_size' => $initialSize,
                            ]);
                            $useAsync = false;
                        } else {
                            $useAsync = true;
                        }

                    } catch (Throwable $connectionException) {
                        Log::warning('Queue connection test failed, using sync processing', [
                            'connection' => $queueConnection,
                            'error' => $connectionException->getMessage(),
                        ]);
                        $useAsync = false;
                    }

                    if ($useAsync) {
                        try {
                            Log::debug('Dispatching to queue');
                            ProcessAuditLog::dispatch($auditData)
                                ->onConnection($queueConnection);
                            Log::debug('Queue dispatch successful');
                        } catch (Throwable $queueException) {
                            Log::warning('Queue dispatch failed, falling back to sync', [
                                'error' => $queueException->getMessage(),
                            ]);
                            ChangeLog::create($auditData);
                        }
                    } else {
                        Log::debug('Using sync processing due to queue issues');
                        ChangeLog::create($auditData);
                    }
                } else {
                    // Pure async mode - let it fail if queue is down
                    Log::debug('Pure async mode - dispatching to queue');
                    ProcessAuditLog::dispatch($auditData)
                        ->onConnection(Config::get('audit.queue_connection', 'audit'));
                }
            } else {
                // Pure synchronous mode
                Log::debug('Pure sync mode - creating audit log directly');
                ChangeLog::create($auditData);
            }
        } catch (Throwable $e) {
            // Log the error but don't throw to prevent breaking the original operation
            Log::error('Failed to process audit log', [
                'model' => get_class($model),
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Always reset the processing flag
            self::$isProcessing = false;
        }
    }

    /**
     * Build the audit data array
     */
    private function buildAuditData(string $event, Model $model): array
    {
        $user = Auth::user();
        $context = $this->buildContext();

        return [
            'timestamp' => now(),
            'event_type' => $event,
            'entity_type' => get_class($model),
            'entity_id' => $model->getKey(),
            'user_id' => $user?->id,
            'changes' => $this->getChanges($event, $model),
            'old_values' => $this->getOldValues($event, $model),
            'new_values' => $this->getNewValues($event, $model),
            'context' => $context,
            'source' => $this->detectSource(),
        ];
    }

    /**
     * Get the changes for the model (structured diff for updates only)
     */
    private function getChanges(string $event, Model $model): array
    {
        // Only provide structured diff for updates - create/delete use old_values/new_values instead
        if ($event !== 'updated') {
            return [];
        }

        $changes = [];
        foreach ($model->getDirty() as $field => $newValue) {
            if ($this->shouldSkipField($field, $model)) {
                continue;
            }

            $oldValue = $model->getOriginal($field);
            $changes[$field] = [
                'from' => $oldValue,
                'to' => $newValue,
            ];
        }

        return $changes;
    }

    /**
     * Get old values for the model (smart storage approach)
     */
    private function getOldValues(string $event, Model $model): array
    {
        return match ($event) {
            'updated' => $this->getChangedFieldsOldValues($model),
            'deleted' => $this->getFilteredAttributes($model), // Full record for deleted
            default => [], // Empty for created, restored, etc.
        };
    }

    /**
     * Get new values for the model (smart storage approach)
     */
    private function getNewValues(string $event, Model $model): array
    {
        return match ($event) {
            'created' => $this->getFilteredAttributes($model), // Full record for created
            'updated' => $this->getChangedFieldsNewValues($model), // Only changed fields
            'restored' => $this->getFilteredAttributes($model), // Full record for restored
            default => [], // Empty for deleted, etc.
        };
    }

    /**
     * Build context information
     */
    private function buildContext(): array
    {
        $context = [];
        $includeContext = Config::get('audit.include_context', []);

        if (request()) {
            if ($includeContext['ip_address'] ?? false) {
                $context['ip_address'] = $this->getAnonymizedIpAddress(request()->ip());
            }
            if ($includeContext['user_agent'] ?? false) {
                $context['user_agent'] = request()->userAgent();
            }
            if ($includeContext['url'] ?? false) {
                $context['url'] = request()->fullUrl();
            }
            if ($includeContext['method'] ?? false) {
                $context['method'] = request()->method();
            }
            if ($includeContext['session_id'] ?? false) {
                try {
                    $session = request()->session();
                    if ($session) {
                        $context['session_id'] = $session->getId();
                    }
                } catch (RuntimeException $e) {
                    // Session store not set (e.g., in console commands) - skip session_id
                }
            }
            if ($includeContext['request_id'] ?? false) {
                // Try multiple ways to get request ID
                $requestId = $this->getRequestId();
                if ($requestId) {
                    $context['request_id'] = $requestId;
                }
            }
        }

        // Capture command information for console operations
        if (($includeContext['command'] ?? false) && app()->runningInConsole()) {
            $commandInfo = $this->getCommandInfo();
            if ($commandInfo) {
                $context['command'] = $commandInfo;
            }
        }

        return $context;
    }

    /**
     * Get a unique request identifier
     */
    private function getRequestId(): ?string
    {
        if (! request()) {
            return null;
        }

        // Check for existing request ID headers (common in load balancers, proxies)
        $requestIdHeaders = [
            'X-Request-ID',
            'X-Correlation-ID',
            'X-Trace-ID',
            'Request-ID',
            'Correlation-ID',
        ];

        foreach ($requestIdHeaders as $header) {
            $requestId = request()->header($header);
            if ($requestId) {
                return $requestId;
            }
        }

        // Check if Laravel's request ID middleware is available
        if (request()->hasHeader('X-Laravel-Request-ID')) {
            return request()->header('X-Laravel-Request-ID');
        }

        // Generate our own request ID based on request signature
        return $this->generateRequestId();
    }

    /**
     * Generate a unique request ID based on request characteristics
     */
    private function generateRequestId(): string
    {
        $request = request();

        // Create a unique identifier based on request properties
        $ipAddress = $this->getAnonymizedIpAddress($request->ip());
        $signature = sprintf(
            '%s_%s_%s_%s_%d',
            $request->method(),
            md5($request->fullUrl()),
            $ipAddress,
            microtime(true),
            memory_get_usage()
        );

        return 'req_' . substr(md5($signature), 0, 16);
    }

    /**
     * Get information about the currently running command
     */
    private function getCommandInfo(): ?array
    {
        if (! app()->runningInConsole()) {
            return null;
        }

        try {
            // Get the current Artisan command if available
            $kernel = app(\Illuminate\Contracts\Console\Kernel::class);

            // Try to get command info from $_SERVER['argv']
            $argv = $_SERVER['argv'] ?? [];

            if (empty($argv)) {
                return null;
            }

            // First argument is usually the script name (artisan)
            $script = basename($argv[0] ?? '');

            // Second argument is usually the command name
            $commandName = $argv[1] ?? null;

            // Remaining arguments are parameters
            $arguments = array_slice($argv, 2);

            $commandInfo = [
                'script' => $script,
            ];

            if ($commandName) {
                $commandInfo['name'] = $commandName;
            }

            if (! empty($arguments)) {
                // Filter out sensitive information from arguments
                $filteredArgs = $this->filterSensitiveArguments($arguments);
                if (! empty($filteredArgs)) {
                    $commandInfo['arguments'] = $filteredArgs;
                }
            }

            return $commandInfo;

        } catch (Throwable $e) {
            // If we can't determine the command, return null
            return null;
        }
    }

    /**
     * Filter out potentially sensitive information from command arguments
     */
    private function filterSensitiveArguments(array $arguments): array
    {
        $sensitivePatterns = [
            '/^--password=/',
            '/^--token=/',
            '/^--secret=/',
            '/^--key=/',
            '/^--api-key=/',
        ];

        $filtered = [];

        foreach ($arguments as $arg) {
            $isSensitive = false;

            foreach ($sensitivePatterns as $pattern) {
                if (preg_match($pattern, $arg)) {
                    $filtered[] = preg_replace('/=.*/', '=***', $arg);
                    $isSensitive = true;
                    break;
                }
            }

            if (! $isSensitive) {
                $filtered[] = $arg;
            }
        }

        return $filtered;
    }

    /**
     * Detect the source of the change
     */
    private function detectSource(): string
    {
        $sources = Config::get('audit.sources', []);

        if (app()->runningInConsole()) {
            return $sources['command'] ?? 'command';
        }

        if (request()) {
            if (request()->is('api/*') || request()->expectsJson()) {
                return $sources['api'] ?? 'api';
            }

            return $sources['web'] ?? 'web';
        }

        return $sources['system'] ?? 'system';
    }

    /**
     * Check if the model should be excluded from audit logging
     */
    private function shouldExcludeModel(Model $model): bool
    {
        $modelClass = get_class($model);
        $tableName = $model->getTable();

        // Always exclude ChangeLog to prevent recursive logging
        if ($modelClass === ChangeLog::class || $tableName === 'change_logs') {
            return true;
        }

        $excludeModels = Config::get('audit.exclude_models', []);
        $excludeTables = Config::get('audit.exclude_tables', []);

        return in_array($modelClass, $excludeModels) ||
               in_array($tableName, $excludeTables);
    }

    /**
     * Check if the event should be logged
     */
    private function shouldLogEvent(string $event): bool
    {
        $excludeEvents = Config::get('audit.exclude_events', []);

        return ! in_array($event, $excludeEvents);
    }

    /**
     * Check if a field should be skipped from audit logging
     */
    private function shouldSkipField(string $field, Model $model): bool
    {
        // Global excluded fields
        $globalExcluded = Config::get('audit.exclude_fields', []);

        // Model-specific excluded fields
        $modelClass = get_class($model);
        $modelSettings = Config::get("audit.model_settings.{$modelClass}", []);
        $modelExcluded = $modelSettings['exclude_fields'] ?? [];

        $excludedFields = array_merge($globalExcluded, $modelExcluded);

        return in_array($field, $excludedFields);
    }

    /**
     * Check if the model has significant changes (excluding ignored fields)
     */
    private function hasSignificantChanges(Model $model): bool
    {
        foreach ($model->getDirty() as $field => $value) {
            if (! $this->shouldSkipField($field, $model)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get filtered model attributes (excluding sensitive fields)
     */
    private function getFilteredAttributes(Model $model): array
    {
        return array_filter(
            $model->getAttributes(),
            fn ($field) => ! $this->shouldSkipField($field, $model),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Get old values for only the fields that changed
     */
    private function getChangedFieldsOldValues(Model $model): array
    {
        $changedFields = [];

        foreach ($model->getDirty() as $field => $newValue) {
            if (! $this->shouldSkipField($field, $model)) {
                $changedFields[$field] = $model->getOriginal($field);
            }
        }

        return $changedFields;
    }

    /**
     * Get new values for only the fields that changed
     */
    private function getChangedFieldsNewValues(Model $model): array
    {
        $changedFields = [];

        foreach ($model->getDirty() as $field => $newValue) {
            if (! $this->shouldSkipField($field, $model)) {
                $changedFields[$field] = $newValue;
            }
        }

        return $changedFields;
    }

    /**
     * Get anonymized IP address based on privacy configuration
     */
    private function getAnonymizedIpAddress(?string $ipAddress): ?string
    {
        if (! $ipAddress) {
            return null;
        }

        // Check if IP anonymization is enabled
        if (! Config::get('audit.privacy.anonymize_ip_addresses', false)) {
            return $ipAddress;
        }

        $method = Config::get('audit.privacy.ip_anonymization_method', 'subnet');

        return match ($method) {
            'subnet' => $this->anonymizeIpBySubnet($ipAddress),
            'hash' => $this->anonymizeIpByHash($ipAddress),
            'full' => '***',
            default => $ipAddress,
        };
    }

    /**
     * Anonymize IP address by zeroing the last octet (IPv4) or last 64 bits (IPv6)
     */
    private function anonymizeIpBySubnet(string $ipAddress): string
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Zero out the last octet (e.g., 192.168.1.123 -> 192.168.1.0)
            $parts = explode('.', $ipAddress);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Zero out the last 64 bits (interface identifier)
            $binary = inet_pton($ipAddress);
            if ($binary !== false) {
                // Zero out the last 8 bytes (64 bits)
                $binary = substr($binary, 0, 8) . str_repeat("\0", 8);

                return inet_ntop($binary) ?: $ipAddress;
            }
        }

        return $ipAddress;
    }

    /**
     * Anonymize IP address using a one-way hash with salt
     */
    private function anonymizeIpByHash(string $ipAddress): string
    {
        // Use application key as salt for consistent hashing
        $salt = Config::get('app.key', 'audit-salt');

        // Create a truncated hash for privacy while maintaining some uniqueness
        return 'hash_' . substr(hash('sha256', $salt . $ipAddress), 0, 12);
    }
}
