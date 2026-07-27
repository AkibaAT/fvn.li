<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClickStat;
use App\Services\BotDetectionService;
use App\Services\SessionChurnDetector;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillClickStatBotFlags extends Command
{
    protected $signature = 'analytics:backfill-bot-flags
        {--dry-run : Report what would change without writing}
        {--chunk=5000 : Rows loaded per pass}
        {--days= : Limit the per-row pass to this many days back; the session churn pass always weighs the full history}';

    protected $description = 'Re-classify stored click stats as human or automated using the current bot detection rules';

    public function handle(): int
    {
        BotDetectionService::flushCache();

        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(100, (int) $this->option('chunk'));

        $days = $this->option('days') === null ? null : max(1, (int) $this->option('days'));

        // Snapped to a day boundary because session churn is judged per
        // calendar day, and a window cutting into one would have it judged on
        // a fraction of its traffic.
        $since = $days === null ? null : now()->subDays($days)->startOfDay();

        $scope = fn () => ClickStat::query()
            ->when($since !== null, fn ($query) => $query->where('clicked_at', '>=', $since));

        $total = $scope()->count();
        $this->info(sprintf(
            'Re-classifying %s click stat rows%s...',
            number_format($total),
            $days === null ? '' : sprintf(' from the last %d days', $days)
        ));

        $scanned = 0;
        $changed = 0;

        /** @var array<string, array<int, int>> $pending keyed by reason, 'human' for cleared rows */
        $pending = [];

        $scope()
            ->select(['id', 'user_agent', 'ip_address', 'session_id', 'bot_reason'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$scanned, &$changed, &$pending, $dryRun, $total, $chunkSize) {
                foreach ($rows as $row) {
                    // Session churn is decided in aggregate afterwards, so
                    // rows holding that verdict are left for that pass.
                    if ($row->bot_reason === BotDetectionService::REASON_SESSION_CHURN) {
                        continue;
                    }

                    $reason = BotDetectionService::detect($row->user_agent, $row->ip_address, $row->session_id);

                    if ($reason === $row->bot_reason) {
                        continue;
                    }

                    $pending[$reason ?? 'human'][] = $row->id;
                    $changed++;
                }

                $scanned += $rows->count();

                if (! $dryRun) {
                    $pending = $this->flush($pending, $chunkSize);
                }

                $this->line(sprintf(
                    'Scanned %s/%s rows; %s re-classified.',
                    number_format($scanned),
                    number_format($total),
                    number_format($changed)
                ));
            });

        if (! $dryRun) {
            $this->flush($pending, 0);
        }

        $changed += $this->applySessionChurn($dryRun, $since);

        $this->info(sprintf(
            '%s %s of %s rows.',
            $dryRun ? 'Would re-classify' : 'Re-classified',
            number_format($changed),
            number_format($total)
        ));

        $this->reportBreakdown($dryRun);

        if (! $dryRun && $changed > 0) {
            Log::info('Backfilled click stat bot flags', [
                'scanned' => $scanned,
                'changed' => $changed,
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Convict or acquit whole user agents on the session churn rule.
     *
     * Runs after the per-row pass so its sample reflects the current verdicts,
     * and returns the number of rows whose classification moved.
     */
    private function applySessionChurn(bool $dryRun, ?CarbonInterface $since): int
    {
        $result = SessionChurnDetector::apply($since, $dryRun);

        $this->info(sprintf(
            '%s user agent days meet the session churn thresholds: %s rows convicted, %s acquitted.',
            number_format($result['user_agent_days']),
            number_format($result['convicted']),
            number_format($result['acquitted'])
        ));

        return $result['convicted'] + $result['acquitted'];
    }

    /**
     * Write out buckets that have reached the batch threshold.
     *
     * @param  array<string, array<int, int>>  $pending
     * @param  int  $threshold  a bucket smaller than this is kept for the next pass; 0 flushes everything
     * @return array<string, array<int, int>>
     */
    private function flush(array $pending, int $threshold): array
    {
        foreach ($pending as $reason => $ids) {
            if (count($ids) < $threshold) {
                continue;
            }

            foreach (array_chunk($ids, 5000) as $idChunk) {
                // Written through the query builder so re-classification does
                // not disturb the audit timestamps on existing rows.
                DB::table('click_stats')
                    ->whereIn('id', $idChunk)
                    ->update(['bot_reason' => $reason === 'human' ? null : $reason]);
            }

            unset($pending[$reason]);
        }

        return $pending;
    }

    private function reportBreakdown(bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        $rows = DB::table('click_stats')
            ->selectRaw('COALESCE(bot_reason, \'human\') as classification, COUNT(*) as total')
            ->groupBy('classification')
            ->orderByDesc('total')
            ->get();

        $this->table(
            ['Classification', 'Rows'],
            $rows->map(fn ($row) => [$row->classification, number_format((int) $row->total)])->all()
        );
    }
}
