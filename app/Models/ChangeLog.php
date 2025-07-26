<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ChangeLog extends Model
{
    /**
     * Standard Eloquent event types
     */
    public const EVENT_CREATED = 'created';
    public const EVENT_UPDATED = 'updated';
    public const EVENT_DELETED = 'deleted';
    public const EVENT_RESTORED = 'restored';

    /**
     * Source types constants
     */
    public const SOURCE_WEB = 'web';
    public const SOURCE_API = 'api';
    public const SOURCE_COMMAND = 'command';
    public const SOURCE_CRON = 'cron';
    public const SOURCE_SYSTEM = 'system';

    /**
     * Disable automatic timestamps since we use custom timestamp column
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'change_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'timestamp',
        'event_type',
        'entity_type',
        'entity_id',
        'user_id',
        'changes',
        'old_values',
        'new_values',
        'context',
        'source',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'timestamp' => 'datetime',
        'changes' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'context' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Export user's audit data for GDPR compliance (Data Portability - Article 20)
     */
    public static function exportUserData(int $userId): array
    {
        $logs = self::byUser($userId)
            ->orderBy('timestamp', 'desc')
            ->get();

        $export = [
            'user_id' => $userId,
            'exported_at' => now()->toISOString(),
            'total_entries' => $logs->count(),
            'audit_logs' => [],
        ];

        foreach ($logs as $log) {
            $export['audit_logs'][] = [
                'id' => $log->id,
                'timestamp' => $log->timestamp->toISOString(),
                'event_type' => $log->event_type,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'changes' => $log->changes,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'context' => $log->context,
                'source' => $log->source,
                'description' => $log->description,
                'created_at' => $log->created_at?->toISOString(),
                'updated_at' => $log->updated_at?->toISOString(),
            ];
        }

        return $export;
    }

    /**
     * Delete user's audit data for GDPR compliance (Right to Erasure - Article 17)
     */
    public static function deleteUserData(int $userId): int
    {
        return self::byUser($userId)->delete();
    }

    /**
     * Anonymize user's audit data (partial erasure for legitimate interests)
     */
    public static function anonymizeUserData(int $userId): int
    {
        $logs = self::byUser($userId);

        $count = $logs->count();

        if ($count === 0) {
            return 0;
        }

        // Update logs to remove user identification while preserving system audit integrity
        // Assign to system user (ID 1) instead of null to maintain audit trail integrity
        $systemUserId = config('audit.system_user_id', 1);

        // Use a safer approach with parameter binding
        DB::table('change_logs')
            ->whereIn('id', $logs->pluck('id'))
            ->update([
                'user_id' => $systemUserId,
                'context' => DB::raw("
                    context
                    - 'ip_address'
                    - 'user_agent'
                    - 'session_id'
                    || jsonb_build_object(
                        'anonymized', true,
                        'anonymized_at', now(),
                        'original_user_id', {$userId}
                    )
                "),
                'updated_at' => now(),
            ]);

        return $count;
    }

    /**
     * Get audit logs that contain personal data (for privacy impact assessments)
     */
    public static function getPersonalDataLogs()
    {
        return self::query()
            ->where(function ($query) {
                $query->whereNotNull('user_id')
                    ->orWhereRaw("context->'ip_address' IS NOT NULL")
                    ->orWhereRaw("context->'session_id' IS NOT NULL")
                    ->orWhereRaw("context->'user_agent' IS NOT NULL");
            });
    }

    /**
     * Get logs for sensitive models (for compliance reporting)
     */
    public static function getSensitiveModelLogs()
    {
        $sensitiveModels = [];
        $modelSettings = config('audit.model_settings', []);

        foreach ($modelSettings as $modelClass => $settings) {
            if ($settings['sensitive'] ?? false) {
                $sensitiveModels[] = $modelClass;
            }
        }

        if (empty($sensitiveModels)) {
            return self::query()->whereRaw('false'); // Return empty query
        }

        return self::whereIn('entity_type', $sensitiveModels);
    }

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the entity that was changed (polymorphic relationship)
     */
    public function entity()
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    /**
     * Scope to filter by entity type
     */
    public function scopeForEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope to filter by entity
     */
    public function scopeForEntity($query, Model $entity)
    {
        return $query->where('entity_type', get_class($entity))
            ->where('entity_id', $entity->id);
    }

    /**
     * Scope to filter by event type
     */
    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, User|int $user)
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by request ID (for tracing all changes in a single request)
     */
    public function scopeForRequest($query, string $requestId)
    {
        return $query->whereRaw("context->>'request_id' = ?", [$requestId]);
    }

    /**
     * Scope to filter by session ID (for tracing all changes in a user session)
     */
    public function scopeForSession($query, string $sessionId)
    {
        return $query->whereRaw("context->>'session_id' = ?", [$sessionId]);
    }

    /**
     * Scope to get all changes grouped by request ID
     */
    public function scopeGroupedByRequest($query)
    {
        return $query->select('*')
            ->addSelect(DB::raw("context->>'request_id' as request_id"))
            ->whereRaw("context->>'request_id' IS NOT NULL")
            ->orderBy('timestamp');
    }

    /**
     * Scope to get all changes grouped by session ID
     */
    public function scopeGroupedBySession($query)
    {
        return $query->select('*')
            ->addSelect(DB::raw("context->>'session_id' as session_id"))
            ->whereRaw("context->>'session_id' IS NOT NULL")
            ->orderBy('timestamp');
    }

    /**
     * Scope to filter by command name (for tracing changes from specific commands)
     */
    public function scopeForCommand($query, string $commandName)
    {
        return $query->whereRaw("context->'command'->>'name' = ?", [$commandName]);
    }

    /**
     * Scope to filter by command script (e.g., 'artisan', 'php')
     */
    public function scopeForScript($query, string $script)
    {
        return $query->whereRaw("context->'command'->>'script' = ?", [$script]);
    }

    /**
     * Scope to get all changes grouped by command
     */
    public function scopeGroupedByCommand($query)
    {
        return $query->select('*')
            ->addSelect(DB::raw("context->'command'->>'name' as command_name"))
            ->addSelect(DB::raw("context->'command'->>'script' as command_script"))
            ->whereRaw("context->'command' IS NOT NULL")
            ->orderBy('timestamp');
    }

    /**
     * Get a human-readable description of the change
     */
    public function getDescriptionAttribute(): string
    {
        $entityName = $this->entity_type;
        $action = match ($this->event_type) {
            self::EVENT_CREATED => 'created',
            self::EVENT_UPDATED => 'updated',
            self::EVENT_DELETED => 'deleted',
            self::EVENT_RESTORED => 'restored',
            default => $this->event_type,
        };

        $userName = $this->user ? $this->user->name : 'System';

        return "{$userName} {$action} {$entityName} #{$this->entity_id}";
    }
}
