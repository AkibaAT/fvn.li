<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSupportedLanguage extends Model
{
    protected $fillable = [
        'game_id',
        'iso_code',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'iso_code', 'id');
    }
}
