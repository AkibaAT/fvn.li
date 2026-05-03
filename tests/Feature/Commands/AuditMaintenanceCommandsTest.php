<?php

declare(strict_types=1);

use App\Models\ChangeLog;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

function auditMaintenanceLog(array $attributes = []): ChangeLog
{
    $user = $attributes['user'] ?? User::factory()->createQuietly();

    return ChangeLog::create([
        'timestamp' => $attributes['timestamp'] ?? now()->subDay(),
        'event_type' => $attributes['event_type'] ?? ChangeLog::EVENT_UPDATED,
        'entity_type' => $attributes['entity_type'] ?? User::class,
        'entity_id' => $attributes['entity_id'] ?? $user->id,
        'user_id' => $attributes['user_id'] ?? $user->id,
        'changes' => $attributes['changes'] ?? ['email'],
        'old_values' => $attributes['old_values'] ?? ['email' => 'old@example.com'],
        'new_values' => $attributes['new_values'] ?? ['email' => 'new@example.com'],
        'context' => $attributes['context'] ?? ['ip_address' => '203.0.113.44'],
        'source' => $attributes['source'] ?? ChangeLog::SOURCE_WEB,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function auditMaintenanceClickStat(array $attributes = []): ClickStat
{
    return ClickStat::create([
        'game_id' => $attributes['game_id'] ?? Game::factory()->createQuietly()->id,
        'user_id' => $attributes['user_id'] ?? null,
        'type' => $attributes['type'] ?? ClickStat::TYPE_PAGE_VIEW,
        'session_id' => $attributes['session_id'] ?? 'audit-maintenance-session',
        'ip_address' => $attributes['ip_address'] ?? '203.0.113.77',
        'user_agent' => $attributes['user_agent'] ?? 'Test Browser',
        'clicked_at' => $attributes['clicked_at'] ?? now(),
    ]);
}

it('skips audit cleanup when retention is disabled', function () {
    config(['audit.retention.enabled' => false]);
    $log = auditMaintenanceLog();

    $this->artisan('audit:cleanup')
        ->expectsOutput('Audit log retention is disabled in config.')
        ->assertExitCode(0);

    expect(ChangeLog::whereKey($log->id)->exists())->toBeTrue();
});

it('reports audit cleanup dry runs for ip sensitive and general retention buckets', function () {
    config([
        'audit.retention.enabled' => true,
        'audit.retention.days' => 0,
        'audit.retention.sensitive_data_retention_days' => 0,
        'audit.retention.ip_address_retention_days' => 0,
        'audit.model_settings' => [
            User::class => ['sensitive' => true],
        ],
    ]);

    ChangeLog::query()->delete();
    $log = auditMaintenanceLog();

    $this->artisan('audit:cleanup', ['--dry-run' => true])
        ->expectsOutput('DRY RUN MODE - No data will be deleted')
        ->expectsOutput('DRY RUN: Would delete 3 audit log entries/fields.')
        ->assertExitCode(0);

    expect(ChangeLog::whereKey($log->id)->exists())->toBeTrue();
});

it('cleans audit logs when forced and handles empty scoped cleanup', function () {
    config([
        'audit.retention.enabled' => true,
        'audit.retention.days' => 0,
        'audit.retention.sensitive_data_retention_days' => 0,
        'audit.retention.ip_address_retention_days' => 0,
        'audit.model_settings' => [
            User::class => ['sensitive' => true],
        ],
    ]);

    $sensitive = auditMaintenanceLog();
    $general = auditMaintenanceLog([
        'entity_type' => Game::class,
        'entity_id' => Game::factory()->createQuietly()->id,
    ]);

    $this->artisan('audit:cleanup', ['--sensitive-only' => true, '--force' => true])
        ->assertExitCode(0);

    expect(ChangeLog::whereKey($sensitive->id)->exists())->toBeFalse()
        ->and(ChangeLog::whereKey($general->id)->exists())->toBeTrue();

    $this->artisan('audit:cleanup', ['--ip-only' => true, '--force' => true])
        ->assertExitCode(0);

    $general->refresh();
    expect($general->context)->not->toHaveKey('ip_address');

    $this->artisan('audit:cleanup', ['--ip-only' => true, '--force' => true])
        ->assertExitCode(0);
});

it('validates and dry-runs click statistic ip anonymization', function () {
    auditMaintenanceClickStat(['ip_address' => '198.51.100.23']);
    auditMaintenanceClickStat(['ip_address' => '127.0.0.1']);
    auditMaintenanceClickStat(['ip_address' => '198.51.100.0']);

    $this->artisan('click-stats:anonymize-ips', ['--batch-size' => 0])
        ->expectsOutput('Batch size must be between 1 and 10000')
        ->assertExitCode(1);

    $this->artisan('click-stats:anonymize-ips', ['--dry-run' => true])
        ->expectsOutput('Found 1 click statistics with IP addresses that need anonymization.')
        ->expectsOutput('Total records that would be updated: 1')
        ->assertExitCode(0);

    expect(ClickStat::where('ip_address', '198.51.100.23')->exists())->toBeTrue();
});

it('anonymizes click statistic ips when forced', function () {
    $stat = auditMaintenanceClickStat(['ip_address' => '198.51.100.23']);

    $this->artisan('click-stats:anonymize-ips', [
        '--force' => true,
        '--batch-size' => 1,
    ])->assertExitCode(0);

    $stat->refresh();
    expect($stat->ip_address)->toBe('198.51.100.0');

    $this->artisan('click-stats:anonymize-ips', ['--force' => true])
        ->expectsOutput('No IP addresses found that need anonymization.')
        ->assertExitCode(0);
});

it('creates monthly and yearly audit partitions through database statements', function () {
    Log::spy();
    DB::shouldReceive('selectOne')->times(14)->andReturn(null);
    DB::shouldReceive('statement')->times(14)->andReturnTrue();

    $this->artisan('audit:create-partitions', ['--months' => 1])
        ->assertExitCode(0);

    $this->artisan('audit:create-partitions', ['--year' => 2030])
        ->assertExitCode(0);

    Log::shouldHaveReceived('info')->with('Created change_logs partition', Mockery::type('array'))->times(14);
    Log::shouldHaveReceived('info')->with('Created change_logs partitions for year 2030', Mockery::type('array'))->once();
});

it('reports skipped and failed audit partition creation', function () {
    Log::spy();
    DB::shouldReceive('selectOne')->once()->andReturn((object) ['exists' => 1]);
    DB::shouldReceive('selectOne')->once()->andReturn(null);
    DB::shouldReceive('statement')->once()->andThrow(new RuntimeException('not partitioned'));

    $this->artisan('audit:create-partitions', ['--months' => 1])
        ->assertExitCode(1);

    Log::shouldHaveReceived('error')->once();
});
