<?php

declare(strict_types=1);

use App\Models\ChangeLog;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function createPrivacyAuditLog(User $user, array $context = []): ChangeLog
{
    return ChangeLog::create([
        'timestamp' => now(),
        'event_type' => ChangeLog::EVENT_UPDATED,
        'entity_type' => User::class,
        'entity_id' => $user->id,
        'user_id' => $user->id,
        'changes' => ['name'],
        'old_values' => ['name' => 'Old'],
        'new_values' => ['name' => 'New'],
        'context' => $context + [
            'ip_address' => '203.0.113.5',
            'session_id' => 'session-1',
            'user_agent' => 'Browser',
        ],
        'source' => ChangeLog::SOURCE_WEB,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('exports audit privacy data for a target user', function () {
    Storage::fake();
    $user = User::factory()->create(['email' => 'privacy@example.com']);
    createPrivacyAuditLog($user);

    $this->artisan('audit:privacy', [
        'action' => 'export',
        '--email' => 'privacy@example.com',
        '--output' => 'exports/privacy.json',
    ])->assertExitCode(0);

    Storage::assertExists('exports/privacy.json');
    $export = json_decode(Storage::get('exports/privacy.json'), true);
    expect($export['user_id'])->toBe($user->id)
        ->and($export['total_entries'])->toBe(1)
        ->and($export['click_statistics'])->toBeArray();
});

it('deletes audit logs for a user when forced', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    createPrivacyAuditLog($user);
    createPrivacyAuditLog($other);

    $this->artisan('audit:privacy', [
        'action' => 'delete',
        '--user-id' => $user->id,
        '--force' => true,
    ])->assertExitCode(0);

    expect(ChangeLog::byUser($user->id)->count())->toBe(0)
        ->and(ChangeLog::byUser($other->id)->count())->toBe(1);
});

it('anonymizes audit logs and click statistics for a user when forced', function () {
    config(['audit.system_user_id' => User::factory()->create()->id]);
    $user = User::factory()->create();
    $game = Game::factory()->create();
    createPrivacyAuditLog($user);
    ClickStat::create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'type' => ClickStat::TYPE_PAGE_VIEW,
        'session_id' => 'session-privacy',
        'ip_address' => '203.0.113.20',
        'user_agent' => 'Browser',
        'clicked_at' => now(),
    ]);

    $this->artisan('audit:privacy', [
        'action' => 'anonymize',
        '--user-id' => $user->id,
        '--force' => true,
    ])->assertExitCode(0);

    $log = ChangeLog::query()
        ->whereRaw("context->>'anonymized' = 'true'")
        ->first();
    $click = ClickStat::query()->first();

    expect($log->user_id)->toBe(config('audit.system_user_id'))
        ->and($log->context['anonymized'])->toBeTrue()
        ->and($click->user_id)->toBeNull()
        ->and($click->ip_address)->not->toBe('203.0.113.20')
        ->and($click->session_id)->toBe('session-privacy');
});

it('reports privacy metrics and rejects invalid or missing targets', function () {
    $user = User::factory()->create();
    createPrivacyAuditLog($user);
    ClickStat::create([
        'game_id' => Game::factory()->create()->id,
        'type' => ClickStat::TYPE_CUSTOM_LINK,
        'link_id' => 'download',
        'session_id' => 'session-report',
        'ip_address' => '203.0.113.30',
        'user_agent' => 'Browser',
        'clicked_at' => now(),
    ]);

    $this->artisan('audit:privacy', ['action' => 'report'])
        ->assertExitCode(0);

    $this->artisan('audit:privacy', ['action' => 'export'])
        ->assertExitCode(1);

    $this->artisan('audit:privacy', ['action' => 'unknown'])
        ->assertExitCode(1);
});
