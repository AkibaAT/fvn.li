<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ChangeLog;
use App\Models\Game;
use App\Services\GameDelistingService;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Console\Command;

class BackfillLostGames extends Command
{
    private const REFRESH_COMMANDS = ['games:refresh', 'games:refresh-feedless'];

    protected $signature = 'games:backfill-lost {--dry-run : Report what would change without writing}';

    protected $description = 'Re-list games hidden by the refresh commands as lost media';

    public function handle(GameDelistingService $delisting): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];

        $candidates = Game::query()
            ->where('is_visible', false)
            ->where('is_moderation_hidden', false)
            ->whereNull('delisted_at')
            ->whereNotNull('first_visible_at')
            ->orderBy('name')
            ->get();

        foreach ($candidates as $game) {
            $hiddenAt = $this->hiddenByRefreshAt($game);
            if (! $hiddenAt) {
                continue;
            }

            try {
                $reason = $delisting->storePageReason($game);
            } catch (Exception $e) {
                $rows[] = [$game->id, $game->name, 'skipped: ' . $e->getMessage()];

                continue;
            }

            if (! $reason) {
                $rows[] = [$game->id, $game->name, 'skipped: store page looks available'];

                continue;
            }

            if (! $dryRun) {
                $game->is_visible = true;
                $game->delisted_at = $hiddenAt;
                $game->delisted_reason = $reason;
                $game->save();
            }

            $rows[] = [$game->id, $game->name, $reason->value];
        }

        $this->table(['ID', 'Game', 'Result'], $rows);
        $this->info(($dryRun ? 'Dry run: no changes written. ' : '') . count($rows) . ' game(s) hidden by refresh commands.');

        return Command::SUCCESS;
    }

    private function hiddenByRefreshAt(Game $game): ?CarbonInterface
    {
        $change = ChangeLog::query()
            ->where('entity_type', Game::class)
            ->where('entity_id', $game->id)
            ->whereNotNull('old_values->is_visible')
            ->whereNotNull('new_values->is_visible')
            ->latest('timestamp')
            ->first();

        $hiddenByRefresh = $change
            && $change->new_values['is_visible'] === false
            && $change->source === ChangeLog::SOURCE_COMMAND
            && in_array($change->context['command']['name'] ?? null, self::REFRESH_COMMANDS, true);

        return $hiddenByRefresh ? $change->timestamp : null;
    }
}
