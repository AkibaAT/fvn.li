<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\VersionCharacterStats;
use App\Services\Concerns\ReportsProgress;
use App\Support\Stats\StatsPayload;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Normalizer;
use Throwable;

class GameStatsDialoguePersister
{
    use ReportsProgress;

    private const MAX_DIALOGUE_TEXT_BYTES = 65536;

    private const BATCH_SIZE = 1000;

    public function __construct(
        private readonly LanguageMappingService $languageMappingService,
        private readonly CharacterStatsCalculationService $characterStatsService,
        private readonly EssentialCharacterService $essentialCharacterService,
    ) {}

    /**
     * Persist every dialogue line in the payload.
     *
     * Lines are pulled from the payload one at a time and written in fixed-size
     * batches, so a game with a million lines costs the same memory as one with
     * a thousand.
     *
     * @return int the number of dialogue lines written
     */
    public function save(
        GameVersion $version,
        StatsPayload $payload,
        string $defaultLanguage = 'eng',
        ?Game $game = null,
        array $foundLanguages = []
    ): int {
        $this->progress("    [Dialogue] Deleting existing dialogue lines\n");
        DialogueLine::where('game_version_id', $version->id)->delete();
        $this->progress("    [Dialogue] Existing lines deleted\n");

        $menuChoiceCharacter = $this->essentialCharacterService->getOrCreateMenuChoiceCharacter($version->game_id);
        $narratorCharacter = $this->essentialCharacterService->getOrCreateNarratorCharacter($version->game_id);
        $characterCache = [
            'menu_choice' => $menuChoiceCharacter->id,
            'narrator' => $narratorCharacter->id,
        ];

        $now = now();
        $isoCodes = [];
        $buffer = [];
        $written = 0;

        foreach ($payload->dialogueLines() as [$langKey, $line]) {
            if (! array_key_exists($langKey, $isoCodes)) {
                $isoCodes[$langKey] = $langKey === 'default'
                    ? $defaultLanguage
                    : ($this->languageMappingService->resolveLanguageCode($langKey, $game) ?: null);

                if ($isoCodes[$langKey] === null) {
                    Log::warning("Skipping dialogue lines for language {$langKey} - could not determine ISO code");
                }
            }

            $isoCode = $isoCodes[$langKey];
            if ($isoCode === null) {
                continue;
            }

            $text = $line['text'] ?? '';
            if ($text === '') {
                continue;
            }

            $text = $this->processText($text);
            $line['text'] = $text;
            $line['iso_code'] = $isoCode;
            // Carried on the row so the batch insert can reuse it.
            $line['text_hash'] = md5($text);
            $buffer[] = $line;

            if (count($buffer) >= self::BATCH_SIZE) {
                $written += $this->flushBatch($version, $buffer, $now, $foundLanguages, $defaultLanguage, $characterCache);
                $buffer = [];
                $this->progress("    [Dialogue] {$written} lines written\n");
            }
        }

        if ($buffer !== []) {
            $written += $this->flushBatch($version, $buffer, $now, $foundLanguages, $defaultLanguage, $characterCache);
        }

        $this->progress("    [Dialogue] All dialogue lines inserted ({$written} total)\n");
        Cache::forget('dialogue.games_list');

        return $written;
    }

    public function processText(string $text): string
    {
        $text = $this->truncateUtf8Bytes($text, self::MAX_DIALOGUE_TEXT_BYTES);

        if ($this->isZalgo($text)) {
            return $this->stripDiacritics($text);
        }

        return Normalizer::normalize($text, Normalizer::FORM_C);
    }

    public function isZalgo(string $text, float $threshold = 0.9): bool
    {
        $text = $this->truncateUtf8Bytes($text, self::MAX_DIALOGUE_TEXT_BYTES);
        $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);
        if (! is_string($decomposed) || $decomposed === '') {
            return false;
        }

        $totalLength = 0;
        $diacriticCount = 0;
        $offset = 0;
        $byteLength = strlen($decomposed);

        while ($offset < $byteLength && preg_match('/\p{Mn}|./us', $decomposed, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $character = $match[0][0];
            $offset = $match[0][1] + strlen($character);
            $totalLength++;

            if (preg_match('/^\p{Mn}$/u', $character) === 1) {
                $diacriticCount++;
            }
        }

        return $totalLength > 0 && ($diacriticCount / $totalLength) > $threshold;
    }

    public function stripDiacritics(string $text): string
    {
        $text = $this->truncateUtf8Bytes($text, self::MAX_DIALOGUE_TEXT_BYTES);
        $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);

        return preg_replace('/\p{Mn}/u', '', $decomposed);
    }

    public function createCharacter(
        int $gameId,
        string $characterId,
        array $foundLanguages,
        string $defaultLanguage
    ): Character {
        $character = Character::firstOrNew([
            'game_id' => $gameId,
            'character_id' => $characterId,
        ]);

        if (! $character->exists) {
            $displayNames = [];

            foreach ($foundLanguages as $langCode) {
                $displayNames[$langCode] = $characterId;
            }

            if (! in_array('eng', $foundLanguages)) {
                $displayNames['eng'] = $characterId;
            }

            if ($defaultLanguage !== 'eng' && ! in_array($defaultLanguage, $foundLanguages)) {
                $displayNames[$defaultLanguage] = $characterId;
            }

            $character->display_names = $displayNames;
            $character->save();

            return $character;
        }

        $displayNames = $character->display_names ?? [];
        $needsUpdate = false;

        foreach ($foundLanguages as $langCode) {
            if (! isset($displayNames[$langCode])) {
                $displayNames[$langCode] = $characterId;
                $needsUpdate = true;
            }
        }

        if (! isset($displayNames['eng']) && ! in_array('eng', $foundLanguages)) {
            $displayNames['eng'] = $characterId;
            $needsUpdate = true;
        }

        if (! isset($displayNames[$defaultLanguage]) && $defaultLanguage !== 'eng' && ! in_array($defaultLanguage, $foundLanguages)) {
            $displayNames[$defaultLanguage] = $characterId;
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            $character->display_names = $displayNames;
            $character->save();
        }

        return $character;
    }

    public function applySpecialCharacterAssignments(GameVersion $version): void
    {
        Log::info("Applying special character assignments for game version {$version->id}");

        $result = app(CharacterSpecialAssignmentService::class)
            ->fixSpecialCharacterAssignments($version->game_id, null, false);

        if ($result['lines_reassigned'] <= 0) {
            return;
        }

        Log::info("Reassigned {$result['lines_reassigned']} special character lines for game {$version->game_id}");

        $cleanupResult = app(CharacterVersionReferenceService::class)
            ->fixVersionReferences($version->game_id, false);

        if ($cleanupResult['characters_deleted'] > 0) {
            Log::info("Deleted {$cleanupResult['characters_deleted']} orphaned special characters for game {$version->game_id}");
        }
    }

    public function calculateStatsAndReportDiscrepancies(
        GameVersion $version,
        StatsPayload $payload,
        string $defaultLanguage = 'eng',
        ?Game $game = null
    ): void {
        $this->characterStatsService->calculateAndSaveStatsForVersion($version->id);

        $languages = $payload->languages();
        if ($languages === []) {
            return;
        }

        // Both sides of the comparison are fetched in one query each and matched
        // in memory, so the cost is independent of the character count.
        $characterIds = Character::where('game_id', $version->game_id)
            ->pluck('id', 'character_id');

        $calculated = VersionCharacterStats::where('game_version_id', $version->id)
            ->get(['character_id', 'iso_code', 'blocks', 'words'])
            ->keyBy(fn (VersionCharacterStats $stats): string => $stats->character_id . '|' . $stats->iso_code);

        $discrepanciesFound = false;

        foreach ($languages as $langKey => $langData) {
            $isoCode = $langKey === 'default'
                ? $defaultLanguage
                : $this->languageMappingService->resolveLanguageCode((string) $langKey, $game);

            if (! $isoCode || ! isset($langData['characters'])) {
                continue;
            }

            foreach ($langData['characters'] as $charId => $charData) {
                $characterId = $characterIds->get((string) $charId);
                if (! $characterId) {
                    continue;
                }

                $calculatedStats = $calculated->get($characterId . '|' . $isoCode);
                if (! $calculatedStats) {
                    continue;
                }

                $reportedBlocks = $charData['blocks'] ?? 0;
                $reportedWords = $charData['words'] ?? 0;
                if ($reportedBlocks === $calculatedStats->blocks && $reportedWords === $calculatedStats->words) {
                    continue;
                }

                if (! $discrepanciesFound) {
                    Log::warning("Character stats discrepancies found for game version {$version->id}:");
                    $discrepanciesFound = true;
                }

                Log::warning("Character '{$charId}' ({$isoCode}): reported={$reportedBlocks} blocks, {$reportedWords} words | Calculated={$calculatedStats->blocks} blocks, {$calculatedStats->words} words");
            }
        }

        if (! $discrepanciesFound) {
            Log::info("Character stats validation passed for game version {$version->id} - no discrepancies found");
        }
    }

    public function queueWordFrequencyCalculations(int $versionId): void
    {
        $languages = DB::table('version_dialogue_lines')
            ->where('game_version_id', $versionId)
            ->distinct()
            ->pluck('iso_code');

        if ($languages->isEmpty()) {
            return;
        }

        foreach ($languages as $language) {
            try {
                Artisan::call('dialogue:calculate-word-frequencies', [
                    '--version-id' => $versionId,
                    '--language' => $language,
                    '--force' => true,
                ]);
            } catch (Throwable $e) {
                Log::warning("Failed to calculate word frequencies for version {$versionId}, language {$language}: " . $e->getMessage());
            }
        }
    }

    /**
     * Deduplicate, resolve and insert one batch of prepared lines.
     *
     * @param  array<int, array<string, mixed>>  $buffer
     * @return int the number of rows inserted
     */
    private function flushBatch(
        GameVersion $version,
        array $buffer,
        $now,
        array $foundLanguages,
        string $defaultLanguage,
        array &$characterCache
    ): int {
        $textIdMapping = $this->persistUniqueTexts($buffer, $now);
        $dialogueBatch = [];

        foreach ($buffer as $line) {
            $textHash = $line['text_hash'];
            $textId = $textIdMapping[$textHash] ?? DB::table('unique_dialogue_texts')
                ->where('text_hash', $textHash)
                ->value('id');

            if (! $textId) {
                Log::warning("Could not find text ID for hash {$textHash}");

                continue;
            }

            $dialogueBatch[] = [
                'game_version_id' => $version->id,
                'character_id' => $this->characterIdForLine($version, $line, $foundLanguages, $defaultLanguage, $characterCache),
                'iso_code' => $line['iso_code'],
                'file_path' => $line['file'] ?? '',
                'line_number' => $line['line'] ?? 0,
                'text_id' => $textId,
                'context' => $line['context'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($dialogueBatch === []) {
            return 0;
        }

        DB::table('version_dialogue_lines')->insert($dialogueBatch);

        return count($dialogueBatch);
    }

    /**
     * @param  array<int, array<string, mixed>>  $buffer
     * @return array<string, int>
     */
    private function persistUniqueTexts(array $buffer, $now): array
    {
        $uniqueTexts = [];
        foreach ($buffer as $line) {
            $uniqueTexts[$line['text_hash']] = [
                'text_hash' => $line['text_hash'],
                'text_content' => $line['text'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($uniqueTexts === []) {
            return [];
        }

        DB::table('unique_dialogue_texts')->insertOrIgnore(array_values($uniqueTexts));

        $textIdMapping = [];
        $texts = DB::table('unique_dialogue_texts')
            ->whereIn('text_hash', array_keys($uniqueTexts))
            ->get(['id', 'text_hash']);

        foreach ($texts as $text) {
            $textIdMapping[$text->text_hash] = $text->id;
        }

        return $textIdMapping;
    }

    private function characterIdForLine(
        GameVersion $version,
        array $line,
        array $foundLanguages,
        string $defaultLanguage,
        array &$characterCache
    ): int {
        $characterName = empty($line['character']) ? 'narrator' : $line['character'];

        if (! isset($characterCache[$characterName])) {
            $characterCache[$characterName] = $this->createCharacter(
                $version->game_id,
                $characterName,
                $foundLanguages,
                $defaultLanguage
            )->id;
        }

        return $characterCache[$characterName];
    }

    private function truncateUtf8Bytes(string $text, int $maxBytes): string
    {
        if (strlen($text) <= $maxBytes) {
            return $text;
        }

        $truncated = substr($text, 0, $maxBytes);

        while ($truncated !== '' && ! mb_check_encoding($truncated, 'UTF-8')) {
            $truncated = substr($truncated, 0, -1);
        }

        return $truncated;
    }
}
