<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\BotDetectionService;
use App\Services\IpAnonymizationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClickStat extends Model
{
    public const string TYPE_PAGE_VIEW = 'page_view';

    public const string TYPE_CUSTOM_LINK = 'custom_link';

    public const string TYPE_EXTERNAL_PROJECT = 'external_project';

    /**
     * Half-life decay constant for trending scores: ln(2)/7, so a view is
     * worth half as much after seven days.
     */
    public const float TRENDING_DECAY = 0.099;

    public const int TRENDING_WINDOW_DAYS = 14;

    protected $fillable = [
        'game_id',
        'user_id',
        'type',
        'link_id',
        'session_id',
        'ip_address',
        'user_agent',
        'referrer',
        'clicked_at',
        'bot_reason',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    /**
     * Record a click with time-based deduplication (24-hour window)
     * This follows industry best practices for click tracking analytics
     *
     * Automated hits are stored alongside human ones but carry the reason they
     * were classified as such, which keeps the classification auditable and
     * reversible while holding them out of every analytics surface.
     */
    public static function recordClick(
        int $gameId,
        string $type,
        string $sessionId,
        ?string $linkId = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referrer = null
    ): bool {
        $botReason = BotDetectionService::detect($userAgent, $ipAddress, $sessionId);

        // Always record the click for total counts
        self::create([
            'game_id' => $gameId,
            'user_id' => $userId,
            'type' => $type,
            'link_id' => $linkId,
            'session_id' => $sessionId,
            'ip_address' => IpAnonymizationService::anonymize($ipAddress, 'subnet'),
            'user_agent' => $userAgent,
            'referrer' => $referrer,
            'clicked_at' => now(),
            'bot_reason' => $botReason,
        ]);

        return $botReason === null;
    }

    /**
     * Decayed trending scores keyed by game id, counting each visitor once per
     * 24 hours so a single browsing session cannot outweigh genuine reach.
     *
     * @param  array<int, int>|null  $gameIds  restricts the calculation when given
     * @return Collection<int, int>
     */
    public static function trendingScores(?array $gameIds = null): Collection
    {
        $window = self::TRENDING_WINDOW_DAYS;

        $orderedViews = DB::table('click_stats')
            ->where('type', self::TYPE_PAGE_VIEW)
            ->whereNull('bot_reason')
            ->where('clicked_at', '>=', DB::raw("NOW() - INTERVAL '{$window} days'"))
            ->selectRaw('
                game_id,
                clicked_at,
                LAG(clicked_at) OVER (
                    PARTITION BY game_id, ip_address, user_agent
                    ORDER BY clicked_at
                ) as previous_clicked_at
            ');

        if ($gameIds !== null) {
            $orderedViews->whereIn('game_id', $gameIds);
        }

        $decay = self::TRENDING_DECAY;

        return DB::query()
            ->fromSub($orderedViews, 'ordered_views')
            ->where(function ($query) {
                $query->whereNull('previous_clicked_at')
                    ->orWhereRaw("clicked_at >= previous_clicked_at + INTERVAL '24 hours'");
            })
            ->selectRaw("game_id, ROUND(COALESCE(SUM(EXP(-{$decay} * EXTRACT(EPOCH FROM (NOW() - clicked_at)) / 86400)), 0))::integer as score")
            ->groupBy('game_id')
            ->pluck('score', 'game_id');
    }

    public static function getMultipleGameStats(array $gameIds, ?Carbon $since = null): array
    {
        $result = [];

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

    public static function getGameStats(int $gameId, ?Carbon $since = null): array
    {
        $query = DB::table('click_stats')->where('game_id', $gameId)->whereNull('bot_reason');

        if ($since) {
            $query->where('clicked_at', '>=', $since);
        }

        $totalStats = $query->select([
            'type',
            'link_id',
            DB::raw('COUNT(*) as total_clicks'),
            DB::raw('MAX(clicked_at) as last_click'),
        ])
            ->groupBy(['type', 'link_id'])
            ->get();

        $uniqueStats = self::getUniqueClickStats($gameId, $since);

        $result = [
            'page_views_total' => 0,
            'last_page_view' => null,
            'external_project_total' => 0,
            'last_external_project' => null,
            'custom_links' => [],
        ];

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
     * Aggregate click statistics for the games a user owns.
     *
     * Counts only. The rows behind these totals describe how individual
     * visitors browsed the site, which is the visitors' personal data and not
     * the game owner's, so no per-event detail leaves this method however the
     * caller intends to use it. Automated traffic is excluded, matching what
     * the owner already sees on their dashboard.
     */
    public static function exportUserOwnedGameStats(int $userId): array
    {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $itchioUsername = $user->getItchioUsername();
        if (! $itchioUsername) {
            return [];
        }

        $ownedGames = $user->getOwnedGames();
        if ($ownedGames->isEmpty()) {
            return [];
        }

        $gameIds = $ownedGames->pluck('id')->toArray();

        // Totalled in the database so no visitor-level row is ever held in
        // memory here.
        $totals = DB::table('click_stats')
            ->whereIn('game_id', $gameIds)
            ->whereNull('bot_reason')
            ->groupBy('game_id')
            ->selectRaw(
                'game_id,
                COUNT(*) as total_clicks,
                COUNT(*) FILTER (WHERE type = ?) as page_views,
                COUNT(*) FILTER (WHERE type = ?) as external_project_clicks,
                COUNT(*) FILTER (WHERE type = ?) as custom_link_clicks,
                MIN(clicked_at) as first_tracked,
                MAX(clicked_at) as last_tracked',
                [self::TYPE_PAGE_VIEW, self::TYPE_EXTERNAL_PROJECT, self::TYPE_CUSTOM_LINK]
            )
            ->get()
            ->keyBy(fn ($row) => (int) $row->game_id);

        return [
            'user_id' => $userId,
            'exported_at' => now()->toISOString(),
            'total_entries' => (int) $totals->sum('total_clicks'),
            'games_tracked' => $ownedGames->map(function ($game) use ($totals) {
                $gameStats = $totals->get((int) $game->id);

                return [
                    'game_name' => $game->name,
                    'game_url' => $game->url,
                    'total_clicks' => (int) ($gameStats->total_clicks ?? 0),
                    'page_views' => (int) ($gameStats->page_views ?? 0),
                    'external_project_clicks' => (int) ($gameStats->external_project_clicks ?? 0),
                    'custom_link_clicks' => (int) ($gameStats->custom_link_clicks ?? 0),
                    'first_tracked' => $gameStats->first_tracked ?? null,
                    'last_tracked' => $gameStats->last_tracked ?? null,
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * Anonymize click statistics for a specific user
     * This is used when a user requests account deletion (GDPR Article 17)
     * Removes user_id and IP-derived identifiers while preserving aggregate analytics rows
     *
     * @return int the number of rows anonymised, so callers can tell an
     *             account with no recorded activity from a failed attempt
     */
    public static function anonymizePersonalDataForUser(int $userId): int
    {
        return self::where('user_id', $userId)->update([
            'user_id' => null,
            // A pseudonym rather than nothing: unique-visitor counts group on
            // the address, so an empty one would merge every erased account
            // into a single visitor and cost other developers their totals.
            'ip_address' => IpAnonymizationService::pseudonymizeIdentity('click-stat-visitor-' . $userId),
            'updated_at' => now(),
        ]);
    }

    public static function getDailyStats(int $gameId, int $days = 30): array
    {
        $today = now();
        $startDate = $today->copy()->subDays($days)->startOfDay();
        $endDate = $today->copy()->endOfDay();

        $dailyData = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $today->copy()->subDays($days - 1 - $i)->format('Y-m-d');
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

        $totalRows = DB::table('click_stats')
            ->where('game_id', $gameId)
            ->whereNull('bot_reason')
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->selectRaw('DATE(clicked_at) as date, type, link_id, COUNT(*) as total_clicks')
            ->groupByRaw('DATE(clicked_at), type, link_id')
            ->get();

        foreach ($totalRows as $row) {
            $date = (string) $row->date;
            $type = (string) $row->type;
            $linkId = $row->link_id;
            $totalClicks = (int) $row->total_clicks;

            if (! isset($dailyData[$date])) {
                continue;
            }

            if ($type === self::TYPE_PAGE_VIEW) {
                $dailyData[$date]['page_views_total'] += $totalClicks;
            } elseif ($type === self::TYPE_EXTERNAL_PROJECT) {
                $dailyData[$date]['external_project_total'] += $totalClicks;
            } elseif ($type === self::TYPE_CUSTOM_LINK && $linkId) {
                $dailyData[$date]['custom_links_total'] += $totalClicks;

                if (! isset($dailyData[$date]['custom_links_breakdown'][$linkId])) {
                    $dailyData[$date]['custom_links_breakdown'][$linkId] = [
                        'total' => 0,
                        'unique' => 0,
                    ];
                }
                $dailyData[$date]['custom_links_breakdown'][$linkId]['total'] += $totalClicks;
            }
        }

        $orderedClicks = DB::table('click_stats')
            ->where('game_id', $gameId)
            ->whereNull('bot_reason')
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->selectRaw("
                DATE(clicked_at) as date,
                type,
                link_id,
                clicked_at,
                LAG(clicked_at) OVER (
                    PARTITION BY ip_address, user_agent, type, COALESCE(link_id, '')
                    ORDER BY clicked_at
                ) as previous_clicked_at
            ");

        $uniqueRows = DB::query()
            ->fromSub($orderedClicks, 'ordered_clicks')
            ->where(function ($query) {
                $query->whereNull('previous_clicked_at')
                    ->orWhereRaw("clicked_at >= previous_clicked_at + INTERVAL '24 hours'");
            })
            ->selectRaw('date, type, link_id, COUNT(*) as unique_clicks')
            ->groupBy('date', 'type', 'link_id')
            ->get();

        foreach ($uniqueRows as $row) {
            $date = (string) $row->date;
            $type = (string) $row->type;
            $linkId = $row->link_id;
            $uniqueClicks = (int) $row->unique_clicks;

            if (! isset($dailyData[$date])) {
                continue;
            }

            if ($type === self::TYPE_PAGE_VIEW) {
                $dailyData[$date]['page_views_unique'] += $uniqueClicks;
            } elseif ($type === self::TYPE_EXTERNAL_PROJECT) {
                $dailyData[$date]['external_project_unique'] += $uniqueClicks;
            } elseif ($type === self::TYPE_CUSTOM_LINK && $linkId) {
                $dailyData[$date]['custom_links_unique'] += $uniqueClicks;

                if (isset($dailyData[$date]['custom_links_breakdown'][$linkId])) {
                    $dailyData[$date]['custom_links_breakdown'][$linkId]['unique'] += $uniqueClicks;
                }
            }
        }

        return array_values($dailyData);
    }

    public static function getLinkStats(int $gameId, int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        $game = Game::find($gameId);
        if (! $game || ! $game->additional_links) {
            return [];
        }

        $linkStats = [];

        foreach ($game->additional_links as $link) {
            $linkId = $link['id'];

            $totalClicks = self::human()
                ->where('game_id', $gameId)
                ->where('type', self::TYPE_CUSTOM_LINK)
                ->where('link_id', $linkId)
                ->where('clicked_at', '>=', $startDate)
                ->count();

            $uniqueClicks = self::human()
                ->where('game_id', $gameId)
                ->where('type', self::TYPE_CUSTOM_LINK)
                ->where('link_id', $linkId)
                ->where('clicked_at', '>=', $startDate)
                ->select('ip_address', 'user_agent')
                ->distinct()
                ->count();

            $dailyClicks = self::human()
                ->where('game_id', $gameId)
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

    private static function getUniqueClickStats(int $gameId, ?Carbon $since = null): array
    {
        $query = DB::table('click_stats')
            ->where('game_id', $gameId)
            ->whereNull('bot_reason')
            ->selectRaw("
                type,
                link_id,
                clicked_at,
                LAG(clicked_at) OVER (
                    PARTITION BY ip_address, user_agent, type, COALESCE(link_id, '')
                    ORDER BY clicked_at
                ) as previous_clicked_at
            ");

        if ($since) {
            $query->where('clicked_at', '>=', $since);
        }

        $uniqueRows = DB::query()
            ->fromSub($query, 'ordered_clicks')
            ->where(function ($query) {
                $query->whereNull('previous_clicked_at')
                    ->orWhereRaw("clicked_at >= previous_clicked_at + INTERVAL '24 hours'");
            })
            ->selectRaw('type, link_id, COUNT(*) as unique_clicks')
            ->groupBy('type', 'link_id')
            ->get();

        $uniquePageViews = 0;
        $uniqueExternalProject = 0;
        $uniqueCustomLinks = [];

        foreach ($uniqueRows as $row) {
            $type = (string) $row->type;
            $linkId = $row->link_id;
            $uniqueClicks = (int) $row->unique_clicks;

            if ($type === self::TYPE_PAGE_VIEW) {
                $uniquePageViews += $uniqueClicks;
            } elseif ($type === self::TYPE_EXTERNAL_PROJECT) {
                $uniqueExternalProject += $uniqueClicks;
            } elseif ($type === self::TYPE_CUSTOM_LINK && $linkId) {
                if (! isset($uniqueCustomLinks[$linkId])) {
                    $uniqueCustomLinks[$linkId] = ['unique_clicks' => 0];
                }
                $uniqueCustomLinks[$linkId]['unique_clicks'] += $uniqueClicks;
            }
        }

        return [
            'page_views' => $uniquePageViews,
            'external_project' => $uniqueExternalProject,
            'custom_links' => $uniqueCustomLinks,
        ];
    }

    /**
     * Limit a query to hits that were not classified as automated.
     */
    public function scopeHuman(Builder $query): Builder
    {
        return $query->whereNull('bot_reason');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
