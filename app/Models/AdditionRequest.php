<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class AdditionRequest extends Model
{
    use HasFactory;

    /**
     * Available status values.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'game_url',
        'normalized_url',
        'platform',
        'status',
        'rejection_reason',
        'game_id',
        'reviewed_at',
        'reviewed_by',
        'discord_notified_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'reviewed_at' => 'datetime',
        'discord_notified_at' => 'datetime',
    ];

    /**
     * Get all available status values.
     *
     * @return array<string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }

    /**
     * Find or create an addition request for the given URL.
     * Returns the request and whether it was newly created.
     * Returns null if the game already exists and is visible.
     */
    public static function findOrCreateForUrl(string $url, ?string $platform = null): ?array
    {
        // Don't create requests for games that already exist and are visible
        if (self::gameAlreadyExists($url)) {
            return null;
        }

        $normalizedUrl = self::normalizeUrl($url);

        $request = self::where('normalized_url', $normalizedUrl)->first();

        if ($request) {
            return [$request, false];
        }

        $request = self::create([
            'game_url' => $url,
            'normalized_url' => $normalizedUrl,
            'platform' => $platform,
            'status' => self::STATUS_PENDING,
        ]);

        return [$request, true];
    }

    /**
     * Check if a game with the given URL already exists and is visible.
     */
    public static function gameAlreadyExists(string $url): bool
    {
        $normalizedUrl = self::normalizeUrl($url);
        $possibleUrls = self::buildUrlVariants($url, $normalizedUrl);

        if (empty($possibleUrls)) {
            return false;
        }

        $platformKeys = ['itch_io', 'steam', 'other'];

        // Check if a visible game exists with this URL
        return Game::where('is_visible', true)
            ->where(function ($query) use ($platformKeys, $possibleUrls) {
                $isFirstCondition = true;

                foreach ($platformKeys as $platformKey) {
                    $column = DB::raw("url->>'{$platformKey}'");

                    if ($isFirstCondition) {
                        $query->whereIn($column, $possibleUrls);
                        $isFirstCondition = false;

                        continue;
                    }

                    $query->orWhereIn($column, $possibleUrls);
                }
            })
            ->exists();
    }

    /**
     * Normalize a URL for deduplication.
     * Removes protocol, www, trailing slashes, and query parameters.
     */
    public static function normalizeUrl(string $url): string
    {
        // Remove protocol and www
        $normalized = preg_replace('/^https?:\/\/(www\.)?/', '', $url);

        // Remove trailing slashes
        $normalized = rtrim($normalized, '/');

        // Remove query parameters and fragments
        $normalized = strtok($normalized, '?');
        $normalized = strtok($normalized, '#');

        return strtolower($normalized);
    }

    /**
     * Build the set of URL variants we use for deduplication lookups.
     *
     * @return array<int, string>
     */
    private static function buildUrlVariants(string $originalUrl, string $normalizedUrl): array
    {
        $variants = array_filter([
            $originalUrl,
            $normalizedUrl ? "https://{$normalizedUrl}" : null,
            $normalizedUrl ? "http://{$normalizedUrl}" : null,
            $normalizedUrl ? "https://www.{$normalizedUrl}" : null,
            $normalizedUrl ? "http://www.{$normalizedUrl}" : null,
        ]);

        return array_values(array_unique($variants));
    }

    /**
     * Get the game that was created from this request (if approved).
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the admin user who reviewed this request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Add a user to this request if they haven't already requested it.
     */
    public function addUser(User $user): bool
    {
        if ($this->users()->where('user_id', $user->id)->exists()) {
            return false; // User already requested this
        }

        $this->users()->attach($user->id);

        return true;
    }

    /**
     * Get all users who requested this addition.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'addition_request_users')
            ->withTimestamps()
            ->orderBy('addition_request_users.created_at');
    }

    /**
     * Remove a user from this request if they are associated with it.
     * Returns true if the user was removed, false if they weren't associated.
     */
    public function removeUser(User $user): bool
    {
        if (! $this->users()->where('user_id', $user->id)->exists()) {
            return false; // User wasn't associated with this request
        }

        $this->users()->detach($user->id);

        // If no users are left associated with this request and it's still pending,
        // we can delete the entire request
        if ($this->users()->count() === 0 && $this->isPending()) {
            $this->delete();
        }

        return true;
    }

    /**
     * Check if this request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if a user can cancel their participation in this request.
     */
    public function canBeCancelledByUser(User $user): bool
    {
        return $this->isPending() && $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if this request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if this request is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Mark this request as approved.
     */
    public function approve(User $reviewer, ?Game $game = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'game_id' => $game?->id ?? $this->game_id,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Mark this request as rejected.
     */
    public function reject(User $reviewer, ?string $adminNotes = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'rejection_reason' => $adminNotes,
            'game_id' => null,
        ]);
    }

    /**
     * Get a human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Unknown',
        };
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_APPROVED => 'green',
            self::STATUS_REJECTED => 'red',
            default => 'gray',
        };
    }
}
