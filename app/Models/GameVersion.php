<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class GameVersion extends Model
{
    use HasFactory;
    protected $fillable = [
        'published_at',
        'game_id',
        'version',
        'devlog',
        'is_windows',
        'is_linux',
        'is_mac',
        'is_android',
        'is_web',
        'rating',
        'rating_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_windows' => 'boolean',
        'is_linux' => 'boolean',
        'is_mac' => 'boolean',
        'is_android' => 'boolean',
        'is_web' => 'boolean',
        'rating' => 'float',
        'rating_count' => 'integer',
        'is_latest' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (GameVersion $version) {
            // When setting a version as latest, ensure no other versions are marked as latest
            if ($version->is_latest) {
                $version->game->gameVersions()
                    ->where('id', '!=', $version->id)
                    ->update(['is_latest' => false]);
            }
        });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function characterStatsWithoutPlaceholders(): HasMany
    {
        return $this->hasMany(VersionCharacterStats::class, 'game_version_id', 'id')
            ->where('version_character_stats.iso_code', 'not like', 'q%')
            ->orderBy('version_character_stats.character_id')
            ->orderBy('version_character_stats.iso_code');
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'version_character_stats')
            ->withPivot(['iso_code', 'blocks', 'words']);
    }

    public function getStatsForLanguage(string $isoCode)
    {
        return $this->languageStats()
            ->where('iso_code', $isoCode)
            ->first();
    }

    public function languageStats(): HasMany
    {
        return $this->hasMany(VersionLanguageStats::class);
    }

    /**
     * Get dialogue lines for a specific language.
     */
    public function getDialogueLinesForLanguage(
        string $isoCode,
        ?string $character = null,
        int $limit = 100,
        int $offset = 0
    ): Collection {
        $query = $this->dialogueLines()
            ->where('iso_code', $isoCode)
            ->when($character, function ($q) use ($character) {
                return $q->whereHas('character', function ($sq) use ($character) {
                    $sq->where('character_id', $character);
                });
            })
            ->orderBy('file_path')
            ->orderBy('line_number')
            ->skip($offset)
            ->take($limit);

        return $query->get();
    }

    /**
     * Get all dialogue lines for this version.
     */
    public function dialogueLines(): HasMany
    {
        return $this->hasMany(DialogueLine::class);
    }

    /**
     * Count dialogue lines for a specific language.
     */
    public function countDialogueLinesForLanguage(string $isoCode, ?string $character = null): int
    {
        return $this->dialogueLines()
            ->where('iso_code', $isoCode)
            ->when($character, function ($q) use ($character) {
                return $q->whereHas('character', function ($sq) use ($character) {
                    $sq->where('character_id', $character);
                });
            })
            ->count();
    }

    public function getCharacterStatsForLanguage(string $isoCode): Collection
    {
        return $this->characterStats()
            ->where('iso_code', $isoCode)
            ->orderBy('character_id')
            ->get();
    }

    public function characterStats(): HasMany
    {
        return $this->hasMany(VersionCharacterStats::class);
    }

    public function getSupportedLanguageCodes(): array
    {
        return $this->supportedLanguages()
            ->pluck('iso_code')
            ->toArray();
    }

    public function supportedLanguages(): HasMany
    {
        return $this->hasMany(VersionSupportedLanguage::class);
    }

    /**
     * Add a supported language to this version
     * Updated to include is_available parameter
     */
    public function addSupportedLanguage(string $isoCode, bool $isAvailable = true): void
    {
        $this->supportedLanguages()->updateOrCreate(
            ['iso_code' => $isoCode],
            ['is_available' => $isAvailable]
        );
    }

    public function removeSupportedLanguage(string $isoCode): void
    {
        $this->supportedLanguages()
            ->where('iso_code', $isoCode)
            ->delete();
    }

    public function saveFileStats(array $stats): void
    {
        // First, delete any existing file stats for this version
        $this->fileCategories()->delete();

        foreach ($stats as $category => $categoryData) {
            if ($category === 'summary') {
                continue;
            }

            $summary = $stats['summary'];
            $totalCount = $summary["total_{$category}"] ?? 0;

            // Calculate total size for category
            $totalSize = array_sum(array_column($categoryData, 'total_size'));

            // Create category record
            $categoryModel = $this->fileCategories()->create([
                'category' => $category,
                'total_count' => $totalCount,
                'total_size' => $totalSize,
            ]);

            // Create file type records
            foreach ($categoryData as $extension => $data) {
                $categoryModel->fileTypes()->create([
                    'extension' => $extension,
                    'count' => $data['count'],
                    'size' => $data['total_size'],
                ]);
            }
        }
    }

    public function fileCategories(): HasMany
    {
        return $this->hasMany(VersionFileCategory::class);
    }

    /**
     * Check if a language is available for this version
     */
    public function isLanguageAvailable(string $isoCode): bool
    {
        $support = $this->supportedLanguages()
            ->where('iso_code', $isoCode)
            ->first();

        return $support && $support->is_available;
    }

    /**
     * Set the availability of a language for this version
     */
    public function setLanguageAvailability(string $isoCode, bool $isAvailable): bool
    {
        $support = $this->supportedLanguages()
            ->where('iso_code', $isoCode)
            ->first();

        if (! $support) {
            return false;
        }

        $support->is_available = $isAvailable;

        return $support->save();
    }

    /**
     * Get all available languages
     */
    public function getAvailableLanguages(): Collection
    {
        return $this->supportedLanguages()
            ->where('is_available', true)
            ->with('language')
            ->get()
            ->map(fn ($sl) => [
                'iso_code' => $sl->iso_code,
                'ref_name' => $sl->language->ref_name,
                'flag_code' => $sl->language->flag_code,
            ]);
    }

    /**
     * Copy language availability settings from a previous version
     */
    public function copyLanguageAvailabilityFrom(GameVersion $sourceVersion): void
    {
        // Get all language availability settings from source version
        $sourceSettings = $sourceVersion->supportedLanguages()
            ->select(['iso_code', 'is_available'])
            ->get();

        // Apply to current version where the language exists
        foreach ($sourceSettings as $sourceSetting) {
            $targetLanguage = $this->supportedLanguages()
                ->where('iso_code', $sourceSetting->iso_code)
                ->first();

            if ($targetLanguage) {
                $targetLanguage->is_available = $sourceSetting->is_available;
                $targetLanguage->save();
            }
        }
    }
}
