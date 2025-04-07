<?php

declare(strict_types=1);

namespace App\Models;

use DateTime;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        'theme',
        'logo_url',
        'optimized_logos',
        'needs_details_fetch',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'submission_count' => 'integer',
        'participant_count' => 'integer',
        'optimized_logos' => 'array',
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
     * Get the URL for a logo variant
     */
    public function getLogoUrl(string $variant = 'default'): ?string
    {
        if (! isset($this->optimized_logos[$variant], $this->optimized_logos[$variant]['path'])) {
            return $this->logo_url;
        }

        $path = $this->optimized_logos[$variant]['path'];

        return asset('storage/' . $path);
    }

    /**
     * Clear all optimized logos
     */
    public function clearOptimizedLogos(): void
    {
        if ($this->optimized_logos) {
            foreach ($this->optimized_logos as $variant) {
                if (isset($variant['path'])) {
                    Storage::disk('public')->delete($variant['path']);
                }
            }

            // Clear the logos data
            $this->optimized_logos = null;
            $this->save();
        }
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

        return $this->start_date->diffInDays($this->end_date) + 1; // +1 to include both start and end days
    }

    /**
     * Fetch details about a game jam from its page
     */
    public function fetchDetailsFromUrl(Client $client): bool
    {
        try {
            // Fetch the game jam page
            $response = $client->get($this->url, ['cookies' => false]);
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

            // Extract theme
            $this->extractTheme($doc);

            // Extract logo
            $logoElement = $doc->querySelector('.jam_cover_image img, .jam_header_image img');
            if ($logoElement) {
                $this->logo_url = $logoElement->getAttribute('src');
            }

            // If we couldn't find submission count in the stats, try to find it elsewhere
            if (! $this->submission_count) {
                $this->extractSubmissionCount($doc);
            }

            // Try to fetch results if the game jam has ended
            if ($this->hasEnded()) {
                $this->fetchResultsPage($client);
            }

            $this->save();

            return true;
        } catch (Exception $e) {
            // Log error but don't throw
            Log::error('Error fetching game jam details', [
                'jam_url' => $this->url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Fetch and parse the results page for this game jam
     */
    public function fetchResultsPage(Client $client): void
    {
        try {
            // Construct the results page URL
            $resultsUrl = rtrim($this->url, '/') . '/results';
            Log::info('Fetching game jam results page', ['url' => $resultsUrl]);

            // Fetch the results page
            $response = $client->get($resultsUrl, [
                'cookies' => false,
                'http_errors' => false, // Don't throw exceptions for 404s
            ]);

            // If the page doesn't exist or there's an error, log and return
            if ($response->getStatusCode() !== 200) {
                Log::warning('Game jam results page not found', [
                    'url' => $resultsUrl,
                    'status_code' => $response->getStatusCode(),
                ]);

                return;
            }

            Log::info('Successfully fetched game jam results page', ['url' => $resultsUrl]);

            $html = $response->getBody()->getContents();
            $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

            // Process the results page to extract rankings
            $this->extractRankings($doc);

        } catch (Exception $e) {
            // Log error but don't throw
            Log::error('Error fetching game jam results', [
                'jam_url' => $this->url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
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
     * Extract theme from game jam page
     */
    private function extractTheme(HTMLDocument $doc): void
    {
        // Extract theme - try different selectors
        // We need to check all info lines since we can't use :contains() selector
        $infoLines = $doc->querySelectorAll('.jam_info_line .info_value, .jam_info_widget .info_line .info_value');
        foreach ($infoLines as $line) {
            $parentText = $line->parentNode ? $line->parentNode->textContent : '';
            if (str_contains($parentText, 'Theme')) {
                $this->theme = trim($line->textContent);

                return;
            }
        }

        // Try to find theme in any element containing 'Theme:'
        $infoLines = $doc->querySelectorAll('.info_line, .jam_info_line');
        foreach ($infoLines as $line) {
            if (strpos($line->textContent, 'Theme:') !== false) {
                $parts = explode('Theme:', $line->textContent, 2);
                if (count($parts) > 1) {
                    $this->theme = trim($parts[1]);

                    return;
                }
            }
        }

        // Look for theme in headers
        $themeHeaders = $doc->querySelectorAll('h1, h2, h3');
        foreach ($themeHeaders as $header) {
            if (trim(strtolower($header->textContent)) === 'theme:') {
                $nextElement = $header->nextElementSibling;
                if ($nextElement) {
                    $this->theme = trim($nextElement->textContent);

                    return;
                }
            }
        }

        // Look for theme in a dedicated section
        // We need to check all headers since we can't use :contains() selector
        $headers = $doc->querySelectorAll('h1, h2, h3');
        foreach ($headers as $header) {
            if (str_contains($header->textContent, 'Theme')) {
                $nextElement = $header->nextElementSibling;
                if ($nextElement) {
                    $this->theme = trim($nextElement->textContent);

                    return;
                } else {
                    // The theme might be in the same element
                    $text = $header->textContent;
                    $parts = explode('Theme', $text, 2);
                    if (count($parts) > 1) {
                        // Remove any punctuation and get the rest of the text
                        $theme = preg_replace('/^[:\s-]+/', '', $parts[1]);
                        $this->theme = trim($theme);

                        return;
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
     */
    private function extractRankings(HTMLDocument $doc): void
    {
        // The results page has a specific structure with game entries
        // Each game entry has a link to the game and a heading with ranking info

        // First, let's get all the game entries
        // We'll look for links that point to itch.io game pages
        $gameLinks = [];
        $allLinks = $doc->querySelectorAll('a');
        foreach ($allLinks as $link) {
            $href = $link->getAttribute('href');
            if ($href && strpos($href, 'itch.io/') !== false && strpos($href, '/jam/') === false) {
                // This looks like a game link
                $gameLinks[$href] = $link;
            }
        }

        Log::info('Found game links', ['count' => count($gameLinks)]);

        // Now, let's extract the rankings directly from the page content
        $html = $doc->saveHTML();

        // The rankings are in a specific format in the HTML
        // Each game section has a structure like:
        // <a href="https://akibaokapi.itch.io/after-passion">After Passion</a>
        // ...
        // <h3>Ranked <strong>4th</strong> with 41 ratings (Score: 4.390)</h3>

        $rankingsFound = 0;

        foreach ($gameLinks as $gameUrl => $link) {
            // Find the game in our database
            $game = Game::where('url', $gameUrl)->first();
            if (! $game) {
                Log::info('Game not found in database', ['url' => $gameUrl]);

                continue;
            }

            // Get the game title from the link text
            $gameTitle = trim($link->textContent);
            if (empty($gameTitle)) {
                // Try to get it from the database
                $gameTitle = $game->name;
            }

            Log::info('Processing game', ['title' => $gameTitle, 'url' => $gameUrl]);

            // Extract the ranking using a regex pattern
            // We'll look for the game URL followed by any content and then the ranking
            $pattern = '/' . preg_quote($gameUrl, '/') . '.*?Ranked\s+(?:\*\*|<strong>)(\d+[a-z]{2})(?:\*\*|<\/strong>)/s';
            if (preg_match($pattern, $html, $matches)) {
                $ranking = $matches[1];
                Log::info('Found ranking', ['game' => $gameTitle, 'ranking' => $ranking]);

                // Update the pivot table with ranking information
                $pivotData = ['ranking' => $ranking];

                // Check if the game is already associated with this jam
                if ($game->gameJams()->where('game_jam_id', $this->id)->exists()) {
                    // Update the existing pivot record
                    $game->gameJams()->updateExistingPivot($this->id, $pivotData);
                } else {
                    // Create a new association
                    $game->gameJams()->attach($this->id, $pivotData);
                }

                Log::info('Updated game jam ranking', [
                    'game' => $game->name,
                    'jam' => $this->name,
                    'ranking' => $ranking,
                ]);

                $rankingsFound++;
            } else {
                // Try an alternative pattern
                $pattern = '/Ranked\s+(?:\*\*|<strong>)(\d+[a-z]{2})(?:\*\*|<\/strong>).*?' . preg_quote($gameUrl, '/') . '/s';
                if (preg_match($pattern, $html, $matches)) {
                    $ranking = $matches[1];
                    Log::info('Found ranking (alternative pattern)', ['game' => $gameTitle, 'ranking' => $ranking]);

                    // Update the pivot table with ranking information
                    $pivotData = ['ranking' => $ranking];

                    // Check if the game is already associated with this jam
                    if ($game->gameJams()->where('game_jam_id', $this->id)->exists()) {
                        // Update the existing pivot record
                        $game->gameJams()->updateExistingPivot($this->id, $pivotData);
                    } else {
                        // Create a new association
                        $game->gameJams()->attach($this->id, $pivotData);
                    }

                    Log::info('Updated game jam ranking', [
                        'game' => $game->name,
                        'jam' => $this->name,
                        'ranking' => $ranking,
                    ]);

                    $rankingsFound++;
                } else {
                    Log::info('No ranking found for game', ['game' => $gameTitle, 'url' => $gameUrl]);
                }
            }
        }

        Log::info('Rankings extraction complete', ['found' => $rankingsFound, 'total_games' => count($gameLinks)]);
    }
}
