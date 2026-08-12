<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\SimilarGamesService;
use Meilisearch\Client;

it('does not request similar documents for a game excluded from the search index', function () {
    $game = new Game([
        'name' => 'Unlisted Game',
        'is_visible' => false,
    ]);
    $game->forceFill(['id' => 280306]);

    $client = Mockery::mock(Client::class);
    $client->shouldNotReceive('index');

    $results = (new SimilarGamesService($client))->findSimilarGames($game);

    expect($results)->toBeEmpty();
});
