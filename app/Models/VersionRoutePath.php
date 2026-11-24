<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionRoutePath extends Model
{
    protected $table = 'version_route_paths';

    protected $fillable = [
        'game_version_id',
        'ending_label',
        'path_labels',
        'step_count',
        'word_count',
        'choice_count',
        'choices',
    ];

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'path_labels' => 'array',
            'choices' => 'array',

        ];
    }
}
