<?php

declare(strict_types=1);

use App\Services\RenpySaveParser;

function pickleShortString(int $opcode, string $value): string
{
    return chr($opcode) . chr(strlen($value)) . $value;
}

function pickleLongString(int $opcode, string $value): string
{
    return chr($opcode) . pack('V', strlen($value)) . $value;
}

it('decompresses gzip, zlib, and raw save payloads', function () {
    $parser = new RenpySaveParser;
    $payload = 'renpy save bytes';

    expect($parser->decompress(gzencode($payload)))->toBe($payload)
        ->and($parser->decompress(gzcompress($payload)))->toBe($payload)
        ->and($parser->decompress($payload))->toBe($payload);
});

it('rejects payloads that exceed the save parser decompression limit', function () {
    $parser = new RenpySaveParser;
    $oversizedPayload = str_repeat('A', RenpySaveParser::MAX_DECOMPRESSED_BYTES + 1024);

    expect(fn () => $parser->decompress(gzencode($oversizedPayload)))
        ->toThrow(LengthException::class)
        ->and(fn () => $parser->decompress(gzcompress($oversizedPayload)))
        ->toThrow(LengthException::class)
        ->and(fn () => $parser->decompress($oversizedPayload))
        ->toThrow(LengthException::class);
});

it('extracts pickle string opcodes and ignores malformed lengths', function () {
    $parser = new RenpySaveParser;
    $huge = chr(0x58) . pack('V', 1048576) . 'ignored';
    $truncated = chr(0x8C) . chr(20) . 'short';
    $payload = implode('', [
        'noise',
        pickleShortString(0x8C, 'start'),
        pickleLongString(0x58, 'chapter_one'),
        chr(0x8D) . pack('P', strlen('wide_label')) . 'wide_label',
        pickleShortString(0x55, 'legacy_short'),
        pickleLongString(0x54, 'legacy_long'),
        pickleShortString(0x43, 'bytes_short'),
        pickleLongString(0x42, 'bytes_long'),
        $huge,
        $truncated,
    ]);

    expect($parser->extractPickleStrings($payload))->toBe([
        'start',
        'chapter_one',
        'wide_label',
        'legacy_short',
        'legacy_long',
        'bytes_short',
        'bytes_long',
    ]);
});

it('extracts unique seen known labels from compressed save data', function () {
    $payload = implode('', [
        pickleShortString(0x8C, 'start'),
        pickleShortString(0x8C, 'unknown'),
        pickleShortString(0x8C, 'ending'),
        pickleShortString(0x8C, 'start'),
    ]);

    $seen = (new RenpySaveParser)->extractSeenLabels(gzencode($payload), ['start', 'ending']);

    expect($seen)->toBe(['start', 'ending']);
});
