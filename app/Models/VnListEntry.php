<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VnListEntry extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vn_list_id',
        'game_id',
        'sort_order',
        'notes',
        'private_notes',
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
            if (! $entry->sort_order) {
                $entry->sort_order = static::where('vn_list_id', $entry->vn_list_id)
                    ->max('sort_order') + 10;
            }
        });
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(VnList::class, 'vn_list_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
