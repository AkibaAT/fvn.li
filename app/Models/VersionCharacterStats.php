<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionCharacterStats extends Model
{
    protected $fillable = [
        'game_version_id',
        'iso_code',
        'character_id',
        'display_name',
        'blocks',
        'words',
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
