<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ObserverSearchIndexService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ReindexCharacterDialogue implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public int $timeout = 120;

    public int $uniqueFor = 30;

    public function __construct(
        public int $characterId,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->characterId;
    }

    public function handle(ObserverSearchIndexService $searchIndexService): void
    {
        $gameIds = DB::table('version_dialogue_lines')
            ->where('character_id', $this->characterId)
            ->join('game_versions', 'version_dialogue_lines.game_version_id', '=', 'game_versions.id')
            ->distinct()
            ->pluck('game_versions.game_id');

        if ($gameIds->isNotEmpty()) {
            $searchIndexService->reindexDialogue(
                $gameIds,
                'character change',
                ['character_id' => $this->characterId],
            );
        }
    }
}
