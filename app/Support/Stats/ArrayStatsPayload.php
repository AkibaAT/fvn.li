<?php

declare(strict_types=1);

namespace App\Support\Stats;

/**
 * A payload already held in memory.
 *
 * Used by tests and by callers that legitimately have a small stats array in
 * hand. Anything sized like a real game should come from a file-backed payload
 * instead, so the lines are never all resident at once.
 */
class ArrayStatsPayload implements StatsPayload
{
    /**
     * @param  array<string, mixed>  $stats
     */
    public function __construct(private readonly array $stats) {}

    public function languages(): array
    {
        $languages = $this->stats['languages'] ?? [];

        return is_array($languages) ? $languages : [];
    }

    public function fileStatistics(): ?array
    {
        $fileStatistics = $this->stats['file_statistics'] ?? null;

        return is_array($fileStatistics) ? $fileStatistics : null;
    }

    public function withoutFileStatistics(): static
    {
        $stats = $this->stats;
        unset($stats['file_statistics']);

        return new static($stats);
    }

    public function section(string $name): iterable
    {
        $section = $this->stats[$name] ?? [];

        if (! is_array($section)) {
            return;
        }

        foreach ($section as $entry) {
            if (is_array($entry)) {
                yield $entry;
            }
        }
    }

    public function dialogueLines(): iterable
    {
        $dialogueLines = $this->stats['dialogue_lines'] ?? [];

        if (! is_array($dialogueLines)) {
            return;
        }

        foreach ($dialogueLines as $languageKey => $lines) {
            if (! is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                if (is_array($line)) {
                    yield [(string) $languageKey, $line];
                }
            }
        }
    }

    public function release(): void
    {
        // Nothing to release; this payload owns no temporary file.
    }
}
