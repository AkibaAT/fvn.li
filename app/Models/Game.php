<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCustomGameContent;
use App\Models\Concerns\HasGameAttributes;
use App\Models\Concerns\HasGameContentType;
use App\Models\Concerns\HasGameLanguageSupport;
use App\Models\Concerns\HasGameMedia;
use App\Models\Concerns\HasGamePlatformSupport;
use App\Models\Concerns\HasGamePricing;
use App\Models\Concerns\HasGameSearch;
use App\Models\Concerns\HasGameTags;
use App\Models\Concerns\HasGameVersionParsing;
use App\Services\GameDataSyncService;
use App\Services\GameVersionParser;
use DateMalformedStringException;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Scout\Searchable;
use Throwable;

class Game extends Model
{
    use HasCustomGameContent;
    use HasFactory;
    use HasGameAttributes;
    use HasGameContentType;
    use HasGameLanguageSupport;
    use HasGameMedia;
    use HasGamePlatformSupport;
    use HasGamePricing;
    use HasGameSearch, Searchable {
        HasGameSearch::toSearchableArray insteadof Searchable;
        HasGameSearch::searchableAs insteadof Searchable;
        HasGameSearch::shouldBeSearchable insteadof Searchable;
    }
    use HasGameTags;
    use HasGameVersionParsing;

    /**
     * Temporary flags that should not be persisted to the database.
     * These are stored as class properties, not in the $attributes array.
     */
    public bool $priceSetFromApi = false;

    protected $fillable = [
        'itch_id',
        'slug',
        'name',
        'status',
        'is_visible',
        'is_nsfw',
        'description',
        'full_description',
        'custom_css',
        'url',
        'platform',
        'steam_app_id',
        'thumb_url',
        'game_engine',
        'authors',
        'developer',
        'steam_genres',
        'steam_languages',
        'steam_user_tags',
        'custom_tags',
        'source_language_id',
        'min_price',
        'currency',
        'is_on_sale',
        'sale_discount_percent',
        'is_paid',
        'has_demo',
        'is_delisted',
        'is_stats_extraction_disabled',
        'additional_links',
        'screenshots',
        'optimized_thumbnails',
        'has_custom_page',
        'custom_name',
        'custom_description',
        'custom_screenshots',
        'custom_assets',
        'custom_page_updated_at',
        'custom_page_updated_by',
        'view_mode',
        'rating_score',
        'rating_count',
        'english_word_count',
        'trending_score',
        'trending_score_calculated_at',
        // Discord integration fields
        'discord_channel_id',
        'discord_message_id',
        'discord_likes',
        'discord_dislikes',
        'abbreviations',
        'discord_tags',
        'content_type',
        'discord_updated_at',
    ];

    // Removed automatic eager loading of tags to prevent N+1 queries
    // Tags should be explicitly loaded only where needed (game detail, games list)

    protected $appends = ['current_price', 'original_price', 'discount_percentage', 'formatted_current_price', 'formatted_original_price', 'optimized_thumbnail_url', 'primary_url', 'effective_name'];

    protected $casts = [
        'initially_published_at' => 'datetime',
        'latest_version_published_at' => 'datetime',
        'first_visible_at' => 'datetime',
        'min_price' => 'float',
        'is_windows' => 'boolean',
        'is_linux' => 'boolean',
        'is_mac' => 'boolean',
        'is_android' => 'boolean',
        'is_web' => 'boolean',
        'is_nsfw' => 'boolean',
        'is_visible' => 'boolean',
        'is_on_sale' => 'boolean',
        'sale_discount_percent' => 'integer',
        'is_paid' => 'boolean',
        'has_demo' => 'boolean',
        'is_delisted' => 'boolean',
        'is_stats_extraction_disabled' => 'boolean',
        'optimized_thumbnails' => 'array',
        'supported_languages' => 'collection',
        'uploads' => 'array',
        'screenshots' => 'array',
        'additional_links' => 'array',
        'custom_css' => 'string',
        'has_custom_page' => 'boolean',
        'custom_screenshots' => 'array',
        'custom_assets' => 'array',
        'custom_page_updated_at' => 'datetime',
        'view_mode' => 'string',
        'rating_score' => 'float',
        'rating_count' => 'integer',
        'trending_score' => 'integer',
        'trending_score_calculated_at' => 'datetime',
        // Platform support casts
        'platform' => 'string',
        'content_type' => 'string',
        'url' => 'array', // JSONB field storing URLs by platform: { "itch_io": "...", "steam": "...", "other": "..." }
        // Steam-specific casts
        'steam_genres' => 'array',
        'steam_user_tags' => 'array',
        // Discord integration casts
        'discord_likes' => 'array',
        'discord_dislikes' => 'array',
        'abbreviations' => 'array',
        'discord_tags' => 'array',
        'discord_updated_at' => 'datetime',
    ];

    /**
     * Boot the model - add validation for platform field
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $game) {
            // Generate slug if it doesn't exist or if URL/name changed
            if (! $game->slug || $game->isDirty(['url', 'name'])) {
                // Try to get slug from primary URL first
                $primaryUrl = $game->getPrimaryUrl();
                $baseSlug = null;

                if ($primaryUrl) {
                    $baseSlug = basename($primaryUrl);

                    // Check if basename is usable (not empty, not '/', not a domain)
                    // A domain typically has dots and no hyphens/underscores
                    if (empty($baseSlug) || $baseSlug === '/' || strpos($baseSlug, '.') !== false) {
                        $baseSlug = null;
                    }
                }

                // If URL doesn't provide a usable slug, generate from name
                if (! $baseSlug) {
                    $baseSlug = Str::slug($game->name);
                }

                // Find a unique slug
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('slug', $slug)->where('id', '!=', $game->id ?? 0)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                $game->slug = $slug;
            }

            // Validate that platform is set before saving
            if ($game->isDirty('platform') && $game->platform === null) {
                throw new InvalidArgumentException(
                    'Game platform must be explicitly set. Cannot save game without a platform. ' .
                    "Use one of: 'itch_io', 'steam', 'other'"
                );
            }

            // If platform is being set for the first time (new game), ensure it's valid
            if ($game->wasRecentlyCreated && $game->platform === null) {
                throw new InvalidArgumentException(
                    'Game platform must be explicitly set when creating a new game. ' .
                    "Use one of: 'itch_io', 'steam', 'other'"
                );
            }

            return true;
        });
    }

    /**
     * Generate a unique slug from a name
     */
    public function generateUniqueSlug(string $name): string
    {
        // Create base slug from name
        $baseSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        // Remove leading/trailing hyphens
        $baseSlug = trim($baseSlug, '-');

        // Start with base slug
        $slug = $baseSlug;
        $counter = 1;

        // Keep trying with incrementing numbers until we find a unique slug
        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get the source language for this game.
     */
    public function sourceLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'source_language_id');
    }

    /**
     * Get the latest version of the game.
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(GameVersion::class)->where('is_latest', true);
    }

    /**
     * Get all ratings for this game.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Get all Discord server subscriptions for this game.
     */
    public function discordSubscriptions(): HasMany
    {
        return $this->hasMany(GameDiscordSubscription::class);
    }

    /**
     * Get all Discord servers subscribed to this game.
     */
    public function discordServers()
    {
        return $this->belongsToMany(DiscordServer::class, 'game_discord_subscriptions')
            ->withPivot('subscribed_at', 'is_active')
            ->withTimestamps();
    }

    /**
     * Get notification history for this game.
     */
    public function discordNotificationHistory(): HasMany
    {
        return $this->hasMany(DiscordNotificationHistory::class);
    }

    /**
     * Get all user progress records for this game.
     */
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserGameProgress::class);
    }

    public function resolveRouteBinding($value, $field = null): Game
    {
        $field = $field ?: $this->getRouteKeyName();
        $query = $this->where($field, $value);

        return $query->firstOrFail();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @throws BindingResolutionException
     * @throws DateMalformedStringException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function loadFullDetails(): void
    {
        $syncService = app(GameDataSyncService::class);
        $syncService->loadFullDetails($this);
    }

    /**
     * Refresh the base game information from itch.io
     *
     * @throws DateMalformedStringException
     * @throws BindingResolutionException
     * @throws GuzzleException
     */
    public function refreshBaseInfo(): void
    {
        $syncService = app(GameDataSyncService::class);
        $syncService->refreshBaseInfo($this);
    }

    /**
     * Refresh version information for the game
     *
     * @throws GuzzleException|DateMalformedStringException
     * @throws Exception
     * @throws Throwable
     */
    public function refreshVersion(bool $force = false): void
    {
        $syncService = app(GameDataSyncService::class);
        $syncService->refreshVersion($this, $force);
    }

    /**
     * Get all game versions for this game.
     */
    public function gameVersions(): HasMany
    {
        return $this->hasMany(GameVersion::class)->orderBy('published_at', 'desc');
    }

    /**
     * Extract version information from upload metadata
     */
    public function extractVersion(array $upload, bool $allowDateFallback = false): ?string
    {
        $parser = app(GameVersionParser::class);

        return $parser->extractVersion($upload, $allowDateFallback);
    }

    /**
     * Refresh all metadata for the game from its itch.io page
     *
     * @throws BindingResolutionException
     * @throws Throwable
     */
    public function refreshMetadata(?string $originalThumbUrl = null, ?array $originalScreenshots = null): void
    {
        $syncService = app(GameDataSyncService::class);
        $syncService->refreshMetadata($this, $originalThumbUrl, $originalScreenshots);
    }

    /**
     * Get all characters for this game.
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class)->orderBy('character_id');
    }

    /**
     * Get the language mappings specific to this game.
     */
    public function languageMappings(): HasMany
    {
        return $this->hasMany(LanguageMapping::class);
    }

    /**
     * Get the attributes that should be converted to arrays for database storage.
     * Excludes temporary in-memory properties that are not database columns.
     */
    protected function getArrayableAttributes(): array
    {
        $attributes = parent::getArrayableAttributes();

        // Remove temporary properties that should not be persisted to database
        unset($attributes['pendingGameJamId'], $attributes['pendingTagIds']);

        return $attributes;
    }

    /**
     * Accessor for primary_url attribute (for frontend serialization)
     */
    protected function primaryUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getPrimaryUrl()
        );
    }

    protected function effectiveName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getEffectiveName()
        );
    }

    protected function effectiveDescription(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getEffectiveDescription()
        );
    }

}
