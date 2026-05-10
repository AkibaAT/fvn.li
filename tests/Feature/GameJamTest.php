<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameJam;
use App\Services\ItchHttpClientService;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('finds or creates game jams from canonical and rating URLs', function () {
    $created = GameJam::findOrCreateFromUrl(
        'https://itch.io/jam/spring-jam/rate/12345',
        'Spring Jam'
    );

    $existing = GameJam::findOrCreateFromUrl('https://itch.io/jam/spring-jam');

    expect($created->is($existing))->toBeTrue()
        ->and($created->name)->toBe('Spring Jam')
        ->and($created->url)->toBe('https://itch.io/jam/spring-jam')
        ->and($created->needs_details_fetch)->toBeTrue();
});

it('rejects non itch game jam URLs before storage or fetching', function () {
    expect(fn () => GameJam::findOrCreateFromUrl('http://127.0.0.1:8765/jam/internal-admin', 'Internal'))
        ->toThrow(InvalidArgumentException::class, 'Game jam URL must use HTTPS.');

    expect(fn () => GameJam::findOrCreateFromUrl('https://evil.example/jam/redirect', 'External'))
        ->toThrow(InvalidArgumentException::class, 'Game jam URL host must be itch.io.');

    expect(GameJam::count())->toBe(0);

    $jam = GameJam::create([
        'name' => 'Stored Unsafe Jam',
        'url' => 'http://127.0.0.1:8765/jam/internal-admin',
    ]);

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldNotReceive('get');
    $this->app->instance(ItchHttpClientService::class, $client);

    expect($jam->fetchDetailsFromUrl())->toBeFalse();
});

it('reports game jam timing and duration from dates', function () {
    $this->travelTo(now()->setDate(2026, 5, 3)->setTime(12, 0));

    $active = GameJam::create([
        'name' => 'Active Jam',
        'url' => 'https://itch.io/jam/active',
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay(),
    ]);
    $upcoming = GameJam::create([
        'name' => 'Upcoming Jam',
        'url' => 'https://itch.io/jam/upcoming',
        'start_date' => now()->addDay(),
        'end_date' => now()->addDays(3),
    ]);
    $ended = GameJam::create([
        'name' => 'Ended Jam',
        'url' => 'https://itch.io/jam/ended',
        'start_date' => now()->subDays(4),
        'end_date' => now()->subDay(),
    ]);
    $undated = GameJam::create([
        'name' => 'Undated Jam',
        'url' => 'https://itch.io/jam/undated',
    ]);

    expect($active->isActive())->toBeTrue()
        ->and($active->isUpcoming())->toBeFalse()
        ->and($active->hasEnded())->toBeFalse()
        ->and($active->getDurationInDays())->toBe(3)
        ->and($upcoming->isUpcoming())->toBeTrue()
        ->and($ended->hasEnded())->toBeTrue()
        ->and($undated->isActive())->toBeFalse()
        ->and($undated->isUpcoming())->toBeFalse()
        ->and($undated->hasEnded())->toBeFalse()
        ->and($undated->getDurationInDays())->toBeNull();
});

it('fetches and parses game jam details from itch html', function () {
    $jam = GameJam::create([
        'name' => 'Detail Jam',
        'url' => 'https://itch.io/jam/detail-jam',
    ]);

    $html = <<<'HTML'
<!doctype html>
<html>
<body>
    <div class="formatted_description">
        <p>Make something small.</p>
        <script>alert(1)</script>
        <img src="https://img.example/jam.png" onerror="alert(1)">
    </div>
    <span class="date_format">2026-05-01 10:00:00</span>
    <span class="date_format">2026-05-03 18:00:00</span>
    <div class="jam_host_header"><a>Jam Host</a></div>
    <a href="/jam/detail-jam/entries">1,234 Entries</a>
</body>
</html>
HTML;

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/jam/detail-jam', ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(200, [], $html));
    $this->app->instance(ItchHttpClientService::class, $client);

    expect($jam->fetchDetailsFromUrl())->toBeTrue();

    $jam->refresh();

    expect($jam->description)->toContain('Make something small.')
        ->and($jam->description)->toContain('https://img.example/jam.png')
        ->and(strtolower($jam->description))->not->toContain('<script')
        ->and(strtolower($jam->description))->not->toContain('onerror')
        ->and($jam->start_date?->format('Y-m-d H:i:s'))->toBe('2026-05-01 10:00:00')
        ->and($jam->end_date?->format('Y-m-d H:i:s'))->toBe('2026-05-03 18:00:00')
        ->and($jam->host)->toBe('Jam Host')
        ->and($jam->submission_count)->toBe(1234);
});

it('parses ended jam date copy before falling back to date spans', function () {
    $jam = GameJam::create([
        'name' => 'Ended Detail Jam',
        'url' => 'https://itch.io/jam/ended-detail-jam',
    ]);

    $html = <<<'HTML'
<!doctype html>
<html>
<body>
    <div>This jam is now over. It ran from 2025-01-01 09:00:00 to 2025-01-07 21:30:00.</div>
    <span class="date_format">2026-01-01 00:00:00</span>
    <span class="date_format">2026-01-02 00:00:00</span>
    <div class="jam_entries_header">Entries for this jam: 2,345 entries submitted</div>
</body>
</html>
HTML;

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with($jam->url, ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(200, [], $html));
    $this->app->instance(ItchHttpClientService::class, $client);

    expect($jam->fetchDetailsFromUrl())->toBeTrue();

    $jam->refresh();

    expect($jam->start_date?->format('Y-m-d H:i:s'))->toBe('2025-01-01 09:00:00')
        ->and($jam->end_date?->format('Y-m-d H:i:s'))->toBe('2025-01-07 21:30:00')
        ->and($jam->submission_count)->toBe(2345);
});

it('parses open submission date copy and submitted so far counts', function () {
    $jam = GameJam::create([
        'name' => 'Open Submission Jam',
        'url' => 'https://itch.io/jam/open-submission-jam',
    ]);

    $html = <<<'HTML'
<!doctype html>
<html>
<body>
    <div>Submissions open from 2025-07-18 16:00:00 to 2025-07-20 16:00:00.</div>
    <h2>Submitted so far(42)</h2>
</body>
</html>
HTML;

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with($jam->url, ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(200, [], $html));
    $this->app->instance(ItchHttpClientService::class, $client);

    expect($jam->fetchDetailsFromUrl())->toBeTrue();

    $jam->refresh();

    expect($jam->start_date?->format('Y-m-d H:i:s'))->toBe('2025-07-18 16:00:00')
        ->and($jam->end_date?->format('Y-m-d H:i:s'))->toBe('2025-07-20 16:00:00')
        ->and($jam->submission_count)->toBe(42);
});

it('parses stat boxes and info lines while ignoring invalid dates', function () {
    $jam = GameJam::create([
        'name' => 'Stats Box Jam',
        'url' => 'https://itch.io/jam/stats-box-jam',
    ]);

    $html = <<<'HTML'
<!doctype html>
<html>
<body>
    <div class="jam_stats_container">
        <div class="stat_box"><span class="label">Start</span><span class="value">not a date</span></div>
        <div class="stat_box"><span class="label">End</span><span class="value">also bad</span></div>
        <div class="stat_box"><span class="label">Submissions</span><span class="value">77</span></div>
        <div class="stat_box"><span class="label">Participants</span><span class="value">88</span></div>
        <div class="stat_box"><span class="value">missing label</span></div>
    </div>
    <div class="info_line">Starts: 2025-08-01 12:00:00</div>
    <div class="jam_info_line">Ends: 2025-08-02 12:00:00</div>
</body>
</html>
HTML;

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with($jam->url, ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(200, [], $html));
    $this->app->instance(ItchHttpClientService::class, $client);

    expect($jam->fetchDetailsFromUrl())->toBeTrue();

    $jam->refresh();

    expect($jam->submission_count)->toBe(77)
        ->and($jam->participant_count)->toBe(88)
        ->and($jam->start_date?->format('Y-m-d H:i:s'))->toBe('2025-08-01 12:00:00')
        ->and($jam->end_date?->format('Y-m-d H:i:s'))->toBe('2025-08-02 12:00:00');
});

it('falls back to counting entry cells when no submission text is present', function () {
    $jam = GameJam::create([
        'name' => 'Entry Cell Jam',
        'url' => 'https://itch.io/jam/entry-cell-jam',
    ]);

    $html = <<<'HTML'
<!doctype html>
<html>
<body>
    <div class="game_cell">First</div>
    <div class="game_cell">Second</div>
    <div class="game_cell">Third</div>
</body>
</html>
HTML;

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with($jam->url, ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(200, [], $html));
    $this->app->instance(ItchHttpClientService::class, $client);

    expect($jam->fetchDetailsFromUrl())->toBeTrue();

    expect($jam->refresh()->submission_count)->toBe(3);
});

it('returns false for non-rate-limit detail fetch failures and rethrows rate limits', function () {
    $ordinaryFailure = GameJam::create([
        'name' => 'Broken Jam',
        'url' => 'https://itch.io/jam/broken',
    ]);
    $rateLimited = GameJam::create([
        'name' => 'Rate Limited Jam',
        'url' => 'https://itch.io/jam/rate-limited',
    ]);

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/jam/broken', ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(500, [], 'Server error'));
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/jam/rate-limited', ['cookies' => false, 'allow_redirects' => false])
        ->andThrow(new RuntimeException('429 Too Many Requests'));
    $this->app->instance(ItchHttpClientService::class, $client);

    expect($ordinaryFailure->fetchDetailsFromUrl())->toBeFalse();

    $rateLimited->fetchDetailsFromUrl();
})->throws(RuntimeException::class, '429 Too Many Requests');

it('fetches game jam rankings and updates existing JSON URL game relationships', function () {
    $jam = GameJam::create([
        'name' => 'Ranking Jam',
        'url' => 'https://itch.io/jam/ranking-jam',
    ]);
    $game = Game::factory()->create([
        'name' => 'Ranked Game',
        'url' => ['itch_io' => 'https://developer.itch.io/ranked-game'],
    ]);

    $html = <<<'HTML'
<!doctype html>
<html>
<body>
    <div class="game_rank">
        <a class="game_cover" href="https://developer.itch.io/ranked-game?source=jam"></a>
        <div class="game_summary">
            <h2><a>Ranked Game</a></h2>
            <h3>Ranked <strong class="ordinal_rank">1st</strong> with 12 ratings</h3>
        </div>
        <table>
            <tr><th>Criteria</th><th>Rank</th><th>Score</th></tr>
            <tr><td>Overall</td><td>1st</td><td>4.9</td></tr>
            <tr><td>Writing</td><td>2nd</td><td>4.6</td></tr>
        </table>
    </div>
</body>
</html>
HTML;

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('setMaxRetries')->once()->with(1);
    $client->shouldReceive('setBaseCooldown')->once()->with(0);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/jam/ranking-jam/results', ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(200, [], $html));
    $this->app->instance(ItchHttpClientService::class, $client);

    expect($jam->fetchResultsPage(1, 0))->toBeTrue();

    $pivot = DB::table('game_game_jam')
        ->where('game_id', $game->id)
        ->where('game_jam_id', $jam->id)
        ->first();

    expect($pivot)->not->toBeNull()
        ->and($pivot->ranking)->toBe('1st')
        ->and(json_decode($pivot->criteria_rankings, true))->toBe([
            'Overall' => ['rank' => '1st', 'score' => '4.9'],
            'Writing' => ['rank' => '2nd', 'score' => '4.6'],
        ]);
});

it('updates existing ranking relationships and follows result pagination', function () {
    $jam = GameJam::create([
        'name' => 'Paged Ranking Jam',
        'url' => 'https://itch.io/jam/paged-ranking-jam',
    ]);
    $game = Game::factory()->create([
        'name' => 'Paged Ranked Game',
        'url' => ['itch_io' => 'https://developer.itch.io/paged-ranked-game'],
    ]);
    $game->gameJams()->attach($jam->id, [
        'ranking' => 'old',
        'criteria_rankings' => json_encode(['Old' => ['rank' => '9th', 'score' => '1.0']]),
    ]);

    $firstPage = <<<'HTML'
<!doctype html>
<html>
<body>
    <div class="game_rank">
        <a class="game_cover" href="https://developer.itch.io/missing-game"></a>
        <div class="game_summary"><h2><a>Missing Game</a></h2><h3>Ranked <strong class="ordinal_rank">1st</strong></h3></div>
    </div>
    <a class="next_page" href="?page=2">Next</a>
</body>
</html>
HTML;

    $secondPage = <<<'HTML'
<!doctype html>
<html>
<body>
    <div class="game_rank">
        <a class="game_cover" href="https://developer.itch.io/paged-ranked-game?jam=true"></a>
        <div class="game_summary">
            <h2><a>Paged Ranked Game</a></h2>
            <h3>Ranked <strong class="ordinal_rank">3rd</strong> with 4 ratings</h3>
        </div>
        <table>
            <tr><td>Writing</td><td>3rd</td><td>4.1</td></tr>
        </table>
    </div>
</body>
</html>
HTML;

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('setMaxRetries')->twice()->with(1);
    $client->shouldReceive('setBaseCooldown')->twice()->with(0);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/jam/paged-ranking-jam/results', ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(200, [], $firstPage));
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/jam/paged-ranking-jam/results?page=2', ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(200, [], $secondPage));
    $this->app->instance(ItchHttpClientService::class, $client);

    expect($jam->fetchResultsPage(1, 0))->toBeTrue();

    $pivot = DB::table('game_game_jam')
        ->where('game_id', $game->id)
        ->where('game_jam_id', $jam->id)
        ->first();

    expect($pivot->ranking)->toBeNull()
        ->and(json_decode($pivot->criteria_rankings, true))->toBe([
            'Writing' => ['rank' => '3rd', 'score' => '4.1'],
        ]);
});

it('stops ranking pagination at a fixed page limit', function () {
    $jam = GameJam::create([
        'name' => 'Endless Ranking Jam',
        'url' => 'https://itch.io/jam/endless-ranking-jam',
    ]);

    $pageWithNext = <<<'HTML'
<!doctype html>
<html>
<body>
    <a class="next_page" href="?page=next">Next</a>
</body>
</html>
HTML;

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('setMaxRetries')->twice()->with(1);
    $client->shouldReceive('setBaseCooldown')->twice()->with(0);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/jam/endless-ranking-jam/results', ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(200, [], $pageWithNext));
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/jam/endless-ranking-jam/results?page=2', ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(200, [], $pageWithNext));
    $this->app->instance(ItchHttpClientService::class, $client);

    expect($jam->fetchResultsPage(1, 0, 2, 0))->toBeFalse();
});

it('returns false when ranking pages contain no usable game rankings', function () {
    $jam = GameJam::create([
        'name' => 'Empty Ranking Jam',
        'url' => 'https://itch.io/jam/empty-ranking-jam',
    ]);

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('setMaxRetries')->once()->with(1);
    $client->shouldReceive('setBaseCooldown')->once()->with(0);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/jam/empty-ranking-jam/results', ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(200, [], '<html><body><div>No rankings yet</div></body></html>'));
    $this->app->instance(ItchHttpClientService::class, $client);

    expect($jam->fetchResultsPage(1, 0))->toBeFalse();
});

it('throws when the first ranking page cannot be fetched', function () {
    $jam = GameJam::create([
        'name' => 'Missing Ranking Jam',
        'url' => 'https://itch.io/jam/missing-ranking-jam',
    ]);

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('setMaxRetries')->once()->with(1);
    $client->shouldReceive('setBaseCooldown')->once()->with(0);
    $client->shouldReceive('get')
        ->once()
        ->with('https://itch.io/jam/missing-ranking-jam/results', ['cookies' => false, 'allow_redirects' => false])
        ->andReturn(new Response(404, [], 'Not found'));
    $this->app->instance(ItchHttpClientService::class, $client);

    $jam->fetchResultsPage(1, 0);
})->throws(Exception::class, 'Failed to fetch first page of rankings');
