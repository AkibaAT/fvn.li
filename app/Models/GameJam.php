<?php

declare(strict_types=1);

namespace App\Models;

use DateTime;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameJam extends Model
{
    protected $fillable = [
        'name',
        'url',
        'description',
        'start_date',
        'end_date',
        'submission_count',
        'participant_count',
        'host',
        'needs_details_fetch',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'submission_count' => 'integer',
        'participant_count' => 'integer',
        'needs_details_fetch' => 'boolean',
    ];

    /**
     * Find or create a game jam from a URL
     *
     * This method only creates a basic record with name and URL.
     * It does not fetch additional details from the game jam page.
     * Use the FetchGameJamDetails job or command to load full details.
     */
    public static function findOrCreateFromUrl(string $url, ?string $name = null): self
    {
        // Clean up the URL
        if (preg_match('|(https?://[^/]+/jam/[^/]+)/rate/|', $url, $matches)) {
            $url = $matches[1];
        }

        // Check if we already have this game jam
        $gameJam = self::where('url', $url)->first();

        if (! $gameJam) {
            // Create a new game jam with basic information
            $gameJam = new self([
                'name' => $name ?: 'Unknown Game Jam',
                'url' => $url,
                'needs_details_fetch' => true, // Mark as needing details fetch
            ]);

            $gameJam->save();

            // Queue a job to fetch the details
            // We'll implement this job later
            // FetchGameJamDetails::dispatch($gameJam);
        }

        return $gameJam;
    }

    /**
     * Get the games that participated in this game jam.
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_game_jam')
            ->withPivot('ranking')
            ->withTimestamps();
    }

    /**
     * Check if the game jam is currently active
     */
    public function isActive(): bool
    {
        if (! $this->start_date || ! $this->end_date) {
            return false;
        }

        $now = now();

        return $now->greaterThanOrEqualTo($this->start_date) && $now->lessThanOrEqualTo($this->end_date);
    }

    /**
     * Check if the game jam is upcoming
     */
    public function isUpcoming(): bool
    {
        if (! $this->start_date) {
            return false;
        }

        return now()->lessThan($this->start_date);
    }

    /**
     * Check if the game jam has ended
     */
    public function hasEnded(): bool
    {
        if (! $this->end_date) {
            return false;
        }

        return now()->greaterThan($this->end_date);
    }

    /**
     * Get the duration of the game jam in days
     */
    public function getDurationInDays(): ?int
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        return (int) ($this->start_date->diffInDays($this->end_date) + 1); // +1 to include both start and end days
    }

    /**
     * Fetch details about a game jam from its page
     */
    public function fetchDetailsFromUrl(Client $client): bool
    {
        try {
            // Fetch the game jam page
            Log::info('Fetching game jam details page', [
                'url' => $this->url,
                'game_jam_id' => $this->id,
                'game_jam_name' => $this->name,
            ]);

            $response = $client->get($this->url, [
                'cookies' => false,
                'http_errors' => false, // Don't throw exceptions for 4xx/5xx
            ]);

            $statusCode = $response->getStatusCode();

            // Handle rate limiting and server errors
            if ($statusCode === 429) {
                throw new Exception('429 Too Many Requests - Rate limit exceeded when fetching game jam details');
            }

            if ($statusCode !== 200) {
                throw new Exception("HTTP error {$statusCode} when fetching game jam details");
            }

            $html = $response->getBody()->getContents();
            $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

            // Extract description
            $descriptionElement = $doc->querySelector('.formatted_description');
            if ($descriptionElement) {
                $this->description = $descriptionElement->innerHTML;
            }

            // Extract dates
            $this->extractDates($doc);

            // Extract host
            $hostElement = $doc->querySelector('.jam_host_header a, .host_header a');
            if ($hostElement) {
                $this->host = trim($hostElement->textContent);
            }

            // If we couldn't find submission count in the stats, try to find it elsewhere
            if (! $this->submission_count) {
                $this->extractSubmissionCount($doc);
            }

            // We'll skip automatic results fetching here since the command will handle it
            // This prevents duplicate fetching of results
            // if ($this->hasEnded()) {
            //     $this->fetchResultsPage($client);
            // }

            $this->save();

            Log::info('Successfully fetched game jam details', [
                'game_jam_id' => $this->id,
                'game_jam_name' => $this->name,
                'has_description' => ! empty($this->description),
                'has_dates' => $this->start_date !== null && $this->end_date !== null,
                'submission_count' => $this->submission_count,
            ]);

            return true;
        } catch (Exception $e) {
            // Log error but don't throw
            Log::error('Error fetching game jam details', [
                'jam_url' => $this->url,
                'game_jam_id' => $this->id,
                'game_jam_name' => $this->name,
                'error' => $e->getMessage(),
            ]);

            // Re-throw rate limit errors so they can be handled by the retry mechanism
            if (strpos($e->getMessage(), '429 Too Many Requests') !== false) {
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
     */
    public function fetchResultsPage(Client $client, int $maxRetries = 5, int $retryDelay = 30): bool
    {
        $currentPage = 1;
        $hasMorePages = true;
        $rankingsFound = 0;
        $pagesProcessed = 0;

        try {
            Log::info('Starting to fetch rankings for game jam', [
                'game_jam_id' => $this->id,
                'game_jam_name' => $this->name,
                'url' => $this->url,
            ]);

            while ($hasMorePages) {
                $pageDoc = $this->fetchResultsPageNumber($client, $currentPage, $maxRetries, $retryDelay);
                if (! $pageDoc) {
                    // If we couldn't fetch the first page, that's a critical error
                    if ($currentPage === 1) {
                        throw new Exception("Failed to fetch first page of rankings for game jam {$this->name}");
                    }
                    // Otherwise, we've probably reached the end of pagination
                    break;
                }

                $pagesProcessed++;

                // Process the results page to extract rankings
                $pageRankings = $this->extractRankings($pageDoc);
                $rankingsFound += $pageRankings;

                // Check if there's a next page by looking for the pagination element
                $nextPageLink = $pageDoc->querySelector('.next_page:not(.disabled)');
                if (! $nextPageLink) {
                    $hasMorePages = false;
                } else {
                    $currentPage++;
                    // Add a small delay between page requests to be nice to the server
                    sleep(1);
                }
            }

            Log::info('Completed fetching rankings for game jam', [
                'game_jam_id' => $this->id,
                'game_jam_name' => $this->name,
                'pages_processed' => $pagesProcessed,
                'rankings_found' => $rankingsFound,
            ]);

            // If we processed pages but found no rankings, that's suspicious
            if ($pagesProcessed > 0 && $rankingsFound === 0) {
                Log::warning('No rankings found despite processing pages', [
                    'game_jam_id' => $this->id,
                    'game_jam_name' => $this->name,
                    'pages_processed' => $pagesProcessed,
                ]);

                return false;
            }

            return $rankingsFound > 0;

        } catch (Exception $e) {
            Log::error('Error fetching rankings for game jam', [
                'game_jam_id' => $this->id,
                'game_jam_name' => $this->name,
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
     */
    private function fetchResultsPageNumber(Client $client, int $pageNumber, int $maxRetries, int $retryDelay): ?HTMLDocument
    {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Construct the results page URL with page number
                $resultsUrl = rtrim($this->url, '/') . '/results';
                if ($pageNumber > 1) {
                    $resultsUrl .= '?page=' . $pageNumber;
                }

                Log::info('Fetching game jam results page', [
                    'url' => $resultsUrl,
                    'page' => $pageNumber,
                    'attempt' => $attempt,
                    'max_attempts' => $maxRetries,
                    'game_jam_id' => $this->id,
                    'game_jam_name' => $this->name,
                ]);

                // Fetch the results page
                $response = $client->get($resultsUrl, [
                    'cookies' => false,
                    'http_errors' => false, // Don't throw exceptions for 4xx/5xx
                ]);

                $statusCode = $response->getStatusCode();

                // If we hit a rate limit, wait and retry
                if ($statusCode === 429) {
                    $retryAfter = $response->getHeaderLine('Retry-After');
                    $sleepTime = $retryAfter ? (int) $retryAfter : $retryDelay * $attempt;

                    // Increase cooldown with each retry, with a minimum of 30 seconds
                    $sleepTime = max($sleepTime, 30 * $attempt);

                    Log::warning('Rate limited when fetching game jam results, retrying', [
                        'url' => $resultsUrl,
                        'attempt' => $attempt,
                        'retry_after' => $sleepTime,
                        'game_jam_id' => $this->id,
                        'game_jam_name' => $this->name,
                    ]);

                    // Sleep before retrying
                    sleep($sleepTime);

                    continue;
                }

                // If the page doesn't exist or there's another error, log and return null
                if ($statusCode !== 200) {
                    Log::warning('Game jam results page not found', [
                        'url' => $resultsUrl,
                        'status_code' => $statusCode,
                        'game_jam_id' => $this->id,
                        'game_jam_name' => $this->name,
                    ]);

                    // If it's a server error (5xx), we should retry
                    if ($statusCode >= 500 && $statusCode < 600 && $attempt < $maxRetries) {
                        $sleepTime = $retryDelay * $attempt;
                        Log::warning('Server error, retrying after cooldown', [
                            'url' => $resultsUrl,
                            'status_code' => $statusCode,
                            'attempt' => $attempt,
                            'cooldown' => $sleepTime,
                            'game_jam_id' => $this->id,
                        ]);
                        sleep($sleepTime);

                        continue;
                    }

                    return null;
                }

                Log::info('Successfully fetched game jam results page', [
                    'url' => $resultsUrl,
                    'page' => $pageNumber,
                    'game_jam_id' => $this->id,
                    'game_jam_name' => $this->name,
                ]);

                $html = $response->getBody()->getContents();

                return HTMLDocument::createFromString($html, LIBXML_NOERROR);

            } catch (Exception $e) {
                // Check if it's a rate limiting error in the exception message
                if (strpos($e->getMessage(), '429 Too Many Requests') !== false) {
                    $sleepTime = max(30, $retryDelay * $attempt); // Minimum 30 seconds cooldown

                    Log::warning('Rate limit exception when fetching game jam results, retrying', [
                        'jam_url' => $this->url,
                        'page' => $pageNumber,
                        'attempt' => $attempt,
                        'cooldown' => $sleepTime,
                        'game_jam_id' => $this->id,
                        'game_jam_name' => $this->name,
                    ]);

                    // If this is not the last attempt, wait and retry
                    if ($attempt < $maxRetries) {
                        sleep($sleepTime);

                        continue;
                    }
                }

                // Log error
                Log::error('Error fetching game jam results', [
                    'jam_url' => $this->url,
                    'page' => $pageNumber,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'max_attempts' => $maxRetries,
                    'game_jam_id' => $this->id,
                    'game_jam_name' => $this->name,
                ]);

                // If this is the last attempt, return null
                if ($attempt === $maxRetries) {
                    return null;
                }

                // Wait before retrying
                $sleepTime = $retryDelay * $attempt;
                sleep($sleepTime);
            }
        }

        return null;
    }

    /**
     * Extract dates from game jam page
     */
    private function extractDates(HTMLDocument $doc): void
    {
        // First try to find the date range in a text block

        // Look for text like "This jam is now over. It ran from 2023-05-01 04:00:00 to 2023-06-01 03:59:00."
        // We need to check all divs since we can't use :contains() selector
        $divs = $doc->querySelectorAll('div');
        $jamOverText = null;
        foreach ($divs as $div) {
            if (str_contains($div->textContent, 'This jam is now over')) {
                $jamOverText = $div;
                break;
            }
        }

        if ($jamOverText) {
            $text = $jamOverText->textContent;
            if (preg_match('/ran from (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) to (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $text, $matches)) {
                try {
                    $this->start_date = new DateTime($matches[1]);
                    $this->end_date = new DateTime($matches[2]);

                    return; // Dates found, no need to continue
                } catch (Exception) {
                    // Continue with other methods if date parsing fails
                }
            }
        }

        // Look for text like "Submissions open from 2025-07-18 16:00:00 to 2025-07-20 16:00:00"
        // We need to check all divs since we can't use :contains() selector
        $submissionsText = null;
        foreach ($divs as $div) {
            if (str_contains($div->textContent, 'Submissions open from')) {
                $submissionsText = $div;
                break;
            }
        }

        if ($submissionsText) {
            $text = $submissionsText->textContent;
            if (preg_match('/Submissions open from (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) to (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $text, $matches)) {
                try {
                    $this->start_date = new DateTime($matches[1]);
                    $this->end_date = new DateTime($matches[2]);

                    return; // Dates found, no need to continue
                } catch (Exception) {
                    // Continue with other methods if date parsing fails
                }
            }
        }

        // Try the stat boxes
        $dateElements = $doc->querySelectorAll('.jam_stats_container .stat_box, .jam_stats .stat_box');
        if (count($dateElements) > 0) {
            foreach ($dateElements as $element) {
                $label = $element->querySelector('.label');
                $value = $element->querySelector('.value');

                if (! $label || ! $value) {
                    continue;
                }

                $labelText = trim($label->textContent);
                $valueText = trim($value->textContent);

                if (strpos($labelText, 'Start') !== false) {
                    try {
                        $this->start_date = new DateTime($valueText);
                    } catch (Exception) {
                        // Ignore date parsing errors
                    }
                } elseif (strpos($labelText, 'End') !== false) {
                    try {
                        $this->end_date = new DateTime($valueText);
                    } catch (Exception) {
                        // Ignore date parsing errors
                    }
                } elseif (strpos($labelText, 'Submissions') !== false) {
                    $this->submission_count = (int) $valueText;
                } elseif (strpos($labelText, 'Participants') !== false) {
                    $this->participant_count = (int) $valueText;
                }
            }
        }

        // Try alternative date format in info lines
        $infoLines = $doc->querySelectorAll('.info_line, .jam_info_line');
        foreach ($infoLines as $line) {
            $text = $line->textContent;

            if (strpos($text, 'Starts:') !== false) {
                $parts = explode('Starts:', $text, 2);
                if (count($parts) > 1) {
                    try {
                        $this->start_date = new DateTime(trim($parts[1]));
                    } catch (Exception) {
                        // Ignore date parsing errors
                    }
                }
            } elseif (strpos($text, 'Ends:') !== false) {
                $parts = explode('Ends:', $text, 2);
                if (count($parts) > 1) {
                    try {
                        $this->end_date = new DateTime(trim($parts[1]));
                    } catch (Exception) {
                        // Ignore date parsing errors
                    }
                }
            }
        }
    }

    /**
     * Extract submission count from game jam page
     */
    private function extractSubmissionCount(HTMLDocument $doc): void
    {
        // First look for a prominent entry count display
        $entriesDisplay = $doc->querySelector('a[href$="/entries"]');
        if ($entriesDisplay) {
            $text = trim($entriesDisplay->textContent);
            if (preg_match('/^\s*([0-9,]+)\s*Entries\s*$/i', $text, $matches)) {
                $this->submission_count = (int) str_replace(',', '', $matches[1]);

                return;
            }
        }

        // Look for text like "123 entries"
        $submissionText = $doc->querySelector('.jam_entries_header');
        if ($submissionText) {
            $text = $submissionText->textContent;
            if (preg_match('/([0-9,]+)\s+entries/i', $text, $matches)) {
                $this->submission_count = (int) str_replace(',', '', $matches[1]);

                return;
            }
        }

        // Look for "Submitted so far(X)" text
        // We need to check all h2 elements since we can't use :contains() selector
        $h2Elements = $doc->querySelectorAll('h2');
        foreach ($h2Elements as $h2) {
            if (str_contains($h2->textContent, 'Submitted so far')) {
                $text = $h2->textContent;
                if (preg_match('/Submitted so far\(([0-9]+)\)/i', $text, $matches)) {
                    $this->submission_count = (int) $matches[1];

                    return;
                }
            }
        }

        // If still not found, try counting the entries directly
        if (! $this->submission_count) {
            $entries = $doc->querySelectorAll('.game_cell');
            if (count($entries) > 0) {
                $this->submission_count = count($entries);
            }
        }
    }

    /**
     * Extract rankings from the results page
     *
     * @return int The number of rankings found and processed
     */
    private function extractRankings(HTMLDocument $doc): int
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
            $game = Game::where('url', $gameUrl)->first();
            if (! $game) {
                Log::info('Game not found in database', ['url' => $gameUrl]);

                continue;
            }

            // Extract the game title
            $gameTitleElement = $gameRankDiv->querySelector('.game_summary h2 a');
            $gameTitle = $gameTitleElement ? trim($gameTitleElement->textContent) : $game->name;

            Log::info('Processing game', ['title' => $gameTitle, 'url' => $gameUrl, 'game_id' => $game->id, 'game_jam_id' => $this->id]);

            // Extract the ranking - it's in an h3 tag with the format "Ranked <strong>Nth</strong> with X ratings..."
            $rankingElement = $gameRankDiv->querySelector('.game_summary h3 .ordinal_rank');
            if (! $rankingElement) {
                Log::info('No ranking found for game', ['game' => $gameTitle, 'url' => $gameUrl]);

                continue;
            }

            $ranking = trim($rankingElement->textContent);
            Log::info('Found ranking', ['game' => $gameTitle, 'ranking' => $ranking, 'game_id' => $game->id]);

            // Store the ranking data for later processing
            $rankingData[] = [
                'game' => $game,
                'ranking' => $ranking,
            ];
        }

        // Now process all the rankings in a single transaction
        if (! empty($rankingData)) {
            Log::info('Starting transaction to update rankings', [
                'game_jam_id' => $this->id,
                'game_jam_name' => $this->name,
                'rankings_count' => count($rankingData),
            ]);

            // Use a database transaction to ensure all updates are atomic
            DB::transaction(function () use ($rankingData, &$rankingsFound) {
                foreach ($rankingData as $data) {
                    $game = $data['game'];
                    $ranking = $data['ranking'];
                    $pivotData = ['ranking' => $ranking];

                    // Reload the game to ensure we have the latest data
                    $game->refresh();

                    // Check if the game is already associated with this jam
                    if ($game->gameJams()->where('game_jam_id', $this->id)->exists()) {
                        // Update the existing pivot record
                        $game->gameJams()->updateExistingPivot($this->id, $pivotData);
                        Log::info('Updated existing game jam ranking', [
                            'game_id' => $game->id,
                            'game' => $game->name,
                            'jam_id' => $this->id,
                            'jam' => $this->name,
                            'ranking' => $ranking,
                        ]);
                    } else {
                        // Create a new association
                        $game->gameJams()->attach($this->id, $pivotData);
                        Log::info('Created new game jam ranking', [
                            'game_id' => $game->id,
                            'game' => $game->name,
                            'jam_id' => $this->id,
                            'jam' => $this->name,
                            'ranking' => $ranking,
                        ]);
                    }

                    $rankingsFound++;
                }
            });

            Log::info('Transaction completed successfully', [
                'game_jam_id' => $this->id,
                'rankings_updated' => $rankingsFound,
            ]);
        }

        Log::info('Rankings extraction complete for current page', [
            'found' => $rankingsFound,
            'total_games' => count($gameRankDivs),
            'game_jam_id' => $this->id,
            'game_jam_name' => $this->name,
        ]);

        return $rankingsFound;
    }
}
