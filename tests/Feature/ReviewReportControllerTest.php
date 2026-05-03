<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\ReviewReport;
use App\Models\User;

function moderationRating(array $attributes = []): Rating
{
    $game = $attributes['game'] ?? Game::factory()->create([
        'name' => 'Moderated Game',
        'is_visible' => true,
        'url' => ['itch_io' => 'https://example.itch.io/moderated-game'],
    ]);
    unset($attributes['game']);

    $rater = $attributes['rater'] ?? Rater::factory()->create([
        'name' => 'Moderated Rater',
        'external_platform' => 'itch_io',
    ]);
    unset($attributes['rater']);

    return Rating::create(array_merge([
        'event_id' => fake()->unique()->numberBetween(1000, 999999),
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 2,
        'review' => 'This review needs moderation.',
        'is_visible' => true,
        'is_reviewed' => true,
        'has_spoilers' => false,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ], $attributes));
}

it('requires authentication for review report endpoints', function () {
    $rating = moderationRating();
    $report = ReviewReport::create([
        'rating_id' => $rating->id,
        'reporter_id' => User::factory()->create()->id,
        'reason' => 'spam',
    ]);

    $this->getJson(route('browser-api.review-reports.index'))->assertUnauthorized();
    $this->postJson(route('browser-api.review-reports.store', $rating), ['reason' => 'spam'])->assertUnauthorized();
    $this->postJson(route('browser-api.review-reports.resolve', $report), ['status' => 'dismissed'])->assertUnauthorized();
});

it('lets users report reviews and auto-hides after multiple pending reports', function () {
    $author = User::factory()->create();
    $firstReporter = User::factory()->create();
    $secondReporter = User::factory()->create();
    $rating = moderationRating([
        'user_id' => $author->id,
        'rater_id' => null,
        'source_platform' => 'fvn_li',
    ]);

    $this->actingAs($firstReporter)
        ->postJson(route('browser-api.review-reports.store', $rating), [
            'reason' => 'spam',
            'details' => 'This is copied promotional text.',
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Report submitted. Our team will review it shortly.',
        ]);

    expect(ReviewReport::where('rating_id', $rating->id)->where('reporter_id', $firstReporter->id)->exists())
        ->toBeTrue()
        ->and($rating->fresh()->is_visible)->toBeTrue();

    $this->actingAs($secondReporter)
        ->postJson(route('browser-api.review-reports.store', $rating), [
            'reason' => 'harassment',
        ])
        ->assertOk();

    expect($rating->fresh()->is_visible)->toBeFalse();
});

it('rejects invalid duplicate and own review reports', function () {
    $author = User::factory()->create();
    $reporter = User::factory()->create();
    $rating = moderationRating([
        'user_id' => $author->id,
        'rater_id' => null,
        'source_platform' => 'fvn_li',
    ]);

    $this->actingAs($reporter)
        ->postJson(route('browser-api.review-reports.store', $rating), [
            'reason' => 'not-a-reason',
            'details' => str_repeat('x', 1001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason', 'details']);

    ReviewReport::create([
        'rating_id' => $rating->id,
        'reporter_id' => $reporter->id,
        'reason' => 'spam',
    ]);

    $this->actingAs($reporter)
        ->postJson(route('browser-api.review-reports.store', $rating), [
            'reason' => 'spam',
        ])
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'message' => 'You have already reported this review.',
        ]);

    $this->actingAs($author)
        ->postJson(route('browser-api.review-reports.store', $rating), [
            'reason' => 'spam',
        ])
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'message' => 'You cannot report your own review.',
        ]);
});

it('restricts report listing and resolution to admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $regularUser = User::factory()->create(['is_admin' => false]);
    $reporter = User::factory()->create();
    $rating = moderationRating();
    $report = ReviewReport::create([
        'rating_id' => $rating->id,
        'reporter_id' => $reporter->id,
        'reason' => 'off_topic',
        'status' => 'pending',
    ]);

    $this->actingAs($regularUser)
        ->getJson(route('browser-api.review-reports.index'))
        ->assertForbidden();

    $this->actingAs($regularUser)
        ->postJson(route('browser-api.review-reports.resolve', $report), [
            'status' => 'dismissed',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson(route('browser-api.review-reports.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('reports.data.0.id', $report->id);

    $this->actingAs($admin)
        ->postJson(route('browser-api.review-reports.resolve', $report), [
            'status' => 'actioned',
            'admin_notes' => 'Hidden after review.',
            'hide_review' => true,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Report resolved.',
        ]);

    $report->refresh();

    expect($report->status)->toBe('actioned')
        ->and($report->reviewed_by)->toBe($admin->id)
        ->and($report->reviewed_at)->not->toBeNull()
        ->and($report->admin_notes)->toBe('Hidden after review.')
        ->and($rating->fresh()->is_visible)->toBeFalse();
});

it('validates report resolution payloads', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $report = ReviewReport::create([
        'rating_id' => moderationRating()->id,
        'reporter_id' => User::factory()->create()->id,
        'reason' => 'other',
    ]);

    $this->actingAs($admin)
        ->postJson(route('browser-api.review-reports.resolve', $report), [
            'status' => 'pending',
            'admin_notes' => str_repeat('x', 1001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'admin_notes']);
});
