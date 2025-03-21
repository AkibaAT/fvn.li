<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VnListEntry extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vn_list_id',
        'game_id',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::creating(function ($entry) {
            // Set initial sort_order to the highest order + 10 for the list
            if (! $entry->sort_order) {
                $entry->sort_order = static::where('vn_list_id', $entry->vn_list_id)
                    ->max('sort_order') + 10;
            }
        });
    }

    /**
     * Get the list that owns the entry.
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(VnList::class, 'vn_list_id');
    }

    /**
     * Get the game that this entry refers to.
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
