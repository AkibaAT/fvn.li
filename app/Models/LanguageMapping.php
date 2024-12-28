<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LanguageMapping extends Model
{
    protected $fillable = [
        'game_language_key',
        'iso_code',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'iso_code', 'id');
    }
}
