<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\User;

function storedReviewRating(array $attributes = []): Rating
{
    return Rating::create(array_merge([
        'game_id' => Game::factory()->createQuietly()->id,
        'rater_id' => Rater::factory()->createQuietly()->id,
        'rating' => 5,
        'review' => '<p>Clean review.</p>',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ], $attributes));
}

it('reports stored review html changes without mutating rows by default', function () {
    $rating = storedReviewRating([
        'review' => '<div onmouseover="alert(1)">X</div><a href="javascript:alert(2)">link</a>',
        'source_platform' => 'fvn_li',
        'user_id' => User::factory()->createQuietly()->id,
        'rater_id' => null,
    ]);

    $this->artisan('ratings:sanitize-reviews')
        ->expectsOutput('Scanning 1 stored review(s).')
        ->expectsOutput('REPORT ONLY - no review rows will be updated. Re-run with --apply to rewrite stored HTML.')
        ->expectsOutput('Stored reviews that would be updated: 1')
        ->assertExitCode(0);

    expect($rating->fresh()->review)->toBe('<div onmouseover="alert(1)">X</div><a href="javascript:alert(2)">link</a>');
});

it('sanitizes all stored review sources when applied', function () {
    $userReview = storedReviewRating([
        'review' => '<div onmouseover="alert(1)">X</div><a href="javascript:alert(2)">link</a><p style="background-image:url(javascript:alert(3));color:red;text-align:center;position:absolute">Styled</p>',
        'source_platform' => 'fvn_li',
        'user_id' => User::factory()->createQuietly()->id,
        'rater_id' => null,
    ]);
    $importedReview = storedReviewRating([
        'review' => '<p onclick="alert(1)">Imported <strong>review</strong></p>',
        'source_platform' => 'itch_io',
    ]);
    $cleanReview = storedReviewRating([
        'review' => '<p>Already clean.</p>',
        'source_platform' => 'steam',
    ]);
    $emptyAfterSanitizing = storedReviewRating([
        'review' => '<img src="javascript:alert(1)" onerror="alert(2)">',
        'source_platform' => 'fvn_li',
        'user_id' => User::factory()->createQuietly()->id,
        'rater_id' => null,
    ]);

    $originalUpdatedAt = $userReview->updated_at?->toISOString();

    $this->artisan('ratings:sanitize-reviews', [
        '--apply' => true,
        '--force' => true,
        '--batch-size' => 1,
    ])
        ->expectsOutput('Scanning 4 stored review(s).')
        ->expectsOutput('Sanitized 3 stored review(s).')
        ->assertExitCode(0);

    expect($userReview->fresh()->review)->toBe('<div>X</div><a rel="noopener">link</a><p style="color:red;text-align:center">Styled</p>')
        ->and($userReview->fresh()->updated_at?->toISOString())->toBe($originalUpdatedAt)
        ->and($importedReview->fresh()->review)->toBe('<p>Imported <strong>review</strong></p>')
        ->and($cleanReview->fresh()->review)->toBe('<p>Already clean.</p>')
        ->and($emptyAfterSanitizing->fresh()->is_reviewed)->toBeFalse();

    $this->artisan('ratings:sanitize-reviews', ['--apply' => true, '--force' => true])
        ->expectsOutput('Sanitized 0 stored review(s).')
        ->assertExitCode(0);
});

it('can target specific stored review ids and validates options', function () {
    $target = storedReviewRating([
        'review' => '<span onmouseover="alert(1)">Target</span>',
    ]);
    $other = storedReviewRating([
        'review' => '<span onmouseover="alert(1)">Other</span>',
    ]);

    $this->artisan('ratings:sanitize-reviews', ['--batch-size' => 0])
        ->expectsOutput('Batch size must be between 1 and 10000')
        ->assertExitCode(1);

    $this->artisan('ratings:sanitize-reviews', ['--ids' => 'not-a-number'])
        ->expectsOutput('--ids must be a comma-separated list of positive integer rating IDs')
        ->assertExitCode(1);

    $this->artisan('ratings:sanitize-reviews', [
        '--apply' => true,
        '--force' => true,
        '--ids' => (string) $target->id,
    ])
        ->expectsOutput('Scanning 1 stored review(s).')
        ->assertExitCode(0);

    expect($target->fresh()->review)->toBe('<span>Target</span>')
        ->and($other->fresh()->review)->toBe('<span onmouseover="alert(1)">Other</span>');
});
