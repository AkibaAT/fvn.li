<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClickStat;
use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserGameManagementController extends Controller
{
    /**
     * Display the user's owned games for management
     */
    public function index()
    {
        $user = Auth::user();

        // Check if user has itch.io account connected
        $itchioUsername = $user->getItchioUsername();

        if (! $itchioUsername) {
            return view('users.game-management.index', [
                'games' => collect(),
                'itchioUsername' => null,
                'metaTags' => [
                    'title' => 'Manage My Games',
                    'noindex' => true,
                ],
            ]);
        }

        // Get owned games
        $games = $user->getOwnedGames();

        // Get click statistics for the last 30 days
        $gameIds = $games->pluck('id')->toArray();
        $clickStats = [];

        if (! empty($gameIds)) {
            $since = Carbon::now()->subDays(30);
            $clickStats = ClickStat::getMultipleGameStats($gameIds, $since);
        }

        return view('users.game-management.index', [
            'games' => $games,
            'itchioUsername' => $itchioUsername,
            'clickStats' => $clickStats,
            'metaTags' => [
                'title' => 'Manage My Games',
                'noindex' => true,
            ],
        ]);
    }

    /**
     * Show the edit form for a specific game
     */
    public function edit(Game $game)
    {
        $user = Auth::user();

        // Verify ownership
        if (! $user->ownsGame($game)) {
            abort(403, 'You do not have permission to edit this game.');
        }

        // Get detailed click statistics for the last 30 days
        $since = Carbon::now()->subDays(30);
        $clickStats = ClickStat::getGameStats($game->id, $since);

        // Get daily statistics for charts
        $dailyStats = ClickStat::getDailyStats($game->id, 30);

        // Get link-specific statistics
        $linkStats = ClickStat::getLinkStats($game->id, 30);

        return view('users.game-management.edit', [
            'game' => $game,
            'platforms' => Game::getAvailablePlatforms(),
            'clickStats' => $clickStats,
            'dailyStats' => $dailyStats,
            'linkStats' => $linkStats,
            'metaTags' => [
                'title' => "Edit {$game->name}",
                'noindex' => true,
            ],
        ]);
    }

    /**
     * Update the additional links for a game
     */
    public function update(Request $request, Game $game)
    {
        $user = Auth::user();

        // Verify ownership
        if (! $user->ownsGame($game)) {
            abort(403, 'You do not have permission to edit this game.');
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'links' => 'nullable|array|max:15',
            'links.*.name' => 'required|string|max:100',
            'links.*.url' => [
                'required',
                'url',
                'max:255',
                function (string $attribute, $value, $fail) {
                    if ($value) {
                        // Additional security checks for the URL
                        $parsedUrl = parse_url($value);

                        // Block localhost and private IP ranges
                        if (isset($parsedUrl['host'])) {
                            $host = $parsedUrl['host'];

                            // Block localhost variations
                            if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'])) {
                                $fail('The URL cannot point to localhost.');

                                return;
                            }

                            // Block private IP ranges
                            if (filter_var($host, FILTER_VALIDATE_IP)) {
                                if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                                    $fail('The URL cannot point to private or reserved IP addresses.');

                                    return;
                                }
                            }
                        }
                    }
                },
            ],
            'links.*.platform' => 'nullable|string|in:' . implode(',', array_keys(Game::getAvailablePlatforms())),
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('user.games.edit', $game)
                ->withErrors($validator)
                ->withInput();
        }

        // Process and sanitize the links
        $links = $request->input('links', []);
        $processedLinks = [];
        $existingLinks = $game->additional_links ?? [];
        $existingLinksById = collect($existingLinks)->keyBy('id');

        foreach ($links as $index => $link) {
            if (empty($link['name']) || empty($link['url'])) {
                continue; // Skip empty links
            }

            $linkId = $link['id'] ?? uniqid();
            $existingLink = $existingLinksById->get($linkId);

            // Check if the link has been modified
            $hasChanged = ! $existingLink ||
                         $existingLink['name'] !== trim($link['name']) ||
                         $existingLink['url'] !== filter_var(trim($link['url']), FILTER_SANITIZE_URL) ||
                         ($existingLink['platform'] ?? null) !== ($link['platform'] ?? null);

            $processedLinks[] = [
                'id' => $linkId,
                'name' => trim($link['name']),
                'url' => filter_var(trim($link['url']), FILTER_SANITIZE_URL),
                'platform' => $link['platform'] ?? null,
                'sort_order' => $index,
                'last_edited_at' => $hasChanged ? now()->toISOString() : ($existingLink['last_edited_at'] ?? now()->toISOString()),
            ];
        }

        // Update the game
        $game->update([
            'additional_links' => $processedLinks,
        ]);

        $linkCount = count($processedLinks);
        $message = $linkCount > 0
            ? "Successfully updated {$linkCount} download " . ($linkCount === 1 ? 'link' : 'links') . " for {$game->name}"
            : "Successfully removed all download links for {$game->name}";

        return redirect()
            ->route('user.games.index')
            ->with('success', $message);
    }
}
