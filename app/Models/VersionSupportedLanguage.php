<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Casts\LanguageCodeCast;

class VersionSupportedLanguage extends Model
{
    protected $fillable = [
        'game_version_id',
        'iso_code',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'iso_code' => LanguageCodeCast::class,
    ];

    /**
     * Copy availability settings from one version to another
     */
    public static function copyAvailabilitySettings(int $sourceVersionId, int $targetVersionId): void
    {
        // Get all supported languages from source version
        $sourceLanguages = self::where('game_version_id', $sourceVersionId)
            ->select(['iso_code', 'is_available'])
            ->get();

        // Update target version languages where they exist
        foreach ($sourceLanguages as $sourceLanguage) {
            $targetLanguage = self::where('game_version_id', $targetVersionId)
                ->where('iso_code', $sourceLanguage->iso_code->getValue())
                ->first();

            if ($targetLanguage) {
                $targetLanguage->is_available = $sourceLanguage->is_available;
                $targetLanguage->save();
            }
        }
    }

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'iso_code', 'id');
    }
}
