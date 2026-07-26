<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionRouteLabel extends Model
{
    protected $table = 'version_route_labels';

    protected $fillable = [
        'game_version_id',
        'name',
        'file_path',
        'line_number',
        'is_ending',
        'returns_to_caller',
        'externally_invoked',
        'is_scaffolding',
    ];

    protected $casts = [
        'is_ending' => 'boolean',
        'returns_to_caller' => 'boolean',
        'externally_invoked' => 'boolean',
        'is_scaffolding' => 'boolean',
    ];

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }
}
