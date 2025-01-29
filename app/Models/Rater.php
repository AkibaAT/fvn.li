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
        'user_id',
        'name',
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
