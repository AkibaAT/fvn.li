<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class News extends Model
{
    /**
     * News type constants
     */
    public const TYPE_ANNOUNCEMENT = 'announcement';
    public const TYPE_UPDATE = 'update';
    public const TYPE_MAINTENANCE = 'maintenance';
    public const TYPE_INCIDENT = 'incident';

    /**
     * The table associated with the model.
     */
    protected $table = 'news';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'type',
        'is_published',
        'published_at',
        'author_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from title if not provided
        static::creating(function ($news) {
            if (empty($news->slug)) {
                $news->slug = Str::slug($news->title);
            }
        });

        // Set published_at when is_published is set to true
        static::saving(function ($news) {
            if ($news->is_published && !$news->published_at) {
                $news->published_at = now();
            }
        });
    }

    /**
     * Get the author of the news.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Scope to get only published news.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope to order by published date descending.
     * Note: We override Laravel's default latest() which orders by created_at
     */
    public function scopeLatest($query)
    {
        return $query->orderByDesc('published_at');
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the type label for display.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_ANNOUNCEMENT => 'Announcement',
            self::TYPE_UPDATE => 'Update',
            self::TYPE_MAINTENANCE => 'Maintenance',
            self::TYPE_INCIDENT => 'Incident',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get the type color for display.
     */
    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_ANNOUNCEMENT => 'blue',
            self::TYPE_UPDATE => 'green',
            self::TYPE_MAINTENANCE => 'yellow',
            self::TYPE_INCIDENT => 'red',
            default => 'gray',
        };
    }
}

