<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SteamReviewImportService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'fvn.li/1.0 (Visual Novel Review Aggregator)',
            ],
        ]);
    }

    /**
     * Completely sync all reviews for a Steam game
     * Fetches all reviews, updates existing ones, and removes reviews no longer present on Steam
     *
     * @param  Game  $game  The game to sync reviews for
     * @return array Statistics about the sync
     *
     * @throws GuzzleException
     */
    public function syncAllReviews(Game $game): array
    {
        if ($game->platform !== 'steam') {
            throw new Exception('Game is not a Steam game');
        }

        if (! $game->steam_app_id) {
            throw new Exception('Game does not have a Steam App ID');
        }

        $stats = [
            'fetched' => 0,
            'imported' => 0,
            'updated' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $cursor = '*';
        $steamReviewIds = []; // Track all review IDs found on Steam

        Log::info('Starting complete Steam review sync', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'steam_app_id' => $game->steam_app_id,
        ]);

        // Fetch all reviews from Steam
        while (true) {
            try {
                $response = $this->fetchReviews($game->steam_app_id, $cursor);

                if (! $response['success']) {
                    Log::error('Steam API returned unsuccessful response', [
                        'game_id' => $game->id,
                        'response' => $response,
                    ]);
                    break;
                }

                $reviews = $response['reviews'] ?? [];
                $stats['fetched'] += count($reviews);

                Log::debug('Fetched reviews batch for sync', [
                    'game_id' => $game->id,
                    'cursor' => $cursor,
                    'reviews_count' => count($reviews),
                    'total_fetched' => $stats['fetched'],
                ]);

                if (empty($reviews)) {
                    Log::info('No more reviews to fetch for sync', ['game_id' => $game->id]);
                    break;
                }

                foreach ($reviews as $reviewData) {
                    try {
                        $recommendationId = $reviewData['recommendationid'] ?? null;
                        if ($recommendationId) {
                            $steamReviewIds[] = (string) $recommendationId;
                        }

                        $result = $this->syncSingleReview($game, $reviewData);
                        if ($result === 'imported') {
                            $stats['imported']++;
                        } elseif ($result === 'updated') {
                            $stats['updated']++;
                        } elseif (str_starts_with($result, 'skipped:')) {
                            $stats['skipped']++;
                        }
                    } catch (Exception $e) {
                        $stats['errors']++;
                        Log::error('Failed to sync single review', [
                            'game_id' => $game->id,
                            'recommendation_id' => $reviewData['recommendationid'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Get next cursor for pagination
                $nextCursor = $response['cursor'] ?? null;

                if (! $nextCursor) {
                    Log::info('No more pages to fetch for sync', ['game_id' => $game->id]);
                    break;
                }

                $cursor = $nextCursor;

                // Rate limiting - sleep between requests
                sleep(1);

            } catch (Exception $e) {
                $stats['errors']++;
                Log::error('Failed to fetch reviews batch for sync', [
                    'game_id' => $game->id,
                    'cursor' => $cursor,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                break;
            }
        }

        // Remove reviews that are no longer present on Steam
        if (! empty($steamReviewIds)) {
            $deletedCount = Rating::where('game_id', $game->id)
                ->where('source_platform', 'steam')
                ->whereNotIn('external_id', $steamReviewIds)
                ->delete();

            $stats['deleted'] = $deletedCount;

            Log::info('Deleted reviews no longer on Steam', [
                'game_id' => $game->id,
                'deleted_count' => $deletedCount,
            ]);
        }

        Log::info('Completed complete Steam review sync', array_merge(['game_id' => $game->id], $stats));

        return $stats;
    }

    /**
     * Update game rating statistics after import
     */
    public function updateGameRatingStats(Game $game): void
    {
        $stats = Rating::where('game_id', $game->id)
            ->where('is_visible', true)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as count')
            ->first();

        $game->rating_score = $stats->avg_rating ? round((float) $stats->avg_rating, 2) : null;
        $game->rating_count = (int) ($stats->count ?? 0);
        $game->save();

        Log::info('Updated game rating stats', [
            'game_id' => $game->id,
            'rating_score' => $game->rating_score,
            'rating_count' => $game->rating_count,
        ]);
    }

    /**
     * Fetch reviews from Steam API
     *
     * @throws GuzzleException
     */
    private function fetchReviews(int|string $appId, string $cursor): array
    {
        $url = "https://store.steampowered.com/appreviews/{$appId}";

        $response = $this->client->get($url, [
            'query' => [
                'json' => '1',
                'filter' => 'recent', // Use 'recent' to enable proper pagination (will eventually return empty list)
                'language' => 'english', // Only English reviews
                'review_type' => 'all', // Both positive and negative
                'purchase_type' => 'all', // All purchase types
                'num_per_page' => '100', // Maximum 100 reviews per page
                'cursor' => $cursor,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Sync a single review (create or update)
     *
     * @return string 'imported', 'updated', or 'skipped:reason'
     */
    private function syncSingleReview(Game $game, array $reviewData): string
    {
        $recommendationId = $reviewData['recommendationid'] ?? null;
        if (! $recommendationId) {
            return 'skipped:no_id';
        }

        // Only sync reviews with text content
        $reviewText = $reviewData['review'] ?? '';
        if (empty(trim($reviewText))) {
            return 'skipped:no_text';
        }

        // Process review text: normalize line breaks, convert BBCode to HTML
        $reviewText = $this->processReviewText($reviewText);

        // Only sync English reviews
        $language = $reviewData['language'] ?? '';
        if ($language !== 'english') {
            return 'skipped:non_english';
        }

        // Check if we've already imported this review
        $existing = Rating::where('source_platform', 'steam')
            ->where('external_id', (string) $recommendationId)
            ->first();

        // Convert Steam's binary rating to our 1-5 scale
        $rating = $reviewData['voted_up'] ? 5 : 1;

        $metadata = [
            'voted_up' => $reviewData['voted_up'],
            'votes_up' => $reviewData['votes_up'] ?? 0,
            'votes_funny' => $reviewData['votes_funny'] ?? 0,
            'weighted_vote_score' => $reviewData['weighted_vote_score'] ?? 0,
            'comment_count' => $reviewData['comment_count'] ?? 0,
            'steam_purchase' => $reviewData['steam_purchase'] ?? false,
            'received_for_free' => $reviewData['received_for_free'] ?? false,
            'written_during_early_access' => $reviewData['written_during_early_access'] ?? false,
            'playtime_forever' => $reviewData['author']['playtime_forever'] ?? 0,
            'playtime_at_review' => $reviewData['author']['playtime_at_review'] ?? 0,
            'timestamp_updated' => $reviewData['timestamp_updated'] ?? null,
        ];

        if ($existing) {
            // Update existing review if content has changed
            $needsUpdate = false;

            if ($existing->rating !== $rating) {
                $existing->rating = $rating;
                $needsUpdate = true;
            }

            if ($existing->review !== $reviewText) {
                $existing->review = $reviewText;
                $needsUpdate = true;
            }

            if ($existing->external_metadata !== $metadata) {
                $existing->external_metadata = $metadata;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $existing->save();

                Log::debug('Updated existing Steam review', [
                    'game_id' => $game->id,
                    'recommendation_id' => $recommendationId,
                    'rating_id' => $existing->id,
                ]);

                return 'updated';
            }

            return 'skipped:no_changes';
        }

        // Create new review
        DB::transaction(function () use ($game, $reviewData, $recommendationId, $reviewText, $rating, $metadata) {
            // Find or create rater
            $rater = $this->findOrCreateRater($reviewData['author']);

            // Create the rating
            Rating::create([
                'external_id' => (string) $recommendationId,
                'source_platform' => 'steam',
                'game_id' => $game->id,
                'rater_id' => $rater->id,
                'rating' => $rating,
                'review' => $reviewText,
                'published_at' => date('Y-m-d H:i:s', $reviewData['timestamp_created']),
                'event_id' => null, // No event for Steam reviews
                'is_visible' => true, // Auto-approve Steam reviews
                'is_reviewed' => true, // Mark as reviewed since it's from Steam
                'external_metadata' => $metadata,
            ]);
        });

        Log::debug('Imported new Steam review', [
            'game_id' => $game->id,
            'recommendation_id' => $recommendationId,
        ]);

        return 'imported';
    }

    /**
     * Find or create a rater from Steam author data
     */
    private function findOrCreateRater(array $authorData): Rater
    {
        $steamId = $authorData['steamid'] ?? null;
        if (! $steamId) {
            throw new Exception('Steam author data missing steamid');
        }

        // Try to find existing rater by Steam ID
        $rater = Rater::where('steam_id', $steamId)->first();

        if ($rater) {
            return $rater;
        }

        // Fetch Steam username from Steam Community API
        $steamUsername = $this->fetchSteamUsername($steamId);

        // Create new rater
        // Steam raters don't have an itch_id (it's nullable now)
        return Rater::create([
            'itch_id' => null, // Steam users don't have itch IDs
            'name' => $steamUsername ?? "Steam User {$steamId}",
            'steam_id' => $steamId,
            'external_platform' => 'steam',
        ]);
    }

    /**
     * Fetch Steam username from Steam Community API
     */
    private function fetchSteamUsername(string $steamId): ?string
    {
        try {
            $url = "https://steamcommunity.com/profiles/{$steamId}/?xml=1";

            $response = $this->client->get($url, [
                'timeout' => 10,
            ]);

            $xml = $response->getBody()->getContents();

            // Parse XML to extract steamID (username)
            if (preg_match('/<steamID><!\[CDATA\[(.*?)\]\]><\/steamID>/', $xml, $matches)) {
                $username = trim($matches[1]);
                if (! empty($username)) {
                    return $username;
                }
            }

            Log::warning('Could not extract Steam username from XML', [
                'steam_id' => $steamId,
            ]);

            return null;
        } catch (Exception $e) {
            Log::warning('Failed to fetch Steam username', [
                'steam_id' => $steamId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Process Steam review text: convert BBCode to HTML and handle line breaks
     */
    private function processReviewText(string $text): string
    {
        // Normalize line breaks (Steam uses both \r\n and \n)
        $text = str_replace("\r\n", "\n", $text);
        $text = trim($text);

        // Convert BBCode to HTML
        // Steam supports: [b], [i], [u], [strike], [spoiler], [url], [h1], [h2], [h3], [list], [*], [quote], [code]

        // Bold
        $text = preg_replace('/\[b\](.*?)\[\/b\]/is', '<strong>$1</strong>', $text);

        // Italic
        $text = preg_replace('/\[i\](.*?)\[\/i\]/is', '<em>$1</em>', $text);

        // Underline
        $text = preg_replace('/\[u\](.*?)\[\/u\]/is', '<u>$1</u>', $text);

        // Strikethrough
        $text = preg_replace('/\[strike\](.*?)\[\/strike\]/is', '<s>$1</s>', $text);

        // Spoiler - convert to span with click-to-reveal styling (like Steam/Discord)
        // CSS class handles the styling, JavaScript adds the 'revealed' class on click
        $text = preg_replace(
            '/\[spoiler\](.*?)\[\/spoiler\]/is',
            '<span class="spoiler" onclick="this.classList.add(\'revealed\')" title="Click to reveal spoiler">$1</span>',
            $text
        );

        // Headers
        $text = preg_replace('/\[h1\](.*?)\[\/h1\]/is', '<h3>$1</h3>', $text); // h3 for better sizing in reviews
        $text = preg_replace('/\[h2\](.*?)\[\/h2\]/is', '<h4>$1</h4>', $text);
        $text = preg_replace('/\[h3\](.*?)\[\/h3\]/is', '<h5>$1</h5>', $text);

        // URL with text: [url=http://example.com]text[/url]
        $text = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/is', '<a href="$1" target="_blank" rel="noopener">$2</a>', $text);

        // URL without text: [url]http://example.com[/url]
        $text = preg_replace('/\[url\](.*?)\[\/url\]/is', '<a href="$1" target="_blank" rel="noopener">$1</a>', $text);

        // Quote
        $text = preg_replace('/\[quote\](.*?)\[\/quote\]/is', '<blockquote>$1</blockquote>', $text);
        $text = preg_replace('/\[quote=(.*?)\](.*?)\[\/quote\]/is', '<blockquote><cite>$1</cite><br>$2</blockquote>', $text);

        // Code
        $text = preg_replace('/\[code\](.*?)\[\/code\]/is', '<pre><code>$1</code></pre>', $text);

        // Lists - handle unordered lists
        $text = preg_replace('/\[list\](.*?)\[\/list\]/is', '<ul>$1</ul>', $text);
        $text = preg_replace('/\[olist\](.*?)\[\/olist\]/is', '<ol>$1</ol>', $text);

        // List items
        $text = preg_replace('/\[\*\](.*?)(?=\[\*\]|\[\/list\]|\[\/olist\]|$)/is', '<li>$1</li>', $text);

        // Convert remaining newlines to <br> tags
        // But preserve newlines inside <pre> tags
        $text = preg_replace_callback('/<pre>.*?<\/pre>/s', function ($matches) {
            return str_replace("\n", '___NEWLINE___', $matches[0]);
        }, $text);

        $text = nl2br($text);

        // Restore newlines in <pre> tags
        $text = str_replace('___NEWLINE___', "\n", $text);

        return $text;
    }
}
