<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameJam;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GameJamScrapeService
{
    private const MAX_RESULTS_PAGES = 50;

    private const MAX_RESULTS_PAGE_DELAY_SECONDS = 5;

    public function fetchDetails(GameJam $gameJam): bool
    {
        try {
            // Get the ItchHttpClientService
            $itchClient = App::make(ItchHttpClientService::class);

            // Fetch the game jam page
            Log::info('Fetching game jam details page', [
                'url' => $gameJam->url,
                'game_jam_id' => $gameJam->id,
                'game_jam_name' => $gameJam->name,
            ]);

            $safeUrl = GameJam::normalizeAndValidateJamUrl($gameJam->url);

            $response = $itchClient->get($safeUrl, [
                'cookies' => false,
                'allow_redirects' => false,
            ]);

            $statusCode = $response->getStatusCode();

            // The ItchHttpClientService handles 429 errors, but we still need to check for other errors
            if ($statusCode !== 200) {
                throw new Exception("HTTP error {$statusCode} when fetching game jam details");
            }

            $html = $response->getBody()->getContents();
            $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

            // Extract description
            $descriptionElement = $doc->querySelector('.formatted_description');
            if ($descriptionElement) {
                $gameJam->description = app(HtmlSanitizerService::class)->sanitizeDescription($descriptionElement->innerHTML);
            }

            // Extract dates
            app(GameJamDetailsParser::class)->extractDates($gameJam, $doc);

            // Extract host
            $hostElement = $doc->querySelector('.jam_host_header a, .host_header a');
            if ($hostElement) {
                $gameJam->host = trim($hostElement->textContent);
            }

            // If we couldn't find submission count in the stats, try to find it elsewhere
            if (! $gameJam->submission_count) {
                app(GameJamDetailsParser::class)->extractSubmissionCount($gameJam, $doc);
            }

            // We'll skip automatic results fetching here since the command will handle it
            // This prevents duplicate fetching of results
            // if ($gameJam->hasEnded()) {
            //     $gameJam->fetchResultsPage($client);
            // }

            $gameJam->save();

            Log::info('Successfully fetched game jam details', [
                'game_jam_id' => $gameJam->id,
                'game_jam_name' => $gameJam->name,
                'has_description' => ! empty($gameJam->description),
                'has_dates' => $gameJam->start_date !== null && $gameJam->end_date !== null,
                'submission_count' => $gameJam->submission_count,
            ]);

            return true;
        } catch (Exception $e) {
            // Log error but don't throw
            Log::error('Error fetching game jam details', [
                'jam_url' => $gameJam->url,
                'game_jam_id' => $gameJam->id,
                'game_jam_name' => $gameJam->name,
                'error' => $e->getMessage(),
            ]);

            // Re-throw rate limit errors so they can be handled by the retry mechanism
            if (str_contains($e->getMessage(), '429 Too Many Requests')) {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Fetch and parse the results page for this game jam
     *
     * @return bool True if rankings were successfully fetched, false otherwise
     *
     * @throws Exception If there was a critical error fetching rankings
     * @throws Throwable
     */
    public function fetchResultsPage(
        GameJam $gameJam,
        int $maxRetries = 5,
        int $retryDelay = 30,
        int $maxPages = self::MAX_RESULTS_PAGES,
        int $pageDelaySeconds = 1
    ): bool {
        $maxPages = max(1, $maxPages);
        $pageDelaySeconds = max(0, min($pageDelaySeconds, self::MAX_RESULTS_PAGE_DELAY_SECONDS));

        $currentPage = 1;
        $hasMorePages = true;
        $rankingsFound = 0;
        $pagesProcessed = 0;

        try {
            Log::info('Starting to fetch rankings for game jam', [
                'game_jam_id' => $gameJam->id,
                'game_jam_name' => $gameJam->name,
                'url' => $gameJam->url,
            ]);

            while ($hasMorePages) {
                $pageDoc = $this->fetchResultsPageNumber($gameJam, $currentPage, $maxRetries, $retryDelay);
                if (! $pageDoc) {
                    // If we couldn't fetch the first page, that's a critical error
                    if ($currentPage === 1) {
                        throw new Exception("Failed to fetch first page of rankings for game jam {$gameJam->name}");
                    }
                    // Otherwise, we've probably reached the end of pagination
                    break;
                }

                $pagesProcessed++;

                // Process the results page to extract rankings
                $pageRankings = $this->extractRankings($gameJam, $pageDoc);
                $rankingsFound += $pageRankings;

                // Check if there's a next page by looking for the pagination element
                $nextPageLink = $pageDoc->querySelector('.next_page:not(.disabled)');
                if (! $nextPageLink) {
                    $hasMorePages = false;
                } else {
                    if ($currentPage >= $maxPages) {
                        Log::warning('Stopped fetching game jam rankings after reaching page limit', [
                            'game_jam_id' => $gameJam->id,
                            'game_jam_name' => $gameJam->name,
                            'pages_processed' => $pagesProcessed,
                            'max_pages' => $maxPages,
                        ]);

                        return false;
                    }

                    $currentPage++;
                    if ($pageDelaySeconds > 0) {
                        sleep($pageDelaySeconds);
                    }
                }
            }

            Log::info('Completed fetching rankings for game jam', [
                'game_jam_id' => $gameJam->id,
                'game_jam_name' => $gameJam->name,
                'pages_processed' => $pagesProcessed,
                'rankings_found' => $rankingsFound,
            ]);

            // If we processed pages but found no rankings, that's suspicious
            if ($pagesProcessed > 0 && $rankingsFound === 0) {
                Log::warning('No rankings found despite processing pages', [
                    'game_jam_id' => $gameJam->id,
                    'game_jam_name' => $gameJam->name,
                    'pages_processed' => $pagesProcessed,
                ]);

                return false;
            }

            return $rankingsFound > 0;

        } catch (Exception $e) {
            Log::error('Error fetching rankings for game jam', [
                'game_jam_id' => $gameJam->id,
                'game_jam_name' => $gameJam->name,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e; // Re-throw to be handled by the retry mechanism
        }
    }

    /**
     * Fetch a specific page of results
     *
     * @return HTMLDocument|null The parsed HTML document for the page, or null if the fetch failed
     *
     * @throws BindingResolutionException
     * @throws GuzzleException
     */
    private function fetchResultsPageNumber(GameJam $gameJam, int $pageNumber, int $maxRetries, int $retryDelay): ?HTMLDocument
    {
        // Get the ItchHttpClientService
        $itchClient = App::make(ItchHttpClientService::class);
        $itchClient->setMaxRetries($maxRetries);
        $itchClient->setBaseCooldown($retryDelay);

        try {
            // Construct the results page URL with page number
            $resultsUrl = GameJam::normalizeAndValidateJamUrl($gameJam->url).'/results';
            if ($pageNumber > 1) {
                $resultsUrl .= '?page='.$pageNumber;
            }

            Log::info('Fetching game jam results page', [
                'url' => $resultsUrl,
                'page' => $pageNumber,
                'game_jam_id' => $gameJam->id,
                'game_jam_name' => $gameJam->name,
            ]);

            // Fetch the results page - the ItchHttpClientService will handle retries for 429 errors
            $response = $itchClient->get($resultsUrl, [
                'cookies' => false,
                'allow_redirects' => false,
            ]);

            $statusCode = $response->getStatusCode();

            // The ItchHttpClientService handles 429 errors, but we still need to check for other errors
            if ($statusCode !== 200) {
                Log::warning('Game jam results page not found', [
                    'url' => $resultsUrl,
                    'status_code' => $statusCode,
                    'game_jam_id' => $gameJam->id,
                    'game_jam_name' => $gameJam->name,
                ]);

                return null;
            }

            Log::info('Successfully fetched game jam results page', [
                'url' => $resultsUrl,
                'page' => $pageNumber,
                'game_jam_id' => $gameJam->id,
                'game_jam_name' => $gameJam->name,
            ]);

            $html = $response->getBody()->getContents();

            return HTMLDocument::createFromString($html, LIBXML_NOERROR);

        } catch (Exception $e) {
            // Log error
            Log::error('Error fetching game jam results', [
                'jam_url' => $gameJam->url,
                'page' => $pageNumber,
                'error' => $e->getMessage(),
                'game_jam_id' => $gameJam->id,
                'game_jam_name' => $gameJam->name,
            ]);

            return null;
        }
    }

    /**
     * Extract rankings from the results page
     *
     * @return int The number of rankings found and processed
     *
     * @throws Throwable
     */
    private function extractRankings(GameJam $gameJam, HTMLDocument $doc): int
    {
        // Keep track of how many rankings we found
        $rankingsFound = 0;

        // Find all game rank divs which contain the game entries and rankings
        $gameRankDivs = $doc->querySelectorAll('.game_rank');

        // Collect all the ranking data first to avoid partial updates
        $rankingData = [];

        foreach ($gameRankDivs as $gameRankDiv) {
            // Extract the game URL from the link
            $gameLink = $gameRankDiv->querySelector('a.game_cover');
            if (! $gameLink) {
                Log::info('No game link found in game rank div');

                continue;
            }

            $gameUrl = $gameLink->getAttribute('href');
            if (! $gameUrl) {
                Log::info('No URL found in game link');

                continue;
            }

            // Clean URL (remove any query parameters)
            $gameUrl = preg_replace('/\?.*$/', '', $gameUrl);

            // Find the game in our database
            $game = Game::byUrl($gameUrl)->first();
            if (! $game) {
                Log::info('Game not found in database', ['url' => $gameUrl]);

                continue;
            }

            // Extract the game title
            $gameTitleElement = $gameRankDiv->querySelector('.game_summary h2 a');
            $gameTitle = $gameTitleElement ? trim($gameTitleElement->textContent) : $game->name;

            Log::info('Processing game',
                ['title' => $gameTitle, 'url' => $gameUrl, 'game_id' => $game->id, 'game_jam_id' => $gameJam->id]);

            // Extract the ranking - it's in a h3 tag with the format "Ranked <strong>Nth</strong> with X ratings..."
            $rankingElement = $gameRankDiv->querySelector('.game_summary h3 .ordinal_rank');
            if (! $rankingElement) {
                Log::info('No ranking found for game', ['game' => $gameTitle, 'url' => $gameUrl]);

                continue;
            }

            $ranking = trim($rankingElement->textContent);
            Log::info('Found ranking', ['game' => $gameTitle, 'ranking' => $ranking, 'game_id' => $game->id]);

            // Extract criteria rankings if available
            $criteriaRankings = [];

            // The criteria rankings are in a table with columns: Criteria, Rank, Score*, Raw Score
            $criteriaTable = $gameRankDiv->querySelector('table');
            if ($criteriaTable) {
                $criteriaRows = $criteriaTable->querySelectorAll('tr');
                foreach ($criteriaRows as $row) {
                    $cells = $row->querySelectorAll('td');
                    if (count($cells) >= 3) { // We need at least Criteria, Rank, and Score
                        $criteriaName = trim($cells[0]->textContent);
                        $criteriaRank = trim($cells[1]->textContent);
                        $criteriaScore = trim($cells[2]->textContent);

                        // Skip header row or empty rows
                        if (empty($criteriaName) || $criteriaName === 'Criteria') {
                            continue;
                        }

                        $criteriaRankings[$criteriaName] = [
                            'rank' => $criteriaRank,
                            'score' => $criteriaScore,
                        ];
                    }
                }

                if (! empty($criteriaRankings)) {
                    Log::info('Found criteria rankings', [
                        'game' => $gameTitle,
                        'criteria_count' => count($criteriaRankings),
                        'game_id' => $game->id,
                        'criteria' => array_keys($criteriaRankings),
                    ]);
                } else {
                    Log::info('No criteria rankings found in table', [
                        'game' => $gameTitle,
                        'game_id' => $game->id,
                    ]);
                }
            } else {
                Log::info('No criteria table found', [
                    'game' => $gameTitle,
                    'game_id' => $game->id,
                ]);
            }

            // Only use the overall ranking if there's actually an "Overall" category in criteria rankings
            $finalRanking = null;
            if (! empty($criteriaRankings)) {
                foreach (array_keys($criteriaRankings) as $criteriaName) {
                    if (strtolower((string) $criteriaName) === 'overall') {
                        $finalRanking = $ranking;
                        break;
                    }
                }
            }

            // Store the ranking data for later processing
            $rankingData[] = [
                'game' => $game,
                'ranking' => $finalRanking,
                'criteria_rankings' => ! empty($criteriaRankings) ? $criteriaRankings : null,
            ];
        }

        // Now process all the rankings in a single transaction
        if (! empty($rankingData)) {
            Log::info('Starting transaction to update rankings', [
                'game_jam_id' => $gameJam->id,
                'game_jam_name' => $gameJam->name,
                'rankings_count' => count($rankingData),
            ]);

            // Use a database transaction to ensure all updates are atomic
            $changedGameIds = [];

            DB::transaction(function () use ($gameJam, $rankingData, &$rankingsFound, &$changedGameIds) {
                foreach ($rankingData as $data) {
                    $game = $data['game'];
                    $ranking = $data['ranking'];
                    $criteriaRankings = $data['criteria_rankings'] ?? null;
                    $pivotData = [
                        'ranking' => $ranking,
                        'criteria_rankings' => $criteriaRankings ? json_encode($criteriaRankings) : null,
                    ];

                    // Reload the game to ensure we have the latest data
                    $game->refresh();

                    // Check if the game is already associated with this jam
                    if ($game->gameJams()->where('game_jam_id', $gameJam->id)->exists()) {
                        // Update the existing pivot record
                        $game->gameJams()->updateExistingPivot($gameJam->id, $pivotData);
                        $changedGameIds[] = $game->id;
                        Log::info('Updated existing game jam ranking', [
                            'game_id' => $game->id,
                            'game' => $game->name,
                            'jam_id' => $gameJam->id,
                            'jam' => $gameJam->name,
                            'ranking' => $ranking,
                            'has_criteria_rankings' => $criteriaRankings !== null,
                            'criteria_count' => $criteriaRankings ? count($criteriaRankings) : 0,
                        ]);
                    } else {
                        // Create a new association
                        $game->gameJams()->attach($gameJam->id, $pivotData);
                        $changedGameIds[] = $game->id;
                        Log::info('Created new game jam ranking', [
                            'game_id' => $game->id,
                            'game' => $game->name,
                            'jam_id' => $gameJam->id,
                            'jam' => $gameJam->name,
                            'ranking' => $ranking,
                            'has_criteria_rankings' => $criteriaRankings !== null,
                            'criteria_count' => $criteriaRankings ? count($criteriaRankings) : 0,
                        ]);
                    }

                    $rankingsFound++;
                }
            });

            Log::info('Transaction completed successfully', [
                'game_jam_id' => $gameJam->id,
                'rankings_updated' => $rankingsFound,
            ]);

            if (! empty($changedGameIds)) {
                GameFilterService::clearCache();

                Game::query()
                    ->whereIn('id', array_unique($changedGameIds))
                    ->where('is_visible', true)
                    ->with(['tags', 'gameJams', 'gameVersions'])
                    ->chunk(100, function ($games) {
                        $games->searchable();
                    });
            }
        }

        Log::info('Rankings extraction complete for current page', [
            'found' => $rankingsFound,
            'total_games' => count($gameRankDivs),
            'game_jam_id' => $gameJam->id,
            'game_jam_name' => $gameJam->name,
        ]);

        return $rankingsFound;
    }
}
