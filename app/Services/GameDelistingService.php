<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Support\DelistedReason;
use Exception;
use Illuminate\Support\Facades\Log;

class GameDelistingService
{
    public function __construct(private readonly ItchHttpClientService $itchClient) {}

    /**
     * @return bool Whether the response leaves no uploads to sync
     */
    public function recordUploadsResponse(Game $game, int $status, mixed $uploadsData): bool
    {
        if ($reason = $this->reasonFor($game, $status, $uploadsData)) {
            $this->delist($game, $reason);

            return true;
        }

        return $status === 400 || $status === 404;
    }

    public function delist(Game $game, DelistedReason $reason): void
    {
        $game->delisted_at ??= now();
        $game->delisted_reason = $reason;
        $game->save();
    }

    public function classifyStorePage(int $status, string $body): ?DelistedReason
    {
        // FlareSolverr reports itch.io's not-found page as status 200.
        return match (true) {
            $status === 404, $status === 200 && (str_contains($body, 'not_found_game') || str_contains($body, 'game_not_published_error')) => DelistedReason::PageRemoved,
            $status === 200 && str_contains($body, 'game_password_page') => DelistedReason::PasswordProtected,
            $status === 200 && str_contains($body, 'itch.io/takedowns/') => DelistedReason::Blocked,
            default => null,
        };
    }

    public function storePageReason(Game $game): ?DelistedReason
    {
        $url = $game->getUrlForPlatform('itch_io');
        if (! $url) {
            return null;
        }

        $response = $this->itchClient->get($url, [], true);

        return $this->classifyStorePage($response->getStatusCode(), (string) $response->getBody());
    }

    private function reasonFor(Game $game, int $status, mixed $uploadsData): ?DelistedReason
    {
        if ($status === 400 || $status === 404) {
            try {
                return $this->storePageReason($game);
            } catch (Exception $e) {
                Log::warning('Store page check failed', ['game_id' => $game->id, 'error' => $e->getMessage()]);

                return null;
            }
        }

        return $status === 200 && is_array($uploadsData) && ($uploadsData['uploads'] ?? null) === [] && $this->hasHadDownloads($game)
            ? DelistedReason::NoDownloads
            : null;
    }

    private function hasHadDownloads(Game $game): bool
    {
        return $game->gameVersions()->where('version', '!=', 'Unknown')->exists();
    }
}
