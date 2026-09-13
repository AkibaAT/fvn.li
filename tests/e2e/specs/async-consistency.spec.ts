import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import type { GameViewFixture } from '../support/laravel';

let fixture: GameViewFixture & { raterId: number; discordServerId: number; listIds: number[] };
const pageErrors = new WeakMap<Page, string[]>();
test.use({ timezoneId: 'America/New_York', locale: 'en-US' });
test.beforeAll(() => {
    fixture = JSON.parse(execFileSync('php', ['tests/e2e/support/make-consistency-fixture.php'], { encoding: 'utf8' }));
});
test.beforeEach(async ({ page, baseURL }) => {
    const errors: string[] = [];
    pageErrors.set(page, errors);
    page.on('pageerror', (error) => errors.push(error.message));
    await page.context().addCookies([{ ...fixture.authCookie, url: baseURL! }]);
    await page.route('**/storage/e2e/**', (route) =>
        route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg"/>' }),
    );
});
test.afterEach(({ page }) => expect(pageErrors.get(page)).toEqual([]));

test('a failed list change preserves prior successful deletions and counts', async ({ page }) => {
    await page.goto('/lists?per_page=16');
    const deleted = page.getByRole('article', { name: 'Deleted list', exact: true });
    page.on('dialog', (dialog) => dialog.accept());
    await deleted.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(deleted).toHaveCount(0);
    await expect(page.getByText('List deleted successfully.', { exact: true })).toBeVisible();
    const allTab = page.getByRole('link', { name: /^All Lists \(/ });
    const savedCount = await allTab.textContent();
    await page.route(`**/browser-api/vn-lists/${fixture.listIds[1]}/toggle-visibility`, (route) =>
        route.fulfill({ status: 500, json: { message: 'Simulated failure' } }),
    );
    await page.getByRole('article', { name: 'Other list', exact: true }).getByRole('button', { name: 'Make Public', exact: true }).click();
    await expect(page.getByText('Simulated failure', { exact: true })).toBeVisible();
    await expect(deleted).toHaveCount(0);
    await expect(allTab).toHaveText(savedCount!);
});

test('deleting the last list on a page returns to a populated page with correct counts', async ({ page }) => {
    await page.goto('/lists?visibility=public&per_page=1&page=2');
    page.on('dialog', (dialog) => dialog.accept());
    await page.getByRole('article', { name: 'Last public list', exact: true }).getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page.getByRole('article', { name: 'Public list', exact: true })).toBeVisible();
    await expect(page).toHaveURL((url) => url.searchParams.get('visibility') === 'public' && url.searchParams.get('page') === '1');
    await expect(page.getByRole('link', { name: 'Public Lists (1)', exact: true })).toBeVisible();
    await expect(page.getByLabel('Select page number')).toHaveValue('1');
});

test('visibility changes update both filtered lists immediately', async ({ page }, info) => {
    await page.goto('/lists?visibility=public');
    const list = page.getByRole('article', { name: 'Public list', exact: true });
    const originalCount = await page.getByRole('article').count();
    await list.getByRole('button', { name: 'Make Private', exact: true }).click();
    await expect(list).toHaveCount(0);
    await expect(page.getByRole('link', { name: `Public Lists (${originalCount - 1})`, exact: true })).toBeVisible();
    await page.getByRole('link', { name: /^Private Lists \(/ }).click();
    await list.getByRole('button', { name: 'Make Public', exact: true }).click();
    await expect(list).toHaveCount(0);
    await expect(page.getByRole('link', { name: `Public Lists (${originalCount})`, exact: true })).toBeVisible();
    await expect(page).toHaveURL(/visibility=private/);
    await page.screenshot({ path: info.outputPath('filtered-lists.png'), fullPage: true });
});

test('overlapping Discord edits persist in order, including repeat changes to one field', async ({ page }) => {
    await page.route('**/browser-api/discord/servers/*/channels', (route) => route.fulfill({ json: { channels: [] } }));
    await page.route('**/browser-api/discord/servers/*/roles', (route) => route.fulfill({ json: { roles: [] } }));
    const pending: Route[] = [];
    await page.route('**/browser-api/discord/servers/*/config', (route) => {
        pending.push(route);
    });
    await page.goto(`/discord/${fixture.discordServerId}`);
    await expect(page.getByRole('heading', { name: 'General Settings', exact: true })).toBeVisible();
    const description = page.getByRole('checkbox', { name: 'Include game description', exact: true });
    const ratings = page.getByRole('checkbox', { name: 'Include ratings', exact: true });
    const originalDescription = await description.isChecked();
    const originalRatings = await ratings.isChecked();
    await description.locator('..').click();
    await expect.poll(() => pending.length).toBe(1);
    await ratings.locator('..').click();
    await description.locator('..').click();
    await expect(description).toBeChecked({ checked: originalDescription });
    await expect(ratings).toBeChecked({ checked: !originalRatings });
    expect(pending).toHaveLength(1);
    for (let index = 0; index < 3; index++) {
        await expect.poll(() => pending.length).toBe(index + 1);
        const response = page.waitForResponse((response) => response.url().endsWith('/config') && response.request().method() === 'PUT');
        await pending[index].continue();
        expect((await response).ok()).toBe(true);
    }
    expect(pending.map((route) => route.request().postDataJSON())).toEqual([
        { include_game_description: !originalDescription },
        { include_ratings: !originalRatings },
        { include_game_description: originalDescription },
    ]);
    const saved = await (await page.request.get(`/browser-api/discord/servers/${fixture.discordServerId}`)).json();
    expect(saved.server.config.include_game_description).toBe(originalDescription);
    expect(saved.server.config.include_ratings).toBe(!originalRatings);
    await page.reload();
    await expect(description).toBeChecked({ checked: originalDescription });
    await expect(ratings).toBeChecked({ checked: !originalRatings });
});

function reviewsPayload(stars: number) {
    return {
        success: true,
        availableRatings: [1, 5],
        reviews: {
            data: [
                {
                    id: 90000 + stars,
                    rating: stars,
                    review: `RESULT FOR ${stars} STARS`,
                    published_at: '2026-05-03T00:30:00Z',
                    is_visible: true,
                    is_reviewed: true,
                    source_platform: 'itch_io',
                    rater: { id: fixture.raterId, name: 'Test reviewer' },
                },
            ],
            current_page: 1,
            last_page: 1,
            per_page: 5,
            total: 1,
            from: 1,
            to: 1,
        },
    };
}
for (const status of [200, 500]) {
    test(`reviews ignore a superseded filter response (${status})`, async ({ page }, info) => {
        let pending: Route | undefined;
        await page.route('**/browser-api/games/*/reviews?*', async (route) => {
            const stars = Number(new URL(route.request().url()).searchParams.get('selectedRating'));
            if (stars === 5) {
                pending = route;
                return;
            }
            await route.fulfill({ json: reviewsPayload(stars) });
        });
        await page.goto(`/games/${fixture.slug}`);
        const filter = page.locator('#reviews select').first();
        await filter.selectOption('5');
        await expect.poll(() => Boolean(pending)).toBe(true);
        await filter.selectOption('1');
        await expect(page.getByText('RESULT FOR 1 STARS', { exact: true })).toBeVisible();
        const response = page.waitForResponse((response) => response.url() === pending!.request().url());
        await pending!.fulfill({ status, json: reviewsPayload(5) });
        await (await response).finished();
        await expect(page.getByText('RESULT FOR 1 STARS', { exact: true })).toBeVisible();
        await expect(page.getByText('RESULT FOR 5 STARS', { exact: true })).toHaveCount(0);
        await expect(page.getByText('Unable to load reviews. Please try again.', { exact: true })).toHaveCount(0);
        await expect(filter).toHaveValue('1');
        await page.locator('#reviews').screenshot({ path: info.outputPath('current-review-filter.png') });
    });
}

test('a failed review request shows an error and can retry the same filter', async ({ page }) => {
    let count = 0;
    await page.route('**/browser-api/games/*/reviews?*', (route) => {
        const selected = new URL(route.request().url()).searchParams.get('selectedRating');
        return route.fulfill({ status: selected === '1' && ++count === 1 ? 500 : 200, json: reviewsPayload(1) });
    });
    await page.goto(`/games/${fixture.slug}`);
    await page.locator('#reviews select').first().selectOption('1');
    await expect(page.getByText('Unable to load reviews. Please try again.', { exact: true })).toBeVisible();
    await page.locator('#reviews').getByRole('button', { name: 'Retry', exact: true }).click();
    await expect(page.getByText('RESULT FOR 1 STARS', { exact: true })).toBeVisible();
});

for (const kind of ['character', 'file']) {
    const openerName = kind === 'character' ? 'View 1 Characters' : 'View File Stats';
    const statsPayload = (name: string) => ({
        success: true,
        data:
            kind === 'character'
                ? { characters: [name], languages: [] }
                : {
                      file_categories: [
                          { category: 'images', total_count: 1, total_size: 1024, file_types: [{ extension: name, count: 1, size: 1024 }] },
                      ],
                  },
    });
    for (const status of [200, 500]) {
        test(`${kind} statistics ignore another versions late response (${status})`, async ({ page }) => {
            let pending: Route | undefined;
            let count = 0;
            await page.route(`**/*/${kind}-stats`, (route) => {
                if (++count === 1) {
                    pending = route;
                    return;
                }
                return route.fulfill({ json: statsPayload('SECOND VERSION DATA') });
            });
            await page.goto(`/games/${fixture.slug}`);
            const openers = page.getByRole('button', { name: openerName, exact: true });
            await expect(openers).toHaveCount(2);
            await openers.nth(0).click();
            await expect.poll(() => Boolean(pending)).toBe(true);
            await page.getByRole('button', { name: 'Close dialog', exact: true }).filter({ visible: true }).click();
            await openers.nth(1).click();
            const dialog = page.getByRole('dialog');
            await expect(dialog).toContainText('SECOND VERSION DATA');
            const response = page.waitForResponse((response) => response.url() === pending!.request().url());
            await pending!.fulfill({ status, json: statsPayload('FIRST VERSION DATA') });
            await (await response).finished();
            await expect(dialog).toContainText('SECOND VERSION DATA');
            await expect(dialog).not.toContainText('FIRST VERSION DATA');
        });
    }
    test(`${kind} statistics recover from a failed request`, async ({ page }) => {
        let count = 0;
        await page.route(`**/*/${kind}-stats`, (route) => route.fulfill({ status: ++count === 1 ? 500 : 200, json: statsPayload('RETRIED DATA') }));
        await page.goto(`/games/${fixture.slug}`);
        const opener = page.getByRole('button', { name: openerName, exact: true }).first();
        await opener.click();
        await expect(page.getByText(`Unable to load ${kind} statistics. Please try again.`, { exact: true })).toBeVisible();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await opener.click();
        await expect(page.getByRole('dialog')).toContainText('RETRIED DATA');
    });
}

for (const provider of ['Discord', 'itch.io']) {
    test(`unlinking ${provider} updates its dashboard features without reloading`, async ({ page }) => {
        await page.goto('/dashboard');
        page.on('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: `Unlink ${provider} account`, exact: true }).click();
        await expect(page.getByRole('button', { name: `Unlink ${provider} account`, exact: true })).toHaveCount(0);
        if (provider === 'Discord') {
            await expect(page.getByRole('checkbox', { name: 'Enable Discord notifications', exact: true })).toHaveCount(0);
        } else {
            await page.getByRole('tab', { name: 'My Games', exact: true }).click();
            await expect(page.getByText('Connect your itch.io account to manage your games', { exact: true })).toBeVisible();
            await expect(page.getByRole('button', { name: 'Sync games', exact: true })).toHaveCount(0);
        }
    });
}
