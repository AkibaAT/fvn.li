import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
let f;
const errors = new WeakMap();
test.beforeEach(async ({ page, baseURL }) => {
    const e = [];
    errors.set(page, e);
    page.on('pageerror', (err) => e.push(err.message));
    f = JSON.parse(execFileSync('php', ['tests/e2e/support/make-audit-fixture.php'], { encoding: 'utf8' }));
    await page.context().addCookies([{ ...f.authCookie, url: baseURL }]);
    await page.route('**/storage/e2e/**', (r) => r.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg"/>' }));
});
test.afterEach(({ page }) => expect(errors.get(page)).toEqual([]));
async function awayBack(page) {
    await page.getByRole('navigation', { name: 'Main navigation', exact: true }).getByRole('link', { name: 'Lists', exact: true }).click();
    await expect(page).toHaveURL(/\/lists(?:\/public)?(?:\?|$)/);
    await page.goBack();
}
async function lists(page) {
    await page.getByRole('button', { name: /^(Add to|Manage in) Lists$/ }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog.getByRole('button', { name: 'Reading Remove', exact: true })).toBeVisible();
    return dialog;
}
async function shot(page, info, name) {
    await page.screenshot({ path: info.outputPath(name + '.png') });
}

test('standalone list editor preserves saved name and visibility after Back', async ({ page }, info) => {
    await page.goto(`/lists/${f.listIds[0]}/edit`);
    await expect(page.getByRole('textbox', { name: 'List Name *', exact: true })).toHaveValue('Deleted list');
    await page.getByRole('textbox', { name: 'List Name *', exact: true }).fill('Saved new title');
    await page.getByLabel('Make this list public', { exact: true }).check();
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/lists/${f.listIds[0]}$`));
    await expect(page.getByRole('heading', { name: 'Saved new title', exact: true })).toBeVisible();
    await page.goBack();
    await expect(page.getByRole('textbox', { name: 'List Name *', exact: true })).toHaveValue('Saved new title');
    await expect(page.getByLabel('Make this list public', { exact: true })).toBeChecked();
    await shot(page, info, 'stale-list-form');
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/lists/${f.listIds[0]}$`));
    await expect(page.getByRole('heading', { name: 'Saved new title', exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Saved new title', exact: true })).toBeVisible();
});

test('saved custom description survives Back and resave', async ({ page }, info) => {
    await page.goto(`/games/${f.slug}`);
    await page.locator('#edit-controls-container').getByRole('button', { name: 'Edit', exact: true }).click();
    const editor = page.frameLocator('iframe.tox-edit-area__iframe').locator('body');
    await editor.fill('New saved description');
    let releaseRefresh;
    let refreshStarted;
    const heldRefresh = new Promise<void>((resolve) => {
        releaseRefresh = resolve;
    });
    const requestedRefresh = new Promise<void>((resolve) => {
        refreshStarted = resolve;
    });
    await page.route(`**/games/${f.slug}`, async (route) => {
        if (route.request().headers()['x-inertia-partial-data']?.includes('game')) {
            refreshStarted();
            await heldRefresh;
        }
        await route.continue();
    });
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await requestedRefresh;
    try {
        await expect(page.getByRole('button', { name: /Saving\.\.\./ })).toBeDisabled();
    } finally {
        releaseRefresh();
    }
    await expect(page.locator('#inner_column')).toHaveText('New saved description');
    await page.unroute(`**/games/${f.slug}`);
    await awayBack(page);
    await expect(page.locator('#inner_column')).toHaveText('New saved description');
    await shot(page, info, 'stale-description');
    await page.locator('#edit-controls-container').getByRole('button', { name: 'Edit', exact: true }).click();
    await expect(editor).toHaveText('New saved description');
    const saved = page.waitForResponse((r) => r.url().endsWith(`/games/${f.gameId}/content`) && r.request().method() === 'PUT');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    expect((await (await saved).json()).data.content).toContain('New saved description');
    await page.reload();
    await expect(page.locator('#inner_column')).toHaveText('New saved description');
});

test('game detail initializes notification and membership controls from saved data', async ({ page }, info) => {
    await page.goto(`/games?noDefaults=1&search=${encodeURIComponent(f.originalName)}`);
    await expect(page.getByRole('button', { name: 'Manage in Lists', exact: true })).toBeVisible();
    await expect(page.getByRole('checkbox', { name: 'Notifications on', exact: true })).toBeChecked();
    await page.goto(`/games/${f.slug}`);
    await expect(page.getByRole('button', { name: 'Manage in Lists', exact: true })).toBeVisible();
    await expect(page.getByRole('checkbox', { name: 'Notifications on', exact: true })).toBeChecked();
    await shot(page, info, 'incorrect-detail-progress');
    const state = await (await page.request.get(`/browser-api/user-progress/${f.gameId}/status`)).json();
    expect(state).toMatchObject({ success: true, receive_updates: true });
    await lists(page);
});

test('browse Back preserves saved membership and notification state', async ({ page }, info) => {
    await page.goto(`/games?noDefaults=1&search=${encodeURIComponent(f.originalName)}`);
    await expect(page.getByRole('checkbox', { name: 'Notifications on', exact: true })).toBeChecked();
    await page.getByText('Notifications on', { exact: true }).click();
    await expect(page.getByRole('checkbox', { name: 'Notifications off', exact: true })).not.toBeChecked();
    const dialog = await lists(page);
    await dialog.getByRole('button', { name: 'Reading Remove', exact: true }).click();
    await expect(dialog.getByRole('button', { name: 'Reading', exact: true })).toBeVisible();
    await dialog.getByRole('button', { name: 'Close dialog', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Add to Lists', exact: true })).toBeVisible();
    await awayBack(page);
    await expect(page.getByRole('button', { name: 'Add to Lists', exact: true })).toBeVisible();
    await expect(page.getByRole('checkbox', { name: 'Notifications off', exact: true })).not.toBeChecked();
    await shot(page, info, 'stale-card-progress');
    await page.reload();
    await expect(page.getByRole('button', { name: 'Add to Lists', exact: true })).toBeVisible();
    await expect(page.getByRole('checkbox', { name: 'Notifications off', exact: true })).not.toBeChecked();
});

test('default status choices save in click order', async ({ page }, info) => {
    const pending = [];
    await page.route('**/games/*/add-to-list', (r) => pending.push(r));
    await page.goto(`/games/${f.slug}`);
    const dialog = await lists(page);
    await dialog.getByRole('button', { name: 'Completed', exact: true }).click();
    await expect.poll(() => pending.length).toBe(1);
    await dialog.getByRole('button', { name: 'Dropped', exact: true }).click();
    expect(pending).toHaveLength(1);
    await pending[0].continue();
    await expect.poll(() => pending.length).toBe(2);
    await pending[1].continue();
    await expect(dialog.getByRole('button', { name: 'Dropped Remove', exact: true })).toBeVisible();
    const result = await (await page.request.get(`/browser-api/games/${f.gameId}/lists`)).json();
    const all = await (await page.request.get('/browser-api/user/lists')).json();
    expect(result.list_ids).not.toContain(all.lists.find((l) => l.type === 'completed').id);
    expect(result.list_ids).toContain(all.lists.find((l) => l.type === 'dropped').id);
    await shot(page, info, 'status-order');
});

test('late bug report load leaves the newer report open', async ({ page }, info) => {
    let pending;
    await page.route(`**/bug-reports/${f.bugReportId}`, (r) => {
        pending = r;
    });
    await page.goto('/dashboard');
    await page.getByRole('button').filter({ hasText: f.reportA }).click();
    await expect.poll(() => !!pending).toBe(true);
    await page.getByRole('dialog').getByRole('button', { name: 'Close dialog', exact: true }).click();
    await page.getByRole('button').filter({ hasText: f.reportB }).click();
    await expect(page.getByRole('dialog').getByText(f.reportB, { exact: true })).toBeVisible();
    const response = page.waitForResponse((r) => r.url().endsWith(`/bug-reports/${f.bugReportId}`));
    await pending.continue();
    await response;
    await expect(page.getByRole('dialog').getByText(f.reportB, { exact: true })).toBeVisible();
    await shot(page, info, 'wrong-bug-report');
});

test('late comment preserves the newer report and its draft', async ({ page }, info) => {
    let pending;
    await page.route('**/bug-reports/*/comments', (r) => {
        pending = r;
    });
    await page.goto('/dashboard');
    await page.getByRole('button').filter({ hasText: f.reportA }).click();
    await page.getByLabel('Add Information', { exact: true }).fill('Comment for report A');
    await page.getByRole('button', { name: 'Send', exact: true }).click();
    await expect.poll(() => !!pending).toBe(true);
    await page.getByRole('dialog').getByRole('button', { name: 'Close dialog', exact: true }).click();
    await page.getByRole('button').filter({ hasText: f.reportB }).click();
    await page.getByLabel('Add Information', { exact: true }).fill('Unsaved draft for report B');
    expect(pending.request().url()).toContain(`/bug-reports/${f.bugReportId}/comments`);
    await pending.fulfill({
        json: {
            success: true,
            comment: {
                id: 900001,
                message: 'Comment for report A',
                is_from_admin: false,
                user: { id: f.userId, name: 'Fixture author' },
                created_at: '2026-05-03T12:00:00Z',
            },
        },
    });
    await expect(page.getByRole('dialog').getByText(f.reportB, { exact: true })).toBeVisible();
    await expect(page.getByRole('dialog').getByText('Comment for report A', { exact: true })).toHaveCount(0);
    await expect(page.getByLabel('Add Information', { exact: true })).toHaveValue('Unsaved draft for report B');
    await shot(page, info, 'comment-crossed-reports');
});

test('late ticket close removes only the original report', async ({ page }, info) => {
    let pending;
    await page.route('**/bug-reports/*/close', (r) => {
        pending = r;
    });
    await page.goto('/dashboard');
    await page.getByRole('button').filter({ hasText: f.reportA }).click();
    await page.getByRole('button', { name: 'Close Ticket', exact: true }).click();
    await expect.poll(() => !!pending).toBe(true);
    await page.getByRole('dialog').getByRole('button', { name: 'Close dialog', exact: true }).click();
    await page.getByRole('button').filter({ hasText: f.reportB }).click();
    await expect(page.getByRole('dialog').getByText(f.reportB, { exact: true })).toBeVisible();
    expect(pending.request().url()).toContain(`/bug-reports/${f.bugReportId}/close`);
    await pending.fulfill({ json: { success: true, message: 'Ticket closed' } });
    await expect(page.getByRole('dialog').getByText(f.reportB, { exact: true })).toBeVisible();
    await expect(page.getByRole('button').filter({ hasText: f.reportA })).toHaveCount(0);
    await expect(page.getByRole('button').filter({ hasText: f.reportB })).toBeVisible();
    await shot(page, info, 'wrong-ticket-removed');
});

test('visitor display choices persist in click order', async ({ page }, info) => {
    const pending = [];
    await page.route('**/games/*/view-mode', (r) => pending.push(r));
    await page.goto(`/games/${f.slug}`);
    await page.getByTitle('Show visitors custom content').click();
    await expect.poll(() => pending.length).toBe(1);
    await page.getByTitle('Show visitors original itch.io content', { exact: true }).click();
    expect(pending).toHaveLength(1);
    await pending[0].continue();
    await expect.poll(() => pending.length).toBe(2);
    await pending[1].continue();
    await expect
        .poll(async () => {
            const d = await (await page.request.get(`/browser-api/games/${f.gameId}/content/view`)).json();
            return d.data.current_view_mode;
        })
        .toBe('original');
    await expect(page.getByTitle('Show visitors original itch.io content', { exact: true })).toHaveClass(/bg-blue-600/);
    await shot(page, info, 'saved-visitor-mode');
});
