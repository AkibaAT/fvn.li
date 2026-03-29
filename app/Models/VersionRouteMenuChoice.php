<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionRouteMenuChoice extends Model
{
    protected $table = 'version_route_menu_choices';

    protected $fillable = [
        'game_version_id',
        'from_label',
        'prompt',
        'prompt_translations',
        'text',
        'translations',
        'condition',
        'target_label',
        'edge_type',
        'file_path',
        'line_number',
    ];

    protected $casts = [
        'translations' => 'array',
        'prompt_translations' => 'array',
    ];

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }
}
