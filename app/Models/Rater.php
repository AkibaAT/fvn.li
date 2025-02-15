<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\RaterAliasService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rater extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'alias',
    ];

    protected static function booted(): void
    {
        self::creating(function (Rater $rater) {
            if (! $rater->alias) {
                $maxAttempts = 10;
                $aliasService = app(RaterAliasService::class);

                // Try regular alias generation first
                for ($i = 0; $i < $maxAttempts; $i++) {
                    $alias = $aliasService->generateAlias();
                    if (! static::where('alias', $alias)->exists()) {
                        $rater->alias = $alias;

                        return;
                    }
                }

                // If all attempts fail, use the fallback unique generation
                $rater->alias = $aliasService->generateUniqueAlias();
            }
        });
    }

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
