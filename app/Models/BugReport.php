<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BugReport extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_WONT_FIX = 'wont_fix';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'page_url',
        'page_title',
        'description',
        'request_parameters',
        'user_agent',
        'status',
        'is_closed',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'request_parameters' => 'array',
        'is_closed' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get all available status values.
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_WONT_FIX => "Won't Fix",
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->is_closed;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_WONT_FIX], true);
    }

    /**
     * Mark this report as resolved.
     */
    public function markAsResolved(User $resolver, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_by' => $resolver->id,
            'resolved_at' => now(),
            'admin_notes' => $notes ?? $this->admin_notes,
        ]);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'warning',
            self::STATUS_IN_PROGRESS => 'info',
            self::STATUS_RESOLVED => 'success',
            self::STATUS_WONT_FIX => 'danger',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? 'Unknown';
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BugReportComment::class)->orderBy('created_at', 'asc');
    }

    public function hasUnreadAdminReplies(): bool
    {
        return $this->comments()
            ->where('is_from_admin', true)
            ->where('is_read', false)
            ->exists();
    }

    public function getUnreadAdminReplyCountAttribute(): int
    {
        return $this->comments()
            ->where('is_from_admin', true)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark all admin replies as read for this report.
     */
    public function markAdminRepliesAsRead(): void
    {
        $this->comments()
            ->where('is_from_admin', true)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }
}
