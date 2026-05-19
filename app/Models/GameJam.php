<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\GameJamScrapeService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;
use Throwable;

class GameJam extends Model
{
    protected $fillable = [
        'name',
        'url',
        'description',
        'start_date',
        'end_date',
        'submission_count',
        'participant_count',
        'host',
        'needs_details_fetch',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'submission_count' => 'integer',
        'participant_count' => 'integer',
        'needs_details_fetch' => 'boolean',
    ];

    public static function findOrCreateFromUrl(string $url, ?string $name = null): self
    {
        $url = self::normalizeAndValidateJamUrl($url);
        $gameJam = self::where('url', $url)->first();

        if (! $gameJam) {
            $gameJam = new self([
                'name' => $name ?: 'Unknown Game Jam',
                'url' => $url,
                'needs_details_fetch' => true,
            ]);
            $gameJam->save();
        }

        return $gameJam;
    }

    public static function normalizeAndValidateJamUrl(string $url): string
    {
        if (preg_match('|(https?://[^/]+/jam/[^/]+)/rate/|', $url, $matches)) {
            $url = $matches[1];
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            throw new InvalidArgumentException('Invalid game jam URL.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $path = rtrim($parts['path'], '/');

        if ($scheme !== 'https') {
            throw new InvalidArgumentException('Game jam URL must use HTTPS.');
        }

        if ($host !== 'itch.io' && ! str_ends_with($host, '.itch.io')) {
            throw new InvalidArgumentException('Game jam URL host must be itch.io.');
        }

        if (! preg_match('#^/jam/[^/]+$#', $path)) {
            throw new InvalidArgumentException('Game jam URL path is invalid.');
        }

        return sprintf('https://%s%s', $host, $path);
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_game_jam')
            ->withPivot('ranking', 'criteria_rankings')
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        if (! $this->start_date || ! $this->end_date) {
            return false;
        }

        $now = now();

        return $now->greaterThanOrEqualTo($this->start_date) && $now->lessThanOrEqualTo($this->end_date);
    }

    public function isUpcoming(): bool
    {
        if (! $this->start_date) {
            return false;
        }

        return now()->lessThan($this->start_date);
    }

    public function hasEnded(): bool
    {
        if (! $this->end_date) {
            return false;
        }

        return now()->greaterThan($this->end_date);
    }

    public function getDurationInDays(): ?int
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        return (int) ($this->start_date->diffInDays($this->end_date) + 1);
    }

    /**
     * @throws BindingResolutionException
     * @throws GuzzleException
     */
    public function fetchDetailsFromUrl(): bool
    {
        return app(GameJamScrapeService::class)->fetchDetails($this);
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function fetchResultsPage(
        int $maxRetries = 5,
        int $retryDelay = 30,
        int $maxPages = 50,
        int $pageDelaySeconds = 1
    ): bool {
        return app(GameJamScrapeService::class)->fetchResultsPage(
            $this,
            $maxRetries,
            $retryDelay,
            $maxPages,
            $pageDelaySeconds
        );
    }
}
