<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCustomGameContent;
use App\Models\Concerns\HasGameAttributes;
use App\Models\Concerns\HasGameLanguageSupport;
use App\Models\Concerns\HasGameMedia;
use App\Models\Concerns\HasGamePricing;
use App\Models\Concerns\HasGameSearch;
use App\Models\Concerns\HasGameTags;
use App\Services\GameDataSyncService;
use App\Services\GameVersionParser;
use Carbon\Carbon;
use DateMalformedStringException;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;
use Throwable;

class Game extends Model
{
    use HasCustomGameContent;
    use HasFactory, Searchable;
    use HasGameAttributes;
    use HasGameLanguageSupport;
    use HasGameMedia;
    use HasGamePricing;
    use HasGameSearch;
    use HasGameTags;

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
        'custom_tags',
        'source_language_id',
        'min_price',
        'is_on_sale',
        'sale_discount_percent',
        'is_paid',
        'has_demo',
        'is_suspended',
        'additional_links',
        'screenshots',
        'blur_screenshots',
        'optimized_thumbnails',
        'has_custom_page',
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

    protected $appends = ['current_price', 'original_price', 'discount_percentage', 'optimized_thumbnail_url'];

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
        'blur_screenshots' => 'boolean',
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
        // Platform support casts
        'platform' => 'string',
        'content_type' => 'string',
        'url' => 'array', // JSONB field storing URLs by platform: { "itch_io": "...", "steam": "...", "other": "..." }
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

        // Validate that platform is set before saving
        static::saving(function (self $game) {
            if ($game->isDirty('platform') && $game->platform === null) {
                throw new \InvalidArgumentException(
                    "Game platform must be explicitly set. Cannot save game without a platform. " .
                    "Use one of: 'itch_io', 'steam', 'other'"
                );
            }

            // If platform is being set for the first time (new game), ensure it's valid
            if ($game->wasRecentlyCreated && $game->platform === null) {
                throw new \InvalidArgumentException(
                    "Game platform must be explicitly set when creating a new game. " .
                    "Use one of: 'itch_io', 'steam', 'other'"
                );
            }

            return true;
        });
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

    // ========== Platform Support Scopes ==========

    /**
     * Scope to find games by URL (searches across all platform URLs in JSONB)
     */
    public function scopeByUrl($query, string $url)
    {
        return $query->where(function ($q) use ($url) {
            // Search for the URL in any platform key within the JSONB
            $q->whereRaw("url->>'itch_io' = ?", [$url])
                ->orWhereRaw("url->>'steam' = ?", [$url])
                ->orWhereRaw("url->>'other' = ?", [$url]);
        });
    }

    /**
     * Scope to OR find games by URL (for use in complex where clauses)
     */
    public function scopeOrByUrl($query, string $url)
    {
        return $query->orWhere(function ($q) use ($url) {
            // Search for the URL in any platform key within the JSONB
            $q->whereRaw("url->>'itch_io' = ?", [$url])
                ->orWhereRaw("url->>'steam' = ?", [$url])
                ->orWhereRaw("url->>'other' = ?", [$url]);
        });
    }

    /**
     * Scope to find games by URL for a specific platform
     */
    public function scopeByUrlForPlatform($query, string $url, string $platform)
    {
        return $query->whereRaw("url->>'$platform' = ?", [$url]);
    }

    /**
     * Scope to filter games by platform
     */
    public function scopeFromPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope to filter itch.io games only
     */
    public function scopeFromItchio($query)
    {
        return $query->where('platform', 'itch_io');
    }

    /**
     * Scope to filter Steam games only
     */
    public function scopeFromSteam($query)
    {
        return $query->where('platform', 'steam');
    }

    /**
     * Scope to filter games from other platforms
     */
    public function scopeFromOther($query)
    {
        return $query->where('platform', 'other');
    }

    // ========== Platform Support Helper Methods ==========

    /**
     * Check if this is an itch.io game
     */
    public function isItchioGame(): bool
    {
        return $this->platform === 'itch_io';
    }

    /**
     * Check if this is a Steam game
     */
    public function isSteamGame(): bool
    {
        return $this->platform === 'steam';
    }

    /**
     * Check if this is a game from another platform
     */
    public function isOtherGame(): bool
    {
        return $this->platform === 'other';
    }

    /**
     * Get the human-readable platform name
     */
    public function getPlatformName(): string
    {
        return match ($this->platform) {
            'itch_io' => 'itch.io',
            'steam' => 'Steam',
            'other' => 'Other',
            default => 'Unknown',
        };
    }

    // ========== Multi-Platform URL Methods ==========

    /**
     * Get URL for a specific platform
     */
    public function getUrlForPlatform(string $platform): ?string
    {
        $urls = $this->url ?? [];
        return $urls[$platform] ?? null;
    }

    /**
     * Get the primary URL (based on the game's platform)
     */
    public function getPrimaryUrl(): ?string
    {
        return $this->getUrlForPlatform($this->platform);
    }

    /**
     * Get all available URLs for this game
     */
    public function getAllUrls(): array
    {
        return $this->url ?? [];
    }

    /**
     * Set URL for a specific platform
     */
    public function setUrlForPlatform(string $platform, string $url): void
    {
        $urls = $this->url ?? [];
        $urls[$platform] = $url;
        $this->url = $urls;
    }

    /**
     * Check if game has URL for a specific platform
     */
    public function hasUrlForPlatform(string $platform): bool
    {
        $urls = $this->url ?? [];
        return isset($urls[$platform]) && !empty($urls[$platform]);
    }

    // ========== Content Type Scopes ==========

    /**
     * Scope to filter visual novels (listed on fvn.li)
     */
    public function scopeVisualNovels($query)
    {
        return $query->where('content_type', 'visual_novel');
    }

    /**
     * Scope to filter adjacent games (related but not VNs)
     */
    public function scopeAdjacentGames($query)
    {
        return $query->where('content_type', 'adjacent');
    }

    /**
     * Scope to filter other content (non-FVN)
     */
    public function scopeOtherContent($query)
    {
        return $query->where('content_type', 'other');
    }

    /**
     * Scope to filter content that should be listed on fvn.li
     */
    public function scopePublicContent($query)
    {
        return $query->where('content_type', 'visual_novel');
    }

    /**
     * Scope to filter content tracked by bot but not listed on fvn.li
     */
    public function scopeBotOnlyContent($query)
    {
        return $query->whereIn('content_type', ['adjacent', 'other']);
    }

    // ========== Content Type Helper Methods ==========

    /**
     * Check if this is a visual novel (listed on fvn.li)
     */
    public function isVisualNovel(): bool
    {
        return $this->content_type === 'visual_novel';
    }

    /**
     * Check if this is an adjacent game (related but not VN)
     */
    public function isAdjacentGame(): bool
    {
        return $this->content_type === 'adjacent';
    }

    /**
     * Check if this is other content (non-FVN)
     */
    public function isOtherContent(): bool
    {
        return $this->content_type === 'other';
    }

    /**
     * Check if this content should be listed on fvn.li
     */
    public function isPublicContent(): bool
    {
        return $this->content_type === 'visual_novel';
    }

    /**
     * Check if this content is bot-only (not listed on fvn.li)
     */
    public function isBotOnlyContent(): bool
    {
        return in_array($this->content_type, ['adjacent', 'other'], true);
    }

    /**
     * Get the human-readable content type name
     */
    public function getContentTypeName(): string
    {
        return match ($this->content_type) {
            'visual_novel' => 'Visual Novel',
            'adjacent' => 'Adjacent Game',
            'other' => 'Other Content',
            default => 'Unknown',
        };
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
        return $this->hasMany(GameVersion::class)->orderByDesc('published_at');
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
    public function refreshMetadata(): void
    {
        $syncService = app(GameDataSyncService::class);
        $syncService->refreshMetadata($this);
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
     * Check if the user can edit this game's custom page
     */
    public function canUserEdit(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Admin can edit all games
        if ($user->is_admin) {
            return true;
        }

        // Check if user has explicit ownership permission
        if ($this->hasExplicitOwnership($user)) {
            return true;
        }

        // Check if user's itch.io account matches the game's namespace
        if ($this->hasItchIoOwnership($user)) {
            return true;
        }

        return false;
    }

    /**
     * Enable custom page editing and copy current data as baseline
     */
    public function enableCustomPage(User $user): void
    {
        $this->update([
            'has_custom_page' => true,
            'custom_description' => $this->full_description,
            'custom_screenshots' => $this->screenshots ?: [],
            'custom_assets' => [],
            'custom_page_updated_at' => now(),
            'custom_page_updated_by' => $user->id,
        ]);
    }

    /**
     * Disable custom page editing (revert to auto-sync)
     */
    public function disableCustomPage(): void
    {
        $this->update([
            'has_custom_page' => false,
            'custom_description' => null,
            'custom_screenshots' => null,
            'custom_assets' => null,
            'custom_page_updated_at' => null,
            'custom_page_updated_by' => null,
        ]);
    }

    /**
     * Update custom page content
     */
    public function updateCustomPage(array $data, User $user): void
    {
        $updateData = [
            'custom_page_updated_at' => now(),
            'custom_page_updated_by' => $user->id,
        ];

        if (isset($data['description'])) {
            $updateData['custom_description'] = $data['description'];
        }

        if (isset($data['screenshots'])) {
            $updateData['custom_screenshots'] = $data['screenshots'];
        }

        if (isset($data['assets'])) {
            $updateData['custom_assets'] = $data['assets'];
        }

        $this->update($updateData);
    }

    /**
     * Get the user who last updated the custom page
     */
    public function customPageUpdatedBy()
    {
        return $this->belongsTo(User::class, 'custom_page_updated_by');
    }

    /**
     * Get additional links sorted by sort_order, filtered by release date
     */
    public function getAdditionalLinksAttribute($value): array
    {
        if (! $value) {
            return [];
        }

        $links = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($links)) {
            return [];
        }

        // Filter out links that haven't reached their release date yet
        $now = Carbon::now();
        $links = array_filter($links, function ($link) use ($now) {
            // If no release_at is set, the link is immediately available
            if (empty($link['release_at'])) {
                return true;
            }

            try {
                $releaseDate = Carbon::parse($link['release_at']);

                return $now->gte($releaseDate);
            } catch (Exception $e) {
                // If parsing fails, show the link (fail safe)
                return true;
            }
        });

        // Sort by sort_order, then by id for consistent ordering
        usort($links, function ($a, $b) {
            $orderA = $a['sort_order'] ?? 0;
            $orderB = $b['sort_order'] ?? 0;

            if ($orderA === $orderB) {
                return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
            }

            return $orderA <=> $orderB;
        });

        return $links;
    }

    /**
     * Get all additional links (including unreleased ones) for management purposes
     */
    public function getAllAdditionalLinks(): array
    {
        $value = $this->attributes['additional_links'] ?? null;

        if (! $value) {
            return [];
        }

        $links = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($links)) {
            return [];
        }

        // Sort by sort_order, then by id for consistent ordering
        usort($links, function ($a, $b) {
            $orderA = $a['sort_order'] ?? 0;
            $orderB = $b['sort_order'] ?? 0;

            if ($orderA === $orderB) {
                return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
            }

            return $orderA <=> $orderB;
        });

        return $links;
    }

    /**
     * Check if the game has any additional links (only released ones)
     */
    public function hasAdditionalLinks(): bool
    {
        return ! empty($this->additional_links);
    }

    /**
     * Get the trending score for this game based on recent page views.
     * Calculates fresh data from click_stats table.
     */
    public function getTrendingScore(): int
    {
        return DB::table('click_stats')
            ->where('game_id', $this->id)
            ->where('type', 'page_view')
            ->where('clicked_at', '>=', DB::raw("NOW() - INTERVAL '14 days'"))
            ->count();
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        // Load relationships if not already loaded
        if (! $this->relationLoaded('tags')) {
            $this->load('tags');
        }
        if (! $this->relationLoaded('gameJams')) {
            $this->load('gameJams');
        }

        // Get latest version data for platforms and supported languages
        $latestVersion = $this->gameVersions()->where('is_latest', true)->first();
        $supportedLanguages = [];
        $englishWordCount = null;

        if ($latestVersion) {
            // Get supported languages from the latest version
            $supportedLanguages = $latestVersion->supportedLanguages()
                ->where('is_available', true)
                ->pluck('iso_code')
                ->toArray();

            // Get English word count
            $englishStats = $latestVersion->languageStats()
                ->where('iso_code', 'eng')
                ->first();
            $englishWordCount = $englishStats?->words;
        }

        return [
            // Basic identifiers
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'authors' => $this->authors ? strip_tags($this->authors) : null,

            // Descriptions for search (with URLs removed)
            'description' => $this->stripUrlsFromText($this->description),
            'full_description' => $this->full_description ? $this->stripUrlsFromText(trim(strip_tags($this->full_description))) : null,
            'custom_description' => $this->custom_description ? $this->stripUrlsFromText(trim(strip_tags($this->custom_description))) : null,
            'custom_tags' => $this->custom_tags,

            // Tags for search and filtering
            'tags' => $this->tags->pluck('name')->toArray(),

            // Game jams for search and filtering
            'game_jams' => $this->gameJams->pluck('name')->toArray(),

            // Status and visibility
            'status' => $this->status,
            'is_visible' => $this->is_visible,
            'is_suspended' => $this->is_suspended,

            // Content flags
            'is_nsfw' => $this->is_nsfw,
            'blur_screenshots' => $this->blur_screenshots,

            // Pricing and availability
            'is_paid' => $this->is_paid,
            'has_demo' => $this->has_demo,
            'min_price' => $this->min_price,
            'is_on_sale' => $this->is_on_sale,
            'sale_discount_percent' => $this->sale_discount_percent,

            // Technical details
            'game_engine' => $this->game_engine,
            'supported_languages' => $supportedLanguages,
            'english_word_count' => $englishWordCount,

            // Platform support (from latest version)
            'latest_version_id' => $latestVersion ? $latestVersion->id : null,
            'is_windows' => $latestVersion ? $latestVersion->is_windows : false,
            'is_linux' => $latestVersion ? $latestVersion->is_linux : false,
            'is_mac' => $latestVersion ? $latestVersion->is_mac : false,
            'is_android' => $latestVersion ? $latestVersion->is_android : false,
            'is_web' => $latestVersion ? $latestVersion->is_web : false,

            // Ratings and popularity
            'rating_score' => $this->rating_score,
            'rating_count' => $this->rating_count,
            'trending_score' => $this->getTrendingScore(),

            // Dates for sorting and display
            'created_at' => $this->created_at?->toISOString(),
            'initially_published_at' => $this->initially_published_at?->toISOString(),
            'latest_version_published_at' => $latestVersion?->published_at?->toISOString(),
            'first_visible_at' => $this->first_visible_at?->toISOString(),

            // Custom page features
            'has_custom_page' => $this->has_custom_page,
            'custom_page_updated_at' => $this->custom_page_updated_at?->toISOString(),
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'games';
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        // Only index visible games with names
        return $this->is_visible && ! empty(trim($this->name));
    }

    protected function devlog(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->latestVersion?->devlog
        );
    }

    protected function rating(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['rating_score'] ?? null
        );
    }

    protected function ratingCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['rating_count'] ?? 0
        );
    }

    /**
     * Get the platforms attribute.
     */
    protected function platforms(): Attribute
    {
        return Attribute::make(
            get: fn () => [
                'windows' => $this->latestVersion?->is_windows ?? false,
                'linux' => $this->latestVersion?->is_linux ?? false,
                'mac' => $this->latestVersion?->is_mac ?? false,
                'android' => $this->latestVersion?->is_android ?? false,
                'web' => $this->latestVersion?->is_web ?? false,
            ],
        );
    }

    /**
     * Get the devlog link from the game's itch.io page
     */
    private function getDevlogLink(): ?string
    {
        try {
            // Use cached HTML to avoid duplicate requests
            $response = $this->getCachedResponse($this->getPrimaryUrl(), [], true);
            $html = $response['body'];
            $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

            $devlog = $doc->querySelector('section#devlog');
            if ($devlog) {
                $devlogLinks = $devlog->querySelectorAll('a');
                foreach ($devlogLinks as $index => $link) {
                    if ($index === 0) {
                        return $link->getAttribute('href');
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to get devlog link', [
                'game_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function getCachedResponse(string $url, array $options = [], bool $anonymous = false): array
    {
        $urlKey = md5($url . serialize($options) . ($anonymous ? 'anon' : 'auth'));

        if (! isset(self::$httpCache[$this->id][$urlKey])) {
            $itchClient = App::make(ItchHttpClientService::class);
            $response = $itchClient->get($url, $options, $anonymous);
            self::$httpCache[$this->id][$urlKey] = [
                'body' => $response->getBody()->getContents(),
                'status_code' => $response->getStatusCode(),
            ];
        }

        return self::$httpCache[$this->id][$urlKey];
    }

    /**
     * Check if a string looks like a probable version number
     */
    private function isProbableVersion(string $version): bool
    {
        if (empty($version)) {
            return false;
        }

        $parsed = $this->parseSemanticVersion($version);
        if (! $parsed) {
            return false;
        }

        [$parts] = $parsed;

        // Reject if first number is too large or looks like a year
        if ($parts[0] > 2100 || ($parts[0] > 100 && strlen((string) $parts[0]) === 4)) {
            return false;
        }

        // Reject if any part is suspiciously large
        if (array_filter($parts, fn ($p) => $p > 10000)) {
            return false;
        }

        return true;
    }

    /**
     * Parse a version string into a normalized format
     */
    private function parseSemanticVersion(string $version): ?array
    {
        // Remove leading 'v' or 'version'
        $version = preg_replace('/^[vV]ersion\s*/', '', $version);
        $version = preg_replace('/^[vV]\s*/', '', $version);

        // Match version pattern with optional letter suffix
        if (! preg_match('/^(\d+(?:\.\d+)*?)([a-zA-Z]+)?$/', $version, $matches)) {
            return null;
        }

        try {
            $parts = array_map('intval', explode('.', $matches[1]));
            $suffix = $matches[2] ?? '';

            return [$parts, $suffix];
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Copy language support from previous version
     */
    private function copyLanguageSupport(GameVersion $gameVersion): void
    {
        // Find the previous version with language support
        $previousVersion = $this->gameVersions()
            ->where('id', '!=', $gameVersion->id)
            ->whereHas('supportedLanguages')
            ->latest('published_at')
            ->first();

        if ($previousVersion) {
            // Copy all supported languages from previous version
            foreach ($previousVersion->supportedLanguages as $supported) {
                $gameVersion->addSupportedLanguage($supported->iso_code, $supported->is_available);
            }
        } else {
            // If no previous version exists, add only source language
            if ($this->source_language_id) {
                $gameVersion->addSupportedLanguage($this->source_language_id);
            } else {
                // Fallback to English only if no source language is defined
                $gameVersion->addSupportedLanguage('eng');
            }
        }
    }

    /**
     * Extract price information from the game page
     */

    /**
     * Check if a paid game has a demo
     */

    /**
     * Extract full description from the game page
     */

    /**
     * Extract custom CSS from the game page
     */

    /**
     * Extract game jam information from the game page
     */

    /**
     * Check if user has explicit ownership through database relationship
     */
    private function hasExplicitOwnership(User $user): bool
    {
        // This could be implemented with a game_owners table in the future
        // For now, return false as we don't have this table
        return false;
    }

    /**
     * Check if user's itch.io account owns this game
     */
    private function hasItchIoOwnership(User $user): bool
    {
        // Use the existing ownsGame method from User model
        return $user->ownsGame($this);
    }

    /**
     * Strip URLs from text for search indexing.
     *
     * Removes HTTP/HTTPS URLs to prevent them from polluting search results.
     */
    private function stripUrlsFromText(?string $text): ?string
    {
        if (empty($text)) {
            return $text;
        }

        // Remove URLs (http, https, www, and basic domain patterns)
        $patterns = [
            // Full URLs with protocol
            '/https?:\/\/[^\s\]]+/i',
            // www.domain.com patterns
            '/www\.[^\s\]]+/i',
            // Basic domain.com patterns (be careful not to remove legitimate words)
            '/\b[a-zA-Z0-9-]+\.[a-zA-Z]{2,}(?:\/[^\s\]]*)?/i',
        ];

        $cleanText = $text;
        foreach ($patterns as $pattern) {
            $cleanText = preg_replace($pattern, '', $cleanText);
        }

        // Clean up extra whitespace left by URL removal
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);

        return trim($cleanText);
    }
}
