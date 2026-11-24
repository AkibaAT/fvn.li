<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportState extends Model
{
    protected $fillable = [
        'type',
        'last_processed_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_processed_id' => 'integer',

        ];
    }
}
