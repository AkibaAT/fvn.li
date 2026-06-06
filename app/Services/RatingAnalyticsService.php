<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RatingAnalyticsService
{
    private const int PHRASES_CACHE_VERSION = 2;
    private const int MAX_REVIEWS = 80;
    private const int MAX_REVIEW_CHARS = 5000;
    private const int MAX_TOTAL_WORDS = 12000;
    private const int MAX_SENTENCES = 20;
    private const int MAX_CANDIDATES = 750;
    private const int COMPARISON_LIMIT = 75;

    public function globalStats(): array
    {
        $agg = DB::table('ratings')
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->where('ratings.is_visible', true)
            ->where('games.is_visible', true)
            ->select([
                DB::raw('MIN(ratings.published_at) as first_rating'),
                DB::raw('MAX(ratings.published_at) as latest_rating'),
                DB::raw('COUNT(*) as total_ratings'),
                DB::raw('SUM(CASE WHEN ratings.is_reviewed THEN 1 ELSE 0 END) as reviewed_count'),
                DB::raw('AVG(ratings.rating) as average_rating'),
                DB::raw('COUNT(DISTINCT ratings.game_id) as unique_games'),
            ])
            ->first();

        $distRows = DB::table('ratings')
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->where('ratings.is_visible', true)
            ->where('games.is_visible', true)
            ->groupBy('ratings.rating')
            ->select([
                'ratings.rating as rating_value',
                DB::raw('COUNT(*) as count_for_rating'),
            ])
            ->get();

        if (! $agg) {
            return $this->emptyStats();
        }

        $visibleBlock = $this->statsBlock(
            (int) ($agg->total_ratings ?? 0),
            (int) ($agg->reviewed_count ?? 0),
            (float) ($agg->average_rating ?? 0),
            (int) ($agg->unique_games ?? 0),
            $this->distribution($distRows, 'count_for_rating')
        );

        return [
            'first_rating' => $agg->first_rating ?? null,
            'latest_rating' => $agg->latest_rating ?? null,
            'all_games' => $visibleBlock,
            'visible_games' => $visibleBlock,
        ];
    }

    public function raterStats(int $raterId): array
    {
        $agg = DB::table('ratings')
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->where('ratings.rater_id', $raterId)
            ->where('ratings.is_visible', true)
            ->select([
                DB::raw('MIN(ratings.published_at) as first_rating'),
                DB::raw('MAX(ratings.published_at) as latest_rating'),
                DB::raw('COUNT(*) as all_total_ratings'),
                DB::raw('SUM(CASE WHEN ratings.is_reviewed THEN 1 ELSE 0 END) as all_reviewed_count'),
                DB::raw('AVG(ratings.rating) as all_average_rating'),
                DB::raw('COUNT(DISTINCT ratings.game_id) as all_unique_games'),
                DB::raw('SUM(CASE WHEN games.is_visible THEN 1 ELSE 0 END) as vis_total_ratings'),
                DB::raw('SUM(CASE WHEN games.is_visible AND ratings.is_reviewed THEN 1 ELSE 0 END) as vis_reviewed_count'),
                DB::raw('AVG(CASE WHEN games.is_visible THEN ratings.rating END) as vis_average_rating'),
                DB::raw('COUNT(DISTINCT CASE WHEN games.is_visible THEN ratings.game_id END) as vis_unique_games'),
            ])
            ->first();

        $distRows = DB::table('ratings')
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->where('ratings.rater_id', $raterId)
            ->where('ratings.is_visible', true)
            ->groupBy('ratings.rating')
            ->select([
                'ratings.rating as rating_value',
                DB::raw('COUNT(*) as all_count_for_rating'),
                DB::raw('SUM(CASE WHEN games.is_visible THEN 1 ELSE 0 END) as vis_count_for_rating'),
            ])
            ->get();

        if (! $agg) {
            return $this->emptyStats();
        }

        return [
            'first_rating' => $agg->first_rating,
            'latest_rating' => $agg->latest_rating,
            'all_games' => $this->statsBlock(
                (int) $agg->all_total_ratings,
                (int) $agg->all_reviewed_count,
                (float) ($agg->all_average_rating ?? 0),
                (int) $agg->all_unique_games,
                $this->distribution($distRows, 'all_count_for_rating')
            ),
            'visible_games' => $this->statsBlock(
                (int) $agg->vis_total_ratings,
                (int) $agg->vis_reviewed_count,
                (float) ($agg->vis_average_rating ?? 0),
                (int) $agg->vis_unique_games,
                $this->distribution($distRows, 'vis_count_for_rating')
            ),
        ];
    }

    public function commonPhrases(int $raterId): array
    {
        return cache()->remember($this->phrasesCacheKey($raterId), now()->addHour(), function () use ($raterId) {
            $reviews = DB::table('ratings')
                ->where('rater_id', $raterId)
                ->where('ratings.is_visible', true)
                ->whereNotNull('review')
                ->select([
                    'ratings.review',
                    'ratings.rating',
                    'games.name as game_name',
                    'games.slug as game_slug',
                    'ratings.rating as game_rating',
                ])
                ->join('games', 'games.id', '=', 'ratings.game_id')
                ->orderBy('ratings.published_at', 'desc')
                ->orderBy('ratings.id', 'desc')
                ->limit(self::MAX_REVIEWS)
                ->get();

            if ($reviews->isEmpty()) {
                return [];
            }

            return $this->rankPhrases($reviews);
        });
    }

    public function phrasesCacheKey(int $raterId): string
    {
        return sprintf('rater_phrases_v%d_%d', self::PHRASES_CACHE_VERSION, $raterId);
    }

    private function rankPhrases($reviews): array
    {
        $allPhrases = [];
        $processedWords = 0;
        $boundaryMarker = '|||BOUNDARY_' . uniqid() . '|||';

        foreach ($reviews as $review) {
            if ($processedWords >= self::MAX_TOTAL_WORDS) {
                break;
            }

            [$words, $sentences, $lowerSentences, $actualWordCount] = $this->tokenizeReview((string) $review->review, $boundaryMarker);
            if ($actualWordCount === 0) {
                continue;
            }

            $processedWords += $actualWordCount;
            $seenPhrases = [];
            $wordsCount = count($words);

            for ($length = 4; $length >= 2; $length--) {
                if ($wordsCount < $length) {
                    continue;
                }

                for ($i = 0; $i <= $wordsCount - $length; $i++) {
                    $phraseWords = array_slice($words, $i, $length);
                    if (in_array($boundaryMarker, $phraseWords, true)) {
                        continue;
                    }

                    $phrase = implode(' ', $phraseWords);
                    if (strlen($phrase) < 5 || isset($seenPhrases[$phrase]) || ! $this->isPhraseMeaningful($phrase)) {
                        continue;
                    }

                    $seenPhrases[$phrase] = true;
                    $matchingSentences = $this->matchingSentences($phrase, $sentences, $lowerSentences);
                    $this->recordPhrase($allPhrases, $phrase, $length, $review, $matchingSentences);
                }
            }
        }

        $allPhrases = $this->finalizePhrases($allPhrases);
        uasort($allPhrases, fn ($a, $b) => $a['count'] !== $b['count']
            ? $b['count'] <=> $a['count']
            : $b['length'] <=> $a['length']);

        return $this->filterSubphrases(array_slice($allPhrases, 0, self::COMPARISON_LIMIT, true));
    }

    private function tokenizeReview(string $review, string $boundaryMarker): array
    {
        $decodedReview = html_entity_decode(mb_substr($review, 0, self::MAX_REVIEW_CHARS), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $textWithDelimiters = preg_replace('/<br\s*\/?>/i', $boundaryMarker, $decodedReview);
        $textWithDelimiters = preg_replace('/<\/(p|div|h[1-6]|li|ul|ol|tr|td|th|blockquote)>/i', $boundaryMarker, $textWithDelimiters);
        $textWithDelimiters = preg_replace('/<(p|div|h[1-6]|li|ul|ol|tr|td|th|blockquote)[^>]*>/i', '', $textWithDelimiters);

        $words = [];
        foreach (explode($boundaryMarker, strip_tags($textWithDelimiters)) as $block) {
            $cleanBlock = preg_replace('/[^\w\s\']/', ' ', strtolower($block));
            $cleanBlock = preg_replace('/\s+/', ' ', $cleanBlock);
            $blockWords = array_filter(explode(' ', trim($cleanBlock)));
            if (count($blockWords) > 0) {
                $words = array_merge($words, $blockWords, [$boundaryMarker]);
            }
        }

        if (end($words) === $boundaryMarker) {
            array_pop($words);
        }

        $sentenceText = preg_replace('/<br\s*\/?>/i', '. ', $decodedReview);
        $sentenceText = preg_replace('/<\/(p|div|h[1-6]|li|ul|ol|tr|td|th|blockquote)>/i', '. ', $sentenceText);
        $sentenceText = preg_replace('/<(p|div|h[1-6]|li|ul|ol|tr|td|th|blockquote)[^>]*>/i', ' ', $sentenceText);
        $sentences = array_slice(array_filter(preg_split('/(?<=[.!?])\s+/', strip_tags($sentenceText))), 0, self::MAX_SENTENCES);

        $actualWordCount = count(array_filter($words, fn ($word) => $word !== $boundaryMarker));

        return [$words, $sentences, array_map('strtolower', $sentences), $actualWordCount];
    }

    private function matchingSentences(string $phrase, array $sentences, array $lowerSentences): array
    {
        $pattern = '/\b' . implode('[-\s]+', array_map(fn ($word) => preg_quote($word, '/'), explode(' ', $phrase))) . '\b/';
        $matchingSentences = [];

        foreach ($lowerSentences as $index => $lowerSentence) {
            if (count($matchingSentences) >= 3) {
                break;
            }
            if (preg_match($pattern, $lowerSentence)) {
                $matchingSentences[] = $sentences[$index];
            }
        }

        return $matchingSentences;
    }

    private function recordPhrase(array &$allPhrases, string $phrase, int $length, object $review, array $matchingSentences): void
    {
        if (! isset($allPhrases[$phrase])) {
            if (count($allPhrases) >= self::MAX_CANDIDATES) {
                return;
            }

            $allPhrases[$phrase] = [
                'count' => 1,
                'length' => $length,
                'total_rating' => $review->rating,
                'contexts' => [],
            ];
        } else {
            $allPhrases[$phrase]['count']++;
            $allPhrases[$phrase]['total_rating'] += $review->rating;
        }

        $allPhrases[$phrase]['contexts'][$review->game_name] ??= [
            'slug' => $review->game_slug,
            'rating' => $review->game_rating,
            'sentences' => [],
        ];

        $existingSentences = $allPhrases[$phrase]['contexts'][$review->game_name]['sentences'];
        if (count($existingSentences) < 3) {
            $allPhrases[$phrase]['contexts'][$review->game_name]['sentences'] = array_merge(
                $existingSentences,
                array_slice($matchingSentences, 0, 3 - count($existingSentences))
            );
        }
    }

    private function finalizePhrases(array $allPhrases): array
    {
        $allPhrases = array_filter($allPhrases, fn ($data) => $data['count'] > 1);

        foreach ($allPhrases as &$data) {
            $data['avg_rating'] = $data['total_rating'] / $data['count'];
            foreach ($data['contexts'] as &$gameData) {
                $gameData['sentences'] = array_slice(array_values(array_unique($gameData['sentences'])), 0, 3);
            }
            unset($data['total_rating']);
        }
        unset($data);

        return $allPhrases;
    }

    private function filterSubphrases(array $topPhrases): array
    {
        $filteredPhrases = [];

        foreach ($topPhrases as $phrase => $data) {
            $isSubphrase = false;
            $relations = [];

            foreach ($topPhrases as $otherPhrase => $otherData) {
                if ($phrase === $otherPhrase) {
                    continue;
                }

                if (stripos($otherPhrase, $phrase) !== false && $otherData['count'] >= ($data['count'] * 0.8)) {
                    $isSubphrase = true;
                    break;
                }

                if (stripos($phrase, $otherPhrase) !== false && $data['count'] >= ($otherData['count'] * 0.8) && count($relations) < 3) {
                    $relations[] = [
                        'phrase' => $otherPhrase,
                        'count' => $otherData['count'],
                        'avg_rating' => $otherData['avg_rating'],
                    ];
                }
            }

            if (! $isSubphrase) {
                $filteredPhrases[$phrase] = $data;
                $filteredPhrases[$phrase]['related'] = $relations;
            }

            if (count($filteredPhrases) >= 10) {
                break;
            }
        }

        return array_slice($filteredPhrases, 0, 10, true);
    }

    private function isPhraseMeaningful(string $phrase): bool
    {
        static $fillerWords = [
            'a' => true, 'about' => true, 'above' => true, 'after' => true, 'again' => true, 'against' => true,
            'all' => true, 'am' => true, 'an' => true, 'and' => true, 'any' => true, 'are' => true, "aren't" => true,
            'as' => true, 'at' => true, 'be' => true, 'because' => true, 'been' => true, 'before' => true,
            'being' => true, 'below' => true, 'between' => true, 'both' => true, 'but' => true, 'by' => true,
            'could' => true, "couldn't" => true, 'did' => true, "didn't" => true, 'do' => true, 'does' => true,
            "doesn't" => true, 'doing' => true, "don't" => true, 'down' => true, 'during' => true, 'each' => true,
            'few' => true, 'for' => true, 'from' => true, 'further' => true, 'had' => true, "hadn't" => true,
            'has' => true, "hasn't" => true, 'have' => true, "haven't" => true, 'having' => true, 'he' => true,
            "he'd" => true, "he'll" => true, "he's" => true, 'her' => true, 'here' => true, "here's" => true,
            'hers' => true, 'herself' => true, 'him' => true, 'himself' => true, 'his' => true, 'how' => true,
            "how's" => true, 'i' => true, "i'd" => true, "i'll" => true, "i'm" => true, "i've" => true,
            'if' => true, 'in' => true, 'into' => true, 'is' => true, "isn't" => true, 'it' => true, "it's" => true,
            'its' => true, 'itself' => true, "let's" => true, 'me' => true, 'more' => true, 'most' => true,
            "mustn't" => true, 'my' => true, 'myself' => true, 'no' => true, 'nor' => true, 'not' => true,
            'of' => true, 'off' => true, 'on' => true, 'once' => true, 'only' => true, 'or' => true, 'other' => true,
            'ought' => true, 'our' => true, 'ours' => true, 'ourselves' => true, 'out' => true, 'over' => true,
            'own' => true, 'same' => true, "shan't" => true, 'she' => true, "she'd" => true, "she'll" => true,
            "she's" => true, 'should' => true, "shouldn't" => true, 'so' => true, 'some' => true, 'such' => true,
            'than' => true, 'that' => true, "that's" => true, 'the' => true, 'their' => true, 'theirs' => true,
            'them' => true, 'themselves' => true, 'then' => true, 'there' => true, "there's" => true,
            'these' => true, 'they' => true, "they'd" => true, "they'll" => true, "they're" => true,
            "they've" => true, 'this' => true, 'those' => true, 'through' => true, 'to' => true, 'too' => true,
            'under' => true, 'until' => true, 'up' => true, 'very' => true, 'was' => true, "wasn't" => true,
            'we' => true, "we'd" => true, "we'll" => true, "we're" => true, "we've" => true, 'were' => true,
            "weren't" => true, 'what' => true, "what's" => true, 'when' => true, "when's" => true,
            'where' => true, "where's" => true, 'which' => true, 'while' => true, 'who' => true, "who's" => true,
            'whom' => true, 'why' => true, "why's" => true, 'with' => true, "won't" => true, 'would' => true,
            "wouldn't" => true, 'you' => true, "you'd" => true, "you'll" => true, "you're" => true,
            "you've" => true, 'your' => true, 'yours' => true, 'yourself' => true, 'yourselves' => true,
        ];

        $words = explode(' ', $phrase);
        $totalWords = count($words);
        if ($totalWords === 0) {
            return false;
        }

        $fillerCount = 0;
        foreach ($words as $word) {
            if (isset($fillerWords[$word])) {
                $fillerCount++;
            }
        }

        return ($fillerCount / $totalWords) < 0.5;
    }

    private function distribution($rows, string $countColumn): array
    {
        $distribution = [];
        foreach ($rows as $row) {
            $rating = (int) $row->rating_value;
            $distribution[$rating] = (int) ($distribution[$rating] ?? 0) + (int) $row->{$countColumn};
        }

        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = $distribution[$i] ?? 0;
        }
        ksort($distribution);

        return $distribution;
    }

    private function statsBlock(int $total, int $reviewed, float $average, int $uniqueGames, array $distribution): array
    {
        return [
            'total_ratings' => $total,
            'reviewed_count' => $reviewed,
            'review_percentage' => $total > 0 ? ($reviewed / $total * 100) : 0,
            'average_rating' => $average,
            'unique_games' => $uniqueGames,
            'rating_distribution' => $distribution,
        ];
    }

    private function emptyStats(): array
    {
        $block = $this->statsBlock(0, 0, 0, 0, [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]);

        return [
            'first_rating' => null,
            'latest_rating' => null,
            'all_games' => $block,
            'visible_games' => $block,
        ];
    }
}
