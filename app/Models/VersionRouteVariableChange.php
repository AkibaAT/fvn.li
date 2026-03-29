<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionRouteVariableChange extends Model
{
    protected $table = 'version_route_variable_changes';

    protected $fillable = [
        'game_version_id',
        'label',
        'variable_name',
        'operation',
        'value',
        'file_path',
        'line_number',
        'context',
    ];

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }
}
