<?php

declare(strict_types=1);

use App\Support\Stats\ArrayStatsPayload;
use App\Support\Stats\NdjsonStatsPayload;
use App\Support\Stats\StatsPayloadFactory;
use Illuminate\Support\Facades\File;

function statsFixtureDirectory(): string
{
    $directory = storage_path('framework/testing/stats-payload-' . uniqid());
    File::makeDirectory($directory, 0755, true);

    return $directory;
}

function writeNdjsonFixture(string $directory): string
{
    $path = "{$directory}/stats.ndjson";
    $records = [
        ['type' => 'meta', 'schema' => 'fvn.renpy_stats.v1'],
        ['type' => 'languages', 'key' => 'default', 'entry' => ['blocks' => 2, 'words' => 6, 'menus' => 1, 'options' => 2]],
        ['type' => 'characters', 'language' => 'default', 'key' => 'eileen', 'entry' => ['display_name' => 'Eileen', 'blocks' => 1, 'words' => 3]],
        ['type' => 'languages', 'key' => 'italian', 'entry' => ['blocks' => 1, 'words' => 3, 'menus' => 0, 'options' => 0]],
        ['type' => 'file_statistics', 'entry' => ['summary' => ['total_size' => 42]]],
        ['type' => 'route_labels', 'entry' => ['name' => 'start', 'file' => 'script.rpy', 'line' => 1]],
        ['type' => 'route_edges', 'entry' => ['from_label' => 'start', 'to_label' => 'end']],
        ['type' => 'dialogue_lines', 'language' => 'default', 'entry' => ['character' => 'eileen', 'text' => 'Hello', 'file' => 'script.rpy', 'line' => 3]],
        ['type' => 'dialogue_lines', 'language' => 'default', 'entry' => ['character' => 'eileen', 'text' => 'Again', 'file' => 'script.rpy', 'line' => 4]],
        ['type' => 'dialogue_lines', 'language' => 'italian', 'entry' => ['character' => 'eileen', 'text' => 'Ciao', 'file' => 'script.rpy', 'line' => 3]],
    ];

    $lines = array_map(fn (array $record): string => json_encode($record), $records);
    File::put($path, implode("\n", $lines) . "\n");

    return $path;
}

it('reads the same content from newline-delimited and in-memory payloads', function () {
    $directory = statsFixtureDirectory();

    try {
        $payloads = [
            'ndjson' => StatsPayloadFactory::fromFile(writeNdjsonFixture($directory)),
            'array' => new ArrayStatsPayload([
                'languages' => [
                    'default' => ['blocks' => 2, 'words' => 6, 'menus' => 1, 'options' => 2, 'characters' => [
                        'eileen' => ['display_name' => 'Eileen', 'blocks' => 1, 'words' => 3],
                    ]],
                    'italian' => ['blocks' => 1, 'words' => 3, 'menus' => 0, 'options' => 0, 'characters' => []],
                ],
                'file_statistics' => ['summary' => ['total_size' => 42]],
                'route_labels' => [['name' => 'start', 'file' => 'script.rpy', 'line' => 1]],
                'route_edges' => [['from_label' => 'start', 'to_label' => 'end']],
                'dialogue_lines' => [
                    'default' => [
                        ['character' => 'eileen', 'text' => 'Hello', 'file' => 'script.rpy', 'line' => 3],
                        ['character' => 'eileen', 'text' => 'Again', 'file' => 'script.rpy', 'line' => 4],
                    ],
                    'italian' => [
                        ['character' => 'eileen', 'text' => 'Ciao', 'file' => 'script.rpy', 'line' => 3],
                    ],
                ],
            ]),
        ];

        expect($payloads['ndjson'])->toBeInstanceOf(NdjsonStatsPayload::class);

        foreach ($payloads as $label => $payload) {
            expect($payload->languages())->toHaveKeys(['default', 'italian'], $label)
                ->and($payload->languages()['default']['words'])->toBe(6, $label)
                ->and($payload->languages()['default']['characters']['eileen']['display_name'])->toBe('Eileen', $label)
                ->and($payload->fileStatistics())->toBe(['summary' => ['total_size' => 42]], $label)
                ->and($payload->withoutFileStatistics()->fileStatistics())->toBeNull($label);

            $labels = iterator_to_array($payload->section('route_labels'), false);
            expect($labels)->toHaveCount(1, $label)
                ->and($labels[0]['name'])->toBe('start', $label)
                ->and(iterator_to_array($payload->section('route_edges'), false))->toHaveCount(1, $label)
                ->and(iterator_to_array($payload->section('route_variables'), false))->toBe([], $label);

            $lines = iterator_to_array($payload->dialogueLines(), false);
            expect($lines)->toHaveCount(3, $label)
                // Order is preserved: consumers rely on it for row ordering.
                ->and(array_map(fn (array $pair): string => $pair[1]['text'], $lines))
                ->toBe(['Hello', 'Again', 'Ciao'], $label)
                ->and(array_map(fn (array $pair): string => $pair[0], $lines))
                ->toBe(['default', 'default', 'italian'], $label);
        }
    } finally {
        File::deleteDirectory($directory);
    }
});

it('reads a large document without holding it in memory', function () {
    $directory = statsFixtureDirectory();
    $path = "{$directory}/big.ndjson";

    try {
        $handle = fopen($path, 'wb');
        fwrite($handle, json_encode(['type' => 'meta', 'schema' => 'fvn.renpy_stats.v1']) . "\n");
        fwrite($handle, json_encode(['type' => 'languages', 'key' => 'default', 'entry' => ['blocks' => 50000]]) . "\n");
        for ($i = 0; $i < 50000; $i++) {
            fwrite($handle, json_encode([
                'type' => 'dialogue_lines',
                'language' => 'default',
                'entry' => [
                    'character' => 'char_' . ($i % 50),
                    'text' => str_repeat('some dialogue text ', 10) . $i,
                    'file' => 'script.rpy',
                    'line' => $i,
                ],
            ]) . "\n");
        }
        fclose($handle);

        $payload = new NdjsonStatsPayload($path);
        $before = memory_get_usage();
        $count = 0;

        foreach ($payload->dialogueLines() as [$language, $line]) {
            $count++;
        }

        $growth = memory_get_usage() - $before;

        expect($count)->toBe(50000)
            // The document is several megabytes; iterating must not scale with it.
            ->and($growth)->toBeLessThan(1024 * 1024)
            ->and(File::size($path))->toBeGreaterThan(5 * 1024 * 1024);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('releases only the temporary files it owns', function () {
    $directory = statsFixtureDirectory();

    try {
        $borrowed = writeNdjsonFixture($directory);
        (new NdjsonStatsPayload($borrowed))->release();
        expect(File::exists($borrowed))->toBeTrue();

        (new NdjsonStatsPayload($borrowed, ownsFile: true))->release();
        expect(File::exists($borrowed))->toBeFalse();
    } finally {
        File::deleteDirectory($directory);
    }
});

it('rejects a truncated document rather than importing it silently', function () {
    $directory = statsFixtureDirectory();

    try {
        $path = "{$directory}/truncated.ndjson";
        File::put($path, json_encode(['type' => 'meta', 'schema' => 'fvn.renpy_stats.v1']) . "\n"
            . '{"type":"dialogue_lines","language":"default","entry":{"text":"cut off he');

        $payload = new NdjsonStatsPayload($path);

        expect(fn () => iterator_to_array($payload->dialogueLines(), false))
            ->toThrow(RuntimeException::class, 'Malformed stats payload at line 2');
    } finally {
        File::deleteDirectory($directory);
    }
});

it('refuses a document that is not analyzer output', function () {
    $directory = statsFixtureDirectory();

    try {
        $path = "{$directory}/not-analyzer-output.json";
        File::put($path, json_encode(['languages' => ['eng' => ['blocks' => 1]]]));

        expect(fn () => StatsPayloadFactory::fromFile($path))
            ->toThrow(RuntimeException::class, 'Unrecognized stats file');
    } finally {
        File::deleteDirectory($directory);
    }
});
