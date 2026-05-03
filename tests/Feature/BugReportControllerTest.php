<?php

declare(strict_types=1);

use App\Models\BugReport;
use App\Models\BugReportComment;
use App\Models\User;

function bugReportFor(User $user, array $attributes = []): BugReport
{
    return BugReport::create(array_merge([
        'user_id' => $user->id,
        'page_url' => 'https://fvn.li/games/example',
        'page_title' => 'Example Game',
        'description' => 'The page does not update after I save.',
        'request_parameters' => ['tab' => 'details'],
        'user_agent' => 'Pest',
        'status' => BugReport::STATUS_OPEN,
        'is_closed' => false,
    ], $attributes));
}

it('requires authentication for bug report endpoints', function () {
    $report = bugReportFor(User::factory()->create());

    $this->getJson(route('browser-api.bug-reports.index'))->assertUnauthorized();
    $this->postJson(route('browser-api.bug-reports.store'), [])->assertUnauthorized();
    $this->getJson(route('browser-api.bug-reports.show', $report))->assertUnauthorized();
    $this->postJson(route('browser-api.bug-reports.comments.store', $report), [])->assertUnauthorized();
    $this->postJson(route('browser-api.bug-reports.close', $report))->assertUnauthorized();
});

it('stores a bug report for the authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeader('User-Agent', 'Feature Test Browser')
        ->postJson(route('browser-api.bug-reports.store'), [
            'page_url' => 'https://fvn.li/dashboard#my-games',
            'page_title' => 'Dashboard',
            'description' => 'Uploading a screenshot stops without updating the gallery.',
            'request_parameters' => ['tab' => 'my-games'],
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Thank you for your bug report! We will review it shortly.',
        ]);

    $report = BugReport::findOrFail($response->json('report_id'));

    expect($report->user_id)->toBe($user->id)
        ->and($report->page_url)->toBe('https://fvn.li/dashboard#my-games')
        ->and($report->request_parameters)->toBe(['tab' => 'my-games'])
        ->and($report->user_agent)->toBe('Feature Test Browser')
        ->and($report->status)->toBe(BugReport::STATUS_OPEN);
});

it('validates bug report submission payloads', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('browser-api.bug-reports.store'), [
            'page_url' => '',
            'description' => 'short',
            'request_parameters' => 'query=broken',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page_url', 'description', 'request_parameters']);
});

it('lists only the current user reports with unread admin reply counts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $admin = User::factory()->create();

    $ownReport = bugReportFor($user, [
        'page_url' => 'https://fvn.li/dashboard',
        'description' => 'Dashboard report with admin replies.',
    ]);
    bugReportFor($otherUser, [
        'page_url' => 'https://fvn.li/other-user',
    ]);

    BugReportComment::create([
        'bug_report_id' => $ownReport->id,
        'user_id' => $admin->id,
        'message' => 'Unread admin reply.',
        'is_from_admin' => true,
        'is_read' => false,
    ]);
    BugReportComment::create([
        'bug_report_id' => $ownReport->id,
        'user_id' => $admin->id,
        'message' => 'Already read admin reply.',
        'is_from_admin' => true,
        'is_read' => true,
    ]);
    BugReportComment::create([
        'bug_report_id' => $ownReport->id,
        'user_id' => $user->id,
        'message' => 'User reply should not count as unread admin reply.',
        'is_from_admin' => false,
        'is_read' => false,
    ]);

    $this->actingAs($user)
        ->getJson(route('browser-api.bug-reports.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'reports')
        ->assertJsonPath('reports.0.id', $ownReport->id)
        ->assertJsonPath('reports.0.unread_count', 1)
        ->assertJsonPath('reports.0.status_label', 'Open')
        ->assertJsonPath('reports.0.status_color', 'warning');
});

it('shows an owned report with comments and marks admin replies read', function () {
    $user = User::factory()->create(['name' => 'Reporter']);
    $admin = User::factory()->create(['name' => 'Admin']);
    $report = bugReportFor($user);

    $adminReply = BugReportComment::create([
        'bug_report_id' => $report->id,
        'user_id' => $admin->id,
        'message' => 'Can you try again?',
        'is_from_admin' => true,
        'is_read' => false,
    ]);

    $this->actingAs($user)
        ->getJson(route('browser-api.bug-reports.show', $report))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('report.id', $report->id)
        ->assertJsonPath('report.is_closed', false)
        ->assertJsonPath('comments.0.id', $adminReply->id)
        ->assertJsonPath('comments.0.message', 'Can you try again?')
        ->assertJsonPath('comments.0.is_from_admin', true)
        ->assertJsonPath('comments.0.user.name', 'Admin');

    expect($adminReply->fresh()->is_read)->toBeTrue();
});

it('does not expose another user bug report', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $report = bugReportFor($owner);

    $this->actingAs($intruder)
        ->getJson(route('browser-api.bug-reports.show', $report))
        ->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Not found',
        ]);

    $this->actingAs($intruder)
        ->postJson(route('browser-api.bug-reports.comments.store', $report), [
            'message' => 'This should not be accepted.',
        ])
        ->assertNotFound();

    $this->actingAs($intruder)
        ->postJson(route('browser-api.bug-reports.close', $report))
        ->assertNotFound();
});

it('adds user comments to open owned bug reports', function () {
    $user = User::factory()->create(['name' => 'Reporter']);
    $report = bugReportFor($user);

    $response = $this->actingAs($user)
        ->postJson(route('browser-api.bug-reports.comments.store', $report), [
            'message' => 'Here is another detail about this issue.',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Comment added successfully.')
        ->assertJsonPath('comment.message', 'Here is another detail about this issue.')
        ->assertJsonPath('comment.is_from_admin', false)
        ->assertJsonPath('comment.user.name', 'Reporter');

    $comment = BugReportComment::findOrFail($response->json('comment.id'));

    expect($comment->bug_report_id)->toBe($report->id)
        ->and($comment->user_id)->toBe($user->id)
        ->and($comment->is_read)->toBeTrue();
});

it('validates comments and rejects comments on closed reports', function () {
    $user = User::factory()->create();
    $openReport = bugReportFor($user);
    $closedReport = bugReportFor($user, ['is_closed' => true]);

    $this->actingAs($user)
        ->postJson(route('browser-api.bug-reports.comments.store', $openReport), [
            'message' => 'no',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);

    $this->actingAs($user)
        ->postJson(route('browser-api.bug-reports.comments.store', $closedReport), [
            'message' => 'This report is already closed.',
        ])
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'message' => 'This report is closed and cannot receive new comments.',
        ]);
});

it('closes owned bug reports once', function () {
    $user = User::factory()->create();
    $report = bugReportFor($user);

    $this->actingAs($user)
        ->postJson(route('browser-api.bug-reports.close', $report))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Bug report closed successfully.',
            'is_closed' => true,
        ]);

    expect($report->fresh()->is_closed)->toBeTrue();

    $this->actingAs($user)
        ->postJson(route('browser-api.bug-reports.close', $report))
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'message' => 'This report is already closed.',
        ]);
});
