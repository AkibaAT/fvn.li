<?php

declare(strict_types=1);

use App\Models\ChangeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('GDPR data export', function () {
    test('exports user audit data with correct structure', function () {
        // Create some audit logs for the user
        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 1,
            'timestamp' => now(),
            'changes' => ['name' => 'My List'],
        ]);

        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'updated',
            'entity_type' => 'Game',
            'entity_id' => 5,
            'timestamp' => now(),
            'changes' => ['rating' => 5],
        ]);

        $export = ChangeLog::exportUserData($this->user->id);

        expect($export)->toHaveKeys(['user_id', 'exported_at', 'total_entries', 'audit_logs'])
            ->and($export['user_id'])->toBe($this->user->id)
            ->and($export['total_entries'])->toBe(2)
            ->and($export['audit_logs'])->toHaveCount(2);
    });

    test('export includes all required fields', function () {
        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 1,
            'timestamp' => now(),
            'changes' => ['name' => 'Test'],
            'old_values' => null,
            'new_values' => ['name' => 'Test'],
            'context' => ['ip' => '127.0.0.1'],
            'source' => 'web',
            'description' => 'Created list',
        ]);

        $export = ChangeLog::exportUserData($this->user->id);
        $log = $export['audit_logs'][0];

        expect($log)->toHaveKeys([
            'id',
            'timestamp',
            'event_type',
            'entity_type',
            'entity_id',
            'changes',
            'old_values',
            'new_values',
            'context',
            'source',
            'description',
            'created_at',
            'updated_at',
        ]);
    });

    test('export returns empty array when user has no audit logs', function () {
        $export = ChangeLog::exportUserData($this->user->id);

        expect($export['total_entries'])->toBe(0)
            ->and($export['audit_logs'])->toBeEmpty();
    });

    test('export only includes logs for specified user', function () {
        $otherUser = User::factory()->create();

        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 1,
            'timestamp' => now(),
        ]);

        ChangeLog::create([
            'user_id' => $otherUser->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 2,
            'timestamp' => now(),
        ]);

        $export = ChangeLog::exportUserData($this->user->id);

        expect($export['total_entries'])->toBe(1)
            ->and($export['audit_logs'][0]['entity_id'])->toBe(1);
    });

    test('export orders logs by timestamp descending', function () {
        $oldTimestamp = now()->subDays(2);
        $newTimestamp = now();

        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 1,
            'timestamp' => $oldTimestamp,
        ]);

        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'updated',
            'entity_type' => 'VnList',
            'entity_id' => 2,
            'timestamp' => $newTimestamp,
        ]);

        $export = ChangeLog::exportUserData($this->user->id);

        // First log should be the newest
        expect($export['audit_logs'][0]['entity_id'])->toBe(2)
            ->and($export['audit_logs'][1]['entity_id'])->toBe(1);
    });
});

describe('GDPR data deletion', function () {
    test('deletes all audit logs for user', function () {
        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 1,
            'timestamp' => now(),
        ]);

        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'updated',
            'entity_type' => 'Game',
            'entity_id' => 2,
            'timestamp' => now(),
        ]);

        $deletedCount = ChangeLog::deleteUserData($this->user->id);

        expect($deletedCount)->toBe(2)
            ->and(ChangeLog::where('user_id', $this->user->id)->count())->toBe(0);
    });

    test('does not delete other users audit logs', function () {
        $otherUser = User::factory()->create();

        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 1,
            'timestamp' => now(),
        ]);

        ChangeLog::create([
            'user_id' => $otherUser->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 2,
            'timestamp' => now(),
        ]);

        ChangeLog::deleteUserData($this->user->id);

        expect(ChangeLog::where('user_id', $otherUser->id)->count())->toBe(1);
    });

    test('returns zero when user has no audit logs', function () {
        $deletedCount = ChangeLog::deleteUserData($this->user->id);

        expect($deletedCount)->toBe(0);
    });
});

describe('GDPR data anonymization', function () {
    test('anonymizes user audit logs by reassigning to system user', function () {
        config(['audit.system_user_id' => 1]);

        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 1,
            'timestamp' => now(),
            'context' => ['ip' => '192.168.1.100'],
        ]);

        $anonymizedCount = ChangeLog::anonymizeUserData($this->user->id);

        expect($anonymizedCount)->toBe(1);

        $log = ChangeLog::where('entity_id', 1)->first();
        expect($log->user_id)->toBe(1); // System user
    });

    test('returns zero when user has no audit logs to anonymize', function () {
        $anonymizedCount = ChangeLog::anonymizeUserData($this->user->id);

        expect($anonymizedCount)->toBe(0);
    });

    test('anonymizes multiple logs for same user', function () {
        config(['audit.system_user_id' => 1]);

        for ($i = 1; $i <= 5; $i++) {
            ChangeLog::create([
                'user_id' => $this->user->id,
                'event_type' => 'created',
                'entity_type' => 'VnList',
                'entity_id' => $i,
                'timestamp' => now(),
            ]);
        }

        $anonymizedCount = ChangeLog::anonymizeUserData($this->user->id);

        expect($anonymizedCount)->toBe(5)
            ->and(ChangeLog::where('user_id', $this->user->id)->count())->toBe(0)
            ->and(ChangeLog::where('user_id', 1)->count())->toBe(5);
    });

    test('does not affect other users logs during anonymization', function () {
        config(['audit.system_user_id' => 1]);
        $otherUser = User::factory()->create();

        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 1,
            'timestamp' => now(),
        ]);

        ChangeLog::create([
            'user_id' => $otherUser->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 2,
            'timestamp' => now(),
        ]);

        ChangeLog::anonymizeUserData($this->user->id);

        expect(ChangeLog::where('user_id', $otherUser->id)->count())->toBe(1);
    });
});

describe('GDPR compliance edge cases', function () {
    test('handles user with large number of audit logs', function () {
        // Create 100 audit logs
        for ($i = 1; $i <= 100; $i++) {
            ChangeLog::create([
                'user_id' => $this->user->id,
                'event_type' => 'created',
                'entity_type' => 'VnList',
                'entity_id' => $i,
                'timestamp' => now()->subMinutes($i),
            ]);
        }

        $export = ChangeLog::exportUserData($this->user->id);

        expect($export['total_entries'])->toBe(100)
            ->and($export['audit_logs'])->toHaveCount(100);
    });

    test('export handles logs with null values', function () {
        ChangeLog::create([
            'user_id' => $this->user->id,
            'event_type' => 'created',
            'entity_type' => 'VnList',
            'entity_id' => 1,
            'timestamp' => now(),
            'changes' => null,
            'old_values' => null,
            'new_values' => null,
            'context' => null,
            'source' => null,
            'description' => null,
        ]);

        $export = ChangeLog::exportUserData($this->user->id);

        expect($export['total_entries'])->toBe(1);
        // Changes field may be null or empty array depending on JSON serialization
        $changes = $export['audit_logs'][0]['changes'];
        expect($changes === null || $changes === [])->toBeTrue();
    });
});

