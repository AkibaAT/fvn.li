<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AndroidBuild extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'game_id',
        'game_version_id',
        'build_id',
        'status',
        'build_path',
        'keystore_path',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'build_id' => 'string',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }
}
