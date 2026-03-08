<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rater extends Model
{
    use HasFactory;

    protected $fillable = [
        'itch_id',
        'name',
        'steam_id',
        'external_platform',
        'is_review_banned',
    ];

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function resolveRouteBinding($value, $field = null): Rater
    {
        $query = $this->where($field ?? 'id', $value);

        return $query->firstOrFail();
    }
}
