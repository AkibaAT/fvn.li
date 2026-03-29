<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionRouteVariable extends Model
{
    protected $table = 'version_route_variables';

    protected $fillable = [
        'game_version_id',
        'name',
        'default_value',
        'type',
        'file_path',
        'line_number',
    ];

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }
}
