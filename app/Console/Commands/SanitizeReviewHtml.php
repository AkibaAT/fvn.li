<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Rating;
use App\Services\HtmlSanitizerService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SanitizeReviewHtml extends Command
{
    protected $signature = 'ratings:sanitize-reviews
                            {--apply : Rewrite stored review HTML}
                            {--force : Skip confirmation prompts when applying}
                            {--batch-size=500 : Number of ratings to process in each batch}
                            {--ids= : Comma-separated rating IDs to process}';

    protected $description = 'Report or sanitize stored rating review HTML';

    public function handle(HtmlSanitizerService $sanitizer): int
    {
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $batchSize = (int) $this->option('batch-size');
        $ids = $this->parseIds((string) ($this->option('ids') ?? ''));

        if ($batchSize < 1 || $batchSize > 10000) {
            $this->error('Batch size must be between 1 and 10000');

            return self::FAILURE;
        }

        if ($ids === false) {
            $this->error('--ids must be a comma-separated list of positive integer rating IDs');

            return self::FAILURE;
        }

        $query = $this->reviewQuery($ids);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No stored reviews found to scan.');

            return self::SUCCESS;
        }

        $this->info("Scanning {$total} stored review(s).");

        if (! $apply) {
            $this->warn('REPORT ONLY - no review rows will be updated. Re-run with --apply to rewrite stored HTML.');
        } elseif (! $force && ! $this->confirm("Rewrite sanitized HTML for changed reviews among {$total} scanned row(s)?")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $stats = [
            'scanned' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'emptied' => 0,
            'errors' => 0,
        ];

        $lastId = 0;
        do {
            $ratings = (clone $query)
                ->where('id', '>', $lastId)
                ->orderBy('id', 'asc')
                ->limit($batchSize)
                ->get();

            if ($ratings->isEmpty()) {
                break;
            }

            foreach ($ratings as $rating) {
                try {
                    $stats['scanned']++;
                    $lastId = (int) $rating->id;

                    $original = (string) $rating->review;
                    $sanitized = $sanitizer->sanitizeReview($original) ?? '';

                    if ($original === $sanitized) {
                        $stats['unchanged']++;

                        continue;
                    }

                    $stats['changed']++;
                    $isReviewed = ! empty(trim(strip_tags($sanitized)));
                    if (! $isReviewed) {
                        $stats['emptied']++;
                    }

                    if (! $apply) {
                        continue;
                    }

                    $rating->timestamps = false;
                    $rating->forceFill([
                        'review' => $sanitized,
                        'is_reviewed' => $isReviewed,
                    ])->saveQuietly();

                } catch (Exception $e) {
                    $stats['errors']++;
                    $this->newLine();
                    $this->error("Failed to process rating {$rating->id}: {$e->getMessage()}");
                }
            }
        } while ($ratings->count() === $batchSize);

        $this->table(
            ['Scanned', 'Changed', 'Unchanged', 'Emptied', 'Errors'],
            [[$stats['scanned'], $stats['changed'], $stats['unchanged'], $stats['emptied'], $stats['errors']]]
        );

        if ($apply) {
            $this->info("Sanitized {$stats['changed']} stored review(s).");
        } else {
            $this->info("Stored reviews that would be updated: {$stats['changed']}");
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reviewQuery(array $ids): Builder
    {
        return Rating::query()
            ->whereNotNull('review')
            ->where('review', '!=', '')
            ->when($ids !== [], fn (Builder $query) => $query->whereIn('id', $ids))
            ->orderBy('id');
    }

    /**
     * @return array<int>|false
     */
    private function parseIds(string $ids): array|false
    {
        $ids = trim($ids);
        if ($ids === '') {
            return [];
        }

        $parsed = [];
        foreach (explode(',', $ids) as $id) {
            $id = trim($id);
            if ($id === '' || ! ctype_digit($id) || (int) $id < 1) {
                return false;
            }

            $parsed[] = (int) $id;
        }

        return array_values(array_unique($parsed));
    }
}
