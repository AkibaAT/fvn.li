<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SocialAccount;
use GuzzleHttp\Client;
use RuntimeException;

class ItchioGameOwnershipSyncService
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return list<int>
     */
    public function sync(SocialAccount $account): array
    {
        if ($account->provider_name !== 'itchio' || ! $account->token) {
            throw new RuntimeException('A connected itch.io account with a valid access token is required.');
        }

        $gameIds = $this->fetchGameIds($account->token);

        $account->update(['itchio_game_ids' => $gameIds]);

        return $gameIds;
    }

    /**
     * @return list<int>
     */
    public function fetchGameIds(string $accessToken): array
    {
        $response = $this->client->get("https://itch.io/api/1/{$accessToken}/my-games", [
            'timeout' => 10,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);

        if (! is_array($data)) {
            throw new RuntimeException('itch.io returned an invalid response.');
        }

        if (isset($data['errors'])) {
            throw new RuntimeException('itch.io rejected the game sync request.');
        }

        if (! array_key_exists('games', $data) || ! is_array($data['games'])) {
            throw new RuntimeException('itch.io returned an invalid games list.');
        }

        $games = $data['games'];

        return collect($games)
            ->pluck('id')
            ->filter(fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
