<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionFileType extends Model
{
    protected $fillable = [
        'version_file_category_id',
        'extension',
        'count',
        'size',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(VersionFileCategory::class, 'version_file_category_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'size' => 'integer',

        ];
    }
}
