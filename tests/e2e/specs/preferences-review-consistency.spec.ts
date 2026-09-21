import { test, expect, type Page, type Route, type TestInfo } from '@playwright/test';
import type { GameViewFixture } from '../support/laravel';
import { execFileSync } from 'node:child_process';
let fixture: GameViewFixture & { gameId: number; discordServerId: number; reviewId: number };
const pageErrors = new WeakMap<Page, string[]>();
test.beforeEach(async ({ page, baseURL }) => {
    const errors: string[] = [];
    pageErrors.set(page, errors);
    page.on('pageerror', (error) => errors.push(error.message));
    fixture = JSON.parse(execFileSync('php', ['tests/e2e/support/make-preferences-review-fixture.php'], { encoding: 'utf8' }));
    await page.context().addCookies([{ ...fixture.authCookie, url: baseURL! }]);
    await page.route('**/storage/e2e/**', (r) => r.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg"/>' }));
    await page.route('**/browser-api/discord/servers/*/channels', (r) => r.fulfill({ json: { channels: [] } }));
    await page.route('**/browser-api/discord/servers/*/roles', (r) => r.fulfill({ json: { roles: [] } }));
    await page.route('**/browser-api/discord/servers/*/preview-embed', (r) => r.fulfill({ json: { embed: { title: 'Fixture preview' } } }));
});

test.afterEach(({ page }) => expect(pageErrors.get(page)).toEqual([]));

async function shot(page: Page, info: TestInfo, name: string) {
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: info.outputPath(name + '.png'), fullPage: true });
}

test('saved search preferences survive Back and subsequent saves', async ({ page }, info) => {
    await page.goto('/dashboard?tab=search');
    const english = page.getByRole('button', { name: 'English', exact: true });
    await expect(english).toHaveClass(/bg-accent/);
    await english.click();
    await page.getByRole('button', { name: 'Save Preferences', exact: true }).first().click();
    await expect(page.getByText('Language preferences saved', { exact: true })).toBeVisible();
    expect((await (await page.request.get('/user/language-preferences')).json()).preferred_languages).toEqual([]);
    await page.getByRole('navigation', { name: 'Main navigation', exact: true }).getByRole('link', { name: 'Games', exact: true }).click();
    await expect(page).toHaveURL(/\/games$/);
    await page.goBack();
    await expect(english).not.toHaveClass(/bg-accent/);
    await page.getByRole('button', { name: 'Save Preferences', exact: true }).first().click();
    await expect(page.getByText('Language preferences saved', { exact: true })).toBeVisible();
    expect((await (await page.request.get('/user/language-preferences')).json()).preferred_languages).toEqual([]);
    await shot(page, info, 'saved-search-preferences');
});

test('double unignore submits once and keeps canonical counts', async ({ page }, info) => {
    const pending: Route[] = [];
    await page.route('**/user/ignored-games', (r) => {
        if (r.request().method() === 'DELETE') {
            pending.push(r);
            return;
        }
        return r.continue();
    });
    await page.goto('/dashboard?tab=search');
    await page.getByRole('button', { name: 'Remove', exact: true }).dblclick();
    await expect.poll(() => pending.length).toBe(1);
    for (const request of pending) {
        const response = page.waitForResponse((r) => r.url() === request.request().url() && r.request().method() === 'DELETE');
        await request.continue();
        await (await response).finished();
    }
    await expect(page.getByText('0 games', { exact: true })).toBeVisible();
    await shot(page, info, 'saved-ignore-count');
});

test('failed Discord configuration save restores the saved value', async ({ page }, info) => {
    await page.route('**/browser-api/discord/servers/*/config', (r) =>
        r.fulfill({ status: 500, json: { message: 'Simulated configuration failure' } }),
    );
    await page.goto(`/discord/${fixture.discordServerId}`);
    const toggle = page.getByRole('checkbox', { name: 'Include thumbnail', exact: true });
    await expect(toggle).toBeChecked();
    await toggle.locator('..').click();
    await expect(page.getByText('Simulated configuration failure', { exact: true })).toBeVisible();
    await expect(toggle).toBeChecked();
    const saved = await (await page.request.get(`/browser-api/discord/servers/${fixture.discordServerId}`)).json();
    expect(saved.server.config.include_thumbnail).toBe(true);
    await shot(page, info, 'restored-discord-switch');
});

test('embed fields survive adding, editing, clearing, and reload', async ({ page }, info) => {
    await page.goto(`/discord/${fixture.discordServerId}?tab=embeds`);
    const saved = page.waitForResponse((r) => r.url().endsWith('/config') && r.request().method() === 'PUT');
    await page.getByRole('button', { name: '+ Add Field', exact: true }).first().click();
    expect((await saved).ok()).toBe(true);
    await expect(page.getByLabel('Field 1 name', { exact: true })).toHaveValue('');
    await page.getByLabel('Field 1 name', { exact: true }).fill('Custom field');
    await page.getByLabel('Field 1 value', { exact: true }).fill('Custom value');
    await expect(page.getByRole('button', { name: 'Save All', exact: true })).toBeEnabled();
    await page.reload();
    await expect(page.getByLabel('Field 1 name', { exact: true })).toHaveValue('Custom field');
    await expect(page.getByLabel('Field 1 value', { exact: true })).toHaveValue('Custom value');
    await page.getByLabel('Field 1 name', { exact: true }).fill('');
    await page.getByLabel('Field 1 value', { exact: true }).fill('');
    await expect(page.getByRole('button', { name: 'Save All', exact: true })).toBeEnabled();
    await page.reload();
    await expect(page.getByLabel('Field 1 name', { exact: true })).toHaveValue('');
    await expect(page.getByLabel('Field 1 value', { exact: true })).toHaveValue('');
    const data = await (await page.request.get(`/browser-api/discord/servers/${fixture.discordServerId}`)).json();
    expect(data.server.config.new_game_embed.fields).toHaveLength(1);
    await shot(page, info, 'saved-embed-field');
});

for (const status of [200, 500]) {
    test(`addition request filters ignore stale responses (${status})`, async ({ page }, info) => {
        let first: Route | undefined;
        const request = (id: number, state: string) => ({
            id,
            game_url: `https://${state}.itch.io/fixture`,
            status: state,
            status_label: state,
            status_color: 'info',
        });
        await page.route('**/browser-api/dashboard/addition-requests*', (r) => {
            const filter = new URL(r.request().url()).searchParams.get('status');
            if (filter === 'pending') {
                first = r;
                return;
            }
            return r.fulfill({ json: { success: true, requests: [request(2, filter === 'approved' ? 'approved' : 'pending')] } });
        });
        await page.goto('/dashboard?tab=additions');
        await expect(page.getByText('https://pending.itch.io/fixture', { exact: true })).toBeVisible();
        const select = page.getByLabel('Filter addition requests by status', { exact: true });
        await select.selectOption('pending');
        await expect.poll(() => Boolean(first)).toBe(true);
        await select.selectOption('approved');
        await expect(page.getByText('https://approved.itch.io/fixture', { exact: true })).toBeVisible();
        const response = page.waitForResponse((r) => r.url() === first!.request().url());
        await first!.fulfill({
            status,
            json: status === 200 ? { success: true, requests: [request(1, 'pending')] } : { message: 'Simulated additions failure' },
        });
        await (await response).finished();
        await expect(page.getByText('https://approved.itch.io/fixture', { exact: true })).toBeVisible();
        await expect(page.getByText('https://pending.itch.io/fixture', { exact: true })).toHaveCount(0);
        await expect(select).toHaveValue('approved');
        await expect(page.getByText('Simulated additions failure', { exact: true })).toHaveCount(0);
        await shot(page, info, 'addition-response-' + status);
    });
}

test('invalid addition URLs show the validation explanation', async ({ page }, info) => {
    await page.goto('/dashboard?tab=additions');
    await page.getByLabel('Game URLs', { exact: true }).fill('not a url');
    const response = page.waitForResponse((r) => r.url().endsWith('/addition-requests') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Submit Requests', exact: true }).click();
    expect((await (await response).json()).message).toContain('Invalid URL format: not a url');
    await expect(page.getByText(/Invalid URL format: not a url/)).toBeVisible();
    await expect(page.getByLabel('Game URLs', { exact: true })).toHaveValue('not a url');
    await shot(page, info, 'submission-explanation');
});

test('review saves refresh history and publication dates', async ({ page }, info) => {
    await page.goto(`/reviews/${fixture.reviewId}`);
    const oldDate = await page.locator('main time').getAttribute('datetime');
    await page.getByRole('button', { name: 'Edit review', exact: true }).click();
    await page.getByRole('button', { name: '5 stars', exact: true }).click();
    const response = page.waitForResponse((r) => r.url().includes('/user-reviews/') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Update Review', exact: true }).click();
    const saved = await (await response).json();
    expect(saved.review.rating).toBe(5);
    expect(saved.review.published_at).not.toBe(oldDate);
    await expect(page.getByRole('button', { name: 'Edit review', exact: true })).toBeVisible();
    await expect(page.locator('main time')).not.toHaveAttribute('datetime', oldDate!);
    await page.getByRole('link', { name: `Back to ${fixture.originalName}`, exact: true }).click();
    await expect(page).toHaveURL(new RegExp('/games/' + fixture.slug + '$'));
    await page.goBack();
    await expect(page.getByText('5/5', { exact: true })).toBeVisible();
    await shot(page, info, 'saved-review-after-back');
    await page.reload();
    await expect(page.getByText('5/5', { exact: true })).toBeVisible();
});

test('review cancel discards the draft rating', async ({ page }, info) => {
    await page.goto(`/reviews/${fixture.reviewId}`);
    await page.getByRole('button', { name: 'Edit review', exact: true }).click();
    await page.getByRole('button', { name: '5 stars', exact: true }).click();
    await page.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(page.getByText('2/5', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Edit review', exact: true }).click();
    await expect(page.getByText('2/5', { exact: true })).toHaveCount(2);
    await shot(page, info, 'discarded-review-draft');
});

test('failed VN request loads can retry without showing stale rows', async ({ page }, info) => {
    let failed = true;
    await page.route('**/browser-api/dashboard/addition-requests*', (r) =>
        r.fulfill(
            failed
                ? { status: 500, json: { message: 'Requests temporarily unavailable' } }
                : {
                      json: {
                          success: true,
                          requests: [
                              { id: 900, game_url: 'https://retry.itch.io/game', status: 'pending', status_label: 'Pending', status_color: 'info' },
                          ],
                      },
                  },
        ),
    );
    await page.goto('/dashboard?tab=additions');
    await expect(page.getByText('Requests temporarily unavailable', { exact: true })).toBeVisible();
    await expect(page.getByText("You haven't submitted any addition requests yet.", { exact: true })).toHaveCount(0);
    await shot(page, info, 'addition-load-error');
    failed = false;
    await page.getByRole('button', { name: 'Retry', exact: true }).click();
    await expect(page.getByText('https://retry.itch.io/game', { exact: true })).toBeVisible();
    await expect(page.getByText('Requests temporarily unavailable', { exact: true })).toHaveCount(0);
});

test('failed Discord saves preserve later pending edits and other successful changes', async ({ page }) => {
    const pending: Route[] = [];
    await page.route('**/browser-api/discord/servers/*/config', (r) => {
        pending.push(r);
    });
    await page.goto(`/discord/${fixture.discordServerId}`);
    const thumbnail = page.getByRole('checkbox', { name: 'Include thumbnail', exact: true });
    const description = page.getByRole('checkbox', { name: 'Include game description', exact: true });
    await expect(thumbnail).toBeChecked();
    await expect(description).toBeChecked();
    await thumbnail.locator('..').click();
    await description.locator('..').click();
    await thumbnail.locator('..').click();
    await thumbnail.locator('..').click();
    await expect.poll(() => pending.length).toBe(1);
    await pending[0].fulfill({ status: 500, json: { message: 'First save failed' } });
    await expect(page.getByText('First save failed', { exact: true })).toBeVisible();
    await expect(thumbnail).not.toBeChecked();
    await expect(description).not.toBeChecked();
    await expect.poll(() => pending.length).toBe(2);
    await pending[1].continue();
    await expect.poll(() => pending.length).toBe(3);
    await pending[2].continue();
    await expect.poll(() => pending.length).toBe(4);
    await expect(thumbnail).not.toBeChecked();
    await pending[3].fulfill({ status: 500, json: { message: 'Last save failed' } });
    await expect(page.getByText('Last save failed', { exact: true })).toBeVisible();
    await expect(thumbnail).toBeChecked();
    await expect(description).not.toBeChecked();
    const saved = await (await page.request.get(`/browser-api/discord/servers/${fixture.discordServerId}`)).json();
    expect(saved.server.config.include_thumbnail).toBe(true);
    expect(saved.server.config.include_game_description).toBe(false);
});

test('excluded tags and ignored games remain saved after Back', async ({ page }) => {
    await page.goto('/dashboard?tab=search');
    const ignored = page.getByRole('link', { name: /^Ignored Game / });
    await expect(ignored).toHaveCount(1);
    await page.getByRole('button', { name: 'Clear All', exact: true }).click();
    await expect(page.getByText('Excluded tags saved', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Remove', exact: true }).click();
    await expect(page.getByText('Game removed from ignore list', { exact: true })).toBeVisible();
    await page.getByRole('navigation', { name: 'Main navigation', exact: true }).getByRole('link', { name: 'Games', exact: true }).click();
    await expect(page).toHaveURL(/\/games$/);
    await page.goBack();
    await expect(page.getByRole('button', { name: 'Clear All', exact: true })).toHaveCount(0);
    await expect(ignored).toHaveCount(0);
    await expect(page.getByText('0 games', { exact: true })).toBeVisible();
});

test('failed unignore leaves the count intact and allows retry', async ({ page }) => {
    let failed = true;
    await page.route('**/user/ignored-games', (r) => {
        if (failed && r.request().method() === 'DELETE') return r.fulfill({ status: 500, json: { message: 'Unignore failed' } });
        return r.continue();
    });
    await page.goto('/dashboard?tab=search');
    const remove = page.getByRole('button', { name: 'Remove', exact: true });
    await remove.click();
    await expect(page.getByText('Unignore failed', { exact: true })).toBeVisible();
    await expect(page.getByText('1 game', { exact: true })).toBeVisible();
    await expect(remove).toBeEnabled();
    failed = false;
    await remove.click();
    await expect(page.getByText('0 games', { exact: true })).toBeVisible();
});

test('saving preferences preserves other unsaved selections', async ({ page }) => {
    await page.goto('/dashboard?tab=search');
    const english = page.getByRole('button', { name: 'English', exact: true });
    await english.click();
    await expect(english).not.toHaveClass(/bg-accent/);
    await page.getByRole('button', { name: 'Clear All', exact: true }).click();
    await expect(page.getByText('Excluded tags saved', { exact: true })).toBeVisible();
    await expect(english).not.toHaveClass(/bg-accent/);
    expect((await (await page.request.get('/user/language-preferences')).json()).preferred_languages).toEqual(['eng']);

    const tag = page.getByRole('button', { name: /^E2E Excluded / }).first();
    await tag.click();
    await expect(tag).toHaveClass(/bg-red-700/);
    await page.getByRole('button', { name: 'Save Preferences', exact: true }).first().click();
    await expect(page.getByText('Language preferences saved', { exact: true })).toBeVisible();
    await expect(tag).toHaveClass(/bg-red-700/);
    expect((await (await page.request.get('/user/excluded-tags')).json()).excluded_tags).toEqual([]);
    await page.getByRole('button', { name: 'Remove', exact: true }).click();
    await expect(page.getByText('0 games', { exact: true })).toBeVisible();
    await expect(tag).toHaveClass(/bg-red-700/);
});
