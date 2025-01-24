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

    protected $casts = [
        'count' => 'integer',
        'size' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(VersionFileCategory::class, 'version_file_category_id');
    }
}
