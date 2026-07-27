<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Convicts traffic that never carries a session forward.
 *
 * A browser returns the session cookie it was given, so any real population
 * sharing a user agent leaves multi-hit sessions behind. A client that starts
 * a fresh session on every request is keeping no state at all, and doing so
 * from a new address each time is not a browser.
 *
 * The verdict is necessarily collective — a session of one is what every
 * genuine first-time visitor looks like — and is reached per user agent per
 * day. Judging a whole history instead would let a period of honest traffic
 * excuse a later campaign under the same user agent string, and judging a
 * single fixed window would leave every other day unclassified.
 */
class SessionChurnDetector
{
    private const string SCRATCH_TABLE = 'session_churn_convicted';

    /**
     * Rows belonging to an erased account are human by construction and carry
     * no session of their own to judge, so they neither inform the verdict nor
     * receive it.
     */
    private const string EXCLUDE_ANONYMISED = " AND session_id NOT LIKE 'anonymized\\_%'";

    /**
     * Apply the verdict, returning the number of rows convicted and acquitted.
     *
     * @param  CarbonInterface|null  $since  restricts both directions to recent days
     * @return array{convicted: int, acquitted: int, user_agent_days: int}
     */
    public static function apply(?CarbonInterface $since = null, bool $dryRun = false): array
    {
        self::buildConvictedTable($since);

        try {
            $windowSql = $since === null ? '' : ' AND cs.clicked_at >= ?';
            $windowBindings = $since === null ? [] : [$since];

            $convictable = (int) (DB::selectOne('
                SELECT COUNT(*) AS total
                FROM click_stats cs
                JOIN ' . self::SCRATCH_TABLE . ' c
                  ON c.user_agent = cs.user_agent AND c.day = cs.clicked_at::date
                WHERE cs.bot_reason IS NULL'
                . str_replace('session_id', 'cs.session_id', self::EXCLUDE_ANONYMISED)
                . $windowSql, $windowBindings)->total ?? 0);

            $acquittable = (int) (DB::selectOne('
                SELECT COUNT(*) AS total
                FROM click_stats cs
                LEFT JOIN ' . self::SCRATCH_TABLE . ' c
                  ON c.user_agent = cs.user_agent AND c.day = cs.clicked_at::date
                WHERE cs.bot_reason = ? AND c.user_agent IS NULL' . $windowSql,
                array_merge([BotDetectionService::REASON_SESSION_CHURN], $windowBindings)
            )->total ?? 0);

            $userAgentDays = (int) (DB::selectOne('SELECT COUNT(*) AS total FROM ' . self::SCRATCH_TABLE)->total ?? 0);

            if (! $dryRun) {
                DB::update('
                    UPDATE click_stats cs
                    SET bot_reason = ?
                    FROM ' . self::SCRATCH_TABLE . ' c
                    WHERE cs.user_agent = c.user_agent
                      AND cs.clicked_at::date = c.day
                      AND cs.bot_reason IS NULL'
                    . str_replace('session_id', 'cs.session_id', self::EXCLUDE_ANONYMISED)
                    . $windowSql,
                    array_merge([BotDetectionService::REASON_SESSION_CHURN], $windowBindings)
                );

                // Anti-join rather than a correlated NOT EXISTS: the planner
                // hashes the scratch table once instead of probing per row.
                DB::update('
                    UPDATE click_stats cs
                    SET bot_reason = NULL
                    FROM (
                        SELECT inner_cs.id
                        FROM click_stats inner_cs
                        LEFT JOIN ' . self::SCRATCH_TABLE . ' c
                          ON c.user_agent = inner_cs.user_agent AND c.day = inner_cs.clicked_at::date
                        WHERE inner_cs.bot_reason = ?
                          AND c.user_agent IS NULL'
                    . ($since === null ? '' : ' AND inner_cs.clicked_at >= ?') . '
                    ) stale
                    WHERE cs.id = stale.id',
                    array_merge([BotDetectionService::REASON_SESSION_CHURN], $windowBindings)
                );
            }

            return [
                'convicted' => $convictable,
                'acquitted' => $acquittable,
                'user_agent_days' => $userAgentDays,
            ];
        } finally {
            DB::statement('DROP TABLE IF EXISTS ' . self::SCRATCH_TABLE);
        }
    }

    /**
     * Materialise the convicted user agent days into a temporary table.
     *
     * Aggregating once and storing the result keeps the planner from redoing
     * the whole group-by for every statement that consults it.
     *
     * Rows already convicted by a per-row rule are excluded from the sample so
     * a crawler cannot drag an otherwise honest user agent over the thresholds;
     * rows holding this verdict stay in, so it can be revisited.
     */
    private static function buildConvictedTable(?CarbonInterface $since): void
    {
        DB::statement('DROP TABLE IF EXISTS ' . self::SCRATCH_TABLE);

        $bindings = [
            (int) Config::get('analytics.bot_detection.session_churn.min_rows', 50),
            (int) Config::get('analytics.bot_detection.session_churn.max_authenticated_rows', 0),
            (float) Config::get('analytics.bot_detection.session_churn.min_subnet_dispersion', 0.5),
            (float) Config::get('analytics.bot_detection.session_churn.max_session_reuse_ratio', 0.01),
        ];

        DB::statement('
            CREATE TEMPORARY TABLE ' . self::SCRATCH_TABLE . ' AS
            SELECT user_agent, day
            FROM (
                SELECT
                    user_agent,
                    clicked_at::date AS day,
                    COUNT(*) AS rows_seen,
                    COUNT(DISTINCT session_id) AS sessions,
                    COUNT(DISTINCT ip_address) AS subnets,
                    COUNT(*) FILTER (WHERE user_id IS NOT NULL) AS authenticated_rows
                FROM click_stats
                WHERE user_agent IS NOT NULL
                  AND (bot_reason IS NULL OR bot_reason = \'' . BotDetectionService::REASON_SESSION_CHURN . '\')'
            . self::EXCLUDE_ANONYMISED
            . ($since === null ? '' : ' AND clicked_at >= ?') . '
                GROUP BY user_agent, clicked_at::date
            ) per_user_agent_day
            WHERE rows_seen >= ?
              AND authenticated_rows <= ?
              AND subnets::numeric / NULLIF(rows_seen, 0) >= ?
              AND (rows_seen - sessions)::numeric / NULLIF(sessions, 0) <= ?',
            $since === null ? $bindings : array_merge([$since], $bindings)
        );

        DB::statement('CREATE INDEX ON ' . self::SCRATCH_TABLE . ' (user_agent, day)');
    }
}
