<?php

declare(strict_types=1);

namespace App\Support\Stats;

/**
 * A source of extracted Ren'Py statistics.
 *
 * Large games produce stats documents with hundreds of thousands of dialogue
 * lines. Decoding one into a PHP array costs several times the document size in
 * memory, so consumers must never hold the whole thing at once. Implementations
 * expose the small aggregate sections eagerly and the large collections lazily,
 * so callers can persist them in batches.
 *
 * Analyzer output is read by {@see NdjsonStatsPayload}. {@see ArrayStatsPayload}
 * covers callers that legitimately hold a small stats array already.
 */
interface StatsPayload
{
    /**
     * Per-language aggregate stats, keyed by the language key used by the
     * extractor ('default' for the source language). Each entry keeps the
     * historical shape, including its nested 'characters' map.
     *
     * @return array<string, array<string, mixed>>
     */
    public function languages(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function fileStatistics(): ?array;

    /**
     * A copy that reports no file statistics, used when the archive is an
     * optimized derivative whose file inventory no longer describes the original.
     */
    public function withoutFileStatistics(): static;

    /**
     * Lazily yield entries of a top-level list section, one of the route_*
     * collections. A missing section yields nothing.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function section(string $name): iterable;

    /**
     * Lazily yield every dialogue line, tagged with its extractor language key.
     *
     * @return iterable<int, array{0: string, 1: array<string, mixed>}>
     */
    public function dialogueLines(): iterable;

    /**
     * Release any temporary file backing this payload.
     */
    public function release(): void;
}
