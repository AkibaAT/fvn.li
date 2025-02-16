<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkSuspiciousRater extends Command
{
    protected $signature = 'rater:mark-suspicious {rater_id} {--reason=} {--unmark}';
    protected $description = 'Mark or unmark a rater as suspicious';

    public function handle(): void
    {
        $raterId = $this->argument('rater_id');
        $reason = $this->option('reason');
        $unmark = $this->option('unmark');

        $rater = DB::table('raters')->where('id', $raterId)->first();

        if (! $rater) {
            $this->error("Rater {$raterId} not found");

            return;
        }

        DB::table('raters')
            ->where('id', $raterId)
            ->update([
                'is_suspicious' => ! $unmark,
                'suspicion_reason' => $unmark ? null : $reason,
                'marked_suspicious_at' => $unmark ? null : now(),
            ]);

        // Reset their processed ratings to trigger weight recalculation
        DB::table('ratings')
            ->where('rater_id', $raterId)
            ->update(['processed_at' => null]);

        $this->info("Rater {$raterId} " . ($unmark ? 'unmarked' : 'marked') . ' as suspicious');
    }
}
