<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VersionFileCategory extends Model
{
    protected $fillable = [
        'game_version_id',
        'category',
        'total_count',
        'total_size',
    ];

    protected $casts = [
        'total_count' => 'integer',
        'total_size' => 'integer',
    ];

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    public function fileTypes(): HasMany
    {
        return $this->hasMany(VersionFileType::class);
    }
}
