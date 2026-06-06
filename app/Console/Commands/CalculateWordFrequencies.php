<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalculateWordFrequencies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dialogue:calculate-word-frequencies
                            {--version-id= : Calculate for a specific game version ID}
                            {--language= : Calculate for a specific language (iso code)}
                            {--force : Force recalculation even if already cached}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-calculate word frequencies for dialogue to improve performance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $versionId = $this->option('version-id');
        $language = $this->option('language');
        $force = $this->option('force');

        // Get all version+language combinations that have dialogue
        $query = DB::table('version_dialogue_lines as vdl')
            ->select('vdl.game_version_id', 'vdl.iso_code')
            ->distinct()
            // Exclude Q-codes (reserved for local use, not displayed)
            ->where('vdl.iso_code', 'NOT LIKE', 'q%');

        if ($versionId) {
            $query->where('vdl.game_version_id', '=', $versionId);
        }

        if ($language) {
            $query->where('vdl.iso_code', '=', $language);
        }

        $combinations = $query->get();

        if ($combinations->isEmpty()) {
            $this->warn('No dialogue data found for the specified criteria.');

            return 0;
        }

        $this->info('Found ' . count($combinations) . ' version+language combinations to process.');
        $bar = $this->output->createProgressBar(count($combinations));
        $bar->start();

        $processed = 0;
        $skipped = 0;

        foreach ($combinations as $combo) {
            // Check if already cached and not forcing
            if (! $force) {
                $exists = DB::table('version_word_frequencies')
                    ->where('game_version_id', $combo->game_version_id)
                    ->where('iso_code', $combo->iso_code)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }
            }

            $this->calculateAndStore($combo->game_version_id, $combo->iso_code);
            $processed++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Processed: {$processed}");
        if ($skipped > 0) {
            $this->info("Skipped (already cached): {$skipped}");
            $this->info('Use --force to recalculate existing entries.');
        }

        return 0;
    }

    /**
     * Calculate and store word frequencies for a specific version+language combination.
     */
    private function calculateAndStore(int $versionId, string $language): void
    {
        $limit = 100;
        $includePhrases = true;
        $minWordLength = 3;

        // Fetch all dialogue texts for this version and language
        $dialogueTexts = DB::table('version_dialogue_lines as vdl')
            ->join('unique_dialogue_texts as udt', 'udt.id', '=', 'vdl.text_id')
            ->where('vdl.game_version_id', '=', $versionId)
            ->where('vdl.iso_code', '=', $language)
            ->whereNotNull('udt.text_content')
            ->select('udt.text_content')
            ->pluck('text_content');

        if ($dialogueTexts->isEmpty()) {
            return;
        }

        $wordData = $this->calculateWordFrequency($dialogueTexts, $limit, $includePhrases, $minWordLength);

        // Store or update in database
        $existing = DB::table('version_word_frequencies')
            ->where('game_version_id', $versionId)
            ->where('iso_code', $language)
            ->exists();

        if ($existing) {
            DB::table('version_word_frequencies')
                ->where('game_version_id', $versionId)
                ->where('iso_code', $language)
                ->update([
                    'word_data' => json_encode($wordData),
                    'calculated_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('version_word_frequencies')->insert([
                'game_version_id' => $versionId,
                'iso_code' => $language,
                'word_data' => json_encode($wordData),
                'calculated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Calculate word frequency from dialogue texts.
     * This is extracted from DialogueController::getWordFrequency for reusability.
     *
     * @param  Collection  $dialogueTexts
     */
    private function calculateWordFrequency($dialogueTexts, int $limit, bool $includePhrases, int $minWordLength): array
    {
        $stopWords = $this->getStopWords();
        $wordCounts = [];
        $phraseCounts = [];

        // Process each dialogue text
        foreach ($dialogueTexts as $text) {
            // Convert to lowercase and remove special characters, keeping spaces
            $cleaned = strtolower((string) $text);
            $cleaned = preg_replace('/[^\p{L}\p{N}\s\-\']/u', ' ', $cleaned);
            $cleaned = preg_replace('/\s+/', ' ', $cleaned);
            $cleaned = trim($cleaned);

            // Split into words
            $words = explode(' ', $cleaned);
            $words = array_values(array_filter($words, fn ($w) => strlen($w) >= $minWordLength));

            // Count individual words
            foreach ($words as $word) {
                $word = trim($word);
                if (! in_array($word, $stopWords, true) && strlen($word) >= $minWordLength) {
                    $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
                }
            }

            // Count 2-word and 3-word phrases if requested
            if ($includePhrases && count($words) >= 2) {
                // Bigrams (2-word phrases)
                for ($i = 0; $i < count($words) - 1; $i++) {
                    $phrase = $words[$i] . ' ' . $words[$i + 1];
                    // Only count if phrase is meaningful (not all stop words)
                    if (! (in_array($words[$i], $stopWords, true) && in_array($words[$i + 1], $stopWords, true))) {
                        $phraseCounts[$phrase] = ($phraseCounts[$phrase] ?? 0) + 1;
                    }
                }

                // Trigrams (3-word phrases)
                for ($i = 0; $i < count($words) - 2; $i++) {
                    $phrase = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
                    $phraseCounts[$phrase] = ($phraseCounts[$phrase] ?? 0) + 1;
                }
            }
        }

        // Sort by frequency and combine words and phrases
        arsort($wordCounts);
        arsort($phraseCounts);

        // Take top words and phrases, then combine them
        $topWords = array_slice($wordCounts, 0, (int) ($limit * 0.7), true);
        $topPhrases = $includePhrases ? array_slice($phraseCounts, 0, (int) ($limit * 0.3), true) : [];

        $combined = [];
        foreach ($topWords as $text => $count) {
            $combined[] = ['text' => $text, 'value' => $count];
        }
        foreach ($topPhrases as $text => $count) {
            $combined[] = ['text' => $text, 'value' => $count];
        }

        // Sort combined by value descending
        usort($combined, fn ($a, $b) => $b['value'] <=> $a['value']);

        // Limit to requested count
        return array_slice($combined, 0, $limit);
    }

    /**
     * Get the list of stop words.
     * TODO: Move this to a configuration file or database table for easier updates.
     */
    private function getStopWords(): array
    {
        return [
            // Articles, pronouns, possessives
            'the', 'a', 'an', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'them',
            'me', 'him', 'her', 'us', 'my', 'your', 'his', 'her', 'its', 'our', 'their',
            'myself', 'yourself', 'himself', 'herself', 'itself', 'ourselves', 'themselves',

            // Common verbs and contractions
            'is', 'am', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
            'do', 'does', 'did', 'will', 'would', 'shall', 'should', 'may', 'might', 'must',
            'can', 'could', 'ought', 'i\'m', 'you\'re', 'he\'s', 'she\'s', 'it\'s', 'we\'re',
            'they\'re', 'i\'ve', 'you\'ve', 'we\'ve', 'they\'ve', 'i\'d', 'you\'d', 'he\'d',
            'she\'d', 'we\'d', 'they\'d', 'i\'ll', 'you\'ll', 'he\'ll', 'she\'ll', 'we\'ll',
            'they\'ll', 'isn\'t', 'aren\'t', 'wasn\'t', 'weren\'t', 'hasn\'t', 'haven\'t',
            'hadn\'t', 'doesn\'t', 'don\'t', 'didn\'t', 'won\'t', 'wouldn\'t', 'shan\'t',
            'shouldn\'t', 'can\'t', 'cannot', 'couldn\'t', 'mustn\'t', 'let\'s', 'that\'s',
            'who\'s', 'what\'s', 'here\'s', 'there\'s', 'when\'s', 'where\'s', 'why\'s', 'how\'s',

            // Prepositions and conjunctions
            'in', 'on', 'at', 'to', 'for', 'of', 'with', 'from', 'by', 'about', 'as',
            'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between',
            'under', 'around', 'among', 'and', 'but', 'or', 'nor', 'so', 'yet', 'because',
            'although', 'though', 'while', 'if', 'than', 'that', 'whether', 'till', 'until',
            'not', 'over',

            // Question words and demonstratives
            'what', 'when', 'where', 'which', 'who', 'whom', 'whose', 'why', 'how',
            'this', 'that', 'these', 'those', 'here', 'there',

            // Common adverbs and intensifiers
            'very', 'really', 'quite', 'too', 'so', 'just', 'only', 'even', 'also', 'still',
            'already', 'always', 'never', 'often', 'sometimes', 'usually', 'perhaps', 'maybe',
            'probably', 'certainly', 'definitely', 'surely', 'absolutely', 'completely',
            'totally', 'entirely', 'exactly', 'nearly', 'almost', 'hardly', 'barely',
            'right', 'well', 'now', 'then', 'again', 'away', 'off', 'down', 'up', 'out',

            // Common verbs (conversational)
            'go', 'went', 'gone', 'going', 'come', 'came', 'get', 'got', 'getting', 'make',
            'made', 'making', 'take', 'took', 'taken', 'taking', 'give', 'gave', 'given',
            'say', 'said', 'saying', 'know', 'knew', 'known', 'knowing', 'think', 'thought',
            'see', 'saw', 'seen', 'want', 'wanted', 'look', 'looked', 'looking', 'need',
            'use', 'find', 'tell', 'ask', 'work', 'seem', 'feel', 'try', 'leave', 'call',
            'keep', 'let', 'begin', 'help', 'show', 'hear', 'play', 'run', 'move', 'live',
            'believe', 'bring', 'happen', 'write', 'sit', 'stand', 'lose', 'pay', 'meet',
            'include', 'continue', 'set', 'learn', 'change', 'lead', 'understand', 'watch',

            // Common adjectives and quantities
            'good', 'new', 'first', 'last', 'long', 'great', 'little', 'own', 'other', 'old',
            'right', 'big', 'high', 'different', 'small', 'large', 'next', 'early', 'young',
            'important', 'few', 'public', 'bad', 'same', 'able', 'nice', 'sure', 'okay',
            'fine', 'better', 'best', 'worse', 'worst', 'much', 'many', 'more', 'most', 'less',
            'least', 'some', 'any', 'every', 'all', 'both', 'each', 'few', 'more', 'other',
            'another', 'such', 'one', 'two', 'three', 'four', 'five',

            // Conversational filler words
            'yeah', 'yes', 'yep', 'nope', 'nah', 'okay', 'ok', 'hey', 'oh', 'ah', 'um', 'uh',
            'hmm', 'huh', 'wow', 'well', 'like', 'guess', 'suppose', 'mean', 'actually',
            'something', 'anything', 'everything', 'nothing', 'someone', 'anyone', 'everyone',
            'nobody', 'somewhere', 'anywhere', 'everywhere', 'nowhere', 'gonna', 'wanna', 'gotta',

            // Common nouns (too generic)
            'time', 'year', 'day', 'thing', 'things', 'way', 'man', 'people', 'world',
            'life', 'hand', 'part', 'place', 'case', 'week', 'company', 'system', 'program',
            'question', 'work', 'government', 'number', 'night', 'point', 'home', 'water',
            'room', 'mother', 'area', 'money', 'story', 'fact', 'month', 'lot', 'moment',
            'side', 'kind', 'head', 'house', 'service', 'friend', 'father', 'power', 'hour',
            'game', 'line', 'end', 'member', 'law', 'car', 'city', 'community', 'name',
            'president', 'team', 'minute', 'idea', 'kid', 'body', 'information', 'back',
            'parent', 'face', 'others', 'level', 'office', 'door', 'health', 'person',
            'art', 'war', 'history', 'party', 'result', 'change', 'morning', 'reason',
            'research', 'girl', 'guy', 'guys', 'moment', 'air', 'teacher', 'force', 'education',
        ];
    }
}
