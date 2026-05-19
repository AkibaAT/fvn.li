<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DialogueWordFrequencyService
{
    private const MAX_ROWS = 10000;

    private const MAX_CHARACTERS = 2000000;

    private const STOP_WORDS = [
        'the', 'a', 'an', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'them',
        'me', 'him', 'her', 'us', 'my', 'your', 'his', 'its', 'our', 'their',
        'myself', 'yourself', 'himself', 'herself', 'itself', 'ourselves', 'themselves',
        'is', 'am', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
        'do', 'does', 'did', 'will', 'would', 'shall', 'should', 'may', 'might', 'must',
        'can', 'could', 'ought', 'i\'m', 'you\'re', 'he\'s', 'she\'s', 'it\'s', 'we\'re',
        'they\'re', 'i\'ve', 'you\'ve', 'we\'ve', 'they\'ve', 'i\'d', 'you\'d', 'he\'d',
        'she\'d', 'we\'d', 'they\'d', 'i\'ll', 'you\'ll', 'he\'ll', 'she\'ll', 'we\'ll',
        'they\'ll', 'isn\'t', 'aren\'t', 'wasn\'t', 'weren\'t', 'hasn\'t', 'haven\'t',
        'hadn\'t', 'doesn\'t', 'don\'t', 'didn\'t', 'won\'t', 'wouldn\'t', 'shan\'t',
        'shouldn\'t', 'can\'t', 'cannot', 'couldn\'t', 'mustn\'t', 'let\'s', 'that\'s',
        'who\'s', 'what\'s', 'here\'s', 'there\'s', 'when\'s', 'where\'s', 'why\'s', 'how\'s',
        'in', 'on', 'at', 'to', 'for', 'of', 'with', 'from', 'by', 'about', 'as',
        'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between',
        'under', 'around', 'among', 'and', 'but', 'or', 'nor', 'so', 'yet', 'because',
        'although', 'though', 'while', 'if', 'than', 'that', 'whether', 'till', 'until',
        'not', 'over', 'what', 'when', 'where', 'which', 'who', 'whom', 'whose', 'why',
        'how', 'this', 'that', 'these', 'those', 'here', 'there', 'very', 'really',
        'quite', 'too', 'just', 'only', 'even', 'also', 'still', 'already', 'always',
        'never', 'often', 'sometimes', 'usually', 'perhaps', 'maybe', 'probably',
        'certainly', 'definitely', 'surely', 'absolutely', 'completely', 'totally',
        'entirely', 'exactly', 'nearly', 'almost', 'hardly', 'barely', 'right', 'well',
        'now', 'then', 'again', 'away', 'off', 'down', 'up', 'out', 'go', 'went',
        'gone', 'going', 'come', 'came', 'get', 'got', 'getting', 'make', 'made',
        'making', 'take', 'took', 'taken', 'taking', 'give', 'gave', 'given', 'say',
        'said', 'saying', 'know', 'knew', 'known', 'knowing', 'think', 'thought',
        'see', 'saw', 'seen', 'want', 'wanted', 'look', 'looked', 'looking', 'need',
        'use', 'find', 'tell', 'ask', 'work', 'seem', 'feel', 'try', 'leave', 'call',
        'keep', 'let', 'begin', 'help', 'show', 'hear', 'play', 'run', 'move', 'live',
        'believe', 'bring', 'happen', 'write', 'sit', 'stand', 'lose', 'pay', 'meet',
        'include', 'continue', 'set', 'learn', 'change', 'lead', 'understand', 'watch',
        'good', 'new', 'first', 'last', 'long', 'great', 'little', 'own', 'other',
        'old', 'big', 'high', 'different', 'small', 'large', 'next', 'early', 'young',
        'important', 'few', 'public', 'bad', 'same', 'able', 'nice', 'sure', 'okay',
        'fine', 'better', 'best', 'worse', 'worst', 'much', 'many', 'more', 'most',
        'less', 'least', 'some', 'any', 'every', 'all', 'both', 'each', 'another',
        'such', 'one', 'two', 'three', 'four', 'five', 'yeah', 'yes', 'yep', 'nope',
        'nah', 'ok', 'hey', 'oh', 'ah', 'um', 'uh', 'hmm', 'huh', 'wow', 'like',
        'guess', 'suppose', 'mean', 'actually', 'something', 'anything', 'everything',
        'nothing', 'someone', 'anyone', 'everyone', 'nobody', 'somewhere', 'anywhere',
        'everywhere', 'nowhere', 'gonna', 'wanna', 'gotta', 'time', 'year', 'day',
        'thing', 'things', 'way', 'man', 'people', 'world', 'life', 'hand', 'part',
        'place', 'case', 'week', 'company', 'system', 'program', 'question', 'government',
        'number', 'night', 'point', 'home', 'water', 'room', 'mother', 'area', 'money',
        'story', 'fact', 'month', 'lot', 'moment', 'side', 'kind', 'head', 'house',
        'service', 'friend', 'father', 'power', 'hour', 'game', 'line', 'end', 'member',
        'law', 'car', 'city', 'community', 'name', 'president', 'team', 'minute', 'idea',
        'kid', 'body', 'information', 'back', 'parent', 'face', 'others', 'level',
        'office', 'door', 'health', 'person', 'art', 'war', 'history', 'party', 'result',
        'morning', 'reason', 'research', 'girl', 'guy', 'guys', 'air', 'teacher', 'force',
        'education',
    ];

    public function calculate(
        int $versionId,
        string $language = 'eng',
        int $limit = 100,
        bool $includePhrases = true,
        int $minWordLength = 3,
    ): array {
        if ($limit === 100 && $includePhrases === true && $minWordLength === 3) {
            $cached = DB::table('version_word_frequencies')
                ->where('game_version_id', '=', $versionId)
                ->where('iso_code', '=', $language)
                ->first();

            if ($cached) {
                return [
                    'success' => true,
                    'data' => json_decode($cached->word_data, true) ?? [],
                    'cached' => true,
                    'calculated_at' => $cached->calculated_at,
                ];
            }
        }

        $baseQuery = DB::table('version_dialogue_lines as vdl')
            ->join('unique_dialogue_texts as udt', 'udt.id', '=', 'vdl.text_id')
            ->where('vdl.game_version_id', '=', $versionId)
            ->where('vdl.iso_code', '=', $language)
            ->whereNotNull('udt.text_content');

        $corpusStats = (clone $baseQuery)
            ->selectRaw('COUNT(*) as row_count, COALESCE(SUM(CHAR_LENGTH(udt.text_content)), 0) as total_characters')
            ->first();

        $rowCount = (int) ($corpusStats?->row_count ?? 0);
        $totalCharacters = (int) ($corpusStats?->total_characters ?? 0);

        if ($rowCount === 0) {
            return ['success' => true, 'data' => []];
        }

        if ($rowCount > self::MAX_ROWS || $totalCharacters > self::MAX_CHARACTERS) {
            return [
                'success' => false,
                'message' => 'Requested dialogue corpus is too large to process on demand.',
                'status' => 422,
            ];
        }

        $dialogueTexts = (clone $baseQuery)
            ->select('udt.text_content')
            ->orderBy('vdl.id')
            ->cursor();

        $wordCounts = [];
        $phraseCounts = [];

        foreach ($dialogueTexts as $row) {
            $words = $this->tokenize((string) $row->text_content, $minWordLength);

            foreach ($words as $word) {
                if (! in_array($word, self::STOP_WORDS, true)) {
                    $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
                }
            }

            if ($includePhrases) {
                $this->countPhrases($words, $phraseCounts);
            }
        }

        arsort($wordCounts);
        arsort($phraseCounts);

        return [
            'success' => true,
            'data' => $this->combineCounts($wordCounts, $phraseCounts, $includePhrases, $limit),
        ];
    }

    private function tokenize(string $text, int $minWordLength): array
    {
        $cleaned = strtolower($text);
        $cleaned = preg_replace('/[^\p{L}\p{N}\s\-\']/u', ' ', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);

        return array_values(array_filter(
            explode(' ', $cleaned),
            fn ($word) => strlen(trim($word)) >= $minWordLength
        ));
    }

    private function countPhrases(array $words, array &$phraseCounts): void
    {
        if (count($words) < 2) {
            return;
        }

        for ($i = 0; $i < count($words) - 1; $i++) {
            $phrase = $words[$i].' '.$words[$i + 1];
            if (! (in_array($words[$i], self::STOP_WORDS, true) && in_array($words[$i + 1], self::STOP_WORDS, true))) {
                $phraseCounts[$phrase] = ($phraseCounts[$phrase] ?? 0) + 1;
            }
        }

        for ($i = 0; $i < count($words) - 2; $i++) {
            $phrase = $words[$i].' '.$words[$i + 1].' '.$words[$i + 2];
            $phraseCounts[$phrase] = ($phraseCounts[$phrase] ?? 0) + 1;
        }
    }

    private function combineCounts(array $wordCounts, array $phraseCounts, bool $includePhrases, int $limit): array
    {
        $combined = [];
        $topWords = array_slice($wordCounts, 0, (int) ($limit * 0.7), true);
        $topPhrases = $includePhrases ? array_slice($phraseCounts, 0, (int) ($limit * 0.3), true) : [];

        foreach ($topWords as $text => $count) {
            $combined[] = ['text' => $text, 'value' => $count];
        }
        foreach ($topPhrases as $text => $count) {
            $combined[] = ['text' => $text, 'value' => $count];
        }

        usort($combined, fn ($a, $b) => $b['value'] <=> $a['value']);

        return array_slice($combined, 0, $limit);
    }
}
