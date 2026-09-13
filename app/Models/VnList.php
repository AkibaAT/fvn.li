<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\VnListCacheService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class VnList extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_default',
        'is_public',
        'type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
        'is_public' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => app(VnListCacheService::class)->clearPublicListsCache());
        static::deleted(fn () => app(VnListCacheService::class)->clearPublicListsCache());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(VnListEntry::class);
    }

    public function addGame(int $gameId, ?VnListEntry $entry = null): VnListEntry
    {
        return DB::transaction(function () use ($gameId, $entry) {
            $removed = 0;
            if ($this->is_default) {
                $removed = VnListEntry::where('game_id', $gameId)
                    ->whereHas('list', fn ($query) => $query->where('user_id', $this->user_id)
                        ->where('is_default', true)->where('id', '!=', $this->id))
                    ->when($entry, fn ($query) => $query->where('id', '!=', $entry->id))
                    ->delete();
            }

            $entry ??= new VnListEntry(['game_id' => $gameId]);
            $entry->fill([
                'vn_list_id' => $this->id,
                'sort_order' => ($this->entries()->max('sort_order') ?? 0) + 10,
            ])->save();

            if ($removed) {
                app(VnListCacheService::class)->clearPublicListsCache();
            }

            return $entry;
        });
    }
}
