<?php

declare(strict_types=1);

use App\Console\Commands\IndexDialogueTexts;
use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Rater;
use App\Models\UniqueDialogueText;
use App\Models\User;
use App\Services\ImageProcessingService;
use App\Services\PlatformDetectionService;
use App\Services\RatingCalculationService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;
use Meilisearch\Client as MeilisearchClient;
use Meilisearch\Endpoints\Indexes;

function invokeConsoleUtilityCommandMethod(object $command, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($command);
    $methodReflection = $reflection->getMethod($method);
    $methodReflection->setAccessible(true);

    return $methodReflection->invokeArgs($command, $arguments);
}

function consoleUtilityPngPayload(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAGElEQVR4nGP8z8Dwn4GBgYGJAQo4YwAAExIBAwAVY7ZKAAAAAElFTkSuQmCC',
        true
    );
}

it('marks an existing user as admin and reports missing users', function () {
    $user = User::factory()->create([
        'name' => 'Admin Candidate',
        'email' => 'candidate@example.test',
        'is_admin' => false,
    ]);

    $this->artisan('app:make-user-admin', ['email' => $user->email])
        ->expectsOutput("User Admin Candidate ({$user->email}) is now an admin.")
        ->assertExitCode(0);

    expect($user->refresh()->is_admin)->toBeTrue();

    $this->artisan('app:make-user-admin', ['email' => 'missing@example.test'])
        ->expectsOutput('User with email missing@example.test not found.')
        ->assertExitCode(1);
});

it('marks and unmarks suspicious raters and handles missing raters', function () {
    $rater = Rater::factory()->create([
        'is_suspicious' => false,
        'suspicion_reason' => null,
        'marked_suspicious_at' => null,
    ]);

    $this->artisan('rater:mark-suspicious', [
        'rater_id' => $rater->id,
        '--reason' => 'spam pattern',
    ])
        ->expectsOutput("Rater {$rater->id} marked as suspicious")
        ->assertExitCode(0);

    expect($rater->refresh()->is_suspicious)->toBeTrue()
        ->and($rater->suspicion_reason)->toBe('spam pattern')
        ->and($rater->marked_suspicious_at)->not->toBeNull();

    $this->artisan('rater:mark-suspicious', [
        'rater_id' => $rater->id,
        '--unmark' => true,
    ])
        ->expectsOutput("Rater {$rater->id} unmarked as suspicious")
        ->assertExitCode(0);

    expect($rater->refresh()->is_suspicious)->toBeFalse()
        ->and($rater->suspicion_reason)->toBeNull()
        ->and($rater->marked_suspicious_at)->toBeNull();

    $this->artisan('rater:mark-suspicious', ['rater_id' => 999999])
        ->expectsOutput('Rater 999999 not found')
        ->assertExitCode(0);
});

it('delegates full rating recalculation to the rating service', function () {
    $this->mock(RatingCalculationService::class, function ($mock) {
        $mock->shouldReceive('recalculateAllGameRatings')
            ->once()
            ->andReturn(7);
    });

    $this->artisan('ratings:recalculate')
        ->expectsOutput('Starting game rating recalculation...')
        ->expectsOutput('Successfully recalculated ratings for 7 games.')
        ->assertExitCode(0);
});

it('generates a sitemap from visible slugged games and paginated listing pages', function () {
    $sitemapPath = public_path('sitemap.xml');
    @unlink($sitemapPath);

    Game::factory()->count(10)->create([
        'is_visible' => true,
        'slug' => fn (array $attributes) => str($attributes['name'])->slug()->append('-visible')->toString(),
    ]);
    Game::factory()->create([
        'is_visible' => false,
        'slug' => 'hidden-game',
    ]);

    try {
        $this->artisan('sitemap:generate')
            ->expectsOutput('Sitemap generated successfully!')
            ->assertExitCode(0);

        $sitemap = file_get_contents($sitemapPath);

        expect($sitemap)->toContain(route('games.index'))
            ->and($sitemap)->toContain(route('games.index', ['page' => 2]))
            ->and($sitemap)->not->toContain('hidden-game');
    } finally {
        @unlink($sitemapPath);
    }
});

it('cleans downloads for selected games and validates missing selection options', function () {
    $game = Game::factory()->create(['name' => 'Cleanup Target']);
    $oldVersion = GameVersion::factory()->for($game)->create(['is_latest' => false, 'published_at' => now()->subDays(2)]);
    $latestVersion = GameVersion::factory()->for($game)->create(['is_latest' => true, 'published_at' => now()]);

    Storage::fake('local');
    Storage::put("games/{$game->id}/{$oldVersion->id}/old.zip", 'old');
    Storage::put("games/{$game->id}/{$latestVersion->id}/latest.zip", 'latest');

    $this->artisan('games:cleanup-downloads', ['--game-id' => $game->id])
        ->expectsOutput('Found 1 game(s):')
        ->expectsOutput("- {$game->name} (ID: {$game->id}, Status: {$game->status})")
        ->expectsOutput("Cleaning up old downloads for game: {$game->name} (ID: {$game->id})")
        ->expectsOutput('Cleanup completed successfully for 1 game(s)')
        ->assertExitCode(0);

    expect(Storage::exists("games/{$game->id}/{$oldVersion->id}/old.zip"))->toBeFalse()
        ->and(Storage::exists("games/{$game->id}/{$latestVersion->id}/latest.zip"))->toBeTrue();

    $this->artisan('games:cleanup-downloads')
        ->expectsOutput('You must provide either --game-id, --game-name, or --all option')
        ->assertExitCode(1);
});

it('cleans downloads for all games through the archive service', function () {
    Storage::fake('local');

    $firstGame = Game::factory()->create();
    $firstOldVersion = GameVersion::factory()->for($firstGame)->create(['is_latest' => false, 'published_at' => now()->subDays(2)]);
    $firstLatestVersion = GameVersion::factory()->for($firstGame)->create(['is_latest' => true, 'published_at' => now()]);

    $secondGame = Game::factory()->create();
    $secondOldVersion = GameVersion::factory()->for($secondGame)->create(['is_latest' => false, 'published_at' => now()->subDays(2)]);
    $secondLatestVersion = GameVersion::factory()->for($secondGame)->create(['is_latest' => true, 'published_at' => now()]);

    Storage::put("games/{$firstGame->id}/{$firstOldVersion->id}/old.zip", 'old');
    Storage::put("games/{$firstGame->id}/{$firstLatestVersion->id}/latest.zip", 'latest');
    Storage::put("games/{$secondGame->id}/{$secondOldVersion->id}/old.zip", 'old');
    Storage::put("games/{$secondGame->id}/{$secondLatestVersion->id}/latest.zip", 'latest');

    $this->artisan('games:cleanup-downloads', ['--all' => true])
        ->expectsOutput('Cleaning up old downloads for all games...')
        ->expectsOutput('Cleanup completed successfully for 2 games')
        ->assertExitCode(0);

    expect(Storage::exists("games/{$firstGame->id}/{$firstOldVersion->id}/old.zip"))->toBeFalse()
        ->and(Storage::exists("games/{$firstGame->id}/{$firstLatestVersion->id}/latest.zip"))->toBeTrue()
        ->and(Storage::exists("games/{$secondGame->id}/{$secondOldVersion->id}/old.zip"))->toBeFalse()
        ->and(Storage::exists("games/{$secondGame->id}/{$secondLatestVersion->id}/latest.zip"))->toBeTrue();
});

it('indexes no dialogue texts without sending Meilisearch documents', function () {
    $index = Mockery::mock(Indexes::class);
    $index->shouldNotReceive('addDocuments');

    $client = Mockery::mock(MeilisearchClient::class);
    $client->shouldReceive('index')
        ->once()
        ->with('dialogue_texts')
        ->andReturn($index);

    $this->app->instance(MeilisearchClient::class, $client);

    $this->artisan('dialogue:index-texts', ['--batch-size' => 25])
        ->expectsOutputToContain('Total unique texts to index: 0')
        ->expectsOutputToContain('Batch size: 25')
        ->expectsOutputToContain('Successfully indexed 0 unique dialogue texts!')
        ->assertExitCode(0);
});

it('indexes dialogue texts with aggregated game version character and language metadata', function () {
    $game = Game::factory()->create(['name' => 'Indexed Dialogue Game']);
    $version = GameVersion::factory()->for($game)->create();
    $character = Character::factory()->for($game)->create([
        'display_names' => ['eng' => 'Alice'],
    ]);
    $text = UniqueDialogueText::factory()->create([
        'text_content' => 'The searchable line.',
        'text_hash' => md5('The searchable line.'),
    ]);

    DialogueLine::factory()->for($version, 'gameVersion')->for($character)->create([
        'text_id' => $text->id,
        'iso_code' => 'eng',
    ]);
    DialogueLine::factory()->for($version, 'gameVersion')->for($character)->create([
        'text_id' => $text->id,
        'iso_code' => 'deu',
    ]);
    UniqueDialogueText::factory()->create([
        'text_content' => '   ',
        'text_hash' => md5('   '),
    ]);

    $index = Mockery::mock(Indexes::class);
    $index
        ->shouldReceive('addDocuments')
        ->once()
        ->with(Mockery::on(function (array $documents) use ($game, $version, $character, $text) {
            expect($documents)->toHaveCount(1);
            $document = $documents[0];

            return $document['id'] === $text->id
                && $document['text_content'] === 'The searchable line.'
                && $document['game_ids'] === [$game->id]
                && $document['game_names'] === ['Indexed Dialogue Game']
                && $document['version_ids'] === [$version->id]
                && $document['character_ids'] === [$character->id]
                && $document['character_names'] === ['Alice']
                && $document['languages'] === ['deu', 'eng']
                && $document['usage_count'] === 2
                && $document['games_count'] === 1;
        }));

    $client = Mockery::mock(MeilisearchClient::class);
    $client->shouldReceive('index')
        ->once()
        ->with('dialogue_texts')
        ->andReturn($index);

    $this->app->instance(MeilisearchClient::class, $client);

    $this->artisan('dialogue:index-texts', ['--batch-size' => 10])
        ->expectsOutputToContain('Total unique texts to index: 1')
        ->expectsOutputToContain('Successfully indexed 1 unique dialogue texts!')
        ->assertExitCode(0);
});

it('parses dialogue indexing PostgreSQL arrays and download metadata helpers', function () {
    $command = new IndexDialogueTexts;

    expect(invokeConsoleUtilityCommandMethod($command, 'parseIntArray', ['{1,2,2,3}']))
        ->toBe([1, 2, 3])
        ->and(invokeConsoleUtilityCommandMethod($command, 'parseIntArray', ['{}']))
        ->toBe([])
        ->and(invokeConsoleUtilityCommandMethod($command, 'parseStringArray', ['{"Alice, A.","Bob","Alice, A."}']))
        ->toBe(['Alice, A.', 'Bob'])
        ->and(invokeConsoleUtilityCommandMethod($command, 'parseStringArray', [null]))
        ->toBe([]);
});

it('processes selected game thumbnails through the command wrapper', function () {
    Storage::fake('public');

    $game = Game::factory()->create([
        'name' => 'Thumbnail Target',
        'is_visible' => true,
        'thumb_url' => 'https://img.itch.zone/thumb.png',
        'optimized_thumbnails' => null,
    ]);

    $client = Mockery::mock(GuzzleClient::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://img.itch.zone/thumb.png', [
            'timeout' => 30,
            'connect_timeout' => 10,
            'allow_redirects' => false,
        ])
        ->andReturn(new Response(200, [], consoleUtilityPngPayload()));
    $this->app->instance(GuzzleClient::class, $client);

    $imageService = Mockery::mock(ImageProcessingService::class);
    $imageService->shouldReceive('processImageVariant')
        ->twice()
        ->andReturnUsing(function (string $sourcePath, string $destPath, array $config) {
            Storage::disk('public')->put($destPath, 'webp');

            return [
                'width' => $config['width'],
                'height' => $config['height'],
            ];
        });
    $this->app->instance(ImageProcessingService::class, $imageService);

    $this->artisan('games:process-thumbnails', [
        '--game-id' => $game->id,
        '--quality' => 75,
        '--force' => true,
    ])
        ->expectsOutput('Found 1 game(s):')
        ->expectsOutputToContain('Processing 1 games with thumbnails')
        ->assertExitCode(0);

    $game->refresh();

    expect($game->optimized_thumbnails)->toHaveKeys(['small', 'default'])
        ->and(Storage::disk('public')->exists($game->optimized_thumbnails['small']['path']))->toBeTrue()
        ->and(Storage::disk('public')->exists($game->optimized_thumbnails['default']['path']))->toBeTrue();
});

it('thumbnail command reports empty selections', function () {
    $hidden = Game::factory()->create([
        'is_visible' => false,
        'thumb_url' => 'https://cdn.example/hidden.png',
    ]);

    $this->artisan('games:process-thumbnails', ['--game-id' => $hidden->id])
        ->expectsOutput('No games found matching the selection criteria')
        ->assertExitCode(1);
});

it('imports Discord JSON games by creating updating skipping and reporting invalid directories', function () {
    $missingDir = sys_get_temp_dir().'/missing-discord-vns-'.uniqid();

    $this->artisan('games:import-discord', ['--path' => $missingDir])
        ->expectsOutput("Directory not found: {$missingDir}")
        ->assertExitCode(1);

    $importDir = sys_get_temp_dir().'/discord-vns-'.uniqid();
    mkdir($importDir);

    $existing = Game::factory()->create([
        'name' => 'Existing Discord Game',
        'url' => ['itch_io' => 'https://creator.itch.io/existing'],
        'description' => null,
        'authors' => null,
        'status' => 'In development',
    ]);

    file_put_contents($importDir.'/existing.json', json_encode([
        'Name' => 'Existing Discord Game',
        'Page_url' => 'https://creator.itch.io/existing',
        'Description' => 'Imported description',
        'Author_Name' => 'Imported Author',
        'Project_Status' => 'Released',
        'Likes' => ['alice'],
        'Dislikes' => ['bob'],
    ]));
    file_put_contents($importDir.'/new-steam.json', json_encode([
        'Name' => 'New Steam Game',
        'Page_url' => 'https://store.steampowered.com/app/123456/New_Steam_Game/',
        'Description' => 'Steam import',
        'Author_Name' => 'Steam Dev',
        'Project_Status' => 'Released',
        'Thumbnail_url' => 'https://cdn.example/steam.jpg',
    ]));
    file_put_contents($importDir.'/invalid.json', json_encode(['Name' => 'Broken']));
    file_put_contents($importDir.'/notes.txt', 'ignored');

    try {
        $this->artisan('games:import-discord', ['--path' => $importDir])
            ->expectsOutput('Starting Discord games import...')
            ->expectsOutput("Source: {$importDir}")
            ->expectsOutput('Skipping invalid.json: Invalid structure')
            ->expectsOutput('Import complete!')
            ->expectsOutput('Created: 1 | Updated: 1 | Skipped: 1 | Errors: 0')
            ->assertExitCode(0);

        $existing->refresh();
        $created = Game::where('name', 'New Steam Game')->firstOrFail();

        expect($existing->description)->toBe('Imported description')
            ->and($existing->authors)->toBe('Imported Author')
            ->and($existing->status)->toBe('Released')
            ->and($created->platform)->toBe('steam')
            ->and($created->steam_app_id)->toBe(123456)
            ->and($created->content_type)->toBe('visual_novel')
            ->and($created->is_visible)->toBeFalse();
    } finally {
        foreach (glob($importDir.'/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($importDir);
    }
});

it('previews Discord JSON imports in dry run mode without writing games', function () {
    $importDir = sys_get_temp_dir().'/discord-vns-dry-'.uniqid();
    mkdir($importDir);
    file_put_contents($importDir.'/dry.json', json_encode([
        'Name' => 'Dry Run Game',
        'Page_url' => 'https://example.com/dry-run-game',
        'Description' => 'Dry run description',
    ]));

    $this->mock(PlatformDetectionService::class, function ($mock) {
        $mock->shouldReceive('detectPlatform')
            ->once()
            ->with('https://example.com/dry-run-game')
            ->andReturn('other');
    });

    try {
        $this->artisan('games:import-discord', ['--path' => $importDir, '--dry-run' => true])
            ->expectsOutput('DRY RUN MODE - No changes will be saved')
            ->expectsOutput('Created: 1 | Updated: 0 | Skipped: 0 | Errors: 0')
            ->expectsOutput('DRY RUN - No changes were saved')
            ->assertExitCode(0);

        expect(Game::where('name', 'Dry Run Game')->exists())->toBeFalse();
    } finally {
        @unlink($importDir.'/dry.json');
        @rmdir($importDir);
    }
});
