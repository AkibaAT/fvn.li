<?php

declare(strict_types=1);

namespace App\Support\Stats;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * A payload backed by a newline-delimited JSON document.
 *
 * This is the format the analyzer emits. Every line is one self-contained
 * record, so both the extractor and this reader work a record at a time and
 * neither ever holds the full document. Records are written in a fixed order --
 * aggregates first, dialogue lines last -- which lets the small sections be
 * read without touching the large one.
 */
class NdjsonStatsPayload implements StatsPayload
{
    public const SCHEMA = 'fvn.renpy_stats.v1';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $languages = null;

    /** @var array<string, mixed>|null */
    private ?array $fileStatistics = null;

    private bool $preambleRead = false;

    public function __construct(
        private readonly string $path,
        private readonly bool $ownsFile = false,
        private readonly bool $suppressFileStatistics = false
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function languages(): array
    {
        $this->readPreamble();

        return $this->languages ?? [];
    }

    public function fileStatistics(): ?array
    {
        if ($this->suppressFileStatistics) {
            return null;
        }

        $this->readPreamble();

        return $this->fileStatistics;
    }

    public function withoutFileStatistics(): static
    {
        return new static($this->path, $this->ownsFile, true);
    }

    public function section(string $name): iterable
    {
        foreach ($this->records() as $record) {
            // Dialogue lines are written last, so anything else is already past.
            if (($record['type'] ?? null) === 'dialogue_lines') {
                return;
            }

            if (($record['type'] ?? null) === $name && isset($record['entry']) && is_array($record['entry'])) {
                yield $record['entry'];
            }
        }
    }

    public function dialogueLines(): iterable
    {
        foreach ($this->records() as $record) {
            if (($record['type'] ?? null) !== 'dialogue_lines' || ! isset($record['entry']) || ! is_array($record['entry'])) {
                continue;
            }

            yield [(string) ($record['language'] ?? 'default'), $record['entry']];
        }
    }

    public function release(): void
    {
        if ($this->ownsFile && File::exists($this->path)) {
            File::delete($this->path);
        }
    }

    /**
     * Read everything preceding the dialogue lines. The aggregate sections are
     * small enough to hold; the dialogue lines that follow are not, and reading
     * stops before reaching them.
     */
    private function readPreamble(): void
    {
        if ($this->preambleRead) {
            return;
        }

        $this->preambleRead = true;
        $languages = [];

        foreach ($this->records() as $record) {
            $type = $record['type'] ?? null;

            if ($type === 'dialogue_lines') {
                break;
            }

            if ($type === 'languages' && isset($record['key'])) {
                $entry = is_array($record['entry'] ?? null) ? $record['entry'] : [];
                $key = (string) $record['key'];
                $languages[$key] = array_merge($entry, ['characters' => $languages[$key]['characters'] ?? []]);

                continue;
            }

            if ($type === 'characters' && isset($record['language'], $record['key'])) {
                $language = (string) $record['language'];
                $languages[$language]['characters'][(string) $record['key']] =
                    is_array($record['entry'] ?? null) ? $record['entry'] : [];

                continue;
            }

            if ($type === 'file_statistics' && is_array($record['entry'] ?? null)) {
                $this->fileStatistics = $record['entry'];
            }
        }

        $this->languages = $languages;
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function records(): iterable
    {
        $handle = @fopen($this->path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not open stats payload: {$this->path}");
        }

        try {
            $lineNumber = 0;

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $record = json_decode($line, true);
                if (! is_array($record)) {
                    throw new RuntimeException(
                        "Malformed stats payload at line {$lineNumber} of {$this->path}: " . json_last_error_msg()
                    );
                }

                yield $record;
            }
        } finally {
            fclose($handle);
        }
    }
}
