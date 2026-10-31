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
use App\Services\GameDataSyncService;
use App\Services\GameVersionParser;
use App\Support\DelistedReason;
use DateMalformedStringException;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Values that reduce a game to a blank listing.
     */
    public const CONTENT_ATTRIBUTES = [
        'status' => 'In development',
        'is_nsfw' => false,
        'description' => null,
        'full_description' => null,
        'authors' => null,
        'developer' => null,
        'initially_published_at' => null,
        'first_visible_at' => null,
        'thumb_url' => null,
        'optimized_thumbnails' => null,
        'screenshots' => null,
        'game_engine' => 'unknown',
        'error' => null,
        'custom_tags' => '',
        'uploads' => [],
        'is_feedless' => false,
        'source_language_id' => null,
        'min_price' => null,
        'is_on_sale' => false,
        'is_paid' => false,
        'has_demo' => false,
        'sale_discount_percent' => null,
        'currency' => 'USD',
        'additional_links' => null,
        'custom_css' => null,
        'has_custom_page' => false,
        'custom_name' => null,
        'custom_description' => null,
        'custom_screenshots' => null,
        'custom_assets' => null,
        'custom_page_updated_at' => null,
        'custom_page_updated_by' => null,
        'view_mode' => 'original',
        'content_type' => 'visual_novel',
        'steam_genres' => null,
        'steam_languages' => null,
        'steam_user_tags' => null,
        'is_stats_extraction_disabled' => false,
        'delisted_at' => null,
        'delisted_reason' => null,
    ];

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
        'is_moderation_hidden',
        'moderation_note',
        'delisted_at',
        'delisted_reason',
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
        'delisted_at' => 'datetime',
        'delisted_reason' => DelistedReason::class,
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
        'is_stats_extraction_disabled' => 'boolean',
        'is_moderation_hidden' => 'boolean',
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
            if ($game->is_moderation_hidden) {
                $game->is_visible = false;
            }

            if (! $game->slug || $game->isDirty(['url', 'name'])) {
                $primaryUrl = $game->getPrimaryUrl();
                $baseSlug = null;

                if ($primaryUrl) {
                    $baseSlug = basename($primaryUrl);

                    // A domain typically has dots and no hyphens/underscores
                    if (empty($baseSlug) || $baseSlug === '/' || strpos($baseSlug, '.') !== false) {
                        $baseSlug = null;
                    }
                }

                // If URL doesn't provide a usable slug, generate from name
                if (! $baseSlug) {
                    $baseSlug = Str::slug($game->name);
                }

                $slug = $baseSlug;
                $counter = 1;

                while (static::where('slug', $slug)->where('id', '!=', $game->id ?? 0)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                $game->slug = $slug;
            }

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

    public function generateUniqueSlug(string $name): string
    {
        $baseSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
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

    public function sourceLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'source_language_id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(GameVersion::class)->where('is_latest', true);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function discordSubscriptions(): HasMany
    {
        return $this->hasMany(GameDiscordSubscription::class);
    }

    public function discordServers()
    {
        return $this->belongsToMany(DiscordServer::class, 'game_discord_subscriptions')
            ->withPivot('subscribed_at', 'is_active')
            ->withTimestamps();
    }

    public function discordNotificationHistory(): HasMany
    {
        return $this->hasMany(DiscordNotificationHistory::class);
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserGameProgress::class);
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_visible'), true);
    }

    public function scopeListed(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_visible'), true)->whereNull($query->qualifyColumn('delisted_at'));
    }

    public function isDelisted(): bool
    {
        return $this->delisted_at !== null;
    }

    public function canReceiveUpdates(): bool
    {
        return ! $this->is_paid && ! $this->isDelisted();
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

    public function gameVersions(): HasMany
    {
        return $this->hasMany(GameVersion::class)->orderBy('published_at', 'desc');
    }

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

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class)->orderBy('character_id');
    }

    public function languageMappings(): HasMany
    {
        return $this->hasMany(LanguageMapping::class);
    }

    protected function getArrayableAttributes(): array
    {
        $attributes = parent::getArrayableAttributes();

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
