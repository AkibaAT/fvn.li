<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class RaterPhrases extends Component
{
    // Convert filler words into an associative array for faster lookups.
    private const array FILLER_WORDS = [
        'a' => true, 'about' => true, 'above' => true, 'after' => true, 'again' => true, 'against' => true,
        'all' => true, 'am' => true, 'an' => true, 'and' => true, 'any' => true, 'are' => true, "aren't" => true,
        'as' => true, 'at' => true,
        'be' => true, 'because' => true, 'been' => true, 'before' => true, 'being' => true, 'below' => true,
        'between' => true, 'both' => true, 'but' => true, 'by' => true,
        'could' => true, "couldn't" => true, 'did' => true, "didn't" => true, 'do' => true, 'does' => true,
        "doesn't" => true, 'doing' => true, "don't" => true, 'down' => true, 'during' => true,
        'each' => true, 'few' => true, 'for' => true, 'from' => true, 'further' => true,
        'had' => true, "hadn't" => true, 'has' => true, "hasn't" => true, 'have' => true, "haven't" => true,
        'having' => true, 'he' => true, "he'd" => true, "he'll" => true, "he's" => true, 'her' => true,
        'here' => true, "here's" => true, 'hers' => true, 'herself' => true, 'him' => true, 'himself' => true,
        'his' => true, 'how' => true, "how's" => true,
        'i' => true, "i'd" => true, "i'll" => true, "i'm" => true, "i've" => true, 'if' => true, 'in' => true,
        'into' => true, 'is' => true, "isn't" => true, 'it' => true, "it's" => true, 'its' => true,
        'itself' => true, "let's" => true,
        'me' => true, 'more' => true, 'most' => true, "mustn't" => true, 'my' => true, 'myself' => true,
        'no' => true, 'nor' => true, 'not' => true,
        'of' => true, 'off' => true, 'on' => true, 'once' => true, 'only' => true, 'or' => true, 'other' => true,
        'ought' => true, 'our' => true, 'ours' => true, 'ourselves' => true, 'out' => true, 'over' => true,
        'own' => true,
        'same' => true, "shan't" => true, 'she' => true, "she'd" => true, "she'll" => true, "she's" => true,
        'should' => true, "shouldn't" => true, 'so' => true, 'some' => true, 'such' => true,
        'than' => true, 'that' => true, "that's" => true, 'the' => true, 'their' => true, 'theirs' => true,
        'them' => true, 'themselves' => true, 'then' => true, 'there' => true, "there's" => true,
        'these' => true,
        'they' => true, "they'd" => true, "they'll" => true, "they're" => true, "they've" => true,
        'this' => true, 'those' => true, 'through' => true, 'to' => true,
        'too' => true,
        'under' => true, 'until' => true, 'up' => true,
        'very' => true,
        'was' => true, "wasn't" => true, 'we' => true, "we'd" => true, "we'll" => true, "we're" => true,
        "we've" => true, 'were' => true, "weren't" => true, 'what' => true, "what's" => true, 'when' => true,
        "when's" => true, 'where' => true, "where's" => true, 'which' => true, 'while' => true, 'who' => true,
        "who's" => true, 'whom' => true, 'why' => true, "why's" => true, 'with' => true, "won't" => true,
        'would' => true, "wouldn't" => true,
        'you' => true, "you'd" => true, "you'll" => true, "you're" => true, "you've" => true, 'your' => true,
        'yours' => true, 'yourself' => true, 'yourselves' => true,
    ];

    public int $raterId;
    public array $phrases = [];

    public function mount(int $raterId): void
    {
        $this->raterId = $raterId;
        $this->loadCommonPhrases();
    }

    public function render(): View
    {
        return view('livewire.rater-phrases');
    }

    private function isPhraseMeaningful(string $phrase): bool
    {
        $words = explode(' ', $phrase);
        $totalWords = count($words);
        if ($totalWords === 0) {
            return false;
        }

        $fillerCount = 0;
        foreach ($words as $word) {
            if (isset(self::FILLER_WORDS[$word])) {
                $fillerCount++;
            }
        }

        if (($fillerCount / $totalWords) >= 0.5) {
            return false;
        }

        return true;
    }

    private function loadCommonPhrases(): void
    {
        $reviews = DB::table('ratings')
            ->where('rater_id', $this->raterId)
            ->where('ratings.is_visible', true)
            ->whereNotNull('review')
            ->select([
                'ratings.review',
                'ratings.rating',
                'games.name as game_name',
                'ratings.rating as game_rating'
            ])
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->get();

        if ($reviews->isEmpty()) {
            return;
        }

        $allPhrases = [];

        foreach ($reviews as $review) {
            // Preprocess the review text.
            $rawReview = $review->review;
            $cleanText = strtolower(strip_tags($rawReview));
            $cleanText = preg_replace('/[^\w\s\']/', ' ', $cleanText);
            $cleanText = preg_replace('/\s+/', ' ', $cleanText);
            $words = explode(' ', trim($cleanText));
            $wordsCount = count($words);
            if ($wordsCount === 0) {
                continue;
            }
            $seenPhrases = [];

            // Split the review into sentences and precompute their lowercase versions.
            $sentences = preg_split('/(?<=[.!?])\s+/', strip_tags($rawReview));
            $lowerSentences = array_map('strtolower', $sentences);

            for ($length = 4; $length >= 2; $length--) {
                if ($wordsCount < $length) {
                    continue;
                }
                for ($i = 0; $i <= $wordsCount - $length; $i++) {
                    $phrase = implode(' ', array_slice($words, $i, $length));
                    if (strlen($phrase) < 5 || !$this->isPhraseMeaningful($phrase)) {
                        continue;
                    }

                    if (isset($seenPhrases[$phrase])) {
                        continue;
                    }
                    $seenPhrases[$phrase] = true;

                    $pattern = '/\b'.implode('[-\s]+', array_map(function ($word) {
                            return preg_quote($word, '/');
                        }, explode(' ', $phrase))).'\b/';

                    $matchingSentences = [];
                    foreach ($lowerSentences as $index => $lowerSentence) {
                        if (preg_match($pattern, $lowerSentence)) {
                            // Highlight the matching text in the original sentence.
                            $matchingSentences[] = $sentences[$index];
                        }
                    }

                    if (!isset($allPhrases[$phrase])) {
                        $allPhrases[$phrase] = [
                            'count' => 1,
                            'length' => $length,
                            'total_rating' => $review->rating,
                            'contexts' => [
                                $review->game_name => [
                                    'rating' => $review->game_rating,
                                    'sentences' => $matchingSentences,
                                ],
                            ],
                        ];
                    } else {
                        $allPhrases[$phrase]['count']++;
                        $allPhrases[$phrase]['total_rating'] += $review->rating;
                        if (!isset($allPhrases[$phrase]['contexts'][$review->game_name])) {
                            $allPhrases[$phrase]['contexts'][$review->game_name] = [
                                'rating' => $review->game_rating,
                                'sentences' => [],
                            ];
                        }
                        $allPhrases[$phrase]['contexts'][$review->game_name]['sentences'] = array_merge(
                            $allPhrases[$phrase]['contexts'][$review->game_name]['sentences'],
                            $matchingSentences
                        );
                    }
                }
            }
        }

        // Remove phrases that appear only once.
        $allPhrases = array_filter($allPhrases, fn($data) => $data['count'] > 1);

        foreach ($allPhrases as &$data) {
            $data['avg_rating'] = $data['total_rating'] / $data['count'];
            // Remove duplicate sentences in each game's context.
            foreach ($data['contexts'] as &$gameData) {
                $gameData['sentences'] = array_unique($gameData['sentences']);
            }
            unset($data['total_rating']);
        }
        unset($data);

        uasort($allPhrases, function ($a, $b) {
            if ($a['count'] !== $b['count']) {
                return $b['count'] <=> $a['count'];
            }
            return $b['length'] <=> $a['length'];
        });

        $filteredPhrases = [];
        foreach ($allPhrases as $phrase => $data) {
            $isSubphrase = false;
            $relations = [];

            foreach ($allPhrases as $otherPhrase => $otherData) {
                if ($phrase === $otherPhrase) {
                    continue;
                }

                // If this phrase is part of another phrase with similar count.
                if (stripos($otherPhrase, $phrase) !== false &&
                    $otherData['count'] >= ($data['count'] * 0.8)) {
                    $isSubphrase = true;
                    break;
                } elseif (stripos($phrase, $otherPhrase) !== false &&
                    $data['count'] >= ($otherData['count'] * 0.8)) {
                    // Track related phrases.
                    $relations[] = [
                        'phrase' => $otherPhrase,
                        'count' => $otherData['count'],
                        'avg_rating' => $otherData['avg_rating'],
                    ];
                }

                similar_text($phrase, $otherPhrase, $percent);
                if ($percent > 80 && $otherData['count'] >= $data['count']) {
                    $isSubphrase = true;
                    break;
                }
            }

            if (!$isSubphrase) {
                $filteredPhrases[$phrase] = $data;
                $filteredPhrases[$phrase]['related'] = $relations;
            }
        }

        $this->phrases = array_slice($filteredPhrases, 0, 30, true);
    }
}
