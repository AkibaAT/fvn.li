<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionLanguageStats extends Model
{
    protected $fillable = [
        'game_version_id',
        'iso_code',
        'blocks',
        'words',
        'menus',
        'options',
    ];

    protected $casts = [
        'blocks' => 'integer',
        'words' => 'integer',
        'menus' => 'integer',
        'options' => 'integer',
    ];

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'iso_code', 'id');
    }
}
