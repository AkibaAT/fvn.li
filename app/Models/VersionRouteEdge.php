<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionRouteEdge extends Model
{
    protected $table = 'version_route_edges';

    protected $fillable = [
        'game_version_id',
        'from_label',
        'to_label',
        'edge_type',
        'condition',
        'file_path',
        'line_number',
    ];

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }
}
