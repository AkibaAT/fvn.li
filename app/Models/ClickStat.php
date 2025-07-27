<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\IpAnonymizationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ClickStat extends Model
{
    public const string TYPE_PAGE_VIEW = 'page_view';
    public const string TYPE_CUSTOM_LINK = 'custom_link';
    public const string TYPE_EXTERNAL_PROJECT = 'external_project';

    protected $fillable = [
        'game_id',
        'type',
        'link_id',
        'session_id',
        'ip_address',
        'user_agent',
        'referrer',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    /**
     * Record a click with time-based deduplication (24-hour window)
     * This follows industry best practices for click tracking analytics
     */
    public static function recordClick(
        int $gameId,
        string $type,
        string $sessionId,
        ?string $linkId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referrer = null
    ): bool {
        // Always record the click for total counts
        self::create([
            'game_id' => $gameId,
            'type' => $type,
            'link_id' => $linkId,
            'session_id' => $sessionId,
            'ip_address' => IpAnonymizationService::anonymize($ipAddress, 'subnet'),
            'user_agent' => $userAgent,
            'referrer' => $referrer,
            'clicked_at' => now(),
        ]);

        return true;
    }

    /**
     * Get click statistics for a game with both total and unique metrics
     */
    public static function getGameStats(int $gameId, ?Carbon $since = null): array
    {
        $query = self::where('game_id', $gameId);

        if ($since) {
            $query->where('clicked_at', '>=', $since);
        }

        // Get total clicks
        $totalStats = $query->select([
            'type',
            'link_id',
            DB::raw('COUNT(*) as total_clicks'),
            DB::raw('MAX(clicked_at) as last_click'),
        ])
            ->groupBy(['type', 'link_id'])
            ->get();

        // Get unique clicks (24-hour deduplication window)
        $uniqueStats = self::getUniqueClickStats($gameId, $since);

        $result = [
            'page_views_total' => 0,
            'page_views_unique' => 0,
            'last_page_view' => null,
            'external_project_total' => 0,
            'external_project_unique' => 0,
            'last_external_project' => null,
            'custom_links' => [],
        ];

        // Process total stats
        foreach ($totalStats as $stat) {
            if ($stat->type === self::TYPE_PAGE_VIEW) {
                $result['page_views_total'] = $stat->total_clicks;
                $result['last_page_view'] = $stat->last_click;
            } elseif ($stat->type === self::TYPE_EXTERNAL_PROJECT) {
                $result['external_project_total'] = $stat->total_clicks;
                $result['last_external_project'] = $stat->last_click;
            } elseif ($stat->type === self::TYPE_CUSTOM_LINK && $stat->link_id) {
                $result['custom_links'][$stat->link_id] = [
                    'total_clicks' => $stat->total_clicks,
                    'unique_clicks' => 0, // Will be filled from unique stats
                    'last_click' => $stat->last_click,
                ];
            }
        }

        // Add unique stats
        $result['page_views_unique'] = $uniqueStats['page_views'] ?? 0;
        $result['external_project_unique'] = $uniqueStats['external_project'] ?? 0;

        foreach ($uniqueStats['custom_links'] ?? [] as $linkId => $uniqueData) {
            if (isset($result['custom_links'][$linkId])) {
                $result['custom_links'][$linkId]['unique_clicks'] = $uniqueData['unique_clicks'];
            } else {
                // Edge case: unique click exists but no total (shouldn't happen)
                $result['custom_links'][$linkId] = [
                    'total_clicks' => 0,
                    'unique_clicks' => $uniqueData['unique_clicks'],
                    'last_click' => null,
                ];
            }
        }

        return $result;
    }

    /**
     * Get aggregated stats for multiple games (for developer dashboard)
     * Returns both total and unique metrics
     */
    public static function getMultipleGameStats(array $gameIds, ?Carbon $since = null): array
    {
        $result = [];

        // Initialize result structure
        foreach ($gameIds as $gameId) {
            $result[$gameId] = [
                'page_views_total' => 0,
                'page_views_unique' => 0,
                'external_project_total' => 0,
                'external_project_unique' => 0,
                'custom_link_clicks_total' => 0,
                'custom_link_clicks_unique' => 0,
            ];
        }

        // Get stats for each game individually to ensure accurate unique counting
        foreach ($gameIds as $gameId) {
            $gameStats = self::getGameStats($gameId, $since);

            $result[$gameId]['page_views_total'] = $gameStats['page_views_total'];
            $result[$gameId]['page_views_unique'] = $gameStats['page_views_unique'];
            $result[$gameId]['external_project_total'] = $gameStats['external_project_total'];
            $result[$gameId]['external_project_unique'] = $gameStats['external_project_unique'];

            // Sum up custom link clicks
            $totalCustomClicks = 0;
            $uniqueCustomClicks = 0;

            foreach ($gameStats['custom_links'] as $linkStats) {
                $totalCustomClicks += $linkStats['total_clicks'];
                $uniqueCustomClicks += $linkStats['unique_clicks'];
            }

            $result[$gameId]['custom_link_clicks_total'] = $totalCustomClicks;
            $result[$gameId]['custom_link_clicks_unique'] = $uniqueCustomClicks;
        }

        return $result;
    }

    /**
     * Export click statistics for games owned by a user (for GDPR data portability)
     */
    public static function exportUserOwnedGameStats(int $userId): array
    {
        // Get the user to access their owned games
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        // Check if user has itch.io account connected
        $itchioUsername = $user->getItchioUsername();
        if (! $itchioUsername) {
            return [];
        }

        // Get owned games
        $ownedGames = $user->getOwnedGames();
        if ($ownedGames->isEmpty()) {
            return [];
        }

        $gameIds = $ownedGames->pluck('id')->toArray();

        // Get all click statistics for owned games
        $clickStats = self::whereIn('game_id', $gameIds)
            ->orderBy('clicked_at', 'desc')
            ->get();

        return [
            'user_id' => $userId,
            'exported_at' => now()->toISOString(),
            'total_entries' => $clickStats->count(),
            'games_tracked' => $ownedGames->map(function ($game) use ($clickStats) {
                $gameStats = $clickStats->where('game_id', $game->id);

                return [
                    'game_name' => $game->name,
                    'game_url' => $game->url,
                    'total_clicks' => $gameStats->count(),
                    'page_views' => $gameStats->where('type', self::TYPE_PAGE_VIEW)->count(),
                    'external_project_clicks' => $gameStats->where('type', self::TYPE_EXTERNAL_PROJECT)->count(),
                    'custom_link_clicks' => $gameStats->where('type', self::TYPE_CUSTOM_LINK)->count(),
                    'first_tracked' => $gameStats->min('clicked_at'),
                    'last_tracked' => $gameStats->max('clicked_at'),
                ];
            })->values()->toArray(),
            'detailed_logs' => $clickStats->map(function ($stat) use ($ownedGames) {
                $game = $ownedGames->firstWhere('id', $stat->game_id);

                return [
                    'game_name' => $game ? $game->name : 'Unknown Game',
                    'type' => $stat->type,
                    'link_id' => $stat->link_id,
                    'clicked_at' => $stat->clicked_at,
                    'referrer' => $stat->referrer,
                    'description' => match ($stat->type) {
                        self::TYPE_PAGE_VIEW => 'Page view on FVN.li',
                        self::TYPE_EXTERNAL_PROJECT => 'Visit to itch.io project page',
                        self::TYPE_CUSTOM_LINK => 'Download link click: ' . ($stat->link_id ?? 'Unknown'),
                        default => 'Unknown click type'
                    },
                    // Note: session_id, ip_address, and user_agent are excluded for privacy
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * Anonymize click statistics by removing personal identifiers
     * This is used when a user requests account deletion (GDPR Article 17)
     */
    public static function anonymizePersonalData(): int
    {
        // For click statistics, we anonymize by removing IP addresses, user agents, and session IDs
        // We keep the statistical data (game_id, type, link_id, clicked_at) for legitimate business interests

        $count = self::whereNotNull('ip_address')
            ->orWhereNotNull('user_agent')
            ->orWhereNotNull('session_id')
            ->count();

        if ($count === 0) {
            return 0;
        }

        // Remove personal identifiers while keeping statistical data
        self::whereNotNull('ip_address')
            ->orWhereNotNull('user_agent')
            ->orWhereNotNull('session_id')
            ->update([
                'ip_address' => null,
                'user_agent' => null,
                'session_id' => 'anonymized_' . now()->timestamp,
                'updated_at' => now(),
            ]);

        return $count;
    }

    /**
     * Get daily click statistics for a game over a specified period
     */
    public static function getDailyStats(int $gameId, int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Get all clicks for the period
        $clicks = self::where('game_id', $gameId)
            ->where('clicked_at', '>=', $startDate)
            ->where('clicked_at', '<=', $endDate)
            ->orderBy('clicked_at')
            ->get();

        // Initialize daily data structure
        $dailyData = [];
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($days - 1 - $i)->format('Y-m-d');
            $dailyData[$date] = [
                'date' => $date,
                'page_views_total' => 0,
                'page_views_unique' => 0,
                'external_project_total' => 0,
                'external_project_unique' => 0,
                'custom_links_total' => 0,
                'custom_links_unique' => 0,
                'custom_links_breakdown' => [],
            ];
        }

        // Process clicks for total counts
        foreach ($clicks as $click) {
            $date = $click->clicked_at->format('Y-m-d');

            if (! isset($dailyData[$date])) {
                continue; // Skip if outside our range
            }

            if ($click->type === self::TYPE_PAGE_VIEW) {
                $dailyData[$date]['page_views_total']++;
            } elseif ($click->type === self::TYPE_EXTERNAL_PROJECT) {
                $dailyData[$date]['external_project_total']++;
            } elseif ($click->type === self::TYPE_CUSTOM_LINK && $click->link_id) {
                $dailyData[$date]['custom_links_total']++;

                if (! isset($dailyData[$date]['custom_links_breakdown'][$click->link_id])) {
                    $dailyData[$date]['custom_links_breakdown'][$click->link_id] = [
                        'total' => 0,
                        'unique' => 0,
                    ];
                }
                $dailyData[$date]['custom_links_breakdown'][$click->link_id]['total']++;
            }
        }

        // Calculate unique counts using 24-hour deduplication
        $seenFingerprints = [];

        foreach ($clicks as $click) {
            $date = $click->clicked_at->format('Y-m-d');

            if (! isset($dailyData[$date])) {
                continue;
            }

            // Create fingerprint for deduplication
            $fingerprint = hash('sha256',
                ($click->ip_address ?? 'unknown') . '|' .
                ($click->user_agent ?? 'unknown') . '|' .
                $click->type . '|' .
                ($click->link_id ?? 'null')
            );

            // Check if this is a unique click (within 24 hours)
            $isUnique = true;
            $clickTime = $click->clicked_at;

            if (isset($seenFingerprints[$fingerprint])) {
                foreach ($seenFingerprints[$fingerprint] as $previousTime) {
                    if ($clickTime->diffInHours($previousTime) < 24) {
                        $isUnique = false;
                        break;
                    }
                }
            }

            if ($isUnique) {
                // Record this fingerprint and time
                if (! isset($seenFingerprints[$fingerprint])) {
                    $seenFingerprints[$fingerprint] = [];
                }
                $seenFingerprints[$fingerprint][] = $clickTime;

                // Count the unique click
                if ($click->type === self::TYPE_PAGE_VIEW) {
                    $dailyData[$date]['page_views_unique']++;
                } elseif ($click->type === self::TYPE_EXTERNAL_PROJECT) {
                    $dailyData[$date]['external_project_unique']++;
                } elseif ($click->type === self::TYPE_CUSTOM_LINK && $click->link_id) {
                    $dailyData[$date]['custom_links_unique']++;

                    if (isset($dailyData[$date]['custom_links_breakdown'][$click->link_id])) {
                        $dailyData[$date]['custom_links_breakdown'][$click->link_id]['unique']++;
                    }
                }
            }
        }

        return array_values($dailyData);
    }

    /**
     * Get link-specific statistics for a game
     */
    public static function getLinkStats(int $gameId, int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        // Get the game to access link information
        $game = Game::find($gameId);
        if (! $game || ! $game->additional_links) {
            return [];
        }

        $linkStats = [];

        foreach ($game->additional_links as $link) {
            $linkId = $link['id'];

            // Get total clicks for this link
            $totalClicks = self::where('game_id', $gameId)
                ->where('type', self::TYPE_CUSTOM_LINK)
                ->where('link_id', $linkId)
                ->where('clicked_at', '>=', $startDate)
                ->count();

            // Get unique clicks (simplified for performance)
            $uniqueClicks = self::where('game_id', $gameId)
                ->where('type', self::TYPE_CUSTOM_LINK)
                ->where('link_id', $linkId)
                ->where('clicked_at', '>=', $startDate)
                ->select('ip_address', 'user_agent')
                ->distinct()
                ->count();

            // Get daily breakdown
            $dailyClicks = self::where('game_id', $gameId)
                ->where('type', self::TYPE_CUSTOM_LINK)
                ->where('link_id', $linkId)
                ->where('clicked_at', '>=', $startDate)
                ->selectRaw('DATE(clicked_at) as date, COUNT(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('total', 'date')
                ->toArray();

            $linkStats[] = [
                'link_id' => $linkId,
                'link_name' => $link['name'],
                'link_url' => $link['url'],
                'total_clicks' => $totalClicks,
                'unique_clicks' => $uniqueClicks,
                'daily_clicks' => $dailyClicks,
            ];
        }

        return $linkStats;
    }

    /**
     * Get unique click statistics using 24-hour deduplication window
     */
    private static function getUniqueClickStats(int $gameId, ?Carbon $since = null): array
    {
        $query = self::where('game_id', $gameId);

        if ($since) {
            $query->where('clicked_at', '>=', $since);
        }

        // Get all clicks for this game
        $allClicks = $query->orderBy('clicked_at')->get();

        $uniquePageViews = 0;
        $uniqueExternalProject = 0;
        $uniqueCustomLinks = [];
        $seenFingerprints = [];

        foreach ($allClicks as $click) {
            // Create fingerprint for deduplication (IP + UserAgent + Type + LinkId)
            $fingerprint = hash('sha256',
                ($click->ip_address ?? 'unknown') . '|' .
                ($click->user_agent ?? 'unknown') . '|' .
                $click->type . '|' .
                ($click->link_id ?? 'null')
            );

            // Check if we've seen this fingerprint in the last 24 hours
            $isUnique = true;
            $clickTime = $click->clicked_at;

            if (isset($seenFingerprints[$fingerprint])) {
                foreach ($seenFingerprints[$fingerprint] as $previousTime) {
                    if ($clickTime->diffInHours($previousTime) < 24) {
                        $isUnique = false;
                        break;
                    }
                }
            }

            if ($isUnique) {
                // Record this fingerprint and time
                if (! isset($seenFingerprints[$fingerprint])) {
                    $seenFingerprints[$fingerprint] = [];
                }
                $seenFingerprints[$fingerprint][] = $clickTime;

                // Count the unique click
                if ($click->type === self::TYPE_PAGE_VIEW) {
                    $uniquePageViews++;
                } elseif ($click->type === self::TYPE_EXTERNAL_PROJECT) {
                    $uniqueExternalProject++;
                } elseif ($click->type === self::TYPE_CUSTOM_LINK && $click->link_id) {
                    if (! isset($uniqueCustomLinks[$click->link_id])) {
                        $uniqueCustomLinks[$click->link_id] = ['unique_clicks' => 0];
                    }
                    $uniqueCustomLinks[$click->link_id]['unique_clicks']++;
                }
            }
        }

        return [
            'page_views' => $uniquePageViews,
            'external_project' => $uniqueExternalProject,
            'custom_links' => $uniqueCustomLinks,
        ];
    }

    /**
     * Get the game that this click stat belongs to
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
