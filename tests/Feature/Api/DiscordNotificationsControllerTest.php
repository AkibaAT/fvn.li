<?php

declare(strict_types=1);

use App\Models\AdditionRequest;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationHistory;
use App\Models\NotificationQueue;
use App\Models\Rating;
use App\Models\ReviewReport;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function authenticateDiscordBot(): User
{
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['discord-notifications']);

    return $user;
}

function latestGameVersionFor(Game $game, array $attributes = []): GameVersion
{
    $version = GameVersion::factory()->create(array_merge([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => now(),
    ], $attributes));
    $version->forceFill(['is_latest' => true])->save();

    return $version;
}

it('requires authentication and validates pending notification requests', function () {
    $this->getJson('/api/discord-notifications/pending')
        ->assertUnauthorized();

    authenticateDiscordBot();

    $this->getJson('/api/discord-notifications/pending?limit=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['limit'], 'error');
});

it('requires a discord notification bot token instead of a normal user session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/discord-notifications/addition-requests')
        ->assertUnauthorized();

    Sanctum::actingAs($user, ['profile']);

    $this->getJson('/api/discord-notifications/addition-requests')
        ->assertForbidden();
});

it('accepts a real bearer token with the discord notification ability', function () {
    $token = User::factory()
        ->create()
        ->createToken('discord-bot', ['discord-notifications'])
        ->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/discord-notifications/addition-requests')
        ->assertOk()
        ->assertJsonPath('notifications', [])
        ->assertJsonPath('count', 0);
});

it('claims pending Discord notifications and formats bot payloads', function () {
    authenticateDiscordBot();
    $subscriber = User::factory()->create();
    SocialAccount::factory()->discord()->create([
        'user_id' => $subscriber->id,
        'provider_id' => 'discord-user-1',
    ]);
    $game = Game::factory()->create(['name' => 'Discord VN']);
    $version = latestGameVersionFor($game, ['version' => '2.0']);
    $notification = NotificationQueue::create([
        'user_id' => $subscriber->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'channel' => 'discord',
        'status' => 'pending',
        'scheduled_at' => now()->subMinute(),
        'payload' => ['title' => 'Queued'],
        'meta_data' => ['digest' => true, 'digest_type' => 'daily'],
    ]);
    NotificationQueue::create([
        'user_id' => $subscriber->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'channel' => 'browser',
        'status' => 'pending',
        'scheduled_at' => now()->subMinute(),
    ]);

    $this->getJson('/api/discord-notifications/pending?limit=10&batch_key=batch-1')
        ->assertOk()
        ->assertJsonPath('batch_key', 'batch-1')
        ->assertJsonPath('notifications.0.notification_id', $notification->id)
        ->assertJsonPath('notifications.0.discord_user_id', 'discord-user-1')
        ->assertJsonPath('notifications.0.game.name', 'Discord VN')
        ->assertJsonPath('notifications.0.game.version', '2.0')
        ->assertJsonPath('notifications.0.is_digest', true)
        ->assertJsonPath('notifications.0.digest_type', 'daily');

    $notification->refresh();
    expect($notification->status)->toBe('processing')
        ->and($notification->meta_data['batch_key'])->toBe('batch-1');
});

it('returns an empty notification batch when nothing is due', function () {
    authenticateDiscordBot();

    $this->getJson('/api/discord-notifications/pending?batch_key=empty-batch')
        ->assertOk()
        ->assertJsonPath('batch_key', 'empty-batch')
        ->assertJsonPath('notifications', []);
});

it('records delivery status only for matching notification batches', function () {
    authenticateDiscordBot();
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $version = latestGameVersionFor($game);
    $matching = NotificationQueue::create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'channel' => 'discord',
        'status' => 'processing',
        'scheduled_at' => now(),
        'meta_data' => ['batch_key' => 'batch-2', 'digest' => false],
    ]);
    $otherBatch = NotificationQueue::create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'channel' => 'browser',
        'status' => 'processing',
        'scheduled_at' => now(),
        'meta_data' => ['batch_key' => 'other'],
    ]);

    $this->postJson('/api/discord-notifications/status', [
        'batch_key' => 'batch-2',
        'notifications' => [
            ['notification_id' => $matching->id, 'success' => true],
            ['notification_id' => $otherBatch->id, 'success' => false, 'error' => 'wrong batch'],
        ],
    ])->assertOk()
        ->assertJsonPath('message', 'Delivery status recorded successfully');

    $matching->refresh();
    $otherBatch->refresh();
    expect($matching->status)->toBe('sent')
        ->and($matching->processed_at)->not->toBeNull()
        ->and($otherBatch->status)->toBe('processing')
        ->and(NotificationHistory::query()->count())->toBe(1)
        ->and(NotificationHistory::query()->first()->type)->toBe('discord');
});

it('validates delivery status payloads', function () {
    authenticateDiscordBot();

    $this->postJson('/api/discord-notifications/status', [
        'batch_key' => 'batch',
        'notifications' => [['success' => true]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['notifications.0.notification_id'], 'error');
});

it('returns and marks pending addition request notifications', function () {
    authenticateDiscordBot();
    $requester = User::factory()->create(['name' => 'Requester']);
    $additionRequest = AdditionRequest::factory()->create([
        'game_url' => 'https://creator.itch.io/new-vn',
        'status' => AdditionRequest::STATUS_PENDING,
        'discord_notified_at' => null,
    ]);
    $additionRequest->addUser($requester);

    $this->getJson('/api/discord-notifications/addition-requests?limit=5')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('notifications.0.id', $additionRequest->id)
        ->assertJsonPath('notifications.0.url', 'https://creator.itch.io/new-vn')
        ->assertJsonPath('notifications.0.user_count', 1)
        ->assertJsonPath('notifications.0.users.0.name', 'Requester');

    expect($additionRequest->fresh()->discord_notified_at)->not->toBeNull();

    $this->getJson('/api/discord-notifications/addition-requests')
        ->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('notifications', []);
});

it('returns and marks pending review report notifications', function () {
    authenticateDiscordBot();
    $reporter = User::factory()->create(['name' => 'Reporter']);
    $author = User::factory()->create(['name' => 'Review Author']);
    $game = Game::factory()->create(['name' => 'Reported VN']);
    $rating = Rating::create([
        'game_id' => $game->id,
        'user_id' => $author->id,
        'rating' => 4,
        'review' => '<p>This review has a spoiler.</p>',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'fvn_li',
        'published_at' => now(),
    ]);
    $report = ReviewReport::create([
        'rating_id' => $rating->id,
        'reporter_id' => $reporter->id,
        'reason' => 'spoilers',
        'details' => 'Unmarked spoiler',
        'status' => 'pending',
    ]);

    $this->getJson('/api/discord-notifications/review-reports')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('notifications.0.id', $report->id)
        ->assertJsonPath('notifications.0.reason', 'Unmarked spoilers')
        ->assertJsonPath('notifications.0.reporter', 'Reporter')
        ->assertJsonPath('notifications.0.review_author', 'Review Author')
        ->assertJsonPath('notifications.0.game_name', 'Reported VN')
        ->assertJsonPath('notifications.0.review_excerpt', 'This review has a spoiler.');

    expect($report->fresh()->discord_notified_at)->not->toBeNull();
});
