<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\VersionCharacterStats;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Normalizer;
use Throwable;

class GameStatsDialoguePersister
{
    private const MAX_DIALOGUE_TEXT_BYTES = 65536;

    public function __construct(
        private readonly LanguageMappingService $languageMappingService,
        private readonly CharacterStatsCalculationService $characterStatsService,
        private readonly EssentialCharacterService $essentialCharacterService,
    ) {}

    public function save(
        GameVersion $version,
        array $dialogueLines,
        string $defaultLanguage = 'eng',
        ?Game $game = null,
        array $foundLanguages = []
    ): void {
        echo "    [Dialogue] Deleting existing dialogue lines\n";
        DialogueLine::where('game_version_id', $version->id)->delete();
        echo "    [Dialogue] Existing lines deleted\n";

        $menuChoiceCharacter = $this->essentialCharacterService->getOrCreateMenuChoiceCharacter($version->game_id);
        $narratorCharacter = $this->essentialCharacterService->getOrCreateNarratorCharacter($version->game_id);
        $characterCache = [
            'menu_choice' => $menuChoiceCharacter->id,
            'narrator' => $narratorCharacter->id,
        ];

        foreach ($dialogueLines as $langKey => $lines) {
            $isoCode = $langKey === 'default'
                ? $defaultLanguage
                : $this->languageMappingService->resolveLanguageCode($langKey, $game);

            if (! $isoCode) {
                Log::warning("Skipping dialogue lines for language {$langKey} - could not determine ISO code");

                continue;
            }

            $this->saveLanguageDialogueLines($version, $lines, $isoCode, $foundLanguages, $defaultLanguage, $characterCache);
        }

        echo "    [Dialogue] All dialogue lines inserted\n";
        Cache::forget('dialogue.games_list');
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
        array $stats,
        string $defaultLanguage = 'eng',
        ?Game $game = null
    ): void {
        $this->characterStatsService->calculateAndSaveStatsForVersion($version->id);

        if (! isset($stats['languages'])) {
            return;
        }

        $discrepanciesFound = false;

        foreach ($stats['languages'] as $langKey => $langData) {
            $isoCode = $langKey === 'default'
                ? $defaultLanguage
                : $this->languageMappingService->resolveLanguageCode($langKey, $game);

            if (! $isoCode || ! isset($langData['characters'])) {
                continue;
            }

            foreach ($langData['characters'] as $charId => $charData) {
                $character = Character::where('game_id', $version->game_id)
                    ->where('character_id', $charId)
                    ->first();

                if (! $character) {
                    continue;
                }

                $calculatedStats = VersionCharacterStats::where('game_version_id', $version->id)
                    ->where('character_id', $character->id)
                    ->where('iso_code', $isoCode)
                    ->first();

                if (! $calculatedStats) {
                    continue;
                }

                $jsonBlocks = $charData['blocks'] ?? 0;
                $jsonWords = $charData['words'] ?? 0;
                if ($jsonBlocks === $calculatedStats->blocks && $jsonWords === $calculatedStats->words) {
                    continue;
                }

                if (! $discrepanciesFound) {
                    Log::warning("Character stats discrepancies found for game version {$version->id}:");
                    $discrepanciesFound = true;
                }

                Log::warning("Character '{$charId}' ({$isoCode}): JSON={$jsonBlocks} blocks, {$jsonWords} words | Calculated={$calculatedStats->blocks} blocks, {$calculatedStats->words} words");
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

    private function saveLanguageDialogueLines(
        GameVersion $version,
        array $lines,
        string $isoCode,
        array $foundLanguages,
        string $defaultLanguage,
        array &$characterCache
    ): void {
        $now = now();
        $batchSize = 1000;
        $totalLines = count($lines);

        echo "    [Dialogue] Processing {$totalLines} lines for language {$isoCode}\n";
        foreach (array_chunk($lines, $batchSize) as $chunkIndex => $chunk) {
            echo '    [Dialogue] Processing chunk ' . ($chunkIndex + 1) . "\n";
            $chunk = $this->normalizeChunkText($chunk);
            $textIdMapping = $this->persistUniqueTexts($chunk, $now);
            $dialogueBatch = $this->buildDialogueBatch(
                $version,
                $chunk,
                $isoCode,
                $now,
                $foundLanguages,
                $defaultLanguage,
                $characterCache,
                $textIdMapping
            );

            echo '    [Dialogue] Dialogue batch built (' . count($dialogueBatch) . " lines)\n";

            if (! empty($dialogueBatch)) {
                echo "    [Dialogue] Inserting batch into database...\n";
                DB::table('version_dialogue_lines')->insert($dialogueBatch);
                echo "    [Dialogue] Batch inserted\n";
            }
        }
    }

    private function normalizeChunkText(array $chunk): array
    {
        echo "    [Dialogue] Normalizing text...\n";
        foreach ($chunk as $id => $line) {
            $text = $line['text'] ?? '';
            if ($text !== '') {
                $chunk[$id]['text'] = $this->processText($text);
            }
        }
        echo "    [Dialogue] Text normalized\n";

        return $chunk;
    }

    private function persistUniqueTexts(array $chunk, $now): array
    {
        echo "    [Dialogue] Collecting unique texts...\n";
        $uniqueTexts = [];
        foreach ($chunk as $line) {
            $text = $line['text'] ?? '';
            if ($text === '') {
                continue;
            }

            $textHash = md5($text);
            $uniqueTexts[$textHash] = [
                'text_hash' => $textHash,
                'text_content' => $text,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        echo '    [Dialogue] Collected ' . count($uniqueTexts) . " unique texts\n";

        echo "    [Dialogue] Bulk inserting unique dialogue texts...\n";
        if (! empty($uniqueTexts)) {
            DB::table('unique_dialogue_texts')->insertOrIgnore(array_values($uniqueTexts));
        }
        echo "    [Dialogue] Bulk insert completed\n";

        echo "    [Dialogue] Fetching text IDs...\n";
        $textIdMapping = [];
        $texts = DB::table('unique_dialogue_texts')
            ->whereIn('text_hash', array_keys($uniqueTexts))
            ->get(['id', 'text_hash']);

        foreach ($texts as $text) {
            $textIdMapping[$text->text_hash] = $text->id;
        }
        echo '    [Dialogue] Mapped ' . count($textIdMapping) . " text IDs\n";

        return $textIdMapping;
    }

    private function buildDialogueBatch(
        GameVersion $version,
        array $chunk,
        string $isoCode,
        $now,
        array $foundLanguages,
        string $defaultLanguage,
        array &$characterCache,
        array $textIdMapping
    ): array {
        echo "    [Dialogue] Building dialogue batch...\n";
        $dialogueBatch = [];

        foreach ($chunk as $line) {
            $text = $line['text'] ?? '';
            if ($text === '') {
                continue;
            }

            $characterId = $this->characterIdForLine($version, $line, $foundLanguages, $defaultLanguage, $characterCache);
            $textHash = md5($text);
            $textId = $textIdMapping[$textHash] ?? DB::table('unique_dialogue_texts')
                ->where('text_hash', $textHash)
                ->value('id');

            if (! $textId) {
                Log::warning("Could not find text ID for hash {$textHash}");

                continue;
            }

            $dialogueBatch[] = [
                'game_version_id' => $version->id,
                'character_id' => $characterId,
                'iso_code' => $isoCode,
                'file_path' => $line['file'] ?? '',
                'line_number' => $line['line'] ?? 0,
                'text_id' => $textId,
                'context' => $line['context'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $dialogueBatch;
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
